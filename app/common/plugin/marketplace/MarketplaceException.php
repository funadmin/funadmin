<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use RuntimeException;

final class MarketplaceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
