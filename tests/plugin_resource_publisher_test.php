<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\service\PluginAppPublicationRepository;
use app\console\service\PluginAppPublicationService;
use app\console\service\PluginInfrastructureService;
use app\console\service\PluginResourcePublisher;
use app\console\service\PluginResourceRepository;
use fun\plugins\Manifest;

$resourceTestRuntimeRoot = '';
if (!function_exists('runtime_path')) {
    function runtime_path(string $path = ''): string
    {
        global $resourceTestRuntimeRoot;
        return rtrim($resourceTestRuntimeRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

final class MemoryPluginResourceRepository implements PluginResourceRepository
{
    public array $records = [];
    public bool $failNextReplace = false;

    public function all(): array
    {
        return $this->records;
    }

    public function replaceForPlugin(string $pluginCode, array $records): void
    {
        if ($this->failNextReplace) {
            $this->failNextReplace = false;
            throw new RuntimeException('registry write failed');
        }
        $this->records = array_values(array_filter(
            $this->records,
            static fn (array $row): bool => $row['plugin_code'] !== $pluginCode
        ));
        $this->records = array_merge($this->records, $records);
    }
}

final class ResourceTestAppRepository implements PluginAppPublicationRepository
{
    public array $records = [];
    public bool $failNextReplace = false;
    public mixed $beforeFailure = null;

    public function all(): array
    {
        return $this->records;
    }

    public function replaceAppForPlugin(string $pluginCode, array $records): void
    {
        if ($this->failNextReplace) {
            $this->failNextReplace = false;
            if (is_callable($this->beforeFailure)) {
                ($this->beforeFailure)();
            }
            throw new RuntimeException('native registry write failed');
        }
        $this->records = $records;
    }
}

function resourceExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
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
$resourceTestRuntimeRoot = $root . '/runtime';
$public = $root . '/public';
$adminWeb = $root . '/admin-web';
$plugins = $root . '/plugins';
foreach (['demo', 'other'] as $code) {
    mkdir($plugins . '/' . $code . '/resources/public', 0755, true);
    mkdir($plugins . '/' . $code . '/admin-web', 0755, true);
    file_put_contents($plugins . '/' . $code . '/Plugin.php', '<?php namespace plugins\\' . $code . '; final class Plugin {}');
    file_put_contents($plugins . '/' . $code . '/admin-web/Index.vue', '<template>' . $code . '-v1</template>');
    file_put_contents($plugins . '/' . $code . '/resources/public/app.css', $code . '-css-v1');
    file_put_contents($plugins . '/' . $code . '/plugin.json', json_encode([
        'schema_version' => 2,
        'code' => $code,
        'name' => ucfirst($code),
        'version' => '1.0.0',
        'requires' => ['funadmin' => '*', 'php' => '>=8.1', 'plugins' => []],
        'entry' => ['class' => 'plugins\\' . $code . '\\Plugin', 'file' => 'Plugin.php'],
        'adminWeb' => [
            'source' => 'admin-web',
            'components' => ['Index' => 'Index.vue'],
            'minFrontendVersion' => '1.0.0',
            'permissions' => [],
            'menu' => [],
            'routes' => [],
        ],
        'resources' => [
            'public' => ['source' => 'resources/public', 'target' => 'plugin-assets/' . $code . '/public'],
        ],
        'purge' => ['supported' => false],
    ], JSON_UNESCAPED_SLASHES));
}
mkdir($public, 0755, true);
mkdir($adminWeb, 0755, true);
$repository = new MemoryPluginResourceRepository();
$resourceRecovery = $root . '/runtime/plugins/publication-recovery';
$publisher = new PluginResourcePublisher($public, $adminWeb, $repository, $resourceRecovery);

$demo = Manifest::fromDirectory($plugins . '/demo');
$initialPlan = $publisher->preparePublish($demo, 'initial-demo');
resourceExpect(!is_file($adminWeb . '/src/modules/demo/Index.vue'), 'preparePublish 不得修改发布目标');
resourceExpect(!is_file($public . '/plugin-assets/demo/public/app.css'), 'preparePublish 不得提前复制公开资源');
resourceExpect(($initialPlan['plugin_code'] ?? '') === 'demo', '持久计划必须包含正确 plugin_code');
resourceExpect(($initialPlan['operation'] ?? '') === 'publish', '持久计划必须包含发布操作类型');
resourceExpect(count((array) ($initialPlan['planned_targets'] ?? [])) === 2, '持久计划必须预先列出全部 touched target');
resourceExpect(count((array) ($initialPlan['writes'] ?? [])) === 2, '持久计划必须预先列出全部写入及预期 hash');
resourceExpect(array_key_exists('registry_next', $initialPlan), '持久计划必须包含 registry next');
resourceExpect(json_encode($initialPlan, JSON_THROW_ON_ERROR) !== false, '持久计划必须可 JSON 编码');
$snapshot = $publisher->apply($initialPlan);
resourceExpect($snapshot === ($initialPlan['snapshot'] ?? null), 'apply 必须返回已准备的 durable snapshot');
$component = $adminWeb . '/src/modules/demo/Index.vue';
$stylesheet = $public . '/plugin-assets/demo/public/app.css';
resourceExpect(file_get_contents($component) === '<template>demo-v1</template>', 'Admin Web 源码必须发布到 src/modules/demo');
resourceExpect(file_get_contents($stylesheet) === 'demo-css-v1', '公开资源必须发布到 public/plugin-assets');
resourceExpect(!is_dir($public . '/plugin-assets/demo') || !is_file($public . '/plugin-assets/demo/Index.vue'), 'Admin Web 源码不得发布到 public/plugin-assets');
resourceExpect(count($repository->records) === 2, '源码与公开资源必须统一进入 registry');
resourceExpect(($snapshot['rebuildRequired'] ?? false) === true, '源码发布必须要求重新构建前端');

file_put_contents($plugins . '/demo/admin-web/Index.vue', '<template>demo-v2</template>');
file_put_contents($plugins . '/demo/admin-web/Extra.vue', '<template>extra</template>');
$updatedManifest = json_decode((string) file_get_contents($plugins . '/demo/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$updatedManifest['adminWeb']['components']['Extra'] = 'Extra.vue';
file_put_contents($plugins . '/demo/plugin.json', json_encode($updatedManifest, JSON_UNESCAPED_SLASHES));
$snapshot = $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), true, 'update-demo');
resourceExpect(file_get_contents($component) === '<template>demo-v2</template>', '更新必须发布新 Admin Web 源码');
resourceExpect(is_file($adminWeb . '/src/modules/demo/Extra.vue'), '更新必须发布新增源码组件');
$publisher->rollback($snapshot);
resourceExpect(file_get_contents($component) === '<template>demo-v1</template>', '回滚必须恢复旧 Admin Web 源码');
resourceExpect(!is_file($adminWeb . '/src/modules/demo/Extra.vue'), '回滚必须删除新增源码组件');

$binaryV1 = "\x89PNG\r\n\x1a\n\x00\xffold-image";
$binaryV2 = "\x89PNG\r\n\x1a\n\x00\xfenew-image";
file_put_contents($plugins . '/demo/resources/public/logo.png', $binaryV1);
$publisher->publish(Manifest::fromDirectory($plugins . '/demo'), true, 'binary-initial');
file_put_contents($plugins . '/demo/resources/public/logo.png', $binaryV2);
$binarySnapshot = $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), false, 'binary-update');
resourceExpect(json_encode($binarySnapshot, JSON_THROW_ON_ERROR) !== false, '二进制 snapshot 必须可安全写入 JSON journal');
$binaryEntry = $binarySnapshot['files']['plugin-assets/demo/public/logo.png'] ?? [];
resourceExpect(is_array($binaryEntry) && ($binaryEntry['encoding'] ?? '') === 'file', '二进制 snapshot journal 只能保存文件引用元数据');
resourceExpect(isset($binaryEntry['relative_path'], $binaryEntry['sha256']), '文件化 snapshot 必须保存相对路径与 SHA-256');
resourceExpect(!isset($binaryEntry['contents']), '文件化 snapshot 不得嵌入原始内容');

$appRepository = new ResourceTestAppRepository();
$appPublisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $appRepository);
$appPublisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'binary-update');
$appPublisher->attachRecoveryContext('binary-update', ['plugin_code' => 'demo', 'file_snapshot' => $binarySnapshot]);
$recoveryPath = $appPublisher->requireManualRecovery('binary-update', ['migration_started' => true]);
$journal = $appPublisher->inspect('binary-update');
resourceExpect(($journal['manual_recovery'] ?? false) === true, 'migration 失败必须留下 manual_recovery journal');
resourceExpect(str_starts_with((string) $binaryEntry['relative_path'], 'resource-files/'), 'snapshot 路径必须相对 token recovery 目录');
resourceExpect(is_file($recoveryPath . '/' . $binaryEntry['relative_path']), '旧二进制必须持久保存在 durable recovery 目录');
$appPublisher->recover('binary-update', true, static fn (array $context) => $publisher->rollback((array) $context['file_snapshot']));
resourceExpect(file_get_contents($public . '/plugin-assets/demo/public/logo.png') === $binaryV1, 'recover 必须逐字节恢复旧图片');
resourceExpect(!is_dir($recoveryPath), 'recover 完成后必须清理 token durable recovery 目录');

