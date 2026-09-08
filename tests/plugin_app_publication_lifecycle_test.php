<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/console/service/PluginService.php');
$support = (string) file_get_contents($root . '/app/console/service/concern/PluginServiceSupport.php');
$infrastructure = (string) file_get_contents($root . '/app/console/service/PluginInfrastructureService.php');
$resourcePublisher = (string) file_get_contents($root . '/app/console/service/PluginResourcePublisher.php');
$databaseRepository = (string) file_get_contents($root . '/app/console/service/DatabasePluginResourceRepository.php');
$migration = $root . '/database/migrations/065_plugin_app_publication.sql';
$registryNamingMigration = $root . '/database/migrations/068_plugin_publication_registry_naming.sql';

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(str_contains($infrastructure, 'function appPublisher()'), '基础设施必须提供独立原生 App publisher');
$expect(str_contains($infrastructure, 'PluginAppPublicationService'), '基础设施必须构造原生 App publication service');
$expect(str_contains($infrastructure, 'withPublicationLock'), 'Infrastructure 必须以同一全局锁协调 native 与 public publisher');
$expect(str_contains($infrastructure, 'snapshotResourceState') && str_contains($infrastructure, 'restoreResourceState'), '基础设施必须提供菜单权限 snapshot 补偿');
$expect(substr_count($service, 'publishPluginResources(') >= 3, 'install/update/enable 必须统一发布 public/admin-web 与原生 App');
$expect(str_contains($service, 'removePluginResources('), 'uninstall 必须删除 public/admin-web 与原生 App');
$expect(str_contains($service, 'completeAppPublication('), '整个生命周期成功后才允许完成并清理 publication 备份');
$expect(str_contains($service, 'rollbackAppPublication('), 'migration 前生命周期失败必须回滚原生 App publication');
$expect(str_contains($service, 'preservePublicationRecovery('), 'migration 后失败必须保留 publication recovery，禁止回滚旧资源');
$expect(str_contains($service, 'if ($this->deploymentRollbackAllowed)'), 'operate 必须以统一 deploymentRollbackAllowed 控制所有部署回滚');
$refreshPosition = strpos($service, 'refreshLifecycleCaches();');
$completePosition = strpos($service, 'completeAppPublication($code);');
$successPosition = strpos($service, '$success = true;');
$expect($refreshPosition !== false && $completePosition !== false && $successPosition !== false
    && $refreshPosition < $completePosition && $completePosition < $successPosition,
    'cache 失败不得 complete，且 complete 后才可确认整个操作成功');
$expect(str_contains($service, 'if ($migrate) {') && substr_count($service, '$this->deploymentRollbackAllowed = false;') === 3,
    '仅真正开始 install/update/migrate migration 时才允许关闭部署回滚');
