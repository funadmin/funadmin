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
resourceExpect(is_dir($resourceRecovery . '/initial-demo/prepared-files'), '成功发布在 complete 前必须保留恢复材料');
$publisher->complete($initialPlan);
resourceExpect(!is_dir($resourceRecovery . '/initial-demo'), '成功 standalone apply + complete 必须清理恢复材料');
$component = $adminWeb . '/src/modules/demo/Index.vue';
$stylesheet = $public . '/plugin-assets/demo/public/app.css';
resourceExpect(file_get_contents($component) === '<template>demo-v1</template>', 'Admin Web 源码必须发布到 src/modules/demo');
resourceExpect(file_get_contents($stylesheet) === 'demo-css-v1', '公开资源必须发布到 public/plugin-assets');
resourceExpect(!is_dir($public . '/plugin-assets/demo') || !is_file($public . '/plugin-assets/demo/Index.vue'), 'Admin Web 源码不得发布到 public/plugin-assets');
resourceExpect(count($repository->records) === 2, '源码与公开资源必须统一进入 registry');
$registryTargets = array_column($repository->records, 'target_path');
resourceExpect(in_array('public:plugin-assets/demo/public/app.css', $registryTargets, true), 'public registry 必须使用 public: 根前缀');
resourceExpect(in_array('admin-web:src/modules/demo/Index.vue', $registryTargets, true), 'Admin Web registry 必须使用 admin-web: 根前缀');
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
resourceExpect(!is_dir($resourceRecovery . '/update-demo'), '成功 standalone publish + rollback 必须清理恢复材料');

$binaryV1 = "\x89PNG\r\n\x1a\n\x00\xffold-image";
$binaryV2 = "\x89PNG\r\n\x1a\n\x00\xfenew-image";
file_put_contents($plugins . '/demo/resources/public/logo.png', $binaryV1);
$binaryInitialSnapshot = $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), true, 'binary-initial');
$publisher->complete($binaryInitialSnapshot);
file_put_contents($plugins . '/demo/resources/public/logo.png', $binaryV2);
$binarySnapshot = $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), false, 'binary-update');
resourceExpect(json_encode($binarySnapshot, JSON_THROW_ON_ERROR) !== false, '二进制 snapshot 必须可安全写入 JSON journal');
$binaryEntry = $binarySnapshot['files']['public:plugin-assets/demo/public/logo.png'] ?? [];
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

$cleanupRetryPlan = $publisher->preparePublish(Manifest::fromDirectory($plugins . '/demo'), 'cleanup-retry');
$publisher->apply($cleanupRetryPlan);
$cleanupFailingPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point): void {
        if ($point === 'before_cleanup') {
            throw new RuntimeException('simulated cleanup failure');
        }
    }
);
resourceReject(static fn () => $cleanupFailingPublisher->complete($cleanupRetryPlan), 'simulated cleanup failure');
resourceExpect(is_dir($resourceRecovery . '/cleanup-retry'), 'cleanup 失败必须保留 token 恢复材料供重试');
$publisher->complete($cleanupRetryPlan);
resourceExpect(!is_dir($resourceRecovery . '/cleanup-retry'), 'cleanup 重试成功后必须删除 token 恢复材料');

file_put_contents($plugins . '/demo/admin-web/Conflict.vue', '<template>conflict</template>');
file_put_contents($adminWeb . '/src/modules/demo/Conflict.vue', 'untracked-core');
resourceReject(
    static fn () => $publisher->preparePublish(Manifest::fromDirectory($plugins . '/demo'), 'prepare-path-conflict'),
    '未登记'
);
resourceExpect(
    !is_dir($resourceRecovery . '/prepare-path-conflict'),
    '路径冲突属于无副作用校验，不得先创建 token 恢复目录'
);
unlink($plugins . '/demo/admin-web/Conflict.vue');
unlink($adminWeb . '/src/modules/demo/Conflict.vue');

$prepareHashPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point, int $index): void {
        if ($point === 'before_prepare_hash' && $index === 1) {
            throw new RuntimeException('无法计算资源 SHA-256：simulated second file');
        }
    }
);
resourceReject(
    static fn () => $prepareHashPublisher->preparePublish(Manifest::fromDirectory($plugins . '/demo'), 'prepare-hash-fail'),
    'SHA-256'
);
resourceExpect(!is_dir($resourceRecovery . '/prepare-hash-fail'), '第二文件 hash 失败不得留下无 journal token 目录');

$prepareCopyPublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point, int $index): void {
        if ($point === 'before_prepare_copy' && $index === 1) {
            throw new RuntimeException('无法持久化资源发布文件：simulated second file');
        }
    }
);
$walFilePlan = $prepareCopyPublisher->planPublish(Manifest::fromDirectory($plugins . '/demo'), 'prepare-copy-fail');
resourceExpect(!is_dir($resourceRecovery . '/prepare-copy-fail'), '纯 public plan 不得写 snapshot 或 payload');
$walAppPublisher = new PluginAppPublicationService($root . '/wal-app', $root . '/runtime/plugins', new ResourceTestAppRepository());
$walAppPublisher->prepareOperation('prepare-copy-fail', 'demo', 'publish', true, [
    'plugin_code' => 'demo',
    'file_plan' => $walFilePlan,
]);
resourceReject(
    static fn () => $prepareCopyPublisher->materialize($walFilePlan, static function (array $checkpoint) use ($walAppPublisher): void {
        $walAppPublisher->attachRecoveryContext('prepare-copy-fail', ['file_plan' => $checkpoint]);
    }),
    '持久化资源发布文件'
);
$walJournal = $walAppPublisher->inspect('prepare-copy-fail');
resourceExpect(count((array) ($walJournal['recovery_context']['file_plan']['artifacts'] ?? [])) >= 2, 'materialize 前 journal 必须枚举全部 public artifact');
(new PluginInfrastructureService(
    new PluginResourcePublisher($public, $adminWeb, $repository, $resourceRecovery),
    new PluginAppPublicationService($root . '/wal-app', $root . '/runtime/plugins', new ResourceTestAppRepository())
))->recoverPublication('prepare-copy-fail');
resourceExpect(!is_dir($resourceRecovery . '/prepare-copy-fail'), 'public materialize 中断后新实例必须按 journal 清理 artifact');

$coordinatedWalApp = new PluginAppPublicationService($root . '/wal-coordinated-app', $root . '/runtime/plugins', new ResourceTestAppRepository());
$coordinatedWalObserved = false;
$coordinatedWalFiles = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point) use ($coordinatedWalApp, &$coordinatedWalObserved): void {
        if ($point === 'before_snapshot_copy') {
            $journal = $coordinatedWalApp->inspect('coordinated-wal-prepare');
            $plan = (array) ($journal['recovery_context']['file_plan'] ?? []);
            $coordinatedWalObserved = ($plan['materialization_state'] ?? '') === 'planned'
                && count((array) ($plan['artifacts'] ?? [])) >= 2;
            throw new RuntimeException('simulated coordinated materialize crash');
        }
    }
);
resourceReject(
    static fn () => (new PluginInfrastructureService($coordinatedWalFiles, $coordinatedWalApp))->publishResources(
        Manifest::fromDirectory($plugins . '/demo'),
        'coordinated-wal-prepare',
        false
    ),
    'coordinated materialize crash'
);
resourceExpect($coordinatedWalObserved, 'Infrastructure 必须先写 App journal 完整 public 计划再 materialize');

$snapshotFailurePublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point, int $index): void {
        if ($point === 'after_snapshot_file' && $index === 0) {
            throw new RuntimeException('simulated snapshot interruption');
        }
    }
);
resourceReject(
    static fn () => $snapshotFailurePublisher->prepareRemove('demo', 'prepare-snapshot-fail'),
    'snapshot interruption'
);
resourceExpect(!is_dir($resourceRecovery . '/prepare-snapshot-fail'), 'snapshot 中途失败不得留下无 journal token 目录');

