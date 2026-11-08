<?php

declare(strict_types=1);

namespace app\common\form\action;

use RuntimeException;

/**
 * 生成的自定义动作方法桩尚未实现。
 * 列表动作入口把它映射为 422「未实现：{动作名称}」，而不是服务器内部错误。
 */
final class FormActionNotImplementedException extends RuntimeException
{
    public function __construct(private readonly string $label)
    {
        parent::__construct('未实现：' . $label);
    }

    public function label(): string
    {
        return $this->label;
    }
}
