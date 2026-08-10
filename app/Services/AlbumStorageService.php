<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Image;
use App\Models\User;
use App\Utils;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AlbumStorageService
{
    /**
     * 相册对应的物理目录名（与相册 name 一致，alpha_dash 已校验）。
     */
    public function albumFolder(Album $album): string
    {
        return trim($album->name, '/');
    }

    /**
     * 生成目标 pathname（相册下仅保留文件名，便于按相册批量操作）。
     *
     * @return array{path: string, name: string, pathname: string}
     */
    public function buildLocation(?Album $album, string $basename): array
    {
        $name = ltrim(str_replace('\\', '/', $basename), '/');
        if ($album) {
            $path = $this->albumFolder($album);
            return [
                'path' => $path,
                'name' => $name,
                'pathname' => "{$path}/{$name}",
            ];
        }

        $path = Carbon::now()->format('Y/m/d');
        return [
            'path' => $path,
            'name' => $name,
            'pathname' => "{$path}/{$name}",
        ];
    }

    /**
     * 将图片物理文件迁入目标相册目录（或移出相册）。
     */
    public function relocateImage(Image $image, ?Album $targetAlbum): void
    {
        $image->loadMissing('strategy', 'album');
        $basename = $image->name;
        $location = $this->buildLocation($targetAlbum, $basename);

        if ($image->pathname === $location['pathname']) {
            $image->album_id = $targetAlbum?->id;
            $image->save();
            return;
        }

        // 目标已存在同名文件时加后缀，避免覆盖
        $location = $this->ensureUniquePathname($image, $location);

        $shared = $this->isPathShared($image);
        $filesystem = $image->filesystem();

        try {
            if ($filesystem->fileExists($location['pathname'])) {
                $location = $this->ensureUniquePathname($image, $location, true);
            }

            if ($shared) {
                if ($filesystem->fileExists($image->pathname)) {
                    $stream = $filesystem->readStream($image->pathname);
                    $filesystem->writeStream($location['pathname'], $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            } else {
                if ($filesystem->fileExists($image->pathname)) {
                    try {
                        $filesystem->move($image->pathname, $location['pathname']);
                    } catch (FilesystemException $e) {
                        $stream = $filesystem->readStream($image->pathname);
                        $filesystem->writeStream($location['pathname'], $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        $filesystem->delete($image->pathname);
                    }
                }
            }
        } catch (\Throwable $e) {
            Utils::e($e, '迁移相册物理文件时出现异常');
            throw $e;
        }

        $image->fill([
            'path' => $location['path'],
            'name' => $location['name'],
            'album_id' => $targetAlbum?->id,
        ]);
        $image->save();
    }

    /**
     * 批量将图片移动到目标相册（含物理迁移）。
     *
     * @param  Collection<int, Image>|iterable<Image>  $images
     */
    public function moveImagesToAlbum(iterable $images, ?Album $targetAlbum): void
    {
        foreach ($images as $image) {
            $this->relocateImage($image, $targetAlbum);
        }
    }

    /**
     * 相册重命名时同步物理目录与图片 path。
     */
    public function renameAlbumDirectory(Album $album, string $oldName, string $newName): void
    {
        $oldName = trim($oldName, '/');
        $newName = trim($newName, '/');
        if ($oldName === $newName) {
            return;
        }

        $album->loadMissing('images.strategy');
        /** @var Image $image */
        foreach ($album->images as $image) {
            $oldPathname = $image->pathname;
            $newPath = preg_replace('#^'.preg_quote($oldName, '#').'(?:/|$)#', $newName.'/', $image->path.'/') ?: $newName.'/';
            $newPath = rtrim($newPath, '/');
            $newPathname = $newPath === '' ? $image->name : "{$newPath}/{$image->name}";

            if ($oldPathname === $newPathname) {
                continue;
            }

            $shared = $this->isPathShared($image);
            $filesystem = $image->filesystem();

            try {
                if ($filesystem->fileExists($oldPathname)) {
                    if ($shared) {
                        $stream = $filesystem->readStream($oldPathname);
                        $filesystem->writeStream($newPathname, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    } else {
                        try {
                            $filesystem->move($oldPathname, $newPathname);
                        } catch (FilesystemException $e) {
                            $stream = $filesystem->readStream($oldPathname);
                            $filesystem->writeStream($newPathname, $stream);
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                            $filesystem->delete($oldPathname);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Utils::e($e, '重命名相册目录时出现异常');
                throw $e;
            }

            $image->path = $newPath;
            $image->save();
        }
    }

    /**
     * 将相册内已有图片整理到「相册名」物理目录。
     */
    public function syncAlbum(Album $album): int
    {
        $album->loadMissing('images.strategy');
        $count = 0;
        foreach ($album->images as $image) {
            $before = $image->pathname;
            $this->relocateImage($image, $album);
            if ($before !== $image->pathname) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 为指定相册分别生成压缩包并下载。
     * 单个相册：直接返回 {相册名}.zip；多个相册：返回外层 zip，内含各相册独立 zip。
     *
     * @param  array<int>  $albumIds
     */
    public function backupAlbums(User $user, array $albumIds): StreamedResponse
    {
        $albumIds = array_values(array_unique(array_filter(array_map('intval', $albumIds))));
        if (empty($albumIds)) {
            abort(422, '请选择要备份的相册');
        }

        $albums = $user->albums()->whereIn('id', $albumIds)->with(['images.strategy'])->get();
        if ($albums->isEmpty()) {
            abort(404, '未找到相册');
        }

        $tempFiles = [];

        try {
            $albumZips = [];
            foreach ($albums as $album) {
                $zipPath = $this->createAlbumZip($album);
                $tempFiles[] = $zipPath;
                $albumZips[$this->safeZipEntryName($album->name).'.zip'] = $zipPath;
            }

            if (count($albumZips) === 1) {
                $downloadName = array_key_first($albumZips);
                $downloadPath = $albumZips[$downloadName];
            } else {
                $downloadPath = storage_path('app/temp/album-backup-'.Str::uuid().'.zip');
                if (! is_dir(dirname($downloadPath))) {
                    mkdir(dirname($downloadPath), 0755, true);
                }
                $bundle = new ZipArchive();
                if ($bundle->open($downloadPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('无法创建备份压缩包');
                }
                foreach ($albumZips as $entry => $path) {
                    $bundle->addFile($path, $entry);
                }
                $bundle->close();
                $tempFiles[] = $downloadPath;
                $downloadName = 'albums-backup-'.Carbon::now()->format('Ymd-His').'.zip';
            }

            return response()->streamDownload(function () use ($downloadPath, $tempFiles) {
                $handle = fopen($downloadPath, 'rb');
                fpassthru($handle);
                fclose($handle);
                foreach ($tempFiles as $file) {
                    @unlink($file);
                }
            }, $downloadName, [
                'Content-Type' => 'application/zip',
            ]);
        } catch (\Throwable $e) {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
            Utils::e($e, '备份相册时出现异常');
            throw $e;
        }
    }

    /**
     * 创建单个相册的 zip（内含该相册全部图片）。
     */
    protected function createAlbumZip(Album $album): string
    {
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zipPath = $dir.'/album-'.$album->id.'-'.Str::uuid().'.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建相册压缩包');
        }

        $folder = $this->safeZipEntryName($album->name);
        $usedNames = [];

        /** @var Image $image */
        foreach ($album->images as $image) {
            try {
                $filesystem = $image->filesystem();
                if (! $filesystem->fileExists($image->pathname)) {
                    continue;
                }
                $entryName = $image->filename ?: $image->name;
                $entryName = $this->safeZipEntryName(basename($entryName));
                if (isset($usedNames[$entryName])) {
                    $usedNames[$entryName]++;
                    $pi = pathinfo($entryName);
                    $entryName = ($pi['filename'] ?? 'file').'_'.$usedNames[$entryName].(isset($pi['extension']) ? '.'.$pi['extension'] : '');
                } else {
                    $usedNames[$entryName] = 0;
                }

                $stream = $filesystem->readStream($image->pathname);
                $contents = stream_get_contents($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($contents === false) {
                    continue;
                }
                $zip->addFromString("{$folder}/{$entryName}", $contents);
            } catch (\Throwable $e) {
                Utils::e($e, "备份图片 #{$image->id} 失败");
            }
        }

        $zip->close();
        return $zipPath;
    }

    protected function safeZipEntryName(string $name): string
    {
        $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
        return $name !== '' ? $name : 'album';
    }

    protected function isPathShared(Image $image): bool
    {
        return Image::query()
            ->where('strategy_id', $image->strategy_id)
            ->where('id', '<>', $image->id)
            ->where('path', $image->path)
            ->where('name', $image->name)
            ->exists();
    }

    /**
     * @param  array{path: string, name: string, pathname: string}  $location
     * @return array{path: string, name: string, pathname: string}
     */
    protected function ensureUniquePathname(Image $image, array $location, bool $force = false): array
    {
        $exists = Image::query()
            ->where('strategy_id', $image->strategy_id)
            ->where('id', '<>', $image->id)
            ->where('path', $location['path'])
            ->where('name', $location['name'])
            ->exists();

        if (! $exists && ! $force) {
            return $location;
        }

        $pi = pathinfo($location['name']);
        $filename = $pi['filename'] ?? 'file';
        $ext = isset($pi['extension']) ? '.'.$pi['extension'] : '';
        $i = 1;
        do {
            $name = "{$filename}_{$i}{$ext}";
            $pathname = $location['path'] === '' ? $name : "{$location['path']}/{$name}";
            $taken = Image::query()
                ->where('strategy_id', $image->strategy_id)
                ->where('id', '<>', $image->id)
                ->where('path', $location['path'])
                ->where('name', $name)
                ->exists();
            $i++;
        } while ($taken);

        return [
            'path' => $location['path'],
            'name' => $name,
            'pathname' => $pathname,
        ];
    }
}
