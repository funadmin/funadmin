<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginMakeCrud;
use app\common\plugin\sdk\PluginScaffolder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class MySqlPluginCommandTest extends TestCase
{
    public function test_make_crud_infers_real_schema_in_temporary_plugin_without_mutating_table(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        self::assertSame(PluginMakeCrud::class, (new \ReflectionMethod(PluginMakeCrud::class, '__construct'))->getDeclaringClass()->getName());
        $root = sys_get_temp_dir() . '/funadmin-plugin-inspect-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        Db::schema()->create('plugin_inspect', static function (Blueprint $table): void {
            $table->id(); $table->string('title', 80); $table->timestamps(); $table->softDeletes();
        });
        try {
            Db::table('plugin_inspect')->insert(['title' => '原数据']);
            (new PluginScaffolder($root . '/plugins'))->scaffold('inspectfixture');
            $command = new CommandTester(new PluginMakeCrud($root));
            $arguments = ['plugin' => 'inspectfixture', 'entity' => 'inspected', '--table' => 'console_plugin_inspect', '--connection' => 'mysql', '--definition-output' => 'definition.json'];
            self::assertSame(0, $command->execute($arguments), $command->getDisplay());
            self::assertFileExists($root . '/definition.json');
            $definition = json_decode(file_get_contents($root . '/definition.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('adopted', $definition['tableIdentity']['source']);
            self::assertSame('console_plugin_inspect', $definition['table']);
            self::assertSame(['type' => 'plugin', 'plugin' => 'inspectfixture', 'scope' => 'both'], $definition['target']);
            self::assertSame('原数据', Db::table('plugin_inspect')->value('title'));
            self::assertSame([], glob($root . '/plugins/inspectfixture/database/migrations/*.sql'));
            self::assertSame(1, $command->execute($arguments));
            $arguments['--definition-output'] = '../escape.json';
            self::assertSame(1, $command->execute($arguments));
            $arguments['--connection'] = 'not-allowed';
            self::assertSame(1, $command->execute($arguments));
            self::assertStringContainsString('白名单', $command->getDisplay());
        } finally {
            Db::schema()->dropIfExists('plugin_inspect');
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
