<?php

declare(strict_types=1);

namespace app\console\form\exception;

use RuntimeException;
use Throwable;

final class FormMigrationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $ddlApplied,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
