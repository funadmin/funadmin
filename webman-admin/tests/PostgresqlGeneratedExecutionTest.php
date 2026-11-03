<?php

declare(strict_types=1);

namespace Tests;

use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use Tests\Console\CrudDefinitionFixture;

final class PostgresqlGeneratedExecutionTest extends TestCase
{
    public function test_generated_php_executes_crud_soft_delete_restore_and_reference_protection(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $root = sys_get_temp_dir() . '/pg-generated-' . bin2hex(random_bytes(5));
        mkdir($root, 0700);
        $data = CrudDefinitionFixture::make('pg-generated', 'pgsql')->toArray();
        $data['table'] = Db::connection()->getTablePrefix() . 'pg_generated';
        $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        $data['features']['referenceProtection'] = true;
        try {
            $definition = CrudDefinition::fromArray($data);
            $generator = new CrudGenerator($root);
            $plan = $generator->plan($definition);
            $generator->generate($definition, $plan['confirmToken']);
            Db::connection()->unprepared(file_get_contents($root . '/' . $data['generationTargets']['migration']));
            foreach (['model', 'service', 'validate', 'controller'] as $artifact) require $root . '/' . $data['generationTargets'][$artifact];
            $request = new Request("POST /admin/generated/pg-generated HTTP/1.1\r\n\r\n");
            $request->setPost(['title' => 'PG 新增', 'metadata' => ['safe' => true]]);
            Context::set(\Webman\Http\Request::class, $request);
            $controller = new \app\admin\controller\generated\PgGeneratedController();
            $created = json_decode($controller->create()->rawBody(), true);
            self::assertSame(200, $created['code']);
            $id = $created['data']['id'];
            self::assertSame('PG 新增', Db::table('pg_generated')->value('title'));
            self::assertSame(['safe' => true], json_decode(Db::table('pg_generated')->value('metadata'), true));
            $request->setPost(['title' => 'PG 修改']);
            self::assertSame(200, json_decode($controller->update($id)->rawBody(), true)['code']);
            self::assertSame('PG 修改', Db::table('pg_generated')->value('title'));
            self::assertSame(200, json_decode($controller->remove($id)->rawBody(), true)['code']);
            self::assertNotNull(Db::table('pg_generated')->value('deleted_at'));
            self::assertSame(200, json_decode($controller->restore($id)->rawBody(), true)['code']);
            self::assertNull(Db::table('pg_generated')->value('deleted_at'));
            Db::schema()->create('pg_generated_reference', static function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id(); $table->bigInteger('record_id'); $table->foreign('record_id')->references('id')->on('pg_generated');
            });
            Db::table('pg_generated_reference')->insert(['record_id' => $id]);
            self::assertSame(422, json_decode($controller->remove($id)->rawBody(), true)['code']);
            self::assertNull(Db::table('pg_generated')->value('deleted_at'));
        } finally {
            Db::schema()->dropIfExists('pg_generated_reference'); Db::schema()->dropIfExists('pg_generated'); Context::destroy();
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
