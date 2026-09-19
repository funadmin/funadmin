<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 多语言译文条目（locale + key + value），供前端运行时拉取合并。
 */
class LanguageLine extends BaseModel
{
    protected $name = 'language_line';
}
