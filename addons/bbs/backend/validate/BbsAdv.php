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

class BbsAdv extends validate
{
    protected $rule = [
        'pid|广告位置' => [
            'require' => 'require',
        ],
        'ad_image|广告图片' => [
            'require' => 'require',
        ],
        'ad_name|广告名' => [
            'require' => 'require',
        ],

    ];
}