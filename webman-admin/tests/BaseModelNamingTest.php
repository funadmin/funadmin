<?php

declare(strict_types=1);

namespace Tests;

use app\common\model\BaseModel;
use PHPUnit\Framework\TestCase;

final class BaseModelNamingTest extends TestCase
{
    public function test_only_camel_case_helpers_are_exposed(): void
    {
        self::assertTrue(method_exists(BaseModel::class, 'getTablePrefix'));
        self::assertTrue(method_exists(BaseModel::class, 'getDatabaseName'));
        self::assertTrue(method_exists(BaseModel::class, 'getPluginTableName'));
        self::assertFalse(method_exists(BaseModel::class, 'get_table_prefix'));
        self::assertFalse(method_exists(BaseModel::class, 'get_databasename'));
        self::assertFalse(method_exists(BaseModel::class, 'get_pluginstablename'));
    }
}
