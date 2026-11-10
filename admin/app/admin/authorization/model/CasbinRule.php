<?php

namespace app\admin\authorization\model;

use app\common\model\BaseModel;

class CasbinRule extends BaseModel
{
    protected $table = 'casbin_rule';

    /** casbin_rule 没有时间戳列。 */
    public $timestamps = false;
}
