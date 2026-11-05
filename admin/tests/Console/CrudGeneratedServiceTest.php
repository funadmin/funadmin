<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class CrudGeneratedServiceTest extends TestCase
{
    public function test_generated_service_saves_creates_updates_and_rolls_back_relations(): void
    {
        $context = ProductionTemplateContext::build(CrudDefinitionFixture::make('console-service'));
        self::assertStringNotContainsString('think\\', $context['serviceContent']);
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('console_service', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $files = [];
        try {
            foreach (['modelContent', 'serviceContent'] as $key) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-service-');
                $files[] = $file; file_put_contents($file, $context[$key]); require $file;
            }
            $service = new \app\admin\service\generated\ConsoleServiceService();
            $model = $service->save(null, ['title' => '新增', 'metadata' => ['one' => 1], 'ignored' => 'not-column'], static fn () => null);
            self::assertSame('新增', $model->fresh()->title);
            $service->save($model, ['title' => '编辑'], static fn () => null);
            self::assertSame('编辑', $model->fresh()->title);
            try { $service->save($model, ['title' => '回滚'], static function (): void { throw new \RuntimeException('关系写入失败'); }); }
            catch (\RuntimeException $error) { self::assertSame('关系写入失败', $error->getMessage()); }
            self::assertSame('编辑', $model->fresh()->title);
            self::assertSame(1, $service->query()->count());
        } finally {
            foreach ($files as $file) unlink($file);
            Db::schema()->dropIfExists('console_service');
        }
    }
}
