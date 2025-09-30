<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UploadException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use App\Services\UserService;
use App\Utils;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function upload(Request $request, ImageService $service): Response
    {
        if ($request->hasHeader('Authorization')) {
            $guards = array_keys(config('auth.guards'));

            if (empty($guards)) {
                $guards = [null];
            }

            foreach ($guards as $guard) {
                if (Auth::guard($guard)->check()) {
                    Auth::shouldUse($guard);
                    break;
                }
            }

            if (! Auth::check()) {
                throw new AuthenticationException('Authentication failed.');
            }
        }

        try {
            $image = $service->store($request);
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Api 上传文件时发生异常');
            if (config('app.debug')) {
                return $this->fail($e->getMessage());
            }
            return $this->fail('服务异常，请稍后再试');
        }
        return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
            'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
        ));
    }

    /**
     * 多文件上传接口
     * @param Request $request
     * @param ImageService $service
     * @return Response
     * @throws AuthenticationException
     */
    public function uploadMultiple(Request $request, ImageService $service): Response
    {
        // 身份验证逻辑 - 与web上传保持一致，不需要强制认证
        // 如果需要认证，可以通过中间件控制

        // 验证文件参数
        if (!$request->hasFile('files')) {
            return $this->fail('请选择要上传的文件');
        }

        $files = $request->file('files');

        // 确保files是数组
        if (!is_array($files)) {
            $files = [$files];
        }

        // 验证文件数量限制
        $maxFiles = config('lsky.max_upload_files', 50);
        if (count($files) > $maxFiles) {
            return $this->fail("最多只能上传 {$maxFiles} 个文件");
        }

        $results = [];
        $errors = [];

        \Log::info('开始批量上传文件', [
            'file_count' => count($files),
            'user_id' => Auth::id()
        ]);

        foreach ($files as $index => $file) {
            try {
                // 创建新的Request对象，复制所有数据但只设置当前文件
                $singleFileRequest = Request::create(
                    $request->url(),
                    $request->method(),
                    $request->except('files'),
                    $request->cookies->all(),
                    ['file' => $file],
                    $request->server->all(),
                    $request->getContent()
                );

                // 复制所有重要的请求属性
                $singleFileRequest->setUserResolver(function () use ($request) {
                    return $request->user();
                });

                // 设置session（如果存在）
                if ($request->hasSession()) {
                    $singleFileRequest->setLaravelSession($request->session());
                }

                // 确保strategy_id被正确传递
                if ($request->has('strategy_id')) {
                    $singleFileRequest->merge(['strategy_id' => $request->input('strategy_id')]);
                }

                $image = $service->store($singleFileRequest);

                $results[] = $image->setAppends(['pathname', 'links'])->only(
                    'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
                );

                \Log::info("文件 {$index} 上传成功", ['image_id' => $image->id]);

            } catch (UploadException $e) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
                \Log::error("文件 {$index} 上传失败", [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
            } catch (\Throwable $e) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'error' => '服务异常，请稍后再试'
                ];
                \Log::error("文件 {$index} 上传系统异常", [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $response = [
            'success_count' => count($results),
            'error_count' => count($errors),
            'total_count' => count($files),
            'success_files' => $results,
            'error_files' => $errors
        ];

        if (count($results) === 0) {
            return $this->fail('所有文件上传失败', $response);
        } elseif (count($errors) > 0) {
            return $this->success('部分文件上传成功', $response);
        } else {
            return $this->success('所有文件上传成功', $response);
        }
    }

    public function images(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $images = $user->images()->filter($request)->paginate(40)->withQueryString();
        $images->getCollection()->each(function (Image $image) {
            $image->human_date = $image->created_at->diffForHumans();
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        });
        return $this->success('success', $images);
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        (new UserService())->deleteImages([$request->route('key')], $user, 'key');
        return $this->success('删除成功');
    }

    /**
     * 上传ZIP文件并解压其中的图片
     */
    public function uploadZip(Request $request, ImageService $service): Response
    {
        // 身份验证逻辑 - 与web上传保持一致，不需要强制认证
        // 如果需要认证，可以通过中间件控制

        if (!$request->hasFile('zip_file')) {
            return $this->fail('请选择要上传的ZIP文件');
        }

        $zipFile = $request->file('zip_file');

        // 验证文件类型
        if ($zipFile->getClientOriginalExtension() !== 'zip') {
            return $this->fail('只支持ZIP格式的压缩文件');
        }

        // 验证文件大小 (例如最大50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($zipFile->getSize() > $maxSize) {
            return $this->fail('ZIP文件大小不能超过50MB');
        }

        $results = [];
        $errors = [];
        $tempDir = null;

        try {
            // 创建临时目录
            $tempDir = storage_path('app/temp/zip_' . uniqid());
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // 解压ZIP文件
            $zip = new ZipArchive();
            $zipPath = $zipFile->getRealPath();

            if ($zip->open($zipPath) !== TRUE) {
                return $this->fail('无法打开ZIP文件');
            }

            // 解压到临时目录
            $zip->extractTo($tempDir);
            $zip->close();

            // 获取解压后的所有文件
            $extractedFiles = $this->getAllFiles($tempDir);

            // 过滤出支持的图片文件
            $imageFiles = $this->filterImageFiles($extractedFiles);

            if (empty($imageFiles)) {
                return $this->fail('ZIP文件中没有找到支持的图片文件');
            }

            Log::info('开始处理ZIP文件', [
                'zip_file' => $zipFile->getClientOriginalName(),
                'total_files' => count($extractedFiles),
                'image_files' => count($imageFiles),
                'user_id' => Auth::id()
            ]);

            // 批量上传图片文件
            foreach ($imageFiles as $index => $imagePath) {
                try {
                    // 创建UploadedFile对象
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $imagePath,
                        basename($imagePath),
                        mime_content_type($imagePath),
                        null,
                        true
                    );

                    // 创建新的Request对象
                    $singleFileRequest = Request::create(
                        $request->url(),
                        $request->method(),
                        $request->except(['zip_file']),
                        $request->cookies->all(),
                        ['file' => $uploadedFile],
                        $request->server->all(),
                        $request->getContent()
                    );

                    // 复制所有重要的请求属性
                    $singleFileRequest->setUserResolver(function () use ($request) {
                        return $request->user();
                    });

                    // 设置session（如果存在）
                    if ($request->hasSession()) {
                        $singleFileRequest->setLaravelSession($request->session());
                    }

                    // 确保strategy_id被正确传递
                    if ($request->has('strategy_id')) {
                        $singleFileRequest->merge(['strategy_id' => $request->input('strategy_id')]);
                    }

                    $image = $service->store($singleFileRequest);

                    $results[] = $image->setAppends(['pathname', 'links'])->only(
                        'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
                    );

                    Log::info("ZIP中的文件 {$index} 上传成功", [
                        'file' => basename($imagePath),
                        'image_id' => $image->id
                    ]);

                } catch (UploadException $e) {
                    $errors[] = [
                        'index' => $index,
                        'filename' => basename($imagePath),
                        'error' => $e->getMessage()
                    ];
                    Log::error("ZIP中的文件 {$index} 上传失败", [
                        'file' => basename($imagePath),
                        'error' => $e->getMessage()
                    ]);
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $index,
                        'filename' => basename($imagePath),
                        'error' => '服务异常，请稍后再试'
                    ];
                    Log::error("ZIP中的文件 {$index} 上传系统异常", [
                        'file' => basename($imagePath),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('ZIP文件处理异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fail('ZIP文件处理失败: ' . $e->getMessage());
        } finally {
            // 清理临时文件
            if ($tempDir && is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
        }

        $response = [
            'success_count' => count($results),
            'error_count' => count($errors),
            'total_count' => count($imageFiles),
            'success_files' => $results,
            'error_files' => $errors
        ];

        if (count($results) === 0) {
            return $this->fail('ZIP文件中没有图片上传成功', $response);
        } elseif (count($errors) > 0) {
            return $this->success('ZIP文件部分图片上传成功', $response);
        } else {
            return $this->success('ZIP文件所有图片上传成功', $response);
        }
    }

    /**
     * 递归获取目录中的所有文件
     */
    private function getAllFiles($dir)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * 过滤出支持的图片文件
     */
    private function filterImageFiles($files)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg'];
        $imageFiles = [];

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $allowedExtensions)) {
                $imageFiles[] = $file;
            }
        }

        return $imageFiles;
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
