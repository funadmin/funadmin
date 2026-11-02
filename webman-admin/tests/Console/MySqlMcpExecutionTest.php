<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\McpService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class MySqlMcpExecutionTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('通过独立 MySQL 测试启动器运行');
        self::assertSame('mysql', Db::connection()->getDriverName());
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $this->ready = true;
        Db::schema()->create('mcp_readonly', static function (Blueprint $table): void { $table->id(); $table->string('value'); });
        Db::table('mcp_readonly')->insert(['value' => 'before']);
    }

    protected function tearDown(): void
    {
        if ($this->ready) Db::schema()->dropIfExists('mcp_readonly');
    }

    public function test_mysql_queries_are_read_only_and_do_not_leak_transaction_state(): void
    {
        $service = new McpService();
        $result = $service->handleDbQuery('SELECT value FROM console_mcp_readonly WHERE id = ?', [1]);
        self::assertTrue($result['success']);
        self::assertSame([['value' => 'before']], $result['data']);
        self::assertFalse(Db::connection()->getPdo()->inTransaction());
        self::assertFalse($service->handleDbQuery('SELECT missing_column FROM console_mcp_readonly')['success']);
        self::assertFalse(Db::connection()->getPdo()->inTransaction());
        Db::table('mcp_readonly')->where('id', 1)->update(['value' => 'after']);
        self::assertSame('after', Db::table('mcp_readonly')->value('value'));
        $schema = json_decode($service->handleSchemaResource(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('console_mcp_readonly', $schema);
        self::assertContains('value', array_column($schema['console_mcp_readonly'], 'name'));
    }
}
