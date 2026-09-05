<?php

declare(strict_types=1);

function phase3Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$controller = $root . '/app/backend/controller/system/SystemPlugin.php';
$authSource = (string) file_get_contents($root . '/app/backend/controller/auth/AdminAuth.php');
$route = (string) file_get_contents($root . '/app/backend/route/app.php');
$migrations = glob($root . '/database/migrations/*plugin*admin_web*.sql') ?: [];

phase3Expect(is_file($controller), '缺少 SystemPlugin Admin Web API 控制器');
$source = is_file($controller) ? (string) file_get_contents($controller) : '';
phase3Expect(str_contains($source, 'extends AdminApiController'), 'SystemPlugin 必须继承 AdminApiController');
foreach (['CheckAdminApiRole::class', 'CheckAdminApiCsrf::class', 'SystemLog::class'] as $middleware) {
    phase3Expect(str_contains($source, $middleware), 'SystemPlugin 缺少中间件：' . $middleware);
}
phase3Expect(!str_contains($source, 'extends Backend'), 'SystemPlugin 不得复用旧 Layui Controller');
foreach (['PluginMarketplaceService', 'PluginPackagePipeline', 'PluginService', 'PluginConfigService', 'PluginPackageHistoryService'] as $service) {
    phase3Expect(str_contains($source, $service), 'SystemPlugin 必须调用服务：' . $service);
}

$requiredRoutes = [
    "Route::post('system/plugin/account/login'",
    "Route::post('system/plugin/account/logout'",
    "Route::get('system/plugin/account/current'",
    "Route::get('system/plugin/market/categories'",
    "Route::get('system/plugin/market/search'",
    "Route::get('system/plugin/market/:name/versions'",
    "Route::get('system/plugin/market/:name'",
    "Route::post('system/plugin/market/check-updates'",
    "Route::get('system/plugin/local/discovered'",
    "Route::get('system/plugin/local/installed'",
    "Route::get('system/plugin/local/:name'",
    "Route::post('system/plugin/local/install'",
    "Route::post('system/plugin/cloud/:name/install'",
    "Route::post('system/plugin/:name/update'",
    "Route::post('system/plugin/:name/migrate'",
    "Route::post('system/plugin/:name/enable'",
    "Route::post('system/plugin/:name/disable'",
    "Route::get('system/plugin/:name/config'",
    "Route::put('system/plugin/:name/config'",
    "Route::delete('system/plugin/:name/uninstall'",
    "Route::delete('system/plugin/:name/package'",
    "Route::get('system/plugin/:name/history'",
    "Route::get('system/plugin/:name/operations'",
    "Route::get('system/plugin/modules/enabled'",
];
foreach ($requiredRoutes as $expected) {
    phase3Expect(str_contains($route, $expected), '缺少插件 REST 路由：' . $expected);
}

phase3Expect(count($migrations) === 1, '必须新增唯一且不冲突的插件 Admin Web 权限 migration');
$migration = $migrations ? (string) file_get_contents($migrations[0]) : '';
foreach (['system:plugin:list', 'system:plugin:install', 'system:plugin:update', 'system:plugin:migrate', 'system:plugin:enable', 'system:plugin:disable', 'system:plugin:config', 'system:plugin:uninstall', 'system:plugin:package-delete', 'system:plugin:history'] as $permission) {
    phase3Expect(str_contains($migration, $permission), '权限 migration 缺少：' . $permission);
}
phase3Expect(str_contains($migration, 'system/plugin/index'), '权限 migration 必须注册插件中心菜单');
$pluginPermissionMappings = [
    'installed' => 'system:plugin:list',
    'discovered' => 'system:plugin:list',
    'localdetail' => 'system:plugin:list',
    'enabledmodules' => 'system:plugin:list',
    'accountlogin' => 'system:plugin:account',
    'accountlogout' => 'system:plugin:account',
    'currentaccount' => 'system:plugin:account',
    'marketcategories' => 'system:plugin:list',
    'marketsearch' => 'system:plugin:list',
    'marketdetail' => 'system:plugin:list',
    'marketversions' => 'system:plugin:list',
    'checkupdates' => 'system:plugin:list',
    'installlocal' => 'system:plugin:install',
    'installcloud' => 'system:plugin:install',
    'update' => 'system:plugin:update',
    'migrate' => 'system:plugin:migrate',
    'enable' => 'system:plugin:enable',
    'disable' => 'system:plugin:disable',
    'getconfig' => 'system:plugin:config',
    'saveconfig' => 'system:plugin:config',
    'uninstall' => 'system:plugin:uninstall',
    'deletepackage' => 'system:plugin:package-delete',
    'history' => 'system:plugin:history',
    'operations' => 'system:plugin:history',
];
foreach ($pluginPermissionMappings as $action => $frontendPermission) {
    phase3Expect(
        str_contains($authSource, "'backend/systemplugin:{$action}' => '{$frontendPermission}'"),
        "AdminAuth 缺少插件权限映射：{$action} -> {$frontendPermission}"
    );
}
phase3Expect(str_contains($authSource, "return ['*'];"), '超级管理员必须稳定获得前端权限通配符');
foreach (['accountlogout', 'currentaccount', 'marketcategories', 'marketsearch', 'marketdetail', 'marketversions', 'checkupdates', 'discovered', 'localdetail', 'installcloud', 'getconfig', 'operations', 'enabledmodules'] as $action) {
    phase3Expect(str_contains($migration, "'{$action}'"), '权限 migration 缺少规范化控制器 action：' . $action);
}
phase3Expect(str_contains($source, "file('file')"), '本地安装必须接收 multipart ZIP');
phase3Expect(str_contains($source, 'getOriginalExtension()'), '本地 ZIP 必须校验扩展名');
phase3Expect(str_contains($source, 'getMime()'), '本地 ZIP 必须校验 MIME');
phase3Expect(str_contains($source, 'getSize()'), '本地 ZIP 必须校验上传大小');
phase3Expect(str_contains($source, 'purgeConfirm'), 'purge 必须要求二次确认字段');
$querySource = (string) file_get_contents($root . '/app/backend/service/PluginCenterQueryService.php');
phase3Expect(str_contains($querySource, 'plugin-assets'), 'enabled modules 必须输出受控 plugin-assets URL');
$publisherSource = (string) file_get_contents($root . '/extend/fun/plugins/PluginResourcePublisher.php');
phase3Expect(str_contains($publisherSource, "['resources']"), '插件资源发布必须以 manifest resources 为唯一来源');
phase3Expect(str_contains($publisherSource, "['source']") && str_contains($publisherSource, "['target']"), '资源发布必须读取 manifest source/target');
$fixtureManifest = json_decode((string) file_get_contents($root . '/tests/fixtures/plugins/example/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
phase3Expect(($fixtureManifest['resources']['admin']['source'] ?? '') === 'resources/admin', '标准契约必须声明 Admin 资源源目录');
phase3Expect(($fixtureManifest['resources']['admin']['target'] ?? '') === 'plugin-assets/example', '标准契约必须声明受控 plugin-assets 目标');

echo "plugin phase3 tests passed\n";
