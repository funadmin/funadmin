<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\Manifest;

function cutoverExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cutoverPhpFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if ($item->isFile() && $item->getExtension() === 'php') {
            $files[] = $item->getPathname();
        }
    }
    return $files;
}

$root = dirname(__DIR__);
$schema = json_decode((string) file_get_contents($root . '/extend/fun/plugins/schema/plugin.schema.json'), true, 512, JSON_THROW_ON_ERROR);
cutoverExpect(($schema['required'] ?? []) === ['schema_version', 'code', 'name', 'version', 'requires', 'entry'], 'manifest 根必填字段必须硬切为 code/name');
cutoverExpect(($schema['properties']['code']['pattern'] ?? '') === '^[a-z][a-z0-9]*$', 'manifest code 正则不正确');
cutoverExpect(isset($schema['properties']['name']) && !isset($schema['properties']['title']), 'manifest 显示字段必须为 name 且不得保留根 title');

foreach (['plugins/example/plugin.json', 'tests/fixtures/plugins/example/plugin.json'] as $manifestPath) {
    $manifest = json_decode((string) file_get_contents($root . '/' . $manifestPath), true, 512, JSON_THROW_ON_ERROR);
    cutoverExpect(($manifest['code'] ?? '') === 'example', "{$manifestPath} 必须声明 code=example");
    cutoverExpect(is_string($manifest['name'] ?? null) && $manifest['name'] !== 'example', "{$manifestPath} name 必须是显示名称");
    cutoverExpect(!array_key_exists('title', $manifest), "{$manifestPath} 不得保留根 title");
}

$manifest = Manifest::fromDirectory($root . '/tests/fixtures/plugins/example');
cutoverExpect($manifest->code() === 'example', 'Manifest::code() 必须返回插件标识');
cutoverExpect($manifest->name() === '测试示例插件', 'Manifest::name() 必须返回显示名称');
cutoverExpect(!method_exists($manifest, 'title'), 'Manifest::title() 必须删除');

$productionRoots = [
    $root . '/app/common/plugin',
    $root . '/app/console/controller/system',
    $root . '/app/console/middleware/CheckPluginPermission.php',
    $root . '/app/console/service',
    $root . '/extend/fun/plugins',
];
$forbidden = [
    "/Plugin::where\\(['\"]name['\"]/" => "Plugin::where('name')",
    '/\bplugin_name\b/' => 'plugin_name',
    '/\bpluginName\b/' => 'pluginName',
    '/->title\(\)/' => 'Manifest::title()',
];
foreach ($productionRoots as $productionRoot) {
    $files = is_dir($productionRoot) ? cutoverPhpFiles($productionRoot) : [$productionRoot];
    foreach ($files as $file) {
        if (str_ends_with($file, '/LegacyCloudMarketplaceAdapter.php')) {
            continue;
        }
        $source = (string) file_get_contents($file);
        foreach ($forbidden as $pattern => $label) {
            cutoverExpect(preg_match($pattern, $source) !== 1, str_replace($root . '/', '', $file) . " 仍引用 {$label}");
        }
    }
}

$dtoFiles = glob($root . '/app/common/plugin/marketplace/dto/*.php') ?: [];
foreach ($dtoFiles as $file) {
    $source = (string) file_get_contents($file);
    cutoverExpect(!preg_match('/public readonly string \$(?:title|pluginName)/', $source), basename($file) . ' 业务 DTO 仍暴露旧标识字段');
}

$migration = (string) file_get_contents($root . '/database/migrations/061_plugin_code_name.sql');
cutoverExpect(str_contains($migration, 'information_schema'), '061 必须使用 information_schema 双态守卫');
cutoverExpect(str_contains($migration, 'plugin_code'), '061 必须迁移关联表 plugin_code');
cutoverExpect(str_contains($migration, 'source_name'), '061 必须明确保留通用 source_name 语义');

$allMigrationFiles = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
cutoverExpect(in_array('062_plugin_code_unique_index.sql', $allMigrationFiles, true), '061 后必须存在 062 索引补偿 migration');
$indexCompensation = (string) file_get_contents($root . '/database/migrations/062_plugin_code_unique_index.sql');
cutoverExpect(str_contains($indexCompensation, "INDEX_NAME <> 'uk_plugin_code'"), '062 必须清理 code 上其他 legacy 唯一索引');
cutoverExpect(str_contains($indexCompensation, 'NON_UNIQUE = 0'), '062 只能清理唯一索引，不得删除合理非唯一索引');

echo "plugin code/name cutover tests: PASS\n";
