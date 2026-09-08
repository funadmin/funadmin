<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\service\PluginModificationDetector;
use app\console\service\PluginResourceRepository;

function detectorExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class DetectorRepository implements PluginResourceRepository
{
    public function __construct(public array $records)
    {
    }

    public function all(): array
    {
        return $this->records;
    }

    public function replaceForPlugin(string $pluginCode, array $records): void
    {
        throw new RuntimeException('修改检测器不得写 registry');
    }
}

$root = sys_get_temp_dir() . '/funadmin-modification-detector-' . bin2hex(random_bytes(4));
$app = $root . '/app';
$public = $root . '/public';
$adminWeb = $root . '/admin-web';
mkdir($app . '/demo/service', 0755, true);
mkdir($public . '/plugin-assets/demo', 0755, true);
mkdir($adminWeb . '/src/modules/demo', 0755, true);
file_put_contents($app . '/demo/service/Domain.php', 'domain-v1');
file_put_contents($public . '/plugin-assets/demo/logo.svg', 'logo-v1');
file_put_contents($adminWeb . '/src/modules/demo/Index.vue', 'index-v1');

$treeHash = static function (string $directory): string {
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($directory) + 1);
        $hashes[] = $item->isDir()
            ? 'd' . "\0" . $relative
            : 'f' . "\0" . $relative . "\0" . hash_file('sha256', $item->getPathname());
    }
    sort($hashes, SORT_STRING);
    return hash('sha256', implode("\n", $hashes));
};

$records = [
    [
        'plugin_code' => 'demo', 'resource_type' => 'native_app', 'publication_unit' => 'application:demo',
        'target_path' => 'application:demo/service/Domain.php',
        'sha256' => hash_file('sha256', $app . '/demo/service/Domain.php'),
        'tree_hash' => $treeHash($app . '/demo'),
    ],
    [
        'plugin_code' => 'demo', 'resource_type' => 'file', 'publication_unit' => null,
        'target_path' => 'public:plugin-assets/demo/logo.svg',
        'sha256' => hash_file('sha256', $public . '/plugin-assets/demo/logo.svg'), 'tree_hash' => null,
    ],
    [
        'plugin_code' => 'demo', 'resource_type' => 'file', 'publication_unit' => null,
        'target_path' => 'admin-web:src/modules/demo/Index.vue',
        'sha256' => hash_file('sha256', $adminWeb . '/src/modules/demo/Index.vue'), 'tree_hash' => null,
    ],
];
$repository = new DetectorRepository($records);
$detector = new PluginModificationDetector($app, $public, $adminWeb, $repository);

detectorExpect($detector->isModified('demo') === false, '登记目标未变化时必须 clean');
file_put_contents($public . '/plugin-assets/demo/logo.svg', 'logo-v2');
detectorExpect($detector->isModified('demo') === true, '登记 file 内容变化必须 modified');
file_put_contents($public . '/plugin-assets/demo/logo.svg', 'logo-v1');
unlink($adminWeb . '/src/modules/demo/Index.vue');
detectorExpect($detector->isModified('demo') === true, '登记目标缺失必须 modified');
file_put_contents($adminWeb . '/src/modules/demo/Index.vue', 'index-v1');
file_put_contents($app . '/demo/service/Local.php', 'untracked');
detectorExpect($detector->isModified('demo') === true, 'native publication unit 未登记文件必须 modified');
unlink($app . '/demo/service/Local.php');
file_put_contents($public . '/plugin-assets/demo/local.txt', 'untracked');
detectorExpect($detector->isModified('demo') === true, 'file 发布根目录未登记文件必须 modified');
detectorExpect($repository->records === $records, '修改检测必须只读且不得变更 registry');

echo "plugin modification detector tests: PASS\n";
