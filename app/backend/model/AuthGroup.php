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


namespace app\backend\model;
use think\model\relation\HasOne;

class AuthGroup extends BackendModel
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}