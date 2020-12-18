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
 * Date: 2019/9/2
 */

namespace addons\bbs\common\model;

use app\common\model\BaseModel;

class BbsSignRule extends BaseModel
{
    protected $name = 'addons_bbs_sign_rule';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}