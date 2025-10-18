@section('title', '图片管理')

<style>
    .image-40 { width: 160px; height: 90px; }
    .image-200 { width: 96px; height: 54px; }
    .image-500 { width: 96px; height: 54px; }
    .image-1000 { width: 96px; height: 54px; }
</style>

<x-app-layout>
    <div class="p-2">
        <!-- 筛选和分页控制区域 -->
        <div class="w-full flex flex-col md:flex-row items-center justify-between py-3 md:py-5 lg:py-7 bg-white rounded-lg shadow-sm mb-4 px-4">
            <!-- 时间筛选 -->
            <div class="w-full md:w-auto flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-4 mb-4 md:mb-0">
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">开始时间:</label>
                    <input type="datetime-local" id="start_time" class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ request('start_time') }}">
                </div>
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">结束时间:</label>
                    <input type="datetime-local" id="end_time" class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ request('end_time') }}">
                </div>
                <button id="filter_btn" class="px-4 py-1 bg-blue-500 text-white text-sm rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    筛选
                </button>
                <button id="clear_filter" class="px-4 py-1 bg-gray-500 text-white text-sm rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    清除
                </button>
            </div>

            <!-- 分页大小和批量操作 -->
            <div class="w-full md:w-auto flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-4">
                <!-- 分页大小选择 -->
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">每页显示:</label>
                    <select id="per_page" class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="40" {{ request('per_page', 40) == 40 ? 'selected' : '' }}>40</option>
                        <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200</option>
                        <option value="500" {{ request('per_page') == 500 ? 'selected' : '' }}>500</option>
                        <option value="1000" {{ request('per_page') == 1000 ? 'selected' : '' }}>1000</option>
                    </select>
                </div>

                <!-- 批量操作 -->
                <div class="flex items-center space-x-2">
        <button id="select_all" class="px-3 py-1 text-white text-sm rounded-md focus:outline-none focus:ring-2" style="display: block !important; visibility: visible !important; background-color: #ff8c00; border: none;" onmouseover="this.style.backgroundColor='#ff7f00'" onmouseout="this.style.backgroundColor='#ff8c00'">
            全选
        </button>
                    <button id="select_none" class="px-3 py-1 bg-gray-500 text-white text-sm rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500" style="display: none;">
                        取消全选
                    </button>
                    <button id="bulk_delete" class="px-3 py-1 bg-red-500 text-white text-sm rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500" disabled>
                        批量删除
                    </button>
                </div>
            </div>
        </div>


        @if($images->isNotEmpty())
            <div id="images-grid" class="flex flex-wrap gap-2" data-per-page="{{ request('per_page', 40) }}">
                @foreach($images as $image)
                <div data-json='{{ $image->toJson() }}' data-id="{{ $image->id }}" class="item relative flex flex-col items-center justify-center overflow-hidden rounded-md cursor-pointer group">
                    <!-- 选择复选框 -->
                    <div class="absolute top-1 right-1 z-[2]" onclick="event.stopPropagation();">
                        <input type="checkbox" class="image-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" data-id="{{ $image->id }}" onclick="event.stopPropagation();">
                    </div>

                    <div class="flex absolute top-1 left-1 z-[1] space-x-1">
                        @if($image->is_unhealthy)
                            <span class="bg-red-500 text-white rounded-md text-xs px-1 py-0">违规</span>
                        @endif
                        @if($image->extension === 'gif')
                            <span class="bg-white rounded-md text-xs px-1 py-0">Gif</span>
                        @endif
                    </div>
                    <img class="image-40 object-cover transition-all group-hover:brightness-50 mx-auto" src="{{ $image->thumb_url }}">

                    <div class="absolute top-2 right-2 space-x-1 hidden group-hover:flex">
                        <i data-id="{{ $image->id }}" class="delete fas fa-trash text-red-500 w-4 h-4"></i>
                    </div>

                </div>
                @endforeach
            </div>
            <div class="mt-2">
                {{ $images->links() }}
            </div>
        @else
            <x-no-data message="这里还是空的～" />
        @endif
    </div>

    <x-modal id="content-modal">
        <div id="modal-content"></div>
    </x-modal>

    <script type="text/html" id="image-tpl">
        <div class="w-full mt-4">
            <div class="w-full mb-4 rounded-sm overflow-hidden flex items-center justify-center">
                <a class="w-full" href="__url__" target="_blank">
                    <img src="__url__" alt="__name__" class="w-full object-center object-cover">
                </a>
            </div>
            <div class="relative rounded-md bg-white mb-8 overflow-hidden">
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">上传用户</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__user_name__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">相册</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__album_name__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">角色组</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__group_name__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">储存策略</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__strategy_name__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">图片名称</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__name__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">原始名称</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__origin_name__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">物理路径</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__pathname__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">图片大小</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__size__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">图片类型</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__mimetype__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">MD5</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__md5__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">SHA1</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__sha1__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">尺寸</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__width__*__height__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">权限</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__permission__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">不健康的</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__is_unhealthy__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">上传 IP</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__uploaded_ip__</dd>
                    </div>
                </dl>
                <dl>
                    <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">上传时间</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__created_at__</dd>
                    </div>
                </dl>
            </div>

            <a href="javascript:void(0)" data-id="__id__" class="delete inline-flex justify-center py-1 px-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 float-right bg-red-500">
                删除
            </a>
        </div>
    </script>

    <script type="text/html" id="user-tpl">
        <div class="flex w-full items-center justify-center py-4">
            <img class="rounded-full h-24 w-24" src="__avatar__">
        </div>
        <div class="relative rounded-md bg-white mb-8 overflow-hidden">
            <dl>
                <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">用户名</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__name__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">邮箱</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__email__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">总容量</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__capacity__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">已用容量</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__used_capacity__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">图片数量</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__image_num__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">相册数量</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__album_num__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">注册 IP</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__registered_ip__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-gray-50 px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">邮箱验证时间</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__email_verified_at__</dd>
                </div>
            </dl>
            <dl>
                <div class="bg-white px-2 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">注册时间</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 truncate">__created_at__</dd>
                </div>
            </dl>
        </div>
    </script>

    <script type="text/html" id="search-grammar-tpl">
        <p class="text-gray-600">默认输入关键字搜索会根据图片的别名、原始名称进行匹配，你也可以使用下面的搜索语法进行高级搜索，并可以以任意组合使用这些搜索限定符来缩小结果范围。例如查找用户名为张三，邮箱为 a@qq.com 且图片拓展名为 jpg 的所有图片：</p>
        <p class="text-gray-600 mb-2"><b>name:张三 email:a@qq.com extension:jpg</b></p>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                    限定符
                </th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                    示例
                </th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">name:<span class="italic">USERNAME</span></td>
                <td class="px-3 py-2 text-sm">匹配用户名为 USERNAME 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">album:<span class="italic">ALBUM_NAME</span></td>
                <td class="px-3 py-2 text-sm">匹配所在相册名称为 ALBUM_NAME 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">group:<span class="italic">GROUP_NAME</span></td>
                <td class="px-3 py-2 text-sm">匹配图片所属组名称为 GROUP_NAME 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">strategy:<span class="italic">STRATEGY_NAME</span></td>
                <td class="px-3 py-2 text-sm">匹配图片所属策略名称为 STRATEGY_NAME 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">email:<span class="italic">EMAIL</span></td>
                <td class="px-3 py-2 text-sm">匹配用户邮箱为 EMAIL 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">extension:<span class="italic">EXTENSION</span></td>
                <td class="px-3 py-2 text-sm">匹配图片拓展名为 EXTENSION 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">md5:<span class="italic">FILE_MD5</span></td>
                <td class="px-3 py-2 text-sm">匹配图片文件 md5 值名为 FILE_MD5 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">sha1:<span class="italic">FILE_SHA1</span></td>
                <td class="px-3 py-2 text-sm">匹配图片文件 sha1 值名为 FILE_SHA1 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">ip:<span class="italic">UPLOAD_IP</span></td>
                <td class="px-3 py-2 text-sm">匹配上传 IP 为 UPLOAD_IP 的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">is:public</td>
                <td class="px-3 py-2 text-sm">匹配公开的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">is:private</td>
                <td class="px-3 py-2 text-sm">匹配私有的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">is:unhealthy</td>
                <td class="px-3 py-2 text-sm">匹配不健康的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">is:guest</td>
                <td class="px-3 py-2 text-sm">匹配游客上传的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">is:adminer</td>
                <td class="px-3 py-2 text-sm">匹配管理员上传的图片</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">order:earliest</td>
                <td class="px-3 py-2 text-sm">按最早上传的进行排序</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">order:utmost</td>
                <td class="px-3 py-2 text-sm">按图片大小，从大到小进行排序</td>
            </tr>
            <tr>
                <td class="px-3 py-2 text-gray-600 text-sm">order:least</td>
                <td class="px-3 py-2 text-sm">按图片大小，从小到大进行排序</td>
            </tr>
            </tbody>
        </table>
    </script>


