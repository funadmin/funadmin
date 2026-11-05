<?php

declare(strict_types=1);

namespace app\common\form\dataSource;

use InvalidArgumentException;

final class FormDataSourceException extends InvalidArgumentException
{
    public function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
