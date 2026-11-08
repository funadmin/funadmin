<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\DefinitionValidator;
use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class CrudGeneratedModelTest extends TestCase
{
    public function test_generated_model_loads_and_round_trips_eloquent_values(): void
    {
        $definition = CrudDefinitionFixture::make('console-model');
        (new DefinitionValidator())->validate($definition, sys_get_temp_dir());
        $context = ProductionTemplateContext::build($definition);
        self::assertStringNotContainsString('think\\', $context['modelContent']);
        self::assertStringNotContainsString('protected string $table', $context['modelContent']);
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('console_model', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $file = tempnam(sys_get_temp_dir(), 'funadmin-model-');
        try {
            file_put_contents($file, $context['modelContent']);
            require $file;
            $model = new \app\admin\business\console_model\model\ConsoleModel();
            self::assertSame('console_model', $model->getTable());
            $created = $model->newQuery()->create(['title' => '测试', 'metadata' => ['value' => 7]]);
            self::assertSame(['value' => 7], $created->fresh()->metadata);
            $created->delete();
            self::assertNull($model->newQuery()->find($created->id));
            self::assertNotNull($model->newQuery()->withTrashed()->find($created->id));
        } finally { unlink($file); Db::schema()->dropIfExists('console_model'); }
    }
}
