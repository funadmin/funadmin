<?php

declare(strict_types=1);

namespace app\console\service;

use InvalidArgumentException;
use Throwable;

final class BusinessOperationException extends InvalidArgumentException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly array $details = [],
        ?string $message = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message ?? $errorCode, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function details(): array
    {
        return $this->details;
    }
}
