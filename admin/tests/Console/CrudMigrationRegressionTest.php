<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\service\BusinessModuleService;
use app\admin\development\service\FormCrudDefinitionFactory;
use app\admin\development\service\GenerationResourceTransaction;
use app\common\crud\CrudGenerator;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CrudMigrationRegressionTest extends TestCase
{
    public function test_nonempty_module_and_generation_lists_honor_page_and_size(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        BusinessGenerationFixture::create();
        try {
            [$firstModule, $firstGeneration] = BusinessGenerationFixture::seed();
            [$secondModule, $secondGeneration] = BusinessGenerationFixture::seed();
            $service = new BusinessModuleService();
            $modules = $service->listing(2, 1, '', '', '');
            self::assertSame(2, $modules['total']);
            self::assertCount(1, $modules['list']);
            self::assertSame($firstModule, $modules['list'][0]['id']);
            $history = $service->generations(2, 1);
            self::assertSame(2, $history['total']);
            self::assertCount(1, $history['list']);
            self::assertSame($firstGeneration, (int) $history['list'][0]['id']);
            self::assertNotSame($firstModule, $secondModule);
            self::assertNotSame($firstGeneration, $secondGeneration);
        } finally { BusinessGenerationFixture::drop(); }
    }

    public static function adminPrefixes(): array
    {
        return [[null, '/admin/generated/review-route'], ['/admin/generated/review-route', '/admin/generated/review-route'], ['/admin', '/admin']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminPrefixes')]
    public function test_factory_default_routes_match_frontend_admin_requests(?string $prefix, string $expected): void
    {
        $form = self::form('review_route');
        if ($prefix !== null) $form['publish_config'] = ['apiPrefix' => $prefix];
        $definition = (new FormCrudDefinitionFactory())->create($form);
        $files = (new CrudGenerator(base_path()))->renderManagedBundle($definition);
        $paths = $definition->get('generationTargets');
        $temporary = [];
        try {
            foreach (['model', 'validate', 'service', 'controller'] as $artifact) {
                $file = tempnam(sys_get_temp_dir(), 'crud-route-review-');
                $temporary[] = $file;
                file_put_contents($file, $files[$paths[$artifact]]);
                require $file;
            }
            $class = 'app\\admin\\generated\\review_route\\controller\\ReviewRouteController';
            $definitions = (new \ReflectionMethod(\Webman\Route::class, 'buildAnnotationRouteDefinitions'))->invoke(null, [
                new \Webman\Finder\FileInfo(end($temporary), ['class' => $class]),
            ]);
            $dispatcher = \FastRoute\simpleDispatcher(static function (\FastRoute\RouteCollector $collector) use ($definitions): void {
                foreach ($definitions as $route) $collector->addRoute($route['methods'], $route['path'], $route['callback']);
            });
            self::assertSame(\FastRoute\Dispatcher::FOUND, $dispatcher->dispatch('GET', $expected)[0]);
            self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, $dispatcher->dispatch('GET', '/admin/admin/generated/review-route')[0]);
        } finally { foreach ($temporary as $file) unlink($file); }
    }

    public function test_regeneration_preserves_unchanged_permission_ids_role_and_field_grants(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        (new MigrationService())->run();
        $resources = [
            ['resourceKey' => 'permission|review|generated:review:list', 'resourceType' => 'permission', 'sourceName' => 'review', 'code' => 'generated:review:list', 'obj' => 'admin/generated.reviewcontroller', 'act' => 'index', 'permissionType' => 'route', 'name' => 'Review'],
            ['resourceKey' => 'menu|review|/generated/review', 'resourceType' => 'menu', 'sourceName' => 'review', 'name' => 'Review', 'href' => '/generated/review', 'permission' => 'generated:review:list'],
        ];
        $transaction = new GenerationResourceTransaction();
        $transaction->begin(); $transaction->apply($resources); $transaction->commit();
        $permissionId = (int) Db::table('permission')->where('code', 'generated:review:list')->value('id');
        $menuId = (int) Db::table('admin_menu')->where('source_name', 'review')->whereNull('deleted_at')->value('id');
        Db::table('casbin_rule')->insert(['ptype' => 'p', 'v0' => 'role:2', 'v1' => 'admin', 'v2' => 'admin/generated.reviewcontroller', 'v3' => 'index', 'rule_hash' => hash('sha256', 'review-role-grant')]);
        $fieldId = Db::table('permission_field')->insertGetId(['permission_id' => $permissionId, 'resource' => 'review', 'field' => 'title', 'name' => 'Title']);
        Db::table('auth_group_field_permission')->insert(['role_id' => 2, 'field_id' => $fieldId]);
        $transaction->begin(); $transaction->apply($resources); $transaction->commit();
        self::assertSame(1, Db::table('casbin_rule')->where('v0', 'role:2')->count());
        self::assertSame($permissionId, (int) Db::table('permission')->where('code', 'generated:review:list')->whereNull('deleted_at')->value('id'));
        self::assertSame($menuId, (int) Db::table('admin_menu')->where('source_name', 'review')->whereNull('deleted_at')->value('id'));
        self::assertSame(1, Db::table('auth_group_field_permission')->where('field_id', $fieldId)->count());
        $transaction->begin(); $transaction->apply([$resources[0]]); $transaction->rollback();
        self::assertSame(1, Db::table('admin_menu')->where('id', $menuId)->whereNull('deleted_at')->count());
        $replacement = $resources[0];
        $replacement['resourceKey'] = 'permission|review|generated:review:export';
        $replacement['code'] = 'generated:review:export';
        $replacement['act'] = 'export';
        $transaction->begin(); $transaction->apply([$replacement]); $transaction->commit();
        self::assertSame(0, Db::table('casbin_rule')->where('v0', 'role:2')->count());
        self::assertSame(0, Db::table('auth_group_field_permission')->where('field_id', $fieldId)->count());
        self::assertSame(0, Db::table('admin_menu')->where('id', $menuId)->whereNull('deleted_at')->count());
        self::assertSame(1, Db::table('permission')->where('code', 'generated:review:export')->whereNull('deleted_at')->count());
    }

    public static function form(string $key): array
    {
        return ['form_key' => $key, 'name' => 'Review', 'table_name' => 'fun_' . $key, 'connection' => 'mysql', 'source_type' => 'created',
            'fields' => [['field_name' => 'title', 'label' => 'Title', 'type' => 'input', 'column_type' => 'varchar(80)', 'nullable' => 0]]];
    }
}
