<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlbumRequest;
use App\Models\Album;
use App\Models\User;
use App\Services\AlbumStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlbumController extends Controller
{
    public function albums(): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $albums = $user->albums()->latest()->paginate(40);
        $albums->getCollection()->each(function (Album $album) {
            $album->setVisible(['id', 'name', 'intro', 'image_num']);
        });
        return $this->success('success', compact('albums'));
    }

    public function create(AlbumRequest $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        DB::transaction(function () use ($user, $request) {
            $user->albums()->create(array_filter($request->validated()));
            $user->album_num = $user->albums()->count();
            $user->save();
        });

        return $this->success('创建成功');
    }

    public function update(AlbumRequest $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $album = $user->albums()->find($request->route('id'));
        if (is_null($album)) {
            return $this->fail('不存在的相册');
        }

        $oldName = $album->name;
        $data = array_filter($request->validated());

        DB::transaction(function () use ($album, $data, $oldName) {
            $album->update($data);
            if (isset($data['name']) && $data['name'] !== $oldName) {
                (new AlbumStorageService())->renameAlbumDirectory($album->fresh(), $oldName, $data['name']);
            }
        });

        return $this->success('修改成功');
    }

    public function delete(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Album|null $album */
        $album = $user->albums()->find($request->route('id'));
        if (is_null($album)) {
            return $this->fail('不存在的相册');
        }
        DB::transaction(function () use ($user, $album) {
            $storage = new AlbumStorageService();
            $album->load(['images.strategy']);
            // 物理文件移出相册目录，并解除关联
            foreach ($album->images as $image) {
                $storage->relocateImage($image, null);
            }
            $album->delete();
            $user->album_num = $user->albums()->count();
            $user->save();
        });
        return $this->success('删除成功');
    }

    /**
     * 一键备份：为选中相册分别生成压缩包并下载。
     */
    public function backup(Request $request, AlbumStorageService $storage): StreamedResponse|Response
    {
        /** @var User $user */
        $user = Auth::user();
        $ids = (array) $request->input('ids', []);
        if (empty($ids) && $request->filled('id')) {
            $ids = [(int) $request->input('id')];
        }
        if (empty($ids)) {
            return $this->fail('请选择要备份的相册');
        }

        try {
            return $storage->backupAlbums($user, $ids);
        } catch (\Throwable $e) {
            return $this->fail(config('app.debug') ? $e->getMessage() : '备份失败，请稍后重试');
        }
    }

    /**
     * 将选中相册内图片同步到「相册名」物理文件夹。
     */
    public function sync(Request $request, AlbumStorageService $storage): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $ids = (array) $request->input('ids', []);
        if (empty($ids) && $request->filled('id')) {
            $ids = [(int) $request->input('id')];
        }
        if (empty($ids)) {
            return $this->fail('请选择要同步的相册');
        }

        try {
            $moved = 0;
            $albums = $user->albums()->whereIn('id', $ids)->get();
            foreach ($albums as $album) {
                $moved += $storage->syncAlbum($album);
            }
            return $this->success("同步完成，共整理 {$moved} 个文件");
        } catch (\Throwable $e) {
            return $this->fail(config('app.debug') ? $e->getMessage() : '同步失败，请稍后重试');
        }
    }
}
