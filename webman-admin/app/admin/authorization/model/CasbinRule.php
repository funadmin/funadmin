<?php

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;

class CasbinRule extends BackendModel
{
    protected $table = 'casbin_rule';

    protected $autoWriteTimestamp = false;
}
