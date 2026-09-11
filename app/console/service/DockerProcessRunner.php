<?php

declare(strict_types=1);

namespace app\console\service;

/** 以参数数组调用 Docker CLI 的可替换进程端口。 */
interface DockerProcessRunner
{
    public function run(array $argv, int $timeoutSeconds): ProcessResult;
}
