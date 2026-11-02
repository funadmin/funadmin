<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\McpService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class McpToolsExecutionTest extends TestCase
{
    public function test_configuration_projection_never_exposes_arbitrary_configuration(): void
    {
        $service = new McpService();
        $public = $service->handleSysConfig();
        self::assertSame('sqlite', $public['database']['type']);
        self::assertSame(config('app.default_timezone'), $service->handleSysConfig('app.default_timezone')['value']);
        $this->expectExceptionMessage('配置不在公开白名单');
        $service->handleSysConfig('database.connections.mysql.password');
    }

    public function test_configuration_resource_uses_only_public_projection(): void
    {
        $config = json_decode((new McpService())->handleConfigResource(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('sqlite', $config['database']['type']);
        self::assertArrayNotHasKey('session', $config);
        self::assertArrayNotHasKey('log', $config);
        self::assertArrayNotHasKey('connections', $config['database']);
    }

    public function test_read_only_sql_returns_arrays_and_rejects_side_effects(): void
    {
        $service = new McpService();
        $result = $service->handleDbQuery('SELECT ? AS example', ['safe']);
        self::assertTrue($result['success']);
        self::assertSame([['example' => 'safe']], $result['data']);
        foreach (['DELETE FROM admin', 'SELECT 1; SELECT 2', 'SELECT 1 INTO OUTFILE "/tmp/unsafe"', 'SELECT SLEEP(10)', 'SELECT load_file("/etc/passwd")'] as $sql) {
            self::assertFalse($service->handleDbQuery($sql)['success'], $sql);
        }
        self::assertSame(0, (int) Db::connection()->getPdo()->query('PRAGMA query_only')->fetchColumn());
    }

    public function test_schema_resource_works_with_current_connection(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('mcp_schema_test', static function (Blueprint $table): void { $table->id(); $table->string('name'); });
        try {
            $schema = json_decode((new McpService())->handleSchemaResource(), true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('fun_mcp_schema_test', $schema);
            self::assertContains('name', array_column($schema['fun_mcp_schema_test'], 'name'));
        } finally { Db::schema()->dropIfExists('mcp_schema_test'); }
    }

    public function test_command_tool_runs_only_side_effect_free_commands(): void
    {
        $service = new McpService();
        $result = $service->handleWebmanCommand('version');
        self::assertTrue($result['success']);
        self::assertSame(0, $result['return_code']);
        foreach (['start', 'restart', 'stop', 'make:model', 'crud:generate', 'mcp', 'version;id'] as $command) {
            self::assertFalse($service->handleWebmanCommand($command)['success']);
        }
        self::assertFalse($service->handleWebmanCommand('version', [], ['ansi;id'])['success']);
        self::assertFalse($service->handleWebmanCommand('version', ['--help;id'])['success']);
    }

    public function test_file_tool_refuses_private_runtime_and_configuration(): void
    {
        $service = new McpService();
        $this->expectExceptionMessage('文件路径不在公开范围');
        $service->handleFileOperation('read', base_path('config/database.php'));
    }

    public function test_user_tool_projects_only_safe_fields_and_excludes_deleted_admins(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->dropIfExists('admin');
        Db::schema()->create('admin', static function (Blueprint $table): void {
            $table->id(); $table->string('username'); $table->string('real_name')->default(''); $table->string('email')->nullable();
            $table->string('mobile')->nullable(); $table->string('password'); $table->boolean('status')->default(true); $table->timestamps(); $table->softDeletes();
        });
        try {
            Db::table('admin')->insert([
                ['id' => 1, 'username' => 'one', 'password' => 'hidden-password', 'deleted_at' => null],
                ['id' => 2, 'username' => 'deleted', 'password' => 'hidden-password', 'deleted_at' => '2000-01-01'],
            ]);
            $service = new McpService();
            self::assertSame(1, $service->handleUserManagement('list', 0, 1000)['count']);
            self::assertSame(['total' => 1, 'active' => 1, 'inactive' => 0], $service->handleUserManagement('count'));
            self::assertSame('one', $service->handleUserManagement('info', 1)['username']);
            self::assertStringNotContainsString('hidden-password', json_encode($service->handleUserManagement('list')));
            $this->expectExceptionMessage('用户不存在');
            $service->handleUserManagement('info', 2);
        } finally { Db::schema()->dropIfExists('admin'); }
    }

    public function test_file_tool_reads_public_uploads_but_rejects_sibling_and_symlink_escape(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-mcp-files-' . bin2hex(random_bytes(8));
        mkdir($root . '/public/uploads', 0700, true);
        mkdir($root . '/public/uploads-private', 0700);
        file_put_contents($root . '/public/uploads/example.txt', 'public');
        file_put_contents($root . '/public/uploads-private/example.txt', 'private');
        symlink($root . '/public/uploads-private/example.txt', $root . '/public/uploads/escape.txt');
        try {
            $service = new McpService($root);
            self::assertSame('public', $service->handleFileOperation('read', 'public/uploads/example.txt')['content']);
            self::assertSame(6, $service->handleFileOperation('info', 'public/uploads/example.txt')['size']);
            foreach (['public/uploads-private/example.txt', 'public/uploads/escape.txt', 'public/uploads/../../config/database.php'] as $path) {
                try { $service->handleFileOperation('read', $path); self::fail('越界文件应拒绝'); }
                catch (\RuntimeException $error) { self::assertSame('文件路径不在公开范围', $error->getMessage()); }
            }
        } finally {
            unlink($root . '/public/uploads/escape.txt');
            unlink($root . '/public/uploads/example.txt');
            unlink($root . '/public/uploads-private/example.txt');
            rmdir($root . '/public/uploads'); rmdir($root . '/public/uploads-private'); rmdir($root . '/public'); rmdir($root);
        }
    }

    public function test_log_tool_redacts_credentials_without_stdout_output(): void
    {
        $handler = new \Monolog\Handler\TestHandler();
        $service = (new McpService())->setLogger(new \Monolog\Logger('mcp-test', [$handler]));
        ob_start();
        try { $service->handleWriteLog('password=hidden', 'info', ['api_key' => 'never-expose']); }
        finally { $stdout = ob_get_clean(); }
        self::assertSame('', $stdout);
        $record = $handler->getRecords()[0];
        self::assertStringNotContainsString('hidden', $record->message);
        self::assertSame('[REDACTED]', $record->context['api_key']);
    }

    public function test_missing_crud_definition_is_a_structured_failure(): void
    {
        $result = (new McpService())->handleCrud('nonexistent-definition.json');
        self::assertFalse($result['success']);
        self::assertSame('CRUD 配置文件不存在', $result['error']);
    }
}
