<?php

declare(strict_types=1);

namespace Tests;

use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\plugin\sdk\Manifest;
use app\admin\plugin\service\PluginInfrastructureService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Console\CrudDefinitionFixture;

final class PostgresqlPluginTest extends TestCase
{
    public function test_generated_plugin_has_native_migrations_and_additive_forward_changes(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        Db::schema()->create('system_migration', static function (Blueprint $table): void {
            $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum', 64); $table->bigInteger('executed_at'); $table->unique(['scope', 'version']);
        });
        $root = sys_get_temp_dir() . '/pg-plugin-' . bin2hex(random_bytes(4));
        $plugin = 'pgfixture';
        try {
            (new PluginScaffolder($root . '/plugins'))->scaffold($plugin);
            $data = CrudDefinitionFixture::make('pg-record', 'pgsql')->toArray();
            $data['target'] = ['type' => 'plugin', 'plugin' => $plugin, 'scope' => 'admin'];
            $data['table'] = Db::connection()->getTablePrefix() . 'pg_record';
            $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
            $data['module'] = $plugin; $data['permissionPrefix'] = 'pgfixture:pg-record';
            unset($data['generationTargets']);
            $definition = CrudDefinition::fromArray($data);
            $generator = new CrudGenerator($root);
            $plan = $generator->plan($definition);
            $generator->generate($definition, $plan['confirmToken']);
            $migration = $root . '/plugins/pgfixture/database/migrations/pgsql/001_create_pg_record.sql';
            self::assertFileExists($migration);
            $manifest = Manifest::fromDirectory($root . '/plugins/' . $plugin);
            $infrastructure = new PluginInfrastructureService(projectRoot: $root);
            $infrastructure->assertExternalTables($manifest);
            self::assertSame(['001_create_pg_record'], $infrastructure->migrate($manifest)['executed']);
            self::assertSame([], $infrastructure->migrate($manifest)['executed']);
            $data['fields'][] = ['name' => 'code', 'label' => '编号', 'dbType' => 'varchar(30)', 'nullable' => true, 'unique' => true];
            $next = CrudDefinition::fromArray($data);
            $plan = $generator->plan($next);
            $overwrite = array_column(array_filter($plan['files'], static fn (array $file): bool => $file['status'] === 'conflict'), 'path');
            $generator->generate($next, $plan['confirmToken'], $overwrite);
            self::assertSame(['002_alter_pg_record'], $infrastructure->migrate($manifest)['executed']);
            self::assertTrue(Db::schema()->hasColumn('pg_record', 'code'));
            self::assertTrue(Db::schema()->hasIndex('pg_record', ['code'], 'unique'));
        } finally {
            Db::schema()->dropIfExists('pg_record'); Db::schema()->dropIfExists('system_migration');
            if (is_dir($root)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                rmdir($root);
            }
        }
    }
}
