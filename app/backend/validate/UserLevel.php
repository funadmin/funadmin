<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/2
 */
namespace app\backend\validate;

use think\Validate;

class UserLevel extends Validate
{
    protected $rule = [
        'level_name|等级名称' => [
            'require' => 'require',
            'max'     => '255',
            'unique'  => 'user_level',
        ],

        'description|描述' => [
            'max' => '255',
        ],

    ];
}