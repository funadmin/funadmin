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
 * Date: 2019/9/2
 */
namespace app\common\model;

class  Member extends BaseModel{

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function getProvince(){

        $this->hasOne('Region','province','id');
    }
    public function getCity(){

        $this->hasOne('Region','city','id');
    }
    public function getDistrict(){

        $this->hasOne('Region','district','id');
    }

}