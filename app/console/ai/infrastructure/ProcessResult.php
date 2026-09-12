<?php

declare(strict_types=1);

namespace app\console\ai\infrastructure;

/** 不含隐式 shell 语义的进程执行结果。 */
final class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr
    ) {
    }
}
