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
namespace app\backend\model;


use think\model\concern\SoftDelete;

class Member extends BackendModel {


    /**
     * @var bool
     */
    use SoftDelete;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function group(){
        return  $this->belongsTo('MemberGroup','group_id','id');
    }
    public function level()
    {
        return $this->belongsTo('MemberLevel', 'level_id', 'id', [], 'LEFT');
    }

}
