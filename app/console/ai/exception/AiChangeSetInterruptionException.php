<?php

declare(strict_types=1);

namespace app\console\ai\exception;

use RuntimeException;

/** 模拟或表示进程在 ChangeSet 文件提交期间不可继续的中断。 */
final class AiChangeSetInterruptionException extends RuntimeException
{
}
