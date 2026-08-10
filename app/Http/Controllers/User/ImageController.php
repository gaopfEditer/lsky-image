<?php

namespace App\Http\Controllers\User;

use App\Enums\ImagePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageRenameRequest;
use App\Models\Album;
use App\Models\Image;
use App\Models\User;
use App\Services\AlbumStorageService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ImageController extends Controller
{
    public function index(): View
    {
        return view('user.images');
    }

    public function images(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $images = $user->images()->filter($request)->with('group', 'strategy')->paginate(40);
        $images->getCollection()->each(function (Image $image) {
            // 图片宽高过小会导致前端排版异常
            $image->width = max($image->width, 200);
            $image->height = max($image->height, 200);

            $image->human_date = $image->created_at->diffForHumans();
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            $image->append(['url', 'thumb_url', 'filename', 'links'])->setVisible([
                'id', 'filename', 'url', 'thumb_url', 'human_date', 'date', 'size', 'width', 'height', 'links'
            ]);
        });
        return $this->success('success', compact('images'));
    }

    public function image(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Image $image */
        if (!$image = $user->images()->find($request->route('id'))) {
            return $this->fail('未找到该图片');
        }
        $image->strategy?->setVisible(['name']);
        $image->album?->setVisible(['name']);
        $image->append(['url', 'thumb_url', 'filename', 'links'])->setVisible([
            'id', 'filename', 'origin_name', 'url', 'thumb_url', 'width', 'height', 'size', 'mimetype', 'md5', 'sha1',
            'permission', 'strategy', 'album', 'uploaded_ip', 'links', 'created_at'
        ]);
        return $this->success('success', compact('image'));
    }

    public function permission(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $permission = $request->input('permission');
        $permissions = ['public' => ImagePermission::Public, 'private' => ImagePermission::Private];
        if (!in_array($permission, array_keys($permissions))) {
            return $this->fail('设置失败');
        }
        $user->images()->whereIn('id', (array) $request->input('ids'))->update([
            'permission' => $permissions[$permission],
        ]);
        return $this->success('设置成功');
    }

    public function rename(ImageRenameRequest $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Image $image */
        if ($image = $user->images()->find($request->input('id'))) {
            $image->alias_name = $request->input('name');
            $image->save();
        }

        return $this->success('重命名成功', $image->only('id', 'filename'));
    }

    public function movement(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        try {
            DB::transaction(function () use ($user, $request) {
                /** @var null|Album $album */
                $album = $user->albums()->find((int) $request->input('id'));
                $images = $user->images()
                    ->with(['strategy', 'album'])
                    ->whereIn('id', (array) $request->input('selected'))
                    ->get();

                $affectedAlbumIds = $images->pluck('album_id')->filter()->unique()->all();
                if ($album) {
                    $affectedAlbumIds[] = $album->id;
                }
                if ($albumId = (int) $request->input('album_id')) {
                    $affectedAlbumIds[] = $albumId;
                }

                (new AlbumStorageService())->moveImagesToAlbum($images, $album);

                foreach (array_unique($affectedAlbumIds) as $id) {
                    /** @var Album|null $row */
                    $row = $user->albums()->find($id);
                    if ($row) {
                        $row->image_num = $row->images()->count();
                        $row->save();
                    }
                }
            });
        } catch (\Throwable $e) {
            return $this->fail(config('app.debug') ? $e->getMessage() : '移动失败，请稍后重试');
        }
        return $this->success('移动成功');
    }

    public function delete(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        (new UserService())->deleteImages($request->all() ?: [], $user);
        return $this->success('删除成功');
    }
}