$expect(!str_contains($service, 'disablePluginResources('), 'disable 不得删除原生 App publication unit');
$expect(str_contains($support, '$this->resourcePublicationSnapshots'), 'public/admin-web snapshot 必须进入生命周期上下文');
$expect(str_contains($support, 'rollbackResourcePublication'), 'public/admin-web 必须支持生命周期补偿');
$expect(str_contains($infrastructure, 'rollbackPublishedResources'), 'Infrastructure 必须提供统一发布补偿入口');
$expect(str_contains($support, 'rollbackPublishedResources('), '生命周期补偿不得绕过 Infrastructure 统一锁入口');
$expect(str_contains($infrastructure, '$journal = $appPublisher->inspect($token)'), '异常补偿必须从 durable journal 读取恢复上下文');
$expect(!str_contains($support, '$this->resourcePublicationSnapshots[$code] ?? null'), '生命周期异常补偿不得依赖进程内文件 snapshot');
$expect(!str_contains($support, '$this->resourceStateSnapshots[$code] ?? null'), '生命周期异常补偿不得依赖进程内资源状态 snapshot');
$expect(!str_contains($support, '->publisher()->rollback('), 'PluginServiceSupport 不得在共享 publication lock 外直接回滚 public 资源');
$rollbackMethod = substr($infrastructure, (int) strpos($infrastructure, 'function rollbackPublishedResources'));
$rollbackMethod = substr($rollbackMethod, 0, (int) strpos($rollbackMethod, 'public function recoverPublication'));
$expect(str_contains($rollbackMethod, 'withPublicationLock'), '统一发布补偿必须整段持有共享 publication lock');
$expect(str_contains($rollbackMethod, 'coordinateRecovery('), '生命周期回滚必须委托统一幂等 recovery coordinator');
$recoverMethod = substr($infrastructure, (int) strpos($infrastructure, 'function recoverPublication'));
$recoverMethod = substr($recoverMethod, 0, (int) strpos($recoverMethod, 'public function recoverPublicationContext'));
$expect(str_contains($recoverMethod, 'coordinateRecovery('), '命令恢复必须委托同一幂等 recovery coordinator');
$coordinator = substr($infrastructure, (int) strpos($infrastructure, 'function coordinateRecovery'));
$fileRollback = strpos($coordinator, 'rollbackPrepared($filePlan, false)');
$fileCleanup = strpos($coordinator, '->complete($filePlan)');
$nativeRollback = strpos($coordinator, 'restoreNativeFiles($token)');
$stateRollback = strpos($coordinator, 'restoreResourceState');
$appCleanup = strpos($coordinator, 'cleanupRecoveryArtifacts($token)');
$expect($fileRollback !== false && $fileCleanup !== false && $nativeRollback !== false
    && $stateRollback !== false && $appCleanup !== false
    && $fileRollback < $fileCleanup && $fileCleanup < $nativeRollback
    && $nativeRollback < $stateRollback && $stateRollback < $appCleanup,
    '统一 coordinator 必须按文件恢复清理、native 恢复、registry 恢复、native 清理顺序推进');
foreach (['files_restored', 'files_cleaned', 'native_restored', 'registry_restored', 'artifacts_cleaned'] as $step) {
    $expect(str_contains($coordinator, "markRecoveryStep(\$token, '" . $step . "')"), 'coordinator 缺少阶段 checkpoint：' . $step);
}
$expect(str_contains($infrastructure, 'restoreResourceState'), '菜单权限必须支持已知状态恢复');
$expect(str_contains($support, 'assertNoStalePublication'), '生命周期开始前必须阻断同插件未完成 journal');
$expect(str_contains($infrastructure, 'planPublish($manifest, $token)'), 'Infrastructure 必须在写 journal 前取得无副作用 public/admin-web 计划');
$expect(str_contains($infrastructure, 'planRemove($code, $token)'), 'Infrastructure 必须在写 journal 前取得无副作用删除计划');
$planPosition = strpos($infrastructure, 'planPublish($manifest, $token)');
$journalPosition = strpos($infrastructure, 'prepareOperation($token');
$materializePosition = strpos($infrastructure, '->materialize($filePlan');
$applyPosition = strpos($infrastructure, '->apply($filePlan');
$expect($planPosition !== false && $journalPosition !== false && $materializePosition !== false && $applyPosition !== false
    && $planPosition < $journalPosition && $journalPosition < $materializePosition && $materializePosition < $applyPosition,
    'Infrastructure 必须先纯 plan、再写 App journal、逐项 materialize，最后 apply');
