<?php

declare(strict_types=1);

namespace Tests\Console;

final class MySqlPluginPublicationTest extends PluginPublicationExecutionTest
{
    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要隔离 MySQL 启动器');
    }
}
