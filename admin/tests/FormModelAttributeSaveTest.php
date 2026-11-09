<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\form\model\Form as FormModel;
use app\admin\form\service\FormBuilderRegistryService;
use app\common\form\builder\Form;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use Tests\Support\FormMetadataTables;

/** Eloquent save(array) 的参数是 options 而非属性：这些写入点必须真正落库。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormModelAttributeSaveTest extends TestCase
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

    public function testSavePublishConfigPersistsMergedConfig(): void
    {
        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        $detail = $service->createVisual([
            'code' => 'publish_config_fixture', 'name' => '发布设置', 'connection' => 'sqlite', 'target' => ['type' => 'core'],
        ], 'test:1');
        $moduleId = (int) $detail['module']['id'];

        $service->savePublishConfig($moduleId, ['menuName' => '订单管理', 'sortOrder' => 10]);
        $result = $service->savePublishConfig($moduleId, ['icon' => 'el-icon-list', 'sortOrder' => 20]);

        $expected = ['menuName' => '订单管理', 'sortOrder' => 20, 'icon' => 'el-icon-list'];
        self::assertEquals((object) $expected, $result['publishConfig']);
        $stored = FormModel::findOrFail((int) $detail['module']['form_id'])->publish_config;
        self::assertSame($expected, $stored);
    }

    public function testBuilderRegistrySyncsName(): void
    {
        $table = Db::connection()->getTablePrefix() . 'builder_fixture';
        $connection = (string) config('database.default');
        $form = FormModel::create([
            'form_key' => 'builder_fixture', 'name' => '旧名称', 'table_name' => $table, 'connection' => $connection,
            'source_type' => 'created',
        ]);
        $builder = Form::make('builder_fixture')->title('新名称')->table($table, $connection);
        $builder->input('title', '标题');
        $builder->list(['pageSize' => 50]);

        (new FormBuilderRegistryService())->register((int) $form->id, $builder);

        $fresh = $form->fresh();
        self::assertSame('新名称', $fresh->name);
        self::assertSame(50, $fresh->schema_document['list']['pageSize'] ?? null);
    }
}
