<?php

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;

class CasbinRule extends BackendModel
{
    protected $name = 'casbin_rule';

    protected $autoWriteTimestamp = false;
}
