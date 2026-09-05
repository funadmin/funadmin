<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\Manifest;
use fun\plugins\RuntimeLoader;

function phase4Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$fixture = $root . '/tests/fixtures/plugins/example';
$generator = (string) file_get_contents($root . '/extend/fun/curd/Plugin.php');
$templateDirectory = $root . '/extend/fun/curd/tpl/plugin';
$migrations = glob($root . '/database/migrations/*plugin*legacy*.sql') ?: [];

phase4Expect(!is_file($root . '/app/backend/controller/Plugin.php'), '旧 Plugin Controller 必须删除');
phase4Expect(!is_dir($root . '/app/backend/view/plugin'), '旧 plugin 视图目录必须删除');
phase4Expect(!is_file($root . '/public/static/backend/js/plugin.js'), '旧 plugin.js 必须删除');
phase4Expect(!is_file($root . '/app/backend/lang/zh-cn/plugin.php'), '旧 plugin 语言包必须删除');
phase4Expect(count($migrations) === 1, '必须新增且仅新增一个停用旧插件权限的前向 migration');
$migration = (string) file_get_contents($migrations[0]);
phase4Expect(str_contains($migration, 'backend/plugin') && str_contains($migration, 'status` = 0'), 'migration 必须停用旧 backend/plugin 权限');
phase4Expect(str_contains($migration, 'system:plugin') && str_contains($migration, 'status` = 1'), 'migration 必须保留 system:plugin 权限');

foreach (['plugin.json', 'Plugin.php', 'config/services.php', 'config/events.php', 'routes/plugin.php', 'migrations/001_initial.sql', 'resources/public/.gitkeep', 'resources/admin/entry.js'] as $generatedPath) {
    phase4Expect(str_contains($generator, "'{$generatedPath}'"), '生成器缺少目标：' . $generatedPath);
    phase4Expect(is_file($fixture . '/' . $generatedPath), 'fixture 缺少文件：' . $generatedPath);
}
foreach (['ini.tpl', 'config.tpl', 'menu.tpl', 'controller.tpl', 'view.tpl', 'js.tpl'] as $legacyTemplate) {
    phase4Expect(!is_file($templateDirectory . '/' . $legacyTemplate), '必须删除旧模板：' . $legacyTemplate);
}
foreach (['plugin.ini', 'Plugin.ini', 'service.ini', 'install.sql', 'update.sql', 'uninstall.sql'] as $legacyArtifact) {
    phase4Expect(!str_contains($generator, $legacyArtifact), '生成器不得兼容或生成：' . $legacyArtifact);
}
phase4Expect(str_contains($generator, "addOption('name'"), 'CLI 必须使用 --name 参数');
phase4Expect(str_contains($generator, "addOption('version'"), 'CLI 必须使用 --version 参数');
phase4Expect(!str_contains($generator, "addOption('app'"), 'CLI 不得继续暴露旧 --app 参数');
phase4Expect(!str_contains($generator, "addOption('ver'"), 'CLI 不得继续暴露旧 --ver 参数');

$manifest = Manifest::fromDirectory($fixture);
phase4Expect($manifest->name() === 'example', 'fixture manifest 名称无效');
phase4Expect(array_keys((new RuntimeLoader())->boundaries($manifest)) === ['services', 'events', 'routes'], 'fixture 必须声明完整显式加载边界');
$fixtureManifest = $manifest->toArray();
phase4Expect(($fixtureManifest['admin_web']['entry'] ?? null) === 'entry.js', 'fixture Admin ESM 入口必须相对于发布根');
phase4Expect(is_file($fixture . '/resources/admin/' . $fixtureManifest['admin_web']['entry']), '标准 fixture 的发布源入口必须存在');
$template = (string) file_get_contents($templateDirectory . '/json.tpl');
phase4Expect(str_contains($template, '"entry": "entry.js"'), '生成器 manifest 入口必须相对于发布根');
phase4Expect(is_dir($fixture . '/resources/public'), 'fixture 必须包含公开资源目录');
phase4Expect(is_dir($fixture . '/migrations'), 'fixture 必须包含 migration 目录');

$dynamicRouter = (string) file_get_contents($root . '/admin-web/src/router/dynamic.ts');
phase4Expect(!str_contains($dynamicRouter, "replace(/^plugins\\//, 'modules/')"), '动态路由不得保留 plugins 到 modules 的静态映射');
$pluginModules = (string) file_get_contents($root . '/admin-web/src/router/pluginModules.ts');
phase4Expect(str_contains($pluginModules, 'syncPluginModules'), '插件页面必须由 pluginModules 动态注册器加载');

echo "plugin phase4 tests: PASS\n";
