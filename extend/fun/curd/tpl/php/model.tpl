<?php
declare (strict_types = 1);

namespace {%namespace%};
use app\common\model\BaseModel;
use think\Model;
use think\model\concern\SoftDelete;
/**
 * @mixin \think\Model
 */
class {%name%} extends BaseModel
{
    {%softDelete%}

    {%connection%}

    {%primaryKey%}

    {%tableName%}

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    // 追加属性
    protected $append = [
{%appendsList%}
    ];

{%attrsList%}
}
