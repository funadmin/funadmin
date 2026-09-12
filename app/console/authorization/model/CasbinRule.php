<?php

namespace app\console\authorization\model;

use app\console\model\BackendModel;

class CasbinRule extends BackendModel
{
    protected $name = 'casbin_rule';

    protected $autoWriteTimestamp = false;
}