mkdir($adminWeb . '/src/modules/other', 0755, true);
file_put_contents($adminWeb . '/src/modules/other/Index.vue', 'core');
resourceReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/other'), true, 'other-core'), '未登记');
unlink($adminWeb . '/src/modules/other/Index.vue');

$outside = $root . '/outside.vue';
file_put_contents($outside, 'outside');
resourceExpect(symlink($outside, $adminWeb . '/src/modules/other/Index.vue'), '测试环境必须能创建目标符号链接');
resourceReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/other'), true, 'other-link'), '符号链接');
resourceExpect(file_get_contents($outside) === 'outside', '目标符号链接不得覆盖根目录外文件');
unlink($adminWeb . '/src/modules/other/Index.vue');
unlink($outside);

$before = $repository->records;
file_put_contents($plugins . '/demo/admin-web/Index.vue', '<template>demo-v3</template>');
$repository->failNextReplace = true;
resourceReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), true, 'registry-fail'), 'registry write failed');
resourceExpect(file_get_contents($component) === '<template>demo-v2</template>', 'registry 失败必须恢复更新前 Admin Web 源码');
resourceExpect($repository->records === $before, 'registry 失败必须恢复旧记录');
resourceExpect(!is_dir($resourceRecovery . '/registry-fail'), 'standalone publish 补偿成功后必须清理恢复材料');

