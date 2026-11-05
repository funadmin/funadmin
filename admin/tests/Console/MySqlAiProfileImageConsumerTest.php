<?php

declare(strict_types=1);

namespace Tests\Console;

final class MySqlAiProfileImageConsumerTest extends AiProfileImageConsumerTest
{
    private bool $mysqlReady = false;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        parent::setUp();
        $this->mysqlReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->mysqlReady) parent::tearDown();
    }
}
