<?php

declare(strict_types=1);

namespace app\common\ai\provider;

use RuntimeException;
use Throwable;

/** Provider 边界的稳定错误分类，消息中不得包含凭据。 */
final class AiProviderException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCategory,
        string $message,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function category(): string
    {
        return $this->errorCategory;
    }
}
