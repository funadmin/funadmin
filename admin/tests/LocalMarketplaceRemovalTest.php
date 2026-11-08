<?php

declare(strict_types=1);
namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Webman\Route;
use support\Db;
use app\common\service\database\MigrationService;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LocalMarketplaceRemovalTest extends TestCase
{
    public function test_local_operations_code_and_bundled_exception_are_removed(): void
    {
        self::assertDirectoryDoesNotExist(base_path('app/market'));
        self::assertDirectoryDoesNotExist(base_path('app/admin/plugins/market'));
        self::assertNotContains('market', config('plugins.bundled', []));
    }

    public function test_market_server_routes_are_absent_while_framework_client_routes_remain(): void
    {
        Route::load([config_path()]);
        foreach ([['GET','/market'],['POST','/market/api/v3/auth/login'],['GET','/market/api/v3/plugins'],['GET','/admin/plugins/market/category/'],['POST','/market/pay/notify/alipay']] as [$method,$path]) {
            self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch($method,$path)[0],$path);
        }
        $route=Route::dispatch('GET','/admin/system/plugin/market/categories');
        self::assertSame(\FastRoute\Dispatcher::FOUND,$route[0]);
        self::assertSame('app\\admin\\plugin\\controller\\SystemPlugin',$route[1]['callback'][0]);
        self::assertTrue(class_exists(\app\common\plugin\marketplace\NativeMarketplaceAdapter::class));
        self::assertTrue(class_exists(\app\admin\plugin\service\PluginMarketplaceService::class));
    }

    public function test_public_member_auth_remains_at_its_existing_urls_without_market_classes(): void
    {
        Route::load([config_path()]);
        foreach (['login','register','refresh','me','password','logout'] as $action) {
            $route=Route::dispatch('POST','/api/v3/auth/'.$action);
            self::assertSame(\FastRoute\Dispatcher::FOUND,$route[0]);
            self::assertSame(['app\\api\\controller\\v3\\Auth',$action],$route[1]['callback']);
        }
        self::assertTrue(class_exists(\app\common\service\member\MemberAccountService::class));
        self::assertTrue(class_exists(\app\common\service\member\MemberTokenService::class));
    }

    public function test_baseline_carries_no_local_market_permissions_and_keeps_client_resources(): void
    {
        (new MigrationService())->run();
        self::assertFalse(Db::table('permission')->where(static function ($query): void {
            $query->where(static fn ($source) => $source->where('source_type', 'plugin')->where('source_name', 'market'))->orWhere('code', 'like', 'market:%');
        })->exists());
        self::assertFalse(Db::table('admin_menu')->where('href', 'like', '%/plugin/market%')->exists());
        $clientCode=\app\admin\authorization\service\PermissionCode::derive(\app\admin\plugin\controller\SystemPlugin::class,'installed');
        self::assertSame(1,(int) Db::table('permission')->where('code',$clientCode)->value('status'));
    }
}
