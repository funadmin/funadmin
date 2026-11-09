<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\model\Form;
use app\admin\form\service\FormDesignerService;
use app\common\form\builder\Form as FormBuilder;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use Tests\Support\FormMetadataTables;

/** FormDesignerService::save() 只写身份与非派生列；更新时载荷缺省的管理列保持原值。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormDesignerSaveTest extends TestCase
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

    public function test_update_without_management_keys_keeps_existing_values(): void
    {
        $service = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $created = $service->save(['schema_document' => $this->document('初版'), 'remark' => '备注', 'sort_order' => 7, 'status' => 0]);
        $id = (int) ($created['id'] ?? $created['form']['id'] ?? 0);
        $form = Form::findOrFail($id);

        $service->save(['id' => $id, 'expected_schema_hash' => $form->schema_hash, 'schema_document' => $this->document('二版')]);

        $fresh = $form->fresh();
        self::assertSame('备注', $fresh->remark);
        self::assertSame(7, (int) $fresh->sort_order);
        self::assertSame(0, (int) $fresh->status);
        self::assertSame('二版', $fresh->name);
    }

    public function test_explicit_management_keys_still_update(): void
    {
        $service = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $created = $service->save(['schema_document' => $this->document('初版'), 'remark' => '备注', 'sort_order' => 7]);
        $id = (int) ($created['id'] ?? $created['form']['id'] ?? 0);
        $form = Form::findOrFail($id);
        self::assertSame(1, (int) $form->status);

        $service->save(['id' => $id, 'expected_schema_hash' => $form->schema_hash, 'schema_document' => $this->document('初版'), 'remark' => '', 'sort_order' => 0, 'status' => 0]);

        $fresh = $form->fresh();
        self::assertSame('', $fresh->remark);
        self::assertSame(0, (int) $fresh->sort_order);
        self::assertSame(0, (int) $fresh->status);
    }

    public function test_derived_columns_come_from_compiled_schema(): void
    {
        $service = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $created = $service->save(['schema_document' => $this->document('派生'), 'schema_origin' => 'import']);
        $form = Form::findOrFail((int) ($created['id'] ?? $created['form']['id'] ?? 0));
        $version = Db::table('form_schema_version')->where('form_id', $form->id)->orderByDesc('version')->first();

        self::assertSame('派生', $form->name);
        self::assertSame((string) $version->schema_hash, (string) $form->schema_hash);
        self::assertSame(2, (int) $form->schema_version);
        self::assertSame('import', $form->schema_origin);
        self::assertSame('draft', $form->publish_status);
        self::assertSame('designer_save_fixture', $form->schema_document['key'] ?? null);
    }

    private function document(string $title): array
    {
        $prefix = Db::connection()->getTablePrefix();
        $builder = FormBuilder::make('designer_save_fixture')->title($title)->table($prefix . 'designer_save_fixture', (string) config('database.default'));
        $builder->input('title', '标题')->column('varchar(120)');
        return $builder->compile()->document();
    }
}
