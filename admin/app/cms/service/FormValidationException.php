<?php

declare(strict_types=1);

namespace app\cms\service;

use InvalidArgumentException;

/**
 * 访客提交校验失败。`errors` 为“字段名 → 错误键”；令牌、限流等表单级错误用键 `_form`。
 * 错误键见 FormValueValidator::ERRORS 与 FormSubmissionService::ERRORS，由调用方按语言翻译。
 */
final class FormValidationException extends InvalidArgumentException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors, int $code = 422)
    {
        parent::__construct('表单校验失败', $code);
    }
}
