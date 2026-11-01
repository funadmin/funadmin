<?php

declare(strict_types=1);

namespace app\common\form\validation;

use RuntimeException;

/**
 * 表单异步验证治理异常。
 */
final class FormAsyncValidationException extends RuntimeException
{
    /** @param array<int, array{path: string, message: string}> $fieldErrors */
    public function __construct(
        private readonly string $validationCode,
        private readonly array $fieldErrors = []
    ) {
        parent::__construct($validationCode);
    }

    public function errorCode(): string
    {
        return $this->validationCode;
    }

    /** @return array<int, array{path: string, message: string}> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
