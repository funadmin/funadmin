<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\service\PluginAppPublicationRepository;
use app\console\service\PluginAppPublicationService;
use fun\plugins\Manifest;

final class MemoryPluginAppPublicationRepository implements PluginAppPublicationRepository
{
    public array $records = [];
    public bool $failNextReplace = false;

    public function all(): array
    {
        return $this->records;
    }

    public function replaceAppForPlugin(string $pluginCode, array $records): void
    {
        if ($this->failNextReplace) {
            $this->failNextReplace = false;
            throw new RuntimeException('registry write failed');
        }
        $this->records = array_values(array_filter(
            $this->records,
            static fn (array $row): bool => ($row['plugin_code'] ?? '') !== $pluginCode
                || ($row['resource_type'] ?? '') !== 'native_app'
        ));
        $this->records = array_merge($this->records, $records);
    }
}

function appPublicationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function appPublicationReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        appPublicationExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function appPublicationRemoveTree(string $directory): void
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

function appPublicationPlugin(string $plugins, string $code, array $consoleLayers = ['controller']): Manifest
{
    $directory = $plugins . '/' . $code;
    mkdir($directory . '/app/' . $code . '/service', 0755, true);
    file_put_contents($directory . '/Plugin.php', '<?php namespace plugins\\' . $code . '; final class Plugin {}');
    file_put_contents(
        $directory . '/app/' . $code . '/service/Domain.php',
        '<?php namespace app\\' . $code . '\\service; final class Domain { public const VERSION = 1; }'
    );
    foreach ($consoleLayers as $layer) {
        mkdir($directory . '/app/console/' . $layer, 0755, true);
        $attribute = $layer === 'controller' ? ' use think\\annotation\\route\\Group; #[Group("plugin/' . $code . '")]' : '';
        file_put_contents(
            $directory . '/app/console/' . $layer . '/Entry.php',
            '<?php namespace app\\console\\' . $layer . '\\plugin\\' . $code . ';' . $attribute . ' final class Entry {}'
        );
    }
    file_put_contents($directory . '/plugin.json', json_encode([
        'schema_version' => 2,
        'code' => $code,
        'name' => ucfirst($code),
        'version' => '1.0.0',
        'requires' => ['funadmin' => '*', 'php' => '>=8.1', 'plugins' => []],
        'entry' => ['class' => 'plugins\\' . $code . '\\Plugin', 'file' => 'Plugin.php'],
        'resources' => [],
        'purge' => ['supported' => false],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return Manifest::fromDirectory($directory);
}

$root = sys_get_temp_dir() . '/funadmin-app-publication-' . bin2hex(random_bytes(5));
$plugins = $root . '/plugins';
$app = $root . '/app';
$runtime = $root . '/runtime/plugins';
mkdir($plugins, 0755, true);
mkdir($app . '/console', 0755, true);
$repository = new MemoryPluginAppPublicationRepository();
$publisher = new PluginAppPublicationService($app, $runtime, $repository);

try {
    $demo = appPublicationPlugin($plugins, 'demo', ['controller', 'model', 'service', 'validate', 'middleware']);
    $handle = $publisher->publish($demo, 'install-demo');
    appPublicationExpect(is_file($app . '/demo/service/Domain.php'), '独立应用必须整体映射到 app/demo');
    foreach (['controller', 'model', 'service', 'validate', 'middleware'] as $layer) {
        appPublicationExpect(is_file($app . '/console/' . $layer . '/plugin/demo/Entry.php'), 'Console 固定层映射失败：' . $layer);
    }
    appPublicationExpect(($handle['token'] ?? '') === 'install-demo', 'publish 必须返回恢复 token');
    appPublicationExpect(($publisher->inspect('install-demo')['state'] ?? '') === 'registry_committed', '发布后 journal 必须持久化 registry_committed');
    appPublicationExpect(count($repository->records) === 6, '每个 publication unit 的文件必须登记 registry');
    appPublicationExpect(
        in_array('app/demo/service/Domain.php', array_column($repository->records, 'source_path'), true),
        'registry source_path 必须保持插件根目录相对路径'
    );
    appPublicationExpect(
        in_array('app/console/controller/Entry.php', array_column($repository->records, 'source_path'), true),
        'Console registry source_path 必须保持插件根目录相对路径'
    );
    $publisher->complete('install-demo');
    appPublicationExpect(($publisher->inspect('install-demo')['state'] ?? '') === 'completed', 'complete 必须持久化 completed');
    appPublicationReject(static fn () => $publisher->publish($demo, 'install-demo'), 'operation token 已存在');

    $empty = appPublicationPlugin($plugins, 'empty', []);
    appPublicationRemoveTree($plugins . '/empty/app/empty/service');
    $publisher->prepareOperation('empty-unit', 'empty', 'publish');
    appPublicationExpect($publisher->hasJournal('empty-unit'), '无 native unit 也必须创建 operation journal');
    $publisher->applyPrepared(Manifest::fromDirectory($plugins . '/empty'), 'empty-unit');
    appPublicationExpect(!is_dir($app . '/empty'), '空 publication unit 必须跳过且不得创建无所有权目标');
    appPublicationExpect(
        array_values(array_filter($repository->records, static fn (array $row): bool => ($row['plugin_code'] ?? '') === 'empty')) === [],
        '空 publication unit 不得写入伪文件 registry'
    );
    $emptyJournal = $publisher->inspect('empty-unit');
    appPublicationExpect(($emptyJournal['units'] ?? []) === [], '空 publication 不得生成虚假 unit journal');
    $publisher->complete('empty-unit');

    $unknown = appPublicationPlugin($plugins, 'unknown', ['config']);
    appPublicationReject(static fn () => $publisher->publish($unknown, 'unknown-layer'), '未知 Console layer');
    appPublicationRemoveTree($plugins . '/unknown/app/console/config');
    file_put_contents($plugins . '/unknown/app/console/root.php', '<?php namespace app\\console\\root\\plugin\\unknown; final class RootFile {}');
    $unknown = Manifest::fromDirectory($plugins . '/unknown');
    appPublicationReject(static fn () => $publisher->publish($unknown, 'console-file'), 'Console 根目录只允许固定 layer');

    $linked = appPublicationPlugin($plugins, 'linked');
    file_put_contents($root . '/outside.txt', 'outside');
    symlink($root . '/outside.txt', $plugins . '/linked/app/linked/link.txt');
    appPublicationReject(static fn () => $publisher->publish($linked, 'source-link'), '符号链接');

    $core = appPublicationPlugin($plugins, 'core');
    mkdir($app . '/core', 0755, true);
    file_put_contents($app . '/core/keep.php', 'core');
    appPublicationReject(static fn () => $publisher->publish($core, 'core-conflict'), '未登记');
    appPublicationExpect(file_get_contents($app . '/core/keep.php') === 'core', '核心目标冲突不得覆盖');

    $foreign = appPublicationPlugin($plugins, 'foreign');
    $repository->records[] = [
        'plugin_code' => 'attacker',
        'resource_type' => 'native_app',
        'publication_unit' => 'application:foreign',
        'target_path' => 'application:foreign/service/Domain.php',
        'sha256' => str_repeat('a', 64),
    ];
    appPublicationReject(static fn () => $publisher->publish($foreign, 'foreign-conflict'), '其他插件');
    $repository->records = array_values(array_filter($repository->records, static fn (array $row): bool => ($row['plugin_code'] ?? '') !== 'attacker'));

    $lock = fopen($runtime . '/publication.lock', 'c+');
    appPublicationExpect($lock !== false && flock($lock, LOCK_EX | LOCK_NB), '测试必须取得全局 publication lock');
    appPublicationReject(static fn () => $publisher->publish($foreign, 'locked'), '全局发布锁');
    flock($lock, LOCK_UN);
    fclose($lock);

    file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 2; }');
    $updated = Manifest::fromDirectory($plugins . '/demo');
    $publisher->publish($updated, 'update-demo');
    $demoUnits = array_values(array_unique(array_column(array_filter(
        $repository->records,
        static fn (array $row): bool => ($row['plugin_code'] ?? '') === 'demo'
    ), 'publication_unit')));
    appPublicationExpect(in_array('application:demo', $demoUnits, true), 'application registry 必须使用 application:<code> unit');
    appPublicationExpect(in_array('console-plugin:controller:demo', $demoUnits, true), 'Console registry 必须使用 console-plugin:<layer>:<code> unit');
    appPublicationExpect(str_contains((string) file_get_contents($app . '/demo/service/Domain.php'), 'VERSION = 2'), '更新必须原子替换完整目录');
    appPublicationExpect(!is_dir($app . '/.publication-update-demo-application-demo'), 'swap 后不得残留目标临时目录');
    $updateJournal = $publisher->inspect('update-demo');
    foreach ((array) ($updateJournal['units'] ?? []) as $unit) {
        appPublicationExpect(
            dirname((string) $unit['backup']) === dirname((string) $unit['target']),
            'swap 临时 backup 必须位于目标父目录以保证同文件系统原子 rename'
        );
    }
    $publisher->rollback('update-demo');
    appPublicationExpect(str_contains((string) file_get_contents($app . '/demo/service/Domain.php'), 'VERSION = 1'), 'rollback 必须恢复旧目录');

    appPublicationRemoveTree($plugins . '/demo/app/demo/service');
    $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'empty-delete');
    appPublicationExpect(!is_dir($app . '/demo'), '已登记 unit 更新为空必须删除目标目录');
    appPublicationExpect(
        array_values(array_filter(
            $repository->records,
            static fn (array $row): bool => ($row['plugin_code'] ?? '') === 'demo'
                && ($row['publication_unit'] ?? '') === 'application:demo'
        )) === [],
        '已登记空 unit 必须删除对应 registry 所有权'
    );
    $emptyDeleteJournal = $publisher->inspect('empty-delete');
    appPublicationExpect(count((array) ($emptyDeleteJournal['units'] ?? [])) >= 1, '空更新删除必须写入 unit journal');
    $publisher->rollback('empty-delete');
    appPublicationExpect(is_file($app . '/demo/service/Domain.php'), '空更新删除 rollback 必须恢复旧 unit');
    appPublicationExpect($repository->records !== [], '空更新删除 rollback 必须恢复 registry');

    file_put_contents($app . '/demo/service/Domain.php', 'locally modified');
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'empty-modified'), 'modified');
    file_put_contents($app . '/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 1; }');
    unlink($app . '/demo/service/Domain.php');
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'empty-deleted'), 'deleted');
    file_put_contents($app . '/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 1; }');
    file_put_contents($app . '/demo/service/Local.php', 'local addition');
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'empty-untracked'), 'untracked');
    unlink($app . '/demo/service/Local.php');
    mkdir($plugins . '/demo/app/demo/service', 0755, true);

    file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 3; }');
    $repository->failNextReplace = true;
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'registry-fail'), 'registry write failed');
    appPublicationExpect(str_contains((string) file_get_contents($app . '/demo/service/Domain.php'), 'VERSION = 1'), 'registry 失败必须恢复旧目录');

    file_put_contents($app . '/demo/service/Domain.php', 'locally modified');
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'modified'), 'modified');
    file_put_contents($app . '/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 1; }');
    unlink($app . '/demo/service/Domain.php');
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'deleted'), 'deleted');
    file_put_contents($app . '/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 1; }');
    file_put_contents($app . '/demo/service/Local.php', 'local addition');
    appPublicationReject(static fn () => $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'untracked'), 'untracked');
    unlink($app . '/demo/service/Local.php');

    file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 4; }');
    $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'crash-demo');
    $publisher->attachRecoveryContext('crash-demo', [
        'file_snapshot' => ['plugin_code' => 'demo', 'records' => [['target_path' => 'plugin-assets/demo/a.js']], 'files' => ['plugin-assets/demo/a.js' => 'old']],
        'resource_state' => ['permissions' => [['code' => 'demo:view']], 'menus' => [['href' => '/demo']]],
    ]);
    $restarted = new PluginAppPublicationService($app, $runtime, $repository);
    $stale = $restarted->stale('demo');
    appPublicationExpect(count($stale) === 1 && ($stale[0]['token'] ?? '') === 'crash-demo', 'stale 必须列出插件未完成 journal');
    appPublicationExpect(
        ($stale[0]['recovery_context']['file_snapshot']['plugin_code'] ?? '') === 'demo',
        'journal 必须持久保存 public/admin-web 与菜单权限恢复上下文'
    );
    $restarted->recover('crash-demo');
    appPublicationExpect(str_contains((string) file_get_contents($app . '/demo/service/Domain.php'), 'VERSION = 1'), 'recover 必须回滚未 completed 的 crash 状态');
    appPublicationExpect(($restarted->inspect('crash-demo')['state'] ?? '') === 'completed', 'recover 完成后 journal 必须 closed');
    appPublicationExpect($restarted->stale('demo') === [], 'recover 后 stale 列表必须清空');

    $publisher->remove('demo', 'remove-demo');
    appPublicationExpect(!is_dir($app . '/demo'), 'remove 必须移走独立应用 publication unit');
    $publisher->rollback('remove-demo');
    appPublicationExpect(is_file($app . '/demo/service/Domain.php'), 'remove rollback 必须恢复原生 App');
    appPublicationExpect($repository->records !== [], 'remove rollback 必须恢复 registry');

    file_put_contents($plugins . '/demo/app/demo/service/Domain.php', '<?php namespace app\\demo\\service; final class Domain { public const VERSION = 5; }');
    $publisher->publish(Manifest::fromDirectory($plugins . '/demo'), 'manual-demo');
    $publisher->attachRecoveryContext('manual-demo', ['file_snapshot' => ['plugin_code' => 'demo'], 'resource_state' => []]);
    $recoveryPath = $publisher->requireManualRecovery('manual-demo', ['migration_started' => true]);
    $manualJournal = $publisher->inspect('manual-demo');
    appPublicationExpect(($manualJournal['state'] ?? '') === 'rollback_required', 'migration 后失败必须标记 rollback_required');
    appPublicationExpect(($manualJournal['manual_recovery'] ?? false) === true, 'migration 后失败必须标记 manual recovery');
    appPublicationExpect(($manualJournal['recovery_path'] ?? '') === $recoveryPath && is_dir($recoveryPath), 'migration 后失败必须保留 durable recovery_path');
    appPublicationExpect(is_file($recoveryPath . '/application-demo/service/Domain.php'), 'durable recovery 必须保存旧 native App backup');
    appPublicationReject(static fn () => $publisher->recover('manual-demo'), '人工恢复');
    $publisher->recover('manual-demo', true);
    appPublicationExpect(str_contains((string) file_get_contents($app . '/demo/service/Domain.php'), 'VERSION = 1'), '显式人工恢复必须可执行');

    foreach (['before_manual_journal', 'after_manual_journal', 'after_manual_unlink'] as $faultPoint) {
        $code = str_replace('_', '', $faultPoint);
        $manualBoundary = appPublicationPlugin($plugins, $code);
        $publisher->publish($manualBoundary, $faultPoint . '-initial');
        $publisher->complete($faultPoint . '-initial');
        file_put_contents(
            $plugins . '/' . $code . '/app/' . $code . '/service/Domain.php',
            '<?php namespace app\\' . $code . '\\service; final class Domain { public const VERSION = 2; }'
        );
        $publisher->publish(Manifest::fromDirectory($plugins . '/' . $code), $faultPoint);
        $faultingPublisher = new PluginAppPublicationService(
            $app,
            $runtime,
            $repository,
            static function (string $point, int $index) use ($faultPoint): void {
                if ($point === $faultPoint && $index === 0) {
                    throw new RuntimeException('simulated ' . $faultPoint);
                }
            }
        );
        appPublicationReject(
            static fn () => $faultingPublisher->requireManualRecovery($faultPoint, ['migration_started' => true]),
            'simulated ' . $faultPoint
        );
        $interrupted = (new PluginAppPublicationService($app, $runtime, $repository))->inspect($faultPoint);
        $unit = (array) ($interrupted['units'][0] ?? []);
        $oldBackup = dirname((string) ($unit['target'] ?? '')) . '/.publication-backup-' . $faultPoint
            . '-' . basename((string) ($unit['target'] ?? ''));
        $durable = (string) realpath($runtime) . '/publication-recovery/' . $faultPoint . '/' . str_replace(':', '-', (string) ($unit['unit'] ?? ''));
        appPublicationExpect(is_dir($durable), $faultPoint . ' 中断时 durable 副本必须已通过 tree hash 校验并保留');
        if ($faultPoint === 'before_manual_journal') {
            appPublicationExpect(is_dir($oldBackup), 'journal 更新前中断必须同时保留旧 backup');
            appPublicationExpect(($unit['backup'] ?? '') === $oldBackup, 'journal 更新前不得提前切换 backup 所有权');
        } elseif ($faultPoint === 'after_manual_journal') {
            appPublicationExpect(is_dir($oldBackup), 'journal 更新后、unlink 前中断允许双份 backup');
            appPublicationExpect(($unit['backup'] ?? '') === $durable, '删除旧 backup 前必须先原子更新 unit journal');
        } else {
            appPublicationExpect(!is_dir($oldBackup), 'unlink 后中断旧同父 backup 应已删除');
            appPublicationExpect(($unit['manual_backup_cleaned'] ?? false) === true, 'unlink 后必须逐 unit checkpoint');
        }
        $restartedManual = new PluginAppPublicationService($app, $runtime, $repository);
        $restartedManual->requireManualRecovery($faultPoint, ['migration_started' => true]);
        $restartedManual->recover($faultPoint, true);
        appPublicationExpect(is_file($app . '/' . $code . '/service/Domain.php'), $faultPoint . ' 新实例必须可从 durable backup 恢复');
    }

    $nativeWal = appPublicationPlugin($plugins, 'nativewal', ['controller', 'service']);
    $nativeWalPublisher = new PluginAppPublicationService(
        $app,
        $runtime,
        $repository,
        static function (string $point, int $index): void {
            if ($point === 'after_native_materialize' && $index === 0) {
                throw new RuntimeException('simulated native materialize interruption');
            }
        }
    );
    $nativeWalPublisher->prepareOperation('native-materialize-crash', 'nativewal', 'publish', true);
    appPublicationReject(
        static fn () => $nativeWalPublisher->applyPrepared($nativeWal, 'native-materialize-crash', true, false),
        'native materialize interruption'
    );
    $nativeWalJournal = (new PluginAppPublicationService($app, $runtime, $repository))->inspect('native-materialize-crash');
    appPublicationExpect(count((array) ($nativeWalJournal['units'] ?? [])) === 3, '复制首个 native temp 前 journal 必须预登记全部 unit');
    appPublicationExpect(($nativeWalJournal['units'][0]['materialization_state'] ?? '') === 'materialized', 'native temp 每项完成后必须 checkpoint');
    appPublicationExpect(($nativeWalJournal['units'][1]['materialization_state'] ?? '') === 'planned', '未复制 native temp 必须保持 planned');
    $restartedNativeWal = new PluginAppPublicationService($app, $runtime, $repository);
    $restartedNativeWal->recover('native-materialize-crash');
    appPublicationExpect(($restartedNativeWal->inspect('native-materialize-crash')['state'] ?? '') === 'completed', 'native materialize 中断后新实例必须按 journal 清理');
    foreach ((array) ($nativeWalJournal['units'] ?? []) as $unit) {
        appPublicationExpect(!is_dir((string) ($unit['temp'] ?? '')), '恢复必须清理预登记 native temp');
    }

    $finalizing = appPublicationPlugin($plugins, 'finalizing');
    $publisher->publish($finalizing, 'finalizing-recover');
    $publisher->beginFinalization('finalizing-recover');
    $finalizingJournal = $publisher->inspect('finalizing-recover');
    appPublicationExpect(($finalizingJournal['state'] ?? '') === 'finalizing', '提交决定必须保留可恢复 finalizing 状态');
    $restartedFinalizing = new PluginAppPublicationService($app, $runtime, $repository);
    $restartedFinalizing->recover('finalizing-recover');
    appPublicationExpect(is_file($app . '/finalizing/service/Domain.php'), 'App recover 遇到 finalizing 不得 rollback 已提交目标');
    appPublicationExpect(($restartedFinalizing->inspect('finalizing-recover')['state'] ?? '') === 'completed', 'App recover 必须继续 finalizing cleanup 到 completed');

    foreach (['before_finalization_temp_cleanup', 'before_finalization_backup_cleanup', 'before_finalization_shared_cleanup'] as $finalizeFault) {
        $code = str_replace('_', '', $finalizeFault);
        $manifest = appPublicationPlugin($plugins, $code);
        $publisher->publish($manifest, $finalizeFault . '-initial');
        $publisher->complete($finalizeFault . '-initial');
        file_put_contents(
            $plugins . '/' . $code . '/app/' . $code . '/service/Domain.php',
            '<?php namespace app\\' . $code . '\\service; final class Domain { public const VERSION = 2; }'
        );
        $publisher->publish(Manifest::fromDirectory($plugins . '/' . $code), $finalizeFault);
        $faultingFinalizer = new PluginAppPublicationService(
            $app,
            $runtime,
            $repository,
            static function (string $point) use ($finalizeFault): void {
                if ($point === $finalizeFault) {
                    throw new RuntimeException('simulated ' . $finalizeFault);
                }
            }
        );
        appPublicationReject(static fn () => $faultingFinalizer->complete($finalizeFault), 'simulated ' . $finalizeFault);
        $crashedFinalization = $faultingFinalizer->inspect($finalizeFault);
        appPublicationExpect(($crashedFinalization['commit_decided'] ?? false) === true, $finalizeFault . ' 前必须已有提交决定');
        $resumedFinalization = new PluginAppPublicationService($app, $runtime, $repository);
        $resumedFinalization->recover($finalizeFault);
        appPublicationExpect(
            str_contains((string) file_get_contents($app . '/' . $code . '/service/Domain.php'), 'VERSION = 2'),
            $finalizeFault . ' 新实例恢复不得 rollback'
        );
        appPublicationExpect(($resumedFinalization->inspect($finalizeFault)['state'] ?? '') === 'completed', $finalizeFault . ' 重试完成后才 completed');
    }

    $publisher->prepareOperation('empty-context', 'demo', 'publish');
    $emptyContextCalled = false;
    $publisher->recover('empty-context', false, static function (array $context) use (&$emptyContextCalled): void {
        $emptyContextCalled = true;
        appPublicationExpect($context === [], '空 snapshot 必须保持为空恢复上下文');
    });
    appPublicationExpect($emptyContextCalled, 'operation journal 恢复必须允许空文件上下文');

    $preMigration = appPublicationPlugin($plugins, 'premigration');
    $publisher->prepareOperation('pre-migration-crash', 'premigration', 'publish', true);
    $repository->failNextReplace = true;
    appPublicationReject(
        static fn () => $publisher->applyPrepared($preMigration, 'pre-migration-crash', true, false),
        'registry write failed'
    );
    $preMigrationJournal = $publisher->inspect('pre-migration-crash');
    appPublicationExpect(
        ($preMigrationJournal['deployment_rollback_allowed'] ?? null) === true,
        '协调 apply 的 local rollback false 不得覆盖 migration 前持久部署回滚资格'
    );
    $preMigrationStale = $publisher->stale('premigration');
    appPublicationExpect(
        count($preMigrationStale) === 1 && ($preMigrationStale[0]['token'] ?? '') === 'pre-migration-crash',
        'migration 前协调发布中断必须进入 stale 列表'
    );
    foreach ($preMigrationStale as $staleJournal) {
        $publisher->recover((string) $staleJournal['token']);
    }
    appPublicationExpect(
        ($publisher->inspect('pre-migration-crash')['state'] ?? '') === 'completed',
        'migration 前协调发布中断必须允许 all-stale 自动恢复'
    );

    $postMigration = appPublicationPlugin($plugins, 'postmigration');
    $publisher->prepareOperation('post-migration-crash', 'postmigration', 'publish', false);
    $repository->failNextReplace = true;
    appPublicationReject(
        static fn () => $publisher->applyPrepared($postMigration, 'post-migration-crash', true, false),
        'registry write failed'
    );
    $postMigrationJournal = $publisher->inspect('post-migration-crash');
    appPublicationExpect(
        ($postMigrationJournal['deployment_rollback_allowed'] ?? null) === false,
        'migration 后 journal 必须保持禁止自动部署回滚'
    );
    appPublicationReject(static fn () => $publisher->recover('post-migration-crash'), '人工恢复');
    $publisher->recover('post-migration-crash', true);

    echo "plugin app publication service tests: PASS\n";
} finally {
    appPublicationRemoveTree($root);
}
