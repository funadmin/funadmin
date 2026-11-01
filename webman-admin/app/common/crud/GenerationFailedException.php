<?php

declare(strict_types=1);

namespace app\common\crud;

use RuntimeException;
use Throwable;

final class GenerationFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $manifest,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function manifest(): array
    {
        return $this->manifest;
    }
}