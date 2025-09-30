<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lsky Pro Configuration
    |--------------------------------------------------------------------------
    |
    | 这里配置Lsky Pro的各种参数
    |
    */

    // 多文件上传配置
    'max_upload_files' => env('LSKY_MAX_UPLOAD_FILES', 10),
    'max_file_size' => env('LSKY_MAX_FILE_SIZE', 10240), // KB
    'allowed_extensions' => env('LSKY_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp,bmp,ico,svg'),
];
