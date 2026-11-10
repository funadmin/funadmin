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

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\GuardsMemberReferences;
use app\common\service\member\MemberReferenceGuard;

class MemberLevel extends BaseModel
{
    protected $table = 'member_level';
    protected $fillable = ['name', 'amount', 'discount', 'thumb', 'status', 'sort_order', 'description'];
    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use GuardsMemberReferences;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    protected function memberReferenceKind(): string
    {
        return MemberReferenceGuard::LEVEL;
    }
}
