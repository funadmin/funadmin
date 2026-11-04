<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\admin\system\model;


use app\admin\model\BackendModel;
use app\common\model\concern\LaravelSoftDelete;

class MemberGroup extends BackendModel {
    protected $table = 'member_group';
    protected $fillable = ['name', 'icon', 'status'];

    /**
     * @var bool
     */
    use LaravelSoftDelete;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}
