<?php

namespace think\annotation\model\relation;

use Attribute;
use think\annotation\model\Relation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class BelongsToMany extends Relation
{
    /**
     * BELONGS TO MANY 关联定义
     * @param string $name 关联名
     * @param string $model 模型名
     * @param string $middle 中间表/模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 当前模型关联键
     */
    public function __construct(
        public string $name,
        public string $model,
        public string $middle = '',
        public string $foreignKey = '',
        public string $localKey = ''
    )
    {
    }
}
