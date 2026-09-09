<?php

declare(strict_types=1);

namespace app\console\service;

use RuntimeException;

/** 仅用于表示进程在 durable checkpoint 之间异常终止，不触发当前实例回滚。 */
final class GenerationInterruptionException extends RuntimeException
{
}
