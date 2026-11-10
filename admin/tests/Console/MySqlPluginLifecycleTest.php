<?php

declare(strict_types=1);

namespace Tests\Console;

final class MySqlPluginLifecycleTest extends PluginLifecycleExecutionTest
{
    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要隔离 MySQL 启动器');
        \Tests\Support\MySqlTestDatabase::clear();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') return;
        try { parent::tearDown(); }
        finally { \Tests\Support\MySqlTestDatabase::clear(); }
    }
}