@push('scripts')
        <script>
            let modal = Alpine.store('modal');
            let selectedImages = new Set();
            const PUBLIC_PERMISSION = 1; // \App\Enums\ImagePermission::Public

            // 筛选功能
            function applyFilter() {
                const startTime = document.getElementById('start_time').value;
                const endTime = document.getElementById('end_time').value;
                const perPage = document.getElementById('per_page').value;

                const url = new URL(window.location);
                if (startTime) url.searchParams.set('start_time', startTime);
                if (endTime) url.searchParams.set('end_time', endTime);
                if (perPage) url.searchParams.set('per_page', perPage);

                window.location.href = url.toString();
            }

            // 清除筛选
            function clearFilter() {
                const url = new URL(window.location);
                url.searchParams.delete('start_time');
                url.searchParams.delete('end_time');
                url.searchParams.delete('per_page');
                window.location.href = url.toString();
            }

            // 调整图片flex布局
            function adjustGridLayout() {
                const perPage = document.getElementById('per_page').value;
                const grid = document.getElementById('images-grid');
                const items = document.querySelectorAll('.item');

                // 使用flex布局
                grid.className = 'flex flex-wrap gap-2';

                if (perPage >= 500) {
                    // 500张/页：使用CSS类
                    grid.className += ' gap-1';
                    items.forEach(item => {
                        const img = item.querySelector('img');
                        if (img) {
                            img.className = 'image-500 object-cover transition-all group-hover:brightness-50 mx-auto';
                        }
                        // 隐藏标签
                        const labels = item.querySelectorAll('.absolute.top-1.left-1 span');
                        labels.forEach(label => label.style.display = 'none');
                    });
                } else if (perPage >= 200) {
                    // 200张/页：使用CSS类
                    grid.className += ' gap-1';
                    items.forEach(item => {
                        const img = item.querySelector('img');
                        if (img) {
                            img.className = 'image-200 object-cover transition-all group-hover:brightness-50 mx-auto';
                        }
                    });
                } else {
                    // 40张/页：使用CSS类
                    grid.className += ' gap-2';
                    items.forEach(item => {
                        const img = item.querySelector('img');
                        if (img) {
                            img.className = 'image-40 object-cover transition-all group-hover:brightness-50 mx-auto';
                        }
                        // 显示标签
                        const labels = item.querySelectorAll('.absolute.top-1.left-1 span');
                        labels.forEach(label => label.style.display = '');
                    });
                }
            }

            // 全选功能
            function selectAll() {
                console.log('全选按钮被点击');
                const checkboxes = document.querySelectorAll('.image-checkbox');
                console.log('找到复选框数量:', checkboxes.length);
                const allSelected = Array.from(checkboxes).every(cb => cb.checked);
                const selectAllBtn = document.getElementById('select_all');
                const selectNoneBtn = document.getElementById('select_none');

                checkboxes.forEach(cb => {
                    cb.checked = !allSelected;
                    if (cb.checked) {
                        selectedImages.add(cb.dataset.id);
                    } else {
                        selectedImages.delete(cb.dataset.id);
                    }
                });

                // 更新按钮显示
                if (!allSelected) {
                    selectAllBtn.style.display = 'none';
                    selectNoneBtn.style.display = 'inline-block';
                } else {
                    selectAllBtn.style.display = 'inline-block';
                    selectNoneBtn.style.display = 'none';
                }

                updateBulkDeleteButton();
            }

            // 取消全选功能
            function selectNone() {
                const checkboxes = document.querySelectorAll('.image-checkbox');
                const selectAllBtn = document.getElementById('select_all');
                const selectNoneBtn = document.getElementById('select_none');

                checkboxes.forEach(cb => {
                    cb.checked = false;
                    selectedImages.delete(cb.dataset.id);
                });

                selectAllBtn.style.display = 'inline-block';
                selectNoneBtn.style.display = 'none';

                updateBulkDeleteButton();
            }

            // 更新批量删除按钮状态
            function updateBulkDeleteButton() {
                const bulkDeleteBtn = document.getElementById('bulk_delete');
                bulkDeleteBtn.disabled = selectedImages.size === 0;
                bulkDeleteBtn.textContent = selectedImages.size > 0 ? `批量删除 (${selectedImages.size})` : '批量删除';
            }

            // 批量删除
            function bulkDelete() {
                if (selectedImages.size === 0) return;

                Swal.fire({
                    title: `确认删除选中的 ${selectedImages.size} 张图片吗?`,
                    text: "记录与物理文件将会一起删除，此操作不可恢复。",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '确认删除',
                    cancelButtonText: '取消',
                }).then((result) => {
                    if (result.isConfirmed) {
                        const imageIds = Array.from(selectedImages);
                        console.log('发送删除请求，图片ID:', imageIds);
                        axios.delete('/admin/images/bulk-delete', {
                            data: { ids: imageIds }
                        }).then(response => {
                            console.log('删除响应:', response.data);
                            if (response.data.status) {
                                modal.close('content-modal');
                                toastr.success(response.data.message);
                                setTimeout(function () {
                                    history.go(0);
                                }, 1000);
                            } else {
                                toastr.error(response.data.message);
                            }
                        }).catch(error => {
                            console.error('删除请求失败:', error);
                            toastr.error('删除失败，请重试');
                        });
                    }
                });
            }

            function del(id) {
                Swal.fire({
                    title: `确认删除该图片吗?`,
                    text: "记录与物理文件将会一起删除。",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '确认删除',
                }).then((result) => {
                    if (result.isConfirmed) {
                        axios.delete(`/admin/images/${id}`).then(response => {
                            if (response.data.status) {
                                modal.close('content-modal')
                                toastr.success(response.data.message);
                                setTimeout(function () {
                                    history.go(0);
                                }, 1000);
                            } else {
                                toastr.error(response.data.message);
                            }
                        });
                    }
                });
            }

            $('#grammar').click(function () {
                $('#modal-content').html($('#search-grammar-tpl').html());
                modal.open('content-modal')
            });

            $('.item').click(function () {
                let image = $(this).data('json');
                let previewUrl = ['psd', 'tif'].indexOf(image.extension) === -1 ? image.url : image.thumb_url;
                let html = $('#image-tpl').html()
                    .replace(/__id__/g, image.id)
                    .replace(/__url__/g, previewUrl)
                    .replace(/__user_name__/g, image.user ? image.user.name+'('+image.user.email+')' : '游客')
                    .replace(/__user_email__/g, image.user ? image.user.email : '-')
                    .replace(/__album_name__/g, image.album ? image.album.name : '-')
                    .replace(/__group_name__/g, image.group ? image.group.name : '-')
                    .replace(/__strategy_name__/g, image.strategy ? image.strategy.name : '-')
                    .replace(/__name__/g, image.name)
                    .replace(/__origin_name__/g, image.origin_name)
                    .replace(/__pathname__/g, image.pathname)
                    .replace(/__size__/g, utils.formatSize(image.size * 1024))
                    .replace(/__mimetype__/g, image.mimetype)
                    .replace(/__md5__/g, image.md5)
                    .replace(/__sha1__/g, image.sha1)
                    .replace(/__width__/g, image.width)
                    .replace(/__height__/g, image.height)
                    .replace(/__permission__/g, image.permission === PUBLIC_PERMISSION ? '<i class="fas fa-eye text-red-500"></i> 公开' : '<i class="fas fa-eye-slash text-green-500"></i> 私有')
                    .replace(/__is_unhealthy__/g, image.is_unhealthy ? '<span class="text-red-500"><i class="fas fa-exclamation-triangle"></i> 是</span>' : '否')
                    .replace(/__uploaded_ip__/g, image.uploaded_ip)
                    .replace(/__created_at__/g, image.created_at);

                $('#modal-content').html(html);

                modal.open('content-modal')
            });

            $('.item-user').click(function (e) {
                e.stopPropagation();
                let user = $(this).closest('.item').data('json').user || {};
                let html = $('#user-tpl').html()
                    .replace(/__avatar__/g, user.avatar)
                    .replace(/__name__/g, user.name)
                    .replace(/__email__/g, user.email)
                    .replace(/__capacity__/g, utils.formatSize(user.capacity * 1024))
                    .replace(/__used_capacity__/g, utils.formatSize(user.images_sum_size * 1024))
                    .replace(/__image_num__/g, user.image_num)
                    .replace(/__album_num__/g, user.album_num)
                    .replace(/__registered_ip__/g, user.registered_ip || '-')
                    .replace(/__status__/g, user.status === 1 ? '<span class="text-green-500">正常</span>' : '<span class="text-red-500">冻结</span>')
                    .replace(/__email_verified_at__/g, user.email_verified_at || '-')
                    .replace(/__created_at__/g, user.created_at);

                $('#modal-content').html(html);

                modal.open('content-modal')
            });

            $('.item .delete').click(function (e) {
                e.stopPropagation();
                del($(this).data('id'));
            });

            $('#modal-content').on('click', '.delete', function (e) {
                del($(this).data('id'));
            });

            // 事件监听器
            document.getElementById('filter_btn').addEventListener('click', applyFilter);
            document.getElementById('clear_filter').addEventListener('click', clearFilter);

            // 全选按钮事件监听器
            const selectAllBtn = document.getElementById('select_all');
            if (selectAllBtn) {
                console.log('全选按钮找到，绑定事件');
                selectAllBtn.addEventListener('click', selectAll);
            } else {
                console.error('全选按钮未找到！');
            }

            document.getElementById('select_none').addEventListener('click', selectNone);
            document.getElementById('bulk_delete').addEventListener('click', bulkDelete);
            document.getElementById('per_page').addEventListener('change', function() {
                applyFilter();
            });

            // 页面加载时调整布局
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', adjustGridLayout);
            } else {
                adjustGridLayout();
            }

            // 测试CSS样式是否生效
            setTimeout(() => {
                const testImg = document.querySelector('img');
                if (testImg) {
                    const computedStyle = window.getComputedStyle(testImg);
                    console.log('图片实际尺寸:', computedStyle.width, 'x', computedStyle.height);
                    console.log('图片类名:', testImg.className);
                }
            }, 1000);

            // 复选框变化监听
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('image-checkbox')) {
                    if (e.target.checked) {
                        selectedImages.add(e.target.dataset.id);
                    } else {
                        selectedImages.delete(e.target.dataset.id);
                    }
                    updateBulkDeleteButton();
                }
            });

            // 防止复选框点击事件冒泡到图片容器
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('image-checkbox')) {
                    e.stopPropagation();
                    e.preventDefault();
                }
            });

        </script>
    @endpush
</x-app-layout>
