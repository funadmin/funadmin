<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\http\BusinessApiErrorMapper;
use app\common\form\schema\FormSchemaException;
use PHPUnit\Framework\TestCase;

/** 「表单尚未发布」以稳定错误码返回，前端按错误码而不是文案识别。 */
final class BusinessApiErrorMapperTest extends TestCase
{
    public function test_unpublished_form_schema_maps_to_a_stable_code(): void
    {
        $mapped = BusinessApiErrorMapper::map(new FormSchemaException('表单尚未发布', '/formId', 'FORM_SCHEMA_NOT_PUBLISHED'));
        self::assertSame(422, $mapped['httpStatus']);
        self::assertSame('FORM_SCHEMA_NOT_PUBLISHED', $mapped['error']['code']);
        self::assertSame('表单尚未发布', $mapped['message']);
    }

    public function test_other_schema_errors_stay_validation_errors(): void
    {
        $mapped = BusinessApiErrorMapper::map(new FormSchemaException('字段名重复', '/nodes/0/field'));
        self::assertSame('VALIDATION_ERROR', $mapped['error']['code']);
        self::assertSame('字段名重复', $mapped['message']);
    }
}
