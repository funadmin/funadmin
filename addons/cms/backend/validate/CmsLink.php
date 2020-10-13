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
 * Date: 2019/8/2
 */
namespace app\admin\validate;

use think\validate;

class CmsLink extends validate
{
    protected $rule = [
        'name|名字' => [
            'require' => 'require',
            'max'     => '255',
        ],
        'url|网站地址' => [
            'require' => 'require',
            'max'     => '255',
        ],
    ];
}