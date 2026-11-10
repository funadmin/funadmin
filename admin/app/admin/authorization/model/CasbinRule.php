<?php

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class CasbinRule extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'casbin_rule';

    /** casbin_rule 没有时间戳列。 */
    public $timestamps = false;
}
