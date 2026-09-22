<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

/**
 * 多语言译文条目（locale + key + value），供前端运行时拉取合并。
 * ns 为数据库生成列（crud/plugin 容器取前两段，其余取首段），禁止写入。
 * 软删保证译文包版本协商（MAX id/updated_at/deleted_at）在删除后仍然单调。
 */
class LanguageLine extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'language_line';
}