$crashPlan = $publisher->preparePublish(Manifest::fromDirectory($plugins . '/demo'), 'copy-crash');
$crashAppPublisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $appRepository);
$crashAppPublisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'copy-crash');
$crashAppPublisher->attachRecoveryContext('copy-crash', ['plugin_code' => 'demo', 'file_plan' => $crashPlan]);
$crashingPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point, int $index): void {
        if ($point === 'after_write' && $index === 0) {
            throw new RuntimeException('simulated process termination after first copy');
        }
    }
);
resourceReject(static fn () => $crashingPublisher->apply($crashPlan, false), 'simulated process termination');
$restartedPublisher = new PluginResourcePublisher($public, $adminWeb, $repository, $resourceRecovery);
$restartedAppPublisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $appRepository);
$restartedAppPublisher->recover(
    'copy-crash',
    false,
    static fn (array $context) => $restartedPublisher->rollbackPrepared((array) $context['file_plan'])
);
resourceExpect(file_get_contents($component) === '<template>demo-v2</template>', '新实例恢复必须还原首个已复制目标');
resourceExpect(file_get_contents($adminWeb . '/src/modules/demo/Extra.vue') === '<template>extra</template>', '新实例恢复必须还原未完成发布前的既有文件');

$registryCrashPlan = $publisher->preparePublish(Manifest::fromDirectory($plugins . '/demo'), 'registry-crash');
$registryCrashingPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point): void {
        if ($point === 'before_registry') {
            throw new RuntimeException('simulated termination before registry');
        }
    }
);
resourceReject(static fn () => $registryCrashingPublisher->apply($registryCrashPlan, false), 'before registry');
(new PluginResourcePublisher($public, $adminWeb, $repository, $resourceRecovery))->rollbackPrepared($registryCrashPlan);
resourceExpect($repository->records === $before, 'registry 提交前恢复必须还原旧 registry');
resourceExpect(file_get_contents($component) === '<template>demo-v2</template>', 'registry 提交前恢复必须还原旧文件');

$removePlan = $publisher->prepareRemove('demo', 'remove-crash');
$removeCrashingPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point, int $index): void {
        if ($point === 'after_delete' && $index === 0) {
            throw new RuntimeException('simulated termination during remove');
        }
    }
);
resourceReject(static fn () => $removeCrashingPublisher->apply($removePlan, false), 'during remove');
(new PluginResourcePublisher($public, $adminWeb, $repository, $resourceRecovery))->rollbackPrepared($removePlan);
resourceExpect(is_file($component) && is_file($stylesheet), 'remove 中断后新实例必须恢复全部旧文件');
resourceExpect($repository->records === $before, 'remove 中断后必须恢复 registry');

$preparedRemove = $publisher->prepareRemove('demo', 'remove-demo');
resourceExpect(is_file($component) && is_file($stylesheet), 'prepareRemove 不得删除目标');
$removeSnapshot = $publisher->apply($preparedRemove);
resourceExpect(($removeSnapshot['rebuildRequired'] ?? false) === true, '源码卸载必须要求重新构建前端');
resourceExpect(!is_file($component) && !is_file($stylesheet), '卸载必须删除已登记资源');

mkdir($plugins . '/demo/app/demo/service', 0755, true);
file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace plugin\\demo\\service; // native-v1');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-v1');
file_put_contents($plugins . '/demo/admin-web/Index.vue', '<template>demo-v1</template>');
$coordinatedResourceRepository = new MemoryPluginResourceRepository();
$coordinatedAppRepository = new ResourceTestAppRepository();
$coordinatedResourcePublisher = new PluginResourcePublisher($public, $adminWeb, $coordinatedResourceRepository, $resourceRecovery);
$coordinatedAppPublisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository);
$infrastructure = new PluginInfrastructureService($coordinatedResourcePublisher, $coordinatedAppPublisher);
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-initial');
$coordinatedAppPublisher->complete('coordinated-initial');
$oldFileRegistry = $coordinatedResourceRepository->records;
$oldAppRegistry = $coordinatedAppRepository->records;
file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace plugin\\demo\\service; // native-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-v2');
$coordinatedAppRepository->failNextReplace = true;
$snapshotObserved = false;
$coordinatedAppRepository->beforeFailure = static function () use (&$snapshotObserved, $resourceRecovery): void {
    $snapshotObserved = is_dir($resourceRecovery . '/coordinated-native-fail/resource-files');
};
resourceReject(
    static fn () => $infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-native-fail'),
    'native registry write failed'
);
resourceExpect($snapshotObserved, 'native apply 故障发生时 public snapshot 必须仍存在');
resourceExpect(file_get_contents($stylesheet) === 'demo-css-v1', 'native apply 故障后 public 字节必须保持原状');
resourceExpect($coordinatedResourceRepository->records === $oldFileRegistry, 'native apply 故障后 public registry 必须保持原状');
resourceExpect(str_contains((string) file_get_contents($root . '/app/demo/service/Domain.php'), 'native-v1'), '统一补偿必须恢复 native 字节');
resourceExpect($coordinatedAppRepository->records === $oldAppRegistry, '统一补偿必须恢复 native registry');
resourceExpect(!is_dir($resourceRecovery . '/coordinated-native-fail'), '统一补偿成功后必须最终清理共享 token 目录');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace plugin\\demo\\service; // native-v3');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-v3');
$coordinatedAppRepository->failNextReplace = true;
$coordinatedAppRepository->beforeFailure = static function () use ($resourceRecovery): void {
    $snapshots = glob($resourceRecovery . '/coordinated-rollback-fail/resource-files/*.snapshot') ?: [];
    if ($snapshots !== []) {
        unlink($snapshots[0]);
    }
};
resourceReject(
    static fn () => $infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-rollback-fail'),
    '资源恢复文件不存在'
);
$failedJournal = $coordinatedAppPublisher->inspect('coordinated-rollback-fail');
resourceExpect(($failedJournal['state'] ?? '') === 'rollback_required', '补偿失败必须保留 rollback_required journal');
resourceExpect(str_contains((string) file_get_contents($root . '/app/demo/service/Domain.php'), 'native-v3'), 'public 补偿失败后不得继续破坏 native 当前状态');
resourceExpect(is_dir($resourceRecovery . '/coordinated-rollback-fail/prepared-files'), '补偿失败必须保留 prepared-files');
resourceExpect(is_dir($resourceRecovery . '/coordinated-rollback-fail/resource-files'), '补偿失败必须保留剩余 resource-files');
$failedUnits = (array) ($failedJournal['units'] ?? []);
resourceExpect(isset($failedUnits[0]['backup']) && is_dir((string) $failedUnits[0]['backup']), '补偿失败必须保留 native backup');

