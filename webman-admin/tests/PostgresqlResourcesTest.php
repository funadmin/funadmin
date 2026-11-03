<?php

declare(strict_types=1);

namespace Tests;

use app\common\crud\CrudDefinition;
use app\common\crud\CrudResourceInstaller;
use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Console\CrudDefinitionFixture;

final class PostgresqlResourcesTest extends TestCase
{
    private string $namespace;
    private string $previous;

    protected function setUp(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $this->namespace = 'resources_' . bin2hex(random_bytes(5));
        $connection = Db::connection();
        $connection->statement('CREATE SCHEMA "' . $this->namespace . '"');
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $this->previous = $manager->getDefaultConnection();
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections[$this->namespace] = array_replace($connection->getConfig(), ['name' => $this->namespace, 'search_path' => $this->namespace]);
        $config['database.connections'] = $connections;
        $manager->setDefaultConnection($this->namespace);
    }

    protected function tearDown(): void
    {
        if (!isset($this->namespace)) return;
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $manager->setDefaultConnection($this->previous);
        $manager->purge($this->namespace);
        Db::statement('DROP SCHEMA "' . $this->namespace . '" CASCADE');
    }

    public function test_generated_permission_menu_language_apply_is_idempotent_and_preserves_edits(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $schema = Db::schema();
        $schema->create('permission', static function (Blueprint $table): void {
            $table->id(); $table->bigInteger('pid')->default(0); $table->string('app_name'); $table->string('code')->nullable()->unique();
            foreach (['obj', 'act', 'name', 'resource_type', 'source_type', 'source_name'] as $name) $table->string($name)->default('');
            $table->boolean('status'); $table->boolean('is_public'); $table->integer('sort_order'); $table->timestamps(); $table->softDeletes();
        });
        $schema->create('admin_menu', static function (Blueprint $table): void {
            $table->id(); $table->bigInteger('pid'); $table->bigInteger('permission_id');
            foreach (['app_name', 'name', 'href', 'query', 'target', 'icon', 'source_type', 'source_name'] as $name) $table->text($name);
            $table->boolean('status'); $table->integer('sort_order'); $table->timestamps(); $table->softDeletes();
        });
        $schema->create('language_line', static function (Blueprint $table): void {
            $table->id(); $table->string('locale'); $table->string('key'); $table->text('value'); $table->timestamps(); $table->unique(['locale', 'key']);
        });
        $root = sys_get_temp_dir() . '/pg-resources-' . bin2hex(random_bytes(4));
        mkdir($root . '/database/generated', 0700, true);
        try {
            $data = CrudDefinitionFixture::make('pg-resource', 'pgsql')->toArray();
            $data['permission'] = ['enabled' => true, 'groupName' => '测试权限', 'actions' => [['action' => 'index', 'codeSuffix' => 'index', 'label' => '查看']]];
            $data['menu'] = ['enabled' => true, 'name' => "中文\\'菜单", 'parentId' => 0, 'parentSourceName' => '', 'target' => '_self', 'icon' => 'i-ep-menu', 'sortOrder' => 10, 'hidden' => false, 'keepAlive' => true, 'affix' => false];
            $data['generationTargets']['langMigration'] = 'database/generated/language.sql';
            $definition = CrudDefinition::fromArray($data);
            $context = ProductionTemplateContext::build($definition);
            $files = [];
            foreach (['permissionMigration' => 'permissionMigrationContent', 'langMigration' => 'langMigrationContent'] as $target => $key) {
                $path = $data['generationTargets'][$target];
                file_put_contents($root . '/' . $path, $context[$key]);
                $files[] = ['path' => $path, 'hash' => hash('sha256', $context[$key])];
            }
            $manifest = ['definitionHash' => $definition->hash(), 'files' => $files];
            $installer = new CrudResourceInstaller($root);
            $first = $installer->apply($data, $manifest);
            self::assertGreaterThan(0, $first['langInserted']);
            self::assertSame(1 + count($definition->toArray()['permission']['actions']), Db::table('permission')->count());
            self::assertSame("中文\\'菜单", Db::table('admin_menu')->value('name'));
            $row = Db::table('language_line')->first();
            Db::table('language_line')->where('id', $row->id)->update(['value' => '人工修订']);
            self::assertSame(0, $installer->apply($data, $manifest)['langInserted']);
            self::assertSame('人工修订', Db::table('language_line')->where('id', $row->id)->value('value'));
            $installer->apply($data, $manifest, true);
            self::assertNotSame('人工修订', Db::table('language_line')->where('id', $row->id)->value('value'));
            self::assertSame(1, Db::table('admin_menu')->count());
        } finally {
            foreach (['admin_menu', 'permission', 'language_line'] as $table) $schema->dropIfExists($table);
            foreach (glob($root . '/database/generated/*') as $file) unlink($file);
            rmdir($root . '/database/generated'); rmdir($root . '/database'); rmdir($root);
        }
    }
}
