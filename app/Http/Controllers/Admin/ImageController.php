<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImagePermission;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImageController extends Controller
{
    public function index(Request $request): View
    {
        $keywords = $request->query('keywords');
        $startTime = $request->query('start_time');
        $endTime = $request->query('end_time');
        $perPage = $request->query('per_page', 40);

        // 验证分页大小
        $allowedPerPage = [40, 200, 500, 1000];
        if (!in_array((int)$perPage, $allowedPerPage)) {
            $perPage = 40;
        }

        $images = Image::query()->with(['user' => function (BelongsTo $belongsTo) {
            $belongsTo->withSum('images', 'size');
        }, 'album', 'group', 'strategy'])
        ->when($startTime, function (Builder $builder, $startTime) {
            $builder->where('created_at', '>=', $startTime);
        })
        ->when($endTime, function (Builder $builder, $endTime) {
            $builder->where('created_at', '<=', $endTime);
        })
        ->when($keywords, function (Builder $builder, $keywords) {
            $words = [];
            $qualifiers = [
                'name:', 'album:', 'group:', 'strategy:', 'email:', 'extension:', 'md5:', 'sha1:', 'ip:', 'is:', 'order:',
            ];
            collect(array_filter(explode(' ', $keywords)))->filter(function ($keyword) use ($qualifiers, &$words) {
                if (Str::startsWith($keyword, $qualifiers)) {
                    return true;
                }
                $words[] = $keyword;
                return false;
            })->each(function ($filter) use ($builder) {
                match ($filter) {
                    'is:public' => $builder->where('permission', ImagePermission::Public),
                    'is:private' => $builder->where('permission', ImagePermission::Private),
                    'is:unhealthy' => $builder->where('is_unhealthy', 1),
                    'is:guest' => $builder->whereNull('user_id'),
                    'is:adminer' => $builder->whereHas('user', fn (Builder $builder) => $builder->where('is_adminer', 1)),
                    'order:earliest' => $builder->orderBy('created_at'),
                    'order:utmost' => $builder->orderByDesc('size'),
                    'order:least' => $builder->orderBy('size'),
                    default => 0,
                };

                [$qualifier, $value] = explode(':', $filter);

                if ($value) {
                    $callback = fn (Builder $builder) => $builder->where('name', $value);
                    match ($qualifier) {
                        'name' => $builder->whereHas('user', $callback),
                        'album' => $builder->whereHas('album', $callback),
                        'group' => $builder->whereHas('group', $callback),
                        'strategy' => $builder->whereHas('strategy', $callback),
                        'email' => $builder->whereHas('user', fn (Builder $builder) => $builder->where('email', $value)),
                        'extension' => $builder->where('extension', $value),
                        'md5' => $builder->where('md5', $value),
                        'sha1' => $builder->where('sha1', $value),
                        'ip' => $builder->where('ip', $value),
                        default => 0
                    };
                }
            });

            foreach ($words as $word) {
                $builder->where('name', 'like', "%{$word}%")
                    ->orWhere('origin_name', 'like', "%{$word}%")
                    ->orWhere('alias_name', 'like', "%{$word}%");
            }
        })->latest()->paginate($perPage);
        $images->getCollection()->each(function (Image $image) {
            $image->append('url', 'pathname', 'thumb_url');
            $image->album?->setVisible(['name']);
            $image->group?->setVisible(['name']);
            $image->strategy?->setVisible(['name']);
        });

        $images->appends(array_filter([
            'keywords' => $keywords,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'per_page' => $perPage
        ]));

        return view('admin.image.index', compact('images'));
    }

    public function update(): Response
    {
        return $this->success();
    }

    public function delete(Request $request): Response
    {
        /** @var Image $image */
        $image = Image::with('user', 'strategy', 'album')->find($request->route('id'));
        (new UserService())->deleteImages([$image->id]);
        return $this->success('删除成功');
    }

    public function bulkDelete(Request $request): Response
    {
        try {
            $ids = $request->input('ids', []);

            if (empty($ids) || !is_array($ids)) {
                return $this->fail('请选择要删除的图片');
            }

            // 验证ID是否都是数字
            $ids = array_filter($ids, function($id) {
                return is_numeric($id) && $id > 0;
            });

            if (empty($ids)) {
                return $this->fail('无效的图片ID');
            }

            // 获取图片信息用于删除
            $images = Image::with('user', 'strategy', 'album')->whereIn('id', $ids)->get();

            if ($images->isEmpty()) {
                return $this->fail('未找到要删除的图片');
            }

            // 使用UserService删除图片（物理删除）
            $deletedCount = (new UserService())->deleteImages($ids);

            if ($deletedCount > 0) {
                return $this->success("成功删除 {$deletedCount} 张图片");
            } else {
                return $this->fail('删除失败，请重试');
            }
        } catch (\Exception $e) {
            Log::error('批量删除图片失败: ' . $e->getMessage(), [
                'ids' => $request->input('ids', []),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fail('删除失败: ' . $e->getMessage());
        }
    }
}
