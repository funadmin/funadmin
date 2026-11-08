<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

final class MySqlCrudGeneratedExecutionTest extends TestCase
{
    public function test_generated_sql_and_php_execute_create_update_soft_delete_in_mysql(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = sys_get_temp_dir() . '/funadmin-mysql-generated-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        $data = CrudDefinitionFixture::make('mysql-generated', 'mysql')->toArray();
        $data['table'] = 'console_crud_generated';
        $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        $data['features']['referenceProtection'] = true;
        $definition = CrudDefinition::fromArray($data);
        try {
            $generator = new CrudGenerator($root);
            $plan = $generator->plan($definition);
            $result = $generator->generate($definition, $plan['confirmToken']);
            self::assertSame('written', $result['write']['status']);
            Db::connection()->unprepared(file_get_contents($root . '/' . $data['generationTargets']['migration']));
            self::assertTrue(Db::schema()->hasColumns('crud_generated', ['id', 'title', 'metadata', 'created_at', 'updated_at', 'deleted_at']));
            foreach (['model', 'service', 'validate', 'controller'] as $artifact) require $root . '/' . $data['generationTargets'][$artifact];
            $request = new Request("POST /admin/business/mysql-generated HTTP/1.1\r\n\r\n");
            $request->setPost(['title' => 'MySQL 新增', 'metadata' => ['safe' => true]]);
            Context::set(\Webman\Http\Request::class, $request);
            $controller = new \app\admin\business\mysql_generated\controller\MysqlGeneratedController();
            $created = json_decode($controller->create()->rawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(200, $created['code']);
            self::assertSame('MySQL 新增', Db::table('crud_generated')->value('title'));
            $id = $created['data']['id'];
            $request->setPost(['title' => 'MySQL 修改']);
            self::assertSame(200, json_decode($controller->update($id)->rawBody(), true)['code']);
            self::assertSame('MySQL 修改', Db::table('crud_generated')->value('title'));
            self::assertSame(200, json_decode($controller->remove($id)->rawBody(), true)['code']);
            self::assertNotNull(Db::table('crud_generated')->value('deleted_at'));
            self::assertSame(200, json_decode($controller->restore($id)->rawBody(), true)['code']);
            self::assertNull(Db::table('crud_generated')->value('deleted_at'));
            Db::schema()->create('crud_reference', static function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id(); $table->bigInteger('record_id');
                $table->foreign('record_id')->references('id')->on('crud_generated');
            });
            Db::table('crud_reference')->insert(['record_id' => $id]);
            self::assertSame(422, json_decode($controller->remove($id)->rawBody(), true)['code']);
            self::assertNull(Db::table('crud_generated')->value('deleted_at'));
        } finally {
            Db::schema()->dropIfExists('crud_reference');
            Db::schema()->dropIfExists('crud_generated');
            Context::destroy();
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
