<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\common\model;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Blacklist extends BaseModel {
    /**
     * @var bool
     */
    use SoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}
