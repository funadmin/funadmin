<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CrudRelationsExecutionTest extends TestCase
{
    public function test_generated_dictionary_service_uses_native_models_and_excludes_inactive_or_deleted_items(): void
    {
        $data = CrudDefinitionFixture::make('console-dictionary')->toArray();
        $data['fields'][1]['optionsSource'] = 'labels';
        $data['optionsSource'] = [['name' => 'labels', 'type' => 'dictionary', 'dictionary' => 'console_labels', 'labelField' => 'label', 'valueField' => 'value']];
        $data['features']['dictionary'] = true;
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        Db::schema()->create('dict_type', static function (Blueprint $table): void { $table->id(); $table->string('code'); $table->integer('status'); $table->softDeletes(); });
        Db::schema()->create('dict_item', static function (Blueprint $table): void { $table->id(); $table->integer('type_id'); $table->string('value'); $table->string('label'); $table->integer('status'); $table->integer('sort_order'); $table->softDeletes(); });
        $file = tempnam(sys_get_temp_dir(), 'funadmin-dictionary-');
        try {
            Db::table('dict_type')->insert(['id' => 1, 'code' => 'console_labels', 'status' => 1]);
            Db::table('dict_item')->insert([
                ['type_id' => 1, 'value' => '1', 'label' => '已启用', 'status' => 1, 'sort_order' => 1, 'deleted_at' => null],
                ['type_id' => 1, 'value' => '2', 'label' => '停用', 'status' => 0, 'sort_order' => 2, 'deleted_at' => null],
                ['type_id' => 1, 'value' => '3', 'label' => '已删', 'status' => 1, 'sort_order' => 3, 'deleted_at' => '2000-01-01'],
            ]);
            file_put_contents($file, $context['serviceContent']); require $file;
            $service = new \app\admin\service\generated\ConsoleDictionaryService();
            self::assertSame([['value' => '1', 'label' => '已启用']], $service->options('labels'));
            Db::table('dict_type')->where('id', 1)->update(['status' => 0]);
            self::assertSame([], $service->options('labels'));
        } finally {
            unlink($file); Db::schema()->dropIfExists('dict_item'); Db::schema()->dropIfExists('dict_type');
        }
    }

    public function test_generated_relations_and_option_sources_use_eloquent(): void
    {
        $data = CrudDefinitionFixture::make('console-relation')->toArray();
        $data['fields'][] = ['name' => 'parent_id', 'dbType' => 'bigint', 'nullable' => true, 'component' => 'select', 'optionsSource' => 'parents'];
        $data['relations'] = [['name' => 'parent', 'type' => 'belongsTo', 'field' => 'parent_id', 'target' => 'ConsoleRelation', 'targetField' => 'id', 'optionsSource' => 'parents']];
        $data['optionsSource'] = [['name' => 'parents', 'type' => 'relation', 'labelField' => 'title', 'valueField' => 'id']];
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('console_relation', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('parent_id')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $files = [];
        try {
            foreach (['modelContent', 'serviceContent'] as $key) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-relation-'); $files[] = $file; file_put_contents($file, $context[$key]); require $file;
            }
            $model = \app\admin\model\generated\ConsoleRelation::create(['title' => '父级']);
            $child = \app\admin\model\generated\ConsoleRelation::create(['title' => '子级', 'parent_id' => $model->id]);
            self::assertSame($model->id, $child->parent->id);
            $service = new \app\admin\service\generated\ConsoleRelationService();
            self::assertSame([['label' => '父级', 'value' => 1], ['label' => '子级', 'value' => 2]], $service->options('parents'));
            self::assertSame([['label' => '父级', 'value' => 1]], $service->options('parents', null, ['keyword' => '父', 'page' => 1, 'pageSize' => 10]));
        } finally {
            foreach ($files as $file) unlink($file);
            Db::schema()->dropIfExists('console_relation');
        }
    }
}
