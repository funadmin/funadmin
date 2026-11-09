<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\model\Form as FormModel;
use app\admin\form\repository\FormSchemaRepository;
use app\common\form\builder\Form;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use Tests\Support\FormMetadataTables;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormSchemaRepositoryTest extends TestCase
{
    use FormMetadataTables;

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->createMetadataTables();
    }

    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_save_version_syncs_name_from_title(): void
    {
        $table = Db::connection()->getTablePrefix() . 'repository_fixture';
        $connection = (string) config('database.default');
        $form = FormModel::create([
            'form_key' => 'repository_fixture', 'name' => '旧名称', 'table_name' => $table,
            'connection' => $connection, 'source_type' => 'created',
        ]);
        $builder = Form::make('repository_fixture')->title('新名称')->table($table, $connection);
        $builder->input('title', '标题');

        (new FormSchemaRepository())->saveVersion((int) $form->id, $builder->compile()->document(), 'business_api', 'test:1');

        $fresh = $form->fresh();
        self::assertSame('新名称', $fresh->name);
        self::assertSame('新名称', $fresh->schema_document['title']);
    }
}
