<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\Manifest;

function schemaExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function schemaReject(string $directory, array $manifest, string $message): void
{
    file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    try {
        Manifest::fromDirectory($directory);
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$schemaFile = $root . '/extend/fun/plugins/schema/plugin.schema.json';
schemaExpect(is_file($schemaFile), '必须存在权威 plugin.schema.json');
$schema = json_decode((string) file_get_contents($schemaFile), true, 512, JSON_THROW_ON_ERROR);
schemaExpect(($schema['additionalProperties'] ?? true) === false, '根对象必须拒绝未知字段');

$temp = sys_get_temp_dir() . '/funadmin-manifest-schema-' . bin2hex(random_bytes(5));
$plugin = $temp . '/demo';
mkdir($plugin . '/config', 0755, true);
mkdir($plugin . '/routes', 0755, true);
mkdir($plugin . '/resources/public', 0755, true);
mkdir($plugin . '/resources/admin', 0755, true);
mkdir($plugin . '/migrations', 0755, true);
file_put_contents($plugin . '/Plugin.php', '<?php namespace plugins\\demo; final class Plugin {}');
file_put_contents($plugin . '/config/services.php', '<?php return [];');
file_put_contents($plugin . '/config/events.php', '<?php return [];');
file_put_contents($plugin . '/routes/plugin.php', '<?php return static function ($route): void {};');
file_put_contents($plugin . '/resources/public/app.css', 'body{}');
file_put_contents($plugin . '/resources/admin/entry.js', 'export const register = () => ({});');
file_put_contents($plugin . '/migrations/001_initial.sql', 'SELECT 1;');

$valid = [
    'schema_version' => 1,
    'name' => 'demo',
    'title' => '演示插件',
    'version' => '1.0.0',
    'requires' => ['php' => '>=8.1', 'funadmin' => '>=1.0.0', 'plugins' => []],
    'entry' => ['class' => 'plugins\\demo\\Plugin', 'file' => 'Plugin.php'],
    'load' => [
        'services' => 'config/services.php',
        'events' => 'config/events.php',
        'routes' => 'routes/plugin.php',
    ],
    'permissions' => [['code' => 'demo:view', 'name' => '查看演示']],
    'menus' => [['name' => '演示', 'path' => '/plugin/demo/index', 'permission' => 'demo:view']],
    'admin_web' => ['entry' => 'entry.js', 'routes' => [['path' => '/plugin/demo/index', 'name' => 'Plugin_demo_Index', 'component' => 'Index']]],
    'resources' => ['public' => ['source' => 'resources/public', 'target' => 'plugin-assets/demo/public'], 'admin' => ['source' => 'resources/admin', 'target' => 'plugin-assets/demo']],
    'migrations' => ['path' => 'migrations'],
    'storage' => ['path' => 'storage/demo'],
    'purge' => ['supported' => true],
];
file_put_contents($plugin . '/plugin.json', json_encode($valid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
schemaExpect(Manifest::fromDirectory($plugin)->name() === 'demo', '完整合法 manifest 必须通过同一 schema validator');

$unknown = $valid;
$unknown['surprise'] = true;
schemaReject($plugin, $unknown, '未知根字段必须拒绝');
$missingRequires = $valid;
unset($missingRequires['requires']);
schemaReject($plugin, $missingRequires, '缺少 requires 必须拒绝');
$wrongType = $valid;
$wrongType['permissions'] = 'demo:view';
schemaReject($plugin, $wrongType, 'permissions 错误类型必须拒绝');
$traversal = $valid;
$traversal['entry']['file'] = '../Plugin.php';
schemaReject($plugin, $traversal, '入口文件路径越界必须拒绝');
$traversal = $valid;
$traversal['load']['routes'] = '/etc/passwd';
schemaReject($plugin, $traversal, '加载路径绝对地址必须拒绝');
$traversal = $valid;
$traversal['resources']['public']['target'] = '../core';
schemaReject($plugin, $traversal, '资源目标路径越界必须拒绝');
$wrongNamespace = $valid;
$wrongNamespace['entry']['class'] = 'plugins\\other\\Plugin';
schemaReject($plugin, $wrongNamespace, 'entry namespace 与 name 不一致必须拒绝');
file_put_contents($plugin . '/Plugin.php', '<?php namespace plugins\\other; final class Plugin {}');
schemaReject($plugin, $valid, 'Plugin.php namespace 与 manifest 不一致必须拒绝');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($temp);

echo "plugin manifest schema tests: PASS\n";
