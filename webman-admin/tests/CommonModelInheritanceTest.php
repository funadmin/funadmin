<?php

declare(strict_types=1);

namespace Tests;

use app\common\model\BaseModel;
use PHPUnit\Framework\TestCase;
use support\Db;

final class CommonModelInheritanceTest extends TestCase
{
    public function test_backend_models_use_the_common_base_directly(): void
    {
        foreach ([\app\admin\authorization\model\AdminMenu::class, \app\admin\authorization\model\Permission::class, \app\admin\form\model\Form::class, \app\admin\development\model\BusinessModule::class] as $class) {
            self::assertSame(BaseModel::class, (new \ReflectionClass($class))->getParentClass()->getName(), $class);
        }
    }

    public function test_generated_model_preserves_casts_and_soft_deletes_with_common_base(): void
    {
        $definition = \Tests\Console\CrudDefinitionFixture::make('common-inheritance');
        $context = \app\common\crud\ProductionTemplateContext::build($definition, ['modelNamespace' => 'Tests\\GeneratedCommon']);
        $file = tempnam(sys_get_temp_dir(), 'funadmin-common-model-');
        file_put_contents($file, $context['modelContent']);
        try {
            require $file;
            $class = 'Tests\\GeneratedCommon\\generated\\CommonInheritance';
            self::assertSame(BaseModel::class, (new \ReflectionClass($class))->getParentClass()->getName());
            Db::schema()->create('common_inheritance', static function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id(); $table->string('title'); $table->text('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
            });
            $model = $class::create(['title' => '公共模型', 'metadata' => ['value' => 1]]);
            self::assertSame(['value' => 1], $model->fresh()->metadata);
            $model->delete();
            self::assertNull($class::find($model->getKey()));
            self::assertTrue($class::withTrashed()->findOrFail($model->getKey())->restore());
            self::assertSame('公共模型', $class::findOrFail($model->getKey())->title);
        } finally {
            Db::schema()->dropIfExists('common_inheritance');
            unlink($file);
        }
    }

    public function test_generated_model_named_base_model_has_no_import_collision(): void
    {
        $context = \app\common\crud\ProductionTemplateContext::build(\Tests\Console\CrudDefinitionFixture::make('base-model'), ['modelNamespace' => 'Tests\\GeneratedName']);
        $file = tempnam(sys_get_temp_dir(), 'funadmin-model-name-');
        file_put_contents($file, $context['modelContent']);
        try {
            $process = proc_open([PHP_BINARY, '-l', $file], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $output);
        } finally { unlink($file); }
    }
}
