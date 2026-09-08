<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\service\DevPluginService;
use fun\plugins\PluginScaffolder;

function devPluginExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function devPluginReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        devPluginExpect(str_contains($exception->getMessage(), $contains), '异常信息不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期抛出异常：' . $contains);
}

function devPluginRemove(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

$root = sys_get_temp_dir() . '/funadmin-dev-plugin-api-' . bin2hex(random_bytes(5));
mkdir($root . '/plugins', 0755, true);
$audits = [];
$service = new DevPluginService(
    $root,
    static function (string $archive): void {
        devPluginExpect(is_file($archive), 'package 必须复用归档重验回调');
    },
    static function (array $audit) use (&$audits): int {
        $audits[] = $audit;
        return count($audits);
    },
    static function (string $code, string $output, callable $verify): array {
        devPluginExpect($code === 'shop', 'package 必须传递受控插件 code');
        devPluginExpect(str_contains(str_replace('\\', '/', $output), '/runtime/download/plugins/shop-1.0.0.zip'), 'package 必须传递受控输出路径');
        if (!is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }
        file_put_contents($output, 'test archive');
        $verify($output);
        return ['sha256' => hash_file('sha256', $output), 'tree_hash' => hash('sha256', 'tree'), 'files' => ['plugin.json']];
    }
);

try {
    $preview = $service->previewCreate(['name' => 'shop', 'title' => '商城', 'application' => true, 'console' => true, 'adminWeb' => true]);
    devPluginExpect(($preview['auditId'] ?? 0) === 1, 'preview 必须返回 auditId');
    devPluginExpect(($preview['conflicts'] ?? null) === [], '新插件 preview 不应有冲突');
    devPluginExpect(in_array('plugins/shop/plugin.json', array_column($preview['plan']['files'] ?? [], 'path'), true), 'preview 必须返回目标文件计划');
    devPluginExpect(!isset($preview['confirmToken']), '创建插件不得返回无用途 token');

    $created = $service->create(['name' => 'shop', 'title' => '商城', 'application' => true, 'console' => true, 'adminWeb' => true]);
    devPluginExpect(($created['auditId'] ?? 0) === 2 && ($created['plan']['status'] ?? '') === 'created', 'create 必须原子生成并返回审计');
    devPluginExpect(is_file($root . '/plugins/shop/plugin.json'), 'create 必须调用 PluginScaffolder');

    $conflict = $service->previewCreate(['name' => 'shop']);
    devPluginExpect(($conflict['conflicts'] ?? []) === ['plugins/shop'], 'preview 必须报告已有目录冲突');
    devPluginReject(static fn () => $service->create(['name' => 'shop']), '已存在');

    $validated = $service->validate('shop');
    devPluginExpect(($validated['valid'] ?? false) === true && ($validated['manifest']['schema_version'] ?? null) === 2, 'validate 必须复用 Manifest v2');
    devPluginExpect(($validated['plan']['operation'] ?? '') === 'validate', 'validate 必须返回统一 plan');

    $options = $service->options();
    devPluginExpect(($options[0]['code'] ?? '') === 'shop', 'options 必须列出有效开发插件');
    devPluginExpect(($options[0]['scopes'] ?? []) === ['application', 'console', 'both'], 'options 必须按插件目录返回可用 scope');

    (new PluginScaffolder($root . '/plugins'))->scaffold('consoleonly', '纯管理插件', false, true, false);
    $consoleOnly = array_values(array_filter($service->options(), static fn (array $item): bool => ($item['code'] ?? '') === 'consoleonly'));
    devPluginExpect(($consoleOnly[0]['scopes'] ?? []) === ['console'], '纯 Console 插件必须提供 console scope，不能依赖 adminWeb 声明');

    mkdir($root . '/plugins/broken', 0755, true);
    file_put_contents($root . '/plugins/broken/plugin.json', '{}');
    devPluginExpect(count($service->options()) === 2, '损坏插件不得进入可选列表');

    $packaged = $service->package('shop');
    devPluginExpect(($packaged['auditId'] ?? 0) > 0, 'package 必须返回 auditId');
    devPluginExpect(str_starts_with((string) ($packaged['downloadPath'] ?? ''), 'runtime/download/plugins/'), 'package 只返回受控下载相对路径');
    devPluginExpect(($packaged['downloadUrl'] ?? '') === '/development/plugin/package/shop/download', 'package 必须返回后台安全下载 URL');
    devPluginExpect(is_file($root . '/' . $packaged['downloadPath']), 'package 必须写入受控 runtime/download');
    devPluginExpect($service->packageDownload('shop')['path'] === realpath($root . '/' . $packaged['downloadPath']), '安全下载必须解析 code+version 派生文件');
    devPluginReject(static fn () => $service->package('shop', '../../escape.zip'), '不允许指定');
    unlink($root . '/' . $packaged['downloadPath']);
    symlink(__FILE__, $root . '/' . $packaged['downloadPath']);
    devPluginReject(static fn () => $service->packageDownload('shop'), '符号链接');
    unlink($root . '/' . $packaged['downloadPath']);

    $serializedAudits = json_encode($audits, JSON_THROW_ON_ERROR);
    devPluginExpect(!str_contains($serializedAudits, 'confirmToken'), '普通审计不得包含确认 token');

    $controller = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/development/DevPlugin.php');
    foreach (['CheckAdminApiRole::class', 'CheckAdminApiCsrf::class', 'SystemLog::class', "#[Group('development/plugin')]", "#[Post('create/preview')]", "#[Post('create')]", "#[Post('validate')]", "#[Post('package')]", "#[Get('package/{code}/download')]", "#[Get('options')]"] as $marker) {
        devPluginExpect(str_contains($controller, $marker), 'DevPlugin 控制器契约缺少：' . $marker);
    }

    $migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/067_plugin_development_permissions.sql');
    foreach (['previewcreate', 'create', 'validate', 'package', 'packagedownload', 'options'] as $action) {
        devPluginExpect(str_contains($migration, "'console/development.devplugin','{$action}'"), '插件开发权限资源必须匹配控制器：' . $action);
    }

    $crudController = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/development/DevCrud.php');
    $crudService = (string) file_get_contents(dirname(__DIR__) . '/app/console/service/DevCrudService.php');
    devPluginExpect(str_contains($crudService, 'PluginCrudDefinitionFactory'), 'DevCrud infer 必须复用 PluginCrudDefinitionFactory');
    devPluginExpect(str_contains($crudController, "post('targetType'"), 'DevCrud infer 必须接受 targetType');
} finally {
    devPluginRemove($root);
}

echo "dev plugin api tests: PASS\n";
