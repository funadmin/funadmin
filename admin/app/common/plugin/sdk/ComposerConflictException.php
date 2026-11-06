<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use app\admin\plugin\PluginPreconditionException;

/** Confirmation required, not a failed lifecycle operation. */
final class ComposerConflictException extends PluginPreconditionException
{
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('插件自带依赖存在版本冲突，请确认继续或取消');
    }
}
