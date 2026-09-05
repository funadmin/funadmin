<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\Manifest;
use fun\plugins\PluginResourcePublisher;

function resourceExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function resourceReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        resourceExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

$root = sys_get_temp_dir() . '/funadmin-resource-publisher-' . bin2hex(random_bytes(5));
$public = $root . '/public';
$plugins = $root . '/plugins';
foreach (['demo', 'other'] as $name) {
    mkdir($plugins . '/' . $name . '/resources/public', 0755, true);
    mkdir($plugins . '/' . $name . '/resources/admin', 0755, true);
    file_put_contents($plugins . '/' . $name . '/Plugin.php', '<?php namespace plugins\\' . $name . '; final class Plugin {}');
    file_put_contents($plugins . '/' . $name . '/resources/admin/entry.js', $name . '-entry-v1');
    file_put_contents($plugins . '/' . $name . '/resources/public/app.css', $name . '-css-v1');
    file_put_contents($plugins . '/' . $name . '/plugin.json', json_encode([
        'schema_version' => 1,
        'name' => $name,
        'title' => ucfirst($name),
        'version' => '1.0.0',
        'requires' => ['plugins' => []],
        'entry' => ['class' => 'plugins\\' . $name . '\\Plugin'],
        'admin_web' => ['entry' => 'entry.js', 'routes' => []],
        'resources' => [
            'public' => ['source' => 'resources/public', 'target' => 'plugin-assets/' . $name . '/public'],
            'admin' => ['source' => 'resources/admin', 'target' => 'plugin-assets/' . $name],
        ],
    ], JSON_UNESCAPED_SLASHES));
}
mkdir($public, 0755, true);
$registry = [];
$publisher = new PluginResourcePublisher(
    $public,
    static function () use (&$registry): array { return $registry; },
    static function (string $plugin, array $records) use (&$registry): void {
        $registry = array_values(array_filter($registry, static fn (array $record): bool => $record['plugin'] !== $plugin));
        $registry = array_merge($registry, $records);
    }
);

$demo = Manifest::fromDirectory($plugins . '/demo');
$publisher->publish($demo);
$entry = $public . '/plugin-assets/demo/entry.js';
resourceExpect(file_get_contents($entry) === 'demo-entry-v1', '必须发布真实文件');
resourceExpect(count($registry) === 2 && $registry[0]['sha256'] !== '', '必须逐文件记录 SHA-256 registry');

file_put_contents($plugins . '/demo/resources/admin/new.js', 'new');
unlink($plugins . '/demo/resources/public/app.css');
$snapshot = $publisher->publish(Manifest::fromDirectory($plugins . '/demo'));
resourceExpect(!is_file($public . '/plugin-assets/demo/public/app.css'), '更新必须删除仅属于本插件的旧孤儿文件');
resourceExpect(is_file($public . '/plugin-assets/demo/new.js'), '更新必须发布新增资源');
$publisher->rollback($snapshot);
resourceExpect(file_get_contents($entry) === 'demo-entry-v1', '回滚必须恢复旧文件');
resourceExpect(is_file($public . '/plugin-assets/demo/public/app.css'), '回滚必须恢复旧孤儿文件');
resourceExpect(!is_file($public . '/plugin-assets/demo/new.js'), '回滚必须删除新文件');

mkdir($public . '/plugin-assets/other', 0755, true);
file_put_contents($public . '/plugin-assets/other/entry.js', 'core-owned');
resourceReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/other')), '未登记');
unlink($public . '/plugin-assets/other/entry.js');
file_put_contents($public . '/plugin-assets/other/entry.js', 'third-owned');
mkdir($public . '/plugin-assets/other/public', 0755, true);
file_put_contents($public . '/plugin-assets/other/public/app.css', 'third-owned');
$registry[] = ['plugin' => 'third', 'version' => '1.0.0', 'source_path' => 'x', 'target_path' => 'plugin-assets/other/entry.js', 'sha256' => hash('sha256', 'x')];
$registry[] = ['plugin' => 'third', 'version' => '1.0.0', 'source_path' => 'y', 'target_path' => 'plugin-assets/other/public/app.css', 'sha256' => hash('sha256', 'y')];
resourceReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/other')), '其他插件');

$outside = $root . '/outside';
mkdir($outside, 0755, true);
mkdir($public . '/plugin-assets/symlinked', 0755, true);
resourceExpect(symlink($outside, $public . '/plugin-assets/symlinked/public'), '测试环境必须能创建目标目录符号链接');
$symlinkManifestData = json_decode((string) file_get_contents($plugins . '/demo/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$symlinkManifestData['resources']['public']['target'] = 'plugin-assets/symlinked/public';
file_put_contents($plugins . '/demo/plugin.json', json_encode($symlinkManifestData, JSON_UNESCAPED_SLASHES));
resourceReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo')), '符号链接');
resourceExpect(!is_file($outside . '/app.css'), '目标中间目录符号链接不得逃逸 public 根目录');
file_put_contents($plugins . '/demo/plugin.json', json_encode(array_replace_recursive($symlinkManifestData, [
    'resources' => ['public' => ['target' => 'plugin-assets/demo/public']],
]), JSON_UNESCAPED_SLASHES));
unlink($public . '/plugin-assets/symlinked/public');
rmdir($public . '/plugin-assets/symlinked');
rmdir($outside);

$beforeFailedPublishRegistry = $registry;
file_put_contents($plugins . '/demo/resources/admin/entry.js', 'demo-entry-v2');
file_put_contents($plugins . '/demo/resources/admin/fail.js', 'must-rollback');
$writes = 0;
$failingPublisher = new PluginResourcePublisher(
    $public,
    static function () use (&$registry): array { return $registry; },
    static function (string $plugin, array $records) use (&$registry, &$writes): void {
        $writes++;
        if ($writes === 1) {
            throw new RuntimeException('registry write failed');
        }
        $registry = array_values(array_filter($registry, static fn (array $record): bool => $record['plugin'] !== $plugin));
        $registry = array_merge($registry, $records);
    }
);
resourceReject(static fn () => $failingPublisher->publish(Manifest::fromDirectory($plugins . '/demo')), 'registry write failed');
resourceExpect(file_get_contents($entry) === 'demo-entry-v1', 'registry 写入失败必须自动恢复已覆盖文件');
resourceExpect(!is_file($public . '/plugin-assets/demo/fail.js'), 'registry 写入失败必须自动删除新增文件');
$sortRegistry = static function (array $records): array {
    usort($records, static fn (array $left, array $right): int => strcmp((string) $left['target_path'], (string) $right['target_path']));
    return $records;
};
resourceExpect($sortRegistry($registry) === $sortRegistry($beforeFailedPublishRegistry), 'registry 写入失败必须恢复旧 registry');

$publisher->remove('demo');
resourceExpect(!is_file($entry), '卸载只删除该插件 registry 所属文件');
resourceExpect(is_dir($plugins . '/demo/resources'), '资源发布与删除绝不能搬回或删除源码资源');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

echo "plugin resource publisher tests: PASS\n";
