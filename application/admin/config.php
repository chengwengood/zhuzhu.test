<?php
/*
'''''''''''''''''''''''''''''''''''''''''''''''''''''''''
author:ming    contactQQ:811627583
''''''''''''''''''''''''''''''''''''''''''''''''''''''''''
 */

return [
    // +----------------------------------------------------------------------
    // | 后台模板设置
    // +----------------------------------------------------------------------

    'template' => [
        // 模板路径
        'view_path' => './themes/admin/'
    ],
        // 视图输出字符串内容替换
    'view_replace_str'      => [
        '__UPLOAD__' => '/public/uploads',
        '__STATIC__' => '/public/static/admin',
        '__IMAGES__' => '/public/static/admin/images',
        '__JS__'     => '/public/static/admin/js',
        '__CSS__'    => '/public/static/admin/css',
    ],
];