<?php

declare(strict_types=1);

namespace Tests\Console;

final class MySqlAiSandboxConsumerTest extends AiSandboxConsumerExecutionTest
{
    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') === '1') parent::tearDown();
    }
}
