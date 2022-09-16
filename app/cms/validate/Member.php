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
namespace app\cms\validate;

use think\Validate;

class Member extends Validate
{
    protected $rule = [
        'username|用户名' => 'require|min:2|max:18|unique:member',
        'email|邮箱' => 'require|email|unique:member',
        'password|密码' => 'require|min:6|max:20',
        'vercode|校验码' => 'require|max:6',
        'moto|签名' => 'min:10|max:100',
    ];

    protected $message = [
        'username.max' => '名称最多不能超过25个字符',
        'username.unique' => '名称已经存在',
        'username.min' => '名称最多不能少于2个字符',
        'email.email' => '邮箱格式错误',
    ];

}