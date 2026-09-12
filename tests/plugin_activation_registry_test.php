<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\sdk\ActivationGate;
use app\common\plugin\sdk\ActivationUnavailableException;
use app\common\plugin\sdk\PluginActivationCompiler;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginNotActiveException;

function activationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function activationThrows(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        activationExpect($exception instanceof $class, $message . '，实际异常：' . $exception::class);
        return;
    }
    throw new RuntimeException($message . '，实际未抛出异常');
}

function activationRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

$root = sys_get_temp_dir() . '/funadmin-plugin-activation-' . bin2hex(random_bytes(4));
$runtime = $root . '/runtime/plugins/activation';
$plugins = $root . '/plugins';
mkdir($plugins . '/catalog/app/catalog', 0755, true);
mkdir($plugins . '/catalog/app/console', 0755, true);
mkdir($plugins . '/payment/app/payment', 0755, true);

$records = [
    'catalog' => [
        'code' => 'catalog', 'lifecycle_state' => 'enabled', 'status' => 1,
        'needs_reinstall' => 0, 'operation_token' => null, 'version' => '2.1.0', 'package_hash' => 'catalog-hash',
    ],
    'payment' => [
        'code' => 'payment', 'lifecycle_state' => 'enabled', 'status' => 1,
        'needs_reinstall' => 0, 'operation_token' => null, 'version' => '1.0.0', 'package_hash' => 'payment-hash',
    ],
    'legacy' => [
        'code' => 'legacy', 'lifecycle_state' => 'disabled', 'status' => 0,
        'needs_reinstall' => 1, 'operation_token' => null, 'version' => '0.8.0', 'package_hash' => '',
    ],
    'removed' => [
        'code' => 'removed', 'lifecycle_state' => 'enabled', 'status' => 1,
        'needs_reinstall' => 0, 'operation_token' => null, 'version' => '1.0.0', 'package_hash' => 'removed-hash',
        'deleted_at' => '2026-09-07 12:00:00',
    ],
];
$manifests = [
    'catalog' => ['schema_version' => 2, 'code' => 'catalog', 'version' => '2.1.0', 'requires' => ['plugins' => []]],
    'payment' => ['schema_version' => 2, 'code' => 'payment', 'version' => '1.0.0', 'requires' => ['plugins' => ['catalog' => '^2.0']]],
];

$compiler = new PluginActivationCompiler($runtime, $plugins);
$first = $compiler->compile($records, $manifests);
activationExpect(($first['schema_version'] ?? null) === 1, '激活清单必须包含 schema_version');
activationExpect(is_string($first['generation'] ?? null) && $first['generation'] !== '', '激活清单必须包含 generation');
activationExpect(strlen((string) ($first['activation_hash'] ?? '')) === 64, '激活清单必须包含 activation SHA-256 hash');
activationExpect(strlen((string) ($first['ownership_hash'] ?? '')) === 64, '激活清单必须包含 ownership SHA-256 hash');
activationExpect(array_keys($first['plugins']) === ['catalog', 'legacy', 'payment', 'removed'], '清单必须稳定保留全部 installed/discovered 插件');
activationExpect($first['plugins']['catalog']['applications'] === ['app' => true, 'console' => true], '编译器必须记录独立 app 与 console ownership');
activationExpect($first['plugins']['removed']['enabled'] === false, '软删除记录即使 status=1 也不得编译为启用');
activationExpect(in_array($first['plugins']['removed']['state'], ['discovered', 'deleted'], true), '软删除记录必须编译为不可运行状态');
activationExpect(!is_file($runtime . '/ownership.php'), '不得在 root 发布跨代 ownership 索引');
activationExpect(!is_file($runtime . '/activation.php'), '不得在 root 发布 activation PHP 数据文件');
activationExpect($first['plugins']['payment']['dependencies'] === ['catalog'], '编译器必须固化依赖 code');
activationExpect(is_file($runtime . '/active'), '必须原子发布 active 指针');
$firstFile = $runtime . '/generations/' . $first['generation'] . '/snapshot.json';
activationExpect(is_file($firstFile), '必须在 generation 目录写入单一 JSON 快照');
$firstPayload = json_decode((string) file_get_contents($firstFile), true, 512, JSON_THROW_ON_ERROR);
activationExpect(($firstPayload['generation'] ?? null) === $first['generation'], 'JSON generation 必须匹配 active 指针');
activationExpect(strlen((string) ($firstPayload['activation_hash'] ?? '')) === 64, 'JSON 快照必须包含 activation 完整性校验');
activationExpect(strlen((string) ($firstPayload['ownership_hash'] ?? '')) === 64, 'JSON 快照必须包含 ownership 完整性校验');
activationExpect(array_keys($firstPayload['ownership']) === ['catalog', 'legacy', 'payment', 'removed'], 'ownership 必须与 activation 同 generation 记录全部插件');
$stableHashA = PluginActivationCompiler::integrityHash(['z' => 1, 'nested' => ['b' => 2, 'a' => 1]]);
$stableHashB = PluginActivationCompiler::integrityHash(['nested' => ['a' => 1, 'b' => 2], 'z' => 1]);
activationExpect($stableHashA === $stableHashB, 'hash 工具必须对关联键顺序稳定且可复现');

$reader = new PluginActivationReader($runtime);
$snapshot = $reader->read();
activationExpect($snapshot->isTrusted(), '新编译清单必须可信');
activationExpect($snapshot->plugins()['legacy']['state'] === 'disabled', 'Reader 必须返回所有已知插件状态');
$gate = new ActivationGate($snapshot);
$gate->assertEnabled('catalog', 'app');
$gate->assertEnabled('catalog', 'console');
$gate->assertEnabled('payment', 'app');
activationThrows(fn () => $gate->assertEnabled('legacy', 'app'), PluginNotActiveException::class, 'needs_reinstall/disabled 插件必须 fail closed');
activationThrows(fn () => $gate->assertEnabled('payment', 'console'), PluginNotActiveException::class, '未声明 console ownership 必须 fail closed');
activationThrows(fn () => $gate->assertEnabled('removed', 'app'), PluginNotActiveException::class, '软删除 enabled 记录必须被 Gate 拒绝');

$busyRecords = $records;
$busyRecords['catalog']['operation_token'] = 'busy-token';
$compiler->compile($busyRecords, $manifests);
activationThrows(fn () => (new ActivationGate($reader->read()))->assertEnabled('catalog', 'app'), PluginNotActiveException::class, 'operation_token 非空必须 fail closed');

$disabledRecords = $records;
$disabledRecords['catalog']['lifecycle_state'] = 'disabled';
$disabledRecords['catalog']['status'] = 0;
$compiler->compile($disabledRecords, $manifests);
activationThrows(fn () => (new ActivationGate($reader->read()))->assertEnabled('payment', 'app'), PluginNotActiveException::class, '依赖关闭时依赖方必须 fail closed');

$transitiveRecords = $records + [
    'foundation' => ['code' => 'foundation', 'lifecycle_state' => 'disabled', 'status' => 0, 'needs_reinstall' => 0, 'operation_token' => null, 'version' => '1.0.0', 'package_hash' => 'foundation-hash'],
];
$transitiveManifests = $manifests + [
    'foundation' => ['schema_version' => 2, 'code' => 'foundation', 'version' => '1.0.0', 'requires' => ['plugins' => []]],
];
$transitiveManifests['catalog']['requires']['plugins'] = ['foundation' => '^1.0'];
$compiler->compile($transitiveRecords, $transitiveManifests);
activationThrows(fn () => (new ActivationGate($reader->read()))->assertEnabled('payment', 'app'), PluginNotActiveException::class, '传递依赖关闭时必须 fail closed');

$old = $compiler->compile($records, $manifests);
$newRecords = $records;
$newRecords['catalog']['version'] = '2.2.0';
rmdir($plugins . '/catalog/app/catalog');
$new = $compiler->compile($newRecords, $manifests);
$expectedGenerations = [
    $old['generation'] => ['version' => '2.1.0', 'owns_app' => true],
    $new['generation'] => ['version' => '2.2.0', 'owns_app' => false],
];
foreach ($expectedGenerations as $generation => $expected) {
    file_put_contents($runtime . '/active', $generation . "\n");
    $bundle = $reader->readBundle();
    activationExpect($bundle['activation']->isTrusted() && $bundle['ownership']->isTrusted(), 'generation 切换只能读取旧或新完整 bundle');
    activationExpect($bundle['activation']->plugins()['catalog']['version'] === $expected['version'], 'active 切换后必须刷新为指定 generation');
    activationExpect($bundle['ownership']->owns('catalog', 'app') === $expected['owns_app'], 'ownership 必须与 activation 来自同一 generation');
}
mkdir($plugins . '/catalog/app/catalog');
$valid = $compiler->compile($records, $manifests);
$file = $runtime . '/generations/' . $valid['generation'] . '/snapshot.json';
$payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$payload['plugins']['catalog']['version'] = 'tampered';
file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
$invalidBundle = $reader->readBundle();
activationExpect(!$invalidBundle['activation']->isTrusted(), '仅篡改 plugins 时 activation 必须不可信');
activationExpect($invalidBundle['ownership']->isTrusted(), '仅篡改 plugins 时 ownership 必须独立保持可信');
activationExpect($invalidBundle['ownership']->owns('catalog', 'app'), '可信 ownership 必须保留同 generation 的应用归属');
activationThrows(fn () => (new ActivationGate($reader->read()))->assertEnabled('catalog', 'app'), ActivationUnavailableException::class, '清单损坏时 Gate 必须 fail closed');

$ownershipTampered = $compiler->compile($records, $manifests);
$ownershipTamperedFile = $runtime . '/generations/' . $ownershipTampered['generation'] . '/snapshot.json';
$ownershipTamperedPayload = json_decode((string) file_get_contents($ownershipTamperedFile), true, 512, JSON_THROW_ON_ERROR);
$ownershipTamperedPayload['ownership']['catalog']['applications']['app'] = false;
file_put_contents($ownershipTamperedFile, json_encode($ownershipTamperedPayload, JSON_THROW_ON_ERROR));
$ownershipTamperedBundle = $reader->readBundle();
activationExpect($ownershipTamperedBundle['activation']->isTrusted(), '仅篡改 ownership 时 activation 必须独立保持可信');
activationExpect(!$ownershipTamperedBundle['ownership']->isTrusted(), '仅篡改 ownership 时 ownership 必须不可信');
activationExpect($ownershipTamperedBundle['activation']->plugins()['catalog']['version'] === '2.1.0', '两个区块必须来自 active 指向的同一 generation');

$malicious = $compiler->compile($records, $manifests);
$maliciousFile = $runtime . '/generations/' . $malicious['generation'] . '/snapshot.json';
$marker = $root . '/reader-executed';
file_put_contents($maliciousFile, '<?php file_put_contents(' . var_export($marker, true) . ', "executed"); return [];');
activationExpect(!$reader->read()->isTrusted(), '恶意 PHP 文本必须被视为不可信 JSON');
activationExpect(!is_file($marker), 'Reader 禁止执行运行时快照内容');

$invalidSchema = $compiler->compile($records, $manifests);
$invalidSchemaFile = $runtime . '/generations/' . $invalidSchema['generation'] . '/snapshot.json';
$invalidSchemaPayload = json_decode((string) file_get_contents($invalidSchemaFile), true, 512, JSON_THROW_ON_ERROR);
$invalidSchemaPayload['plugins']['catalog']['enabled'] = 1;
$invalidSchemaPayload['activation_hash'] = PluginActivationCompiler::integrityHash([
    'schema_version' => $invalidSchemaPayload['schema_version'],
    'generation' => $invalidSchemaPayload['generation'],
    'plugins' => $invalidSchemaPayload['plugins'],
]);
file_put_contents($invalidSchemaFile, json_encode($invalidSchemaPayload, JSON_THROW_ON_ERROR));
activationExpect(!$reader->read()->isTrusted(), '字段类型错误即使 hash 正确也必须不可信');

file_put_contents($runtime . '/active', '../escape');
activationExpect(!$reader->read()->isTrusted(), '非法 active 指针必须返回不可信状态');
unlink($runtime . '/active');
activationExpect(!$reader->read()->isTrusted(), 'active 指针缺失必须返回不可信状态');

$readerSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/sdk/PluginActivationReader.php');
foreach (['app\\common\\model\\Plugin', 'Registry', 'Manifest', 'glob(', 'scandir(', 'FilesystemIterator', 'require ', 'eval('] as $forbidden) {
    activationExpect(!str_contains($readerSource, $forbidden), 'Reader 热路径禁止 DB/Registry/Manifest/目录扫描/执行数据：' . $forbidden);
}
activationExpect(str_contains($readerSource, 'JSON_THROW_ON_ERROR'), 'Reader 必须使用 JSON_THROW_ON_ERROR 解码');

activationRemoveTree($root);
echo "plugin activation registry tests passed\n";
