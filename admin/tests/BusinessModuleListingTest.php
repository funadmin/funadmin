<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\development\service\BusinessModuleService;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/** 代码生成列表：生成类型投影（tree_enabled）与生成目标筛选（target）。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BusinessModuleListingTest extends TestCase
{
    public function test_listing_projects_tree_enabled_from_saved_schema_without_returning_schema(): void
    {
        $this->createTables();
        $tree = $this->seed('tree', ['type' => 'core'], ['list' => ['tree' => ['enabled' => true, 'parentField' => 'pid']]]);
        $flat = $this->seed('flat', ['type' => 'core'], ['list' => ['tree' => ['enabled' => false]]]);
        $noList = $this->seed('no_list', ['type' => 'core'], ['nodes' => []]);
        $noForm = $this->seed('no_form', ['type' => 'core'], null);

        $list = (new BusinessModuleService())->listing(1, 20, '', '', '')['list'];
        $byId = array_column($list, null, 'id');

        self::assertTrue($byId[$tree]['tree_enabled']);
        self::assertFalse($byId[$flat]['tree_enabled']);
        self::assertFalse($byId[$noList]['tree_enabled']);
        self::assertFalse($byId[$noForm]['tree_enabled']);
        foreach ($list as $row) {
            self::assertArrayNotHasKey('schema_document', $row);
            self::assertArrayNotHasKey('schema', $row);
        }
    }

    public function test_listing_filters_by_target_type_before_pagination(): void
    {
        $this->createTables();
        $core = $this->seed('core_a', ['type' => 'core'], ['nodes' => []]);
        $legacy = $this->seed('core_legacy', null, ['nodes' => []]);
        $plugin = $this->seed('plugin_a', ['type' => 'plugin', 'pluginCode' => 'cms', 'scope' => 'admin'], ['nodes' => []]);
        $service = new BusinessModuleService();

        $plugins = $service->listing(1, 20, '', '', '', 'plugin');
        self::assertSame(1, $plugins['total']);
        self::assertSame([$plugin], array_column($plugins['list'], 'id'));

        // 缺省目标按 core 处理；total 来自 SQL 过滤后的分页结果，而不是本页内筛选。
        $cores = $service->listing(1, 1, '', '', '', 'core');
        self::assertSame(2, $cores['total']);
        self::assertCount(1, $cores['list']);
        $coreIds = array_merge(array_column($cores['list'], 'id'), array_column($service->listing(2, 1, '', '', '', 'core')['list'], 'id'));
        sort($coreIds);
        self::assertSame([$core, $legacy], $coreIds);

        self::assertSame(3, $service->listing(1, 20, '', '', '')['total']);
    }

    public function test_keyword_matches_table_name_description_and_code(): void
    {
        $this->createTables();
        $goods = $this->seed('shop_goods', ['type' => 'core'], ['nodes' => []], '商品', 'fa_shop_goods');
        $article = $this->seed('cms_article', ['type' => 'core'], ['nodes' => []], '文章', 'fa_cms_article');
        $service = new BusinessModuleService();

        self::assertSame([$goods], array_column($service->listing(1, 20, 'fa_shop', '', '')['list'], 'id'));
        self::assertSame([$article], array_column($service->listing(1, 20, '文章', '', '')['list'], 'id'));
        self::assertSame([$article], array_column($service->listing(1, 20, 'cms_art', '', '')['list'], 'id'));
    }

    public function test_development_service_rejects_unknown_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('target 不合法');
        BusinessDevelopmentService::production(base_path(), ['sqlite'])->modules(1, 20, '', '', '', 'module');
    }

    private function seed(string $code, ?array $target, ?array $schema, ?string $name = null, string $tableName = ''): int
    {
        $formId = null;
        if ($schema !== null) {
            $formId = Db::table('form')->insertGetId(['schema_document' => json_encode($schema, JSON_THROW_ON_ERROR)]);
        }
        return Db::table('business_module')->insertGetId([
            'code' => $code, 'name' => $name ?? $code, 'table_name' => $tableName, 'form_id' => $formId, 'origin' => 'visual',
            'metadata' => $target === null ? null : json_encode(['target' => $target], JSON_THROW_ON_ERROR),
        ]);
    }

    private function createTables(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('form', static function (Blueprint $table): void {
            $table->id(); $table->json('schema_document')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('business_module', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name')->default(''); $table->string('table_name')->default('');
            $table->unsignedBigInteger('form_id')->nullable(); $table->string('origin')->default('visual');
            $table->string('lifecycle_status')->default('draft'); $table->string('generation_status')->default('idle');
            $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('crud_generation', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('business_module_id'); $table->string('status');
            $table->string('recovery_status')->default('none'); $table->timestamps();
        });
    }
}