$publisher->publish(Manifest::fromDirectory($plugins . '/demo'), false, 'standalone-rollback-baseline');
$standaloneRollbackFailurePlan = $publisher->preparePublish(
    Manifest::fromDirectory($plugins . '/demo'),
    'standalone-rollback-fail'
);
$standaloneRollbackSnapshots = glob($resourceRecovery . '/standalone-rollback-fail/resource-files/*.snapshot') ?: [];
resourceExpect($standaloneRollbackSnapshots !== [], 'standalone 回滚失败测试必须存在 snapshot');
unlink($standaloneRollbackSnapshots[0]);
$standaloneRollbackFailingPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point): void {
        if ($point === 'before_registry') {
            throw new RuntimeException('standalone apply failed');
        }
    }
);
resourceReject(
    static fn () => $standaloneRollbackFailingPublisher->apply($standaloneRollbackFailurePlan),
    '资源自动回滚失败'
);
resourceExpect(
    is_dir($resourceRecovery . '/standalone-rollback-fail/prepared-files'),
    'standalone 补偿失败必须保留 prepared-files'
);
resourceExpect(
    is_dir($resourceRecovery . '/standalone-rollback-fail/resource-files'),
    'standalone 补偿失败必须保留剩余 resource-files'
);

$standaloneAppRepository = new ResourceTestAppRepository();
$standaloneAppPublisher = new PluginAppPublicationService($root . '/standalone/app', $root . '/standalone/runtime/plugins', $standaloneAppRepository);
$standaloneAppPublisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'native-standalone-initial');
$standaloneAppPublisher->complete('native-standalone-initial');
file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace plugin\\demo\\service; // native-v4');
$standaloneRecovery = $root . '/standalone/runtime/plugins/publication-recovery/native-standalone-fail/resource-files';
mkdir($standaloneRecovery, 0755, true);
file_put_contents($standaloneRecovery . '/shared.snapshot', 'standalone');
$standaloneAppRepository->failNextReplace = true;
resourceReject(
    static fn () => $standaloneAppPublisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'native-standalone-fail'),
    'native registry write failed'
);
resourceExpect(!is_dir(dirname($standaloneRecovery)), 'native service 单独调用失败时仍必须默认自动清理 token 目录');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) {
    $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);
echo "plugin resource publisher tests: PASS\n";
