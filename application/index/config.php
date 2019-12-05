<?php
return [
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template' => [
        // 模板路径
//        'view_path' => './themes/index/',
        'view_path' => './themes/hdindex/',
    ],
           // 视图输出字符串内容替换
    'view_replace_str'      => [
        '__STATIC__' => '/public/static/index',
        '__IMG__' => '/public/static/index/images',
        '__JS__'     => '/public/static/index/js',
        '__CSS__'    => '/public/static/index/css',
        '__FONTS__'    => '/public/static/index/fonts',
    ],
];