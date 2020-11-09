<?php

/**
 * FunAadmin
 * ============================================================================
 * 版权所有 2017-2028 FunAadmin，并保留所有权利。
 * 网站地址: https://www.FunAadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\common\model;

use app\common\model\BaseModel;

class Blacklist extends BaseModel {

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}