$expect(str_contains($infrastructure, 'prepareOperation('), '无 native unit 时也必须先创建 operation journal');
$expect(!str_contains($support, 'attachRecoveryContext'), 'lifecycle 不得在 publisher 返回后才 attach context');
$expect(str_contains($support, 'recoverPublicationContext'), '恢复命令必须能恢复 public/admin-web 与菜单权限');
$expect(str_contains($infrastructure, 'recoverPublication('), '恢复命令必须通过 Infrastructure 恢复 native、public/admin-web 与菜单权限');
$recoverMethod = substr($infrastructure, (int) strpos($infrastructure, 'function recoverPublication'));
$recoverMethod = substr($recoverMethod, 0, (int) strpos($recoverMethod, 'public function recoverPublicationContext'));
$expect(str_contains($recoverMethod, 'withPublicationLock'), '恢复命令的统一补偿必须持有同一 publication lock');
$expect(!str_contains($recoverMethod, '->recover('), '恢复命令不得由 native recover 抢先恢复或清理共享材料');
$recoveryInfrastructure = new \app\console\service\PluginInfrastructureService();
$recoveryInfrastructure->recoverPublicationContext(['plugin_code' => 'demo', 'file_snapshot' => []]);
$recoveryInfrastructure->recoverPublicationContext(['plugin_code' => 'demo', 'file_snapshot' => ['plugin_code' => 'demo']]);
$recoveryInfrastructure->recoverPublicationContext([
    'plugin_code' => 'demo',
    'file_snapshot' => [
        'plugin_code' => 'other',
        'recovery_token' => 'mismatch',
        'records' => [],
        'files' => [],
        'rebuildRequired' => true,
    ],
]);
$expect(str_contains($support, 'publishResources($manifest, $token)'), '恢复已发布资源必须包含 public/admin-web 与原生 App');
$expect(str_contains($support, 'completePublishedResources('), '生命周期成功必须通过 Infrastructure 统一清理恢复材料');
$expect(!str_contains($support, 'appPublisher()->complete('), '生命周期不得绕过 Infrastructure 单独完成 native publication');
$expect(str_contains($resourcePublisher, "'resource_type' => 'file'"), '现有 public/admin-web registry 必须显式标记 file 根类型');
$expect(str_contains($databaseRepository, "where('resource_type', 'file')"), '现有 publisher 替换 registry 时不得删除 native_app 记录');
$expect(is_file($migration), '必须新增 065 migration，且不得修改历史 migration');
$sql = is_file($migration) ? (string) file_get_contents($migration) : '';
foreach (['resource_type', 'publication_unit', 'operation_token', 'tree_hash'] as $column) {
    $expect(str_contains($sql, '`' . $column . '`'), '065 migration 缺少字段：' . $column);
}
$expect(str_contains($sql, 'information_schema.COLUMNS'), '065 migration 必须可幂等执行');
$expect(str_contains($sql, 'information_schema.STATISTICS'), '065 migration 索引创建必须可幂等执行');
$expect(str_contains($databaseRepository, "where('resource_type', 'file')"), 'file Repository 只能替换 file 记录');
$databaseAppRepository = (string) file_get_contents($root . '/app/console/service/DatabasePluginAppPublicationRepository.php');
$expect(str_contains($databaseAppRepository, "where('resource_type', 'native_app')"), 'native Repository 只能替换 native_app 记录');
$expect(is_file($registryNamingMigration), '必须新增 068 forward migration 收敛 registry 命名');
$registryNamingSql = is_file($registryNamingMigration) ? (string) file_get_contents($registryNamingMigration) : '';
foreach (['public:', 'application:', 'console-plugin:'] as $prefix) {
    $expect(str_contains($registryNamingSql, $prefix), '068 migration 缺少命名契约：' . $prefix);
}
$console = require $root . '/config/console.php';
$expect(isset($console['commands']['plugin:publication-recover']), '必须注册 plugin:publication-recover 恢复命令');

$allMigrations = glob($root . '/database/migrations/*.sql') ?: [];
$numbers = array_map(static fn (string $file): int => (int) basename($file), $allMigrations);
sort($numbers, SORT_NUMERIC);
$expect(in_array(65, $numbers, true), '原生 App publication migration 必须保持编号 065 且不得改写后续 migration 边界');

echo "plugin app publication lifecycle tests: PASS\n";
