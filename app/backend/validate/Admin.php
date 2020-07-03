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
 * Date: 2019/8/2
 */
namespace app\backend\validate;

use think\validate;

class Admin extends validate
{
    protected $rule = [
        'username|用户名' => [
            'require' => 'require',
            'max'     => '100',
            'unique'  => 'admin',
        ],
        'password|密码' =>[
            'require' => 'require',
        ],
        'group_id|用户组'=>[
            'require' => 'require',
        ],
    ];
}