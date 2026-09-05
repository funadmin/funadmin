<?php

namespace think\annotation\model\relation;

use Attribute;
use think\annotation\model\Relation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class HasOneThrough extends Relation
{
    /**
     * HAS ONE 远程关联定义
     * @param string $name 关联名
     * @param string $model 模型名
     * @param string $through 中间模型名
     * @param string $foreignKey 关联外键
     * @param string $throughKey 关联外键
     * @param string $localKey 当前主键
     * @param string $throughPk 中间表主键
     */
    public function __construct(
        public string $name,
        public string $model,
        public string $through,
        public string $foreignKey = '',
        public string $throughKey = '',
        public string $localKey = '',
        public string $throughPk = ''
    )
    {
    }
}
