<?php

namespace think\annotation\model\relation;

use Attribute;
use think\annotation\model\Relation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class MorphOne extends Relation
{
    /**
     * MORPH  One 关联定义
     * @param string $name 关联名
     * @param string $model 模型名
     * @param string|array $morph 多态字段信息
     * @param string $type 多态类型
     */
    public function __construct(
        public string $name,
        public string $model,
        public        $morph = null,
        public string $type = ''
    )
    {
        $this->morph = $morph ?? $name;
    }
}
