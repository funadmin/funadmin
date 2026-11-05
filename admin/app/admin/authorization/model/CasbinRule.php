<?php

namespace app\admin\authorization\model;

use app\common\model\BaseModel;

class CasbinRule extends BaseModel
{
    protected $table = 'casbin_rule';

    protected $autoWriteTimestamp = false;
}
