<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\command\CrudInspect;
use app\admin\development\service\DevCrudService;
use app\common\crud\CrudDefinition;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class MySqlCrudInspectTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('通过独立 MySQL 测试启动器运行');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $this->ready = true;
        Db::schema()->create('crud_inspect', static function (Blueprint $table): void {
            $table->id(); $table->string('title', 80)->comment('标题'); $table->integer('quantity')->default(0); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if ($this->ready) Db::schema()->dropIfExists('crud_inspect');
    }

    public function test_inspect_command_reads_schema_using_whitelisted_named_connection(): void
    {
        $tester = new CommandTester(new CrudInspect());
        self::assertSame(0, $tester->execute(['table' => 'console_crud_inspect', '--connection' => 'mysql']), $tester->getDisplay());
        $result = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains('title', array_column($result['fields'], 'name'));
        self::assertSame(0, Db::table('crud_inspect')->count());
        self::assertSame(1, $tester->execute(['table' => 'console_crud_inspect', '--connection' => 'not-allowed']));
        self::assertStringContainsString('白名单', $tester->getDisplay());
        $tables = (new DevCrudService(base_path(), ['mysql']))->tables('mysql');
        self::assertContains('console_crud_inspect', array_column($tables, 'name'));
    }

    public function test_definition_input_resolves_prefix_once_and_keeps_adopted_identity(): void
    {
        $created = CrudDefinition::fromInput(['table' => 'crud_inspect', 'connection' => 'mysql']);
        self::assertSame('console_crud_inspect', $created->get('table'));
        self::assertSame('console_crud_inspect', CrudDefinition::fromInput($created->toArray())->get('table'));
        self::assertSame('external_table', CrudDefinition::fromInput(['table' => 'external_table', 'tableIdentity' => ['source' => 'adopted', 'kind' => 'physical']])->get('table'));
    }
}
