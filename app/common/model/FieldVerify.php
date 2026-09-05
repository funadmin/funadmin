<?php

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class FieldVerify extends BaseModel
{
    /**
     * 验证规则编码是现有字符串主键。
     *
     * @var string
     */
    protected $pk = 'verify';

    protected $keyType = 'string';

    use LaravelSoftDelete;
}
