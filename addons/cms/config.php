<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/7
 */

return [
    'status' => [
        'title' => '是否开放:',
        'type' => 'radio',
        'rule' => '',
        'content' => [

        ],
        'msg'     => '',
        'tips'     => '',
        'ok'      => '',
        'value'   => '1',

    ],
    'logo' => [
        'title' => 'logo图标:',
        'type' => 'image',
        'rule' => '',
        'content' => [

        ],
        'msg'     => '',
        'tips'     => '',
        'ok'      => '',
        'value'   => 'https://demo.funadmin.com/storage/uploads/20200222/37695ee1af180456740988567849ffa9.png',

    ],
    'theme' => [
        'title' => '主题:',
        'type' => 'text',
        'rule' => '',
        'content' => [

        ],
        'msg'     => '',
        'tips'     => '',
        'ok'      => '',
        'value'   => 'default',

    ],
    'seo' => [
        'title' => 'seo:',
        'type' => 'array',
        'rule' => 'required',
        'content' =>  [
            'title'=>'funadmin cms插件',
            'keywords'=>'funadmin,cms插件,funadmin后台管理框架',
            'description'=>'funadmin 强大的后台管理框架，强大的插件系统',

        ],
        'msg'     => '',
        'tips'     => '',
        'ok'      => '',
        'value'   => [
            'title'=>'funadmin cms插件',
            'keywords'=>'funadmin,cms插件,funadmin后台管理框架',
            'description'=>'funadmin 强大的后台管理框架，强大的插件系统',

        ],

    ],

];