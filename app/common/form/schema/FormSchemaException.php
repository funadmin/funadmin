<?php

declare(strict_types=1);

namespace app\common\form\schema;

use InvalidArgumentException;

final class FormSchemaException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private readonly string $path,
        private readonly string $errorCode = 'FORM_SCHEMA_INVALID'
    ) {
        parent::__construct($message);
    }

    public function schemaPath(): string
    {
        return $this->path;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