$combinedPrepareFailurePublisher = new PluginResourcePublisher(
    $public,
    $adminWeb,
    $repository,
    $resourceRecovery,
    static function (string $point, int $index): void {
        if ($point === 'before_prepare_copy' && $index === 1) {
            throw new RuntimeException('original prepare failure');
        }
        if ($point === 'before_cleanup') {
            throw new RuntimeException('cleanup prepare failure');
        }
    }
);
resourceReject(
    static fn () => $combinedPrepareFailurePublisher->preparePublish(
        Manifest::fromDirectory($plugins . '/demo'),
        'prepare-combined-fail'
    ),
    'original prepare failure；prepare 材料清理失败：cleanup prepare failure；残留路径：'
);
resourceExpect(is_dir($resourceRecovery . '/prepare-combined-fail'), '组合异常必须保留可定位的残留路径');

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
$publisher->complete($preparedRemove);
resourceExpect(!is_dir($resourceRecovery . '/remove-demo'), '成功 standalone remove + complete 必须清理恢复材料');

mkdir($plugins . '/demo/app/demo/service', 0755, true);
file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // native-v1');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-v1');
file_put_contents($plugins . '/demo/admin-web/Index.vue', '<template>demo-v1</template>');
$coordinatedResourceRepository = new MemoryPluginResourceRepository();
$coordinatedAppRepository = new ResourceTestAppRepository();
$coordinatedResourcePublisher = new PluginResourcePublisher($public, $adminWeb, $coordinatedResourceRepository, $resourceRecovery);
$coordinatedAppPublisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository);
$infrastructure = new PluginInfrastructureService($coordinatedResourcePublisher, $coordinatedAppPublisher);
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-initial');
$infrastructure->completePublishedResources('coordinated-initial');
$oldFileRegistry = $coordinatedResourceRepository->records;
$oldAppRegistry = $coordinatedAppRepository->records;
file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // native-v2');
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

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // files-restore-checkpoint-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-files-restore-checkpoint-v2');
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-files-restore', true);
$filesRestoreFailingInfrastructure = new PluginInfrastructureService(
    $coordinatedResourcePublisher,
    $coordinatedAppPublisher,
    static function (string $point): void {
        if ($point === 'before_files_restored') {
            throw new RuntimeException('simulated files restore failure');
        }
    }
);
resourceReject(
    static fn () => $filesRestoreFailingInfrastructure->rollbackPublishedResources('demo', 'coordinated-files-restore'),
    'files restore failure'
);
resourceExpect(
    ($coordinatedAppPublisher->inspect('coordinated-files-restore')['recovery_steps']['files_restored'] ?? true) === false,
    '文件恢复失败不得误记 checkpoint'
);
(new PluginInfrastructureService(
    new PluginResourcePublisher($public, $adminWeb, $coordinatedResourceRepository, $resourceRecovery),
    new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository)
))->recoverPublication('coordinated-files-restore');
resourceExpect(($coordinatedAppPublisher->inspect('coordinated-files-restore')['state'] ?? '') === 'completed', '文件恢复失败后新实例重试必须 completed');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // native-checkpoint-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-checkpoint-v2');
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-checkpoint', true);
$nativeFailingInfrastructure = new PluginInfrastructureService(
    $coordinatedResourcePublisher,
    $coordinatedAppPublisher,
    static function (string $point): void {
        if ($point === 'before_native_restored') {
            throw new RuntimeException('simulated native recovery failure');
        }
    }
);
resourceReject(
    static fn () => $nativeFailingInfrastructure->rollbackPublishedResources('demo', 'coordinated-checkpoint'),
    'native recovery failure'
);
$checkpointJournal = $coordinatedAppPublisher->inspect('coordinated-checkpoint');
resourceExpect(($checkpointJournal['recovery_steps']['files_restored'] ?? false) === true, '文件恢复成功必须立即 checkpoint');
resourceExpect(($checkpointJournal['recovery_steps']['files_cleaned'] ?? false) === true, '文件材料清理成功必须立即 checkpoint');
resourceExpect(($checkpointJournal['recovery_steps']['native_restored'] ?? false) === false, 'native 失败不得误记成功');
resourceExpect(!is_dir($resourceRecovery . '/coordinated-checkpoint'), '文件 clean 成功后必须释放文件材料所有权');
(new PluginInfrastructureService(
    new PluginResourcePublisher(
        $public,
        $adminWeb,
        $coordinatedResourceRepository,
        $resourceRecovery,
        static function (): void {
            throw new RuntimeException('重试不得再访问文件 snapshot');
        }
    ),
    new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository)
))->recoverPublication('coordinated-checkpoint');
$checkpointJournal = $coordinatedAppPublisher->inspect('coordinated-checkpoint');
resourceExpect(($checkpointJournal['state'] ?? '') === 'completed', '新实例必须从 checkpoint 重试到 completed');
resourceExpect(($checkpointJournal['recovery_steps']['artifacts_cleaned'] ?? false) === true, '全部恢复材料清理后必须 checkpoint');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // cleanup-checkpoint-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-cleanup-checkpoint-v2');
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-file-cleanup', true);
$fileCleanupFailingInfrastructure = new PluginInfrastructureService(
    new PluginResourcePublisher(
        $public,
        $adminWeb,
        $coordinatedResourceRepository,
        $resourceRecovery,
        static function (string $point): void {
            if ($point === 'before_cleanup') {
                throw new RuntimeException('simulated file cleanup failure');
            }
        }
    ),
    $coordinatedAppPublisher
);
resourceReject(
    static fn () => $fileCleanupFailingInfrastructure->rollbackPublishedResources('demo', 'coordinated-file-cleanup'),
    'file cleanup failure'
);
$fileCleanupJournal = $coordinatedAppPublisher->inspect('coordinated-file-cleanup');
resourceExpect(($fileCleanupJournal['recovery_steps']['files_restored'] ?? false) === true, 'complete 失败前文件恢复 checkpoint 必须保留');
resourceExpect(($fileCleanupJournal['recovery_steps']['files_cleaned'] ?? true) === false, '文件 complete 失败不得误记 files_cleaned');
file_put_contents($stylesheet, 'retry-must-not-rollback-files');
(new PluginInfrastructureService(
    new PluginResourcePublisher($public, $adminWeb, $coordinatedResourceRepository, $resourceRecovery),
    new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository)
))->recoverPublication('coordinated-file-cleanup');
resourceExpect(file_get_contents($stylesheet) === 'retry-must-not-rollback-files', 'files_restored 后重试只能 cleanup，不得再次 rollback');
resourceExpect(($coordinatedAppPublisher->inspect('coordinated-file-cleanup')['state'] ?? '') === 'completed', '文件 cleanup 重试后必须 completed');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // registry-checkpoint-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-registry-checkpoint-v2');
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-registry-restore', true);
$registryFailingInfrastructure = new PluginInfrastructureService(
    $coordinatedResourcePublisher,
    $coordinatedAppPublisher,
    static function (string $point): void {
        if ($point === 'before_registry_restored') {
            throw new RuntimeException('simulated registry recovery failure');
        }
    }
);
resourceReject(
    static fn () => $registryFailingInfrastructure->rollbackPublishedResources('demo', 'coordinated-registry-restore'),
    'registry recovery failure'
);
$registryJournal = $coordinatedAppPublisher->inspect('coordinated-registry-restore');
resourceExpect(($registryJournal['recovery_steps']['native_restored'] ?? false) === true, 'registry 失败前 native 必须已 checkpoint');
resourceExpect(($registryJournal['recovery_steps']['registry_restored'] ?? true) === false, 'registry 失败不得误记 checkpoint');
(new PluginInfrastructureService(
    new PluginResourcePublisher(
        $public,
        $adminWeb,
        $coordinatedResourceRepository,
        $resourceRecovery,
        static function (): void {
            throw new RuntimeException('registry 重试不得访问文件材料');
        }
    ),
    new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository)
))->recoverPublication('coordinated-registry-restore');
resourceExpect(($coordinatedAppPublisher->inspect('coordinated-registry-restore')['state'] ?? '') === 'completed', 'registry 失败后新实例重试必须 completed');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // artifacts-checkpoint-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-artifacts-checkpoint-v2');
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'coordinated-artifacts-cleanup', true);
$artifactFailingInfrastructure = new PluginInfrastructureService(
    $coordinatedResourcePublisher,
    $coordinatedAppPublisher,
    static function (string $point): void {
        if ($point === 'before_artifacts_cleaned') {
            throw new RuntimeException('simulated native artifact cleanup failure');
        }
    }
);
resourceReject(
    static fn () => $artifactFailingInfrastructure->rollbackPublishedResources('demo', 'coordinated-artifacts-cleanup'),
    'native artifact cleanup failure'
);
$artifactJournal = $coordinatedAppPublisher->inspect('coordinated-artifacts-cleanup');
resourceExpect(($artifactJournal['recovery_steps']['registry_restored'] ?? false) === true, 'native cleanup 前 registry 必须已 checkpoint');
resourceExpect(($artifactJournal['recovery_steps']['artifacts_cleaned'] ?? true) === false, 'native cleanup 失败不得 completed');
(new PluginInfrastructureService(
    new PluginResourcePublisher(
        $public,
        $adminWeb,
        $coordinatedResourceRepository,
        $resourceRecovery,
        static function (): void {
            throw new RuntimeException('native cleanup 重试不得访问文件材料');
        }
    ),
    new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository)
))->recoverPublication('coordinated-artifacts-cleanup');
resourceExpect(($coordinatedAppPublisher->inspect('coordinated-artifacts-cleanup')['state'] ?? '') === 'completed', 'native cleanup 新实例重试必须 completed');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // commit-cleanup-v2');
file_put_contents($plugins . '/demo/resources/public/app.css', 'demo-css-commit-cleanup-v2');
$infrastructure->publishResources(Manifest::fromDirectory($plugins . '/demo'), 'commit-cleanup-crash', true);
$commitCleanupFailingInfrastructure = new PluginInfrastructureService(
    new PluginResourcePublisher(
        $public,
        $adminWeb,
        $coordinatedResourceRepository,
        $resourceRecovery,
        static function (string $point): void {
            if ($point === 'before_cleanup') {
                throw new RuntimeException('simulated committed public cleanup crash');
            }
        }
    ),
    $coordinatedAppPublisher
);
resourceReject(
    static fn () => $commitCleanupFailingInfrastructure->completePublishedResources('commit-cleanup-crash'),
    'committed public cleanup crash'
);
$commitCleanupJournal = $coordinatedAppPublisher->inspect('commit-cleanup-crash');
resourceExpect(($commitCleanupJournal['state'] ?? '') === 'finalizing', '删除任何提交恢复材料前必须原子持久化 finalizing 决定');
resourceExpect(($commitCleanupJournal['commit_decided'] ?? false) === true, 'finalizing journal 必须持久化不可回滚提交决定');
(new PluginInfrastructureService(
    new PluginResourcePublisher($public, $adminWeb, $coordinatedResourceRepository, $resourceRecovery),
    new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', $coordinatedAppRepository)
))->recoverPublication('commit-cleanup-crash');
resourceExpect(
    str_contains((string) file_get_contents($root . '/app/demo/service/Domain.php'), 'commit-cleanup-v2'),
    '提交决定后新实例恢复只能继续 cleanup，不得 rollback native'
);
resourceExpect(file_get_contents($stylesheet) === 'demo-css-commit-cleanup-v2', '提交决定后新实例恢复不得 rollback public');
resourceExpect(($coordinatedAppPublisher->inspect('commit-cleanup-crash')['state'] ?? '') === 'completed', '提交 cleanup 重试全部完成后才可 completed');

file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // native-v3');
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

$standaloneBaseline = $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), false, 'standalone-rollback-baseline');
$publisher->complete($standaloneBaseline);
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
file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; // native-v4');
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
