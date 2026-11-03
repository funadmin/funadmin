<?php

declare(strict_types=1);

// 只读运维盘点，不执行 migration、不创建表、不写配置或安装标记。
require dirname(__DIR__, 2) . '/vendor/autoload.php';
Dotenv\Dotenv::createUnsafeImmutable(base_path())->safeLoad();
Webman\Config::load(config_path(), ['route']);
app\common\service\install\InstallSupport::activateInstalledConfiguration();
$connection = support\Db::connection();
if ($connection->getDriverName() !== 'mysql') throw new RuntimeException('只支持 MySQL 核心结构审计');
$connection->statement("SET time_zone = '+00:00'");
$connection->statement('SET TRANSACTION READ ONLY');
$connection->beginTransaction();
try {
    $audit = new app\common\service\database\CoreDatabaseAudit(app\common\service\database\MigrationService::coreDirectory());
    $report = $audit->inspect($connection);
    foreach (array_slice($argv, 1) as $argument) {
        if (!str_starts_with($argument, '--reference=')) continue;
        $path = substr($argument, strlen('--reference='));
        $private = realpath(base_path('runtime/private'));
        if ($private === false || realpath(dirname($path)) !== $private || is_link($path) || !is_file($path)) throw new RuntimeException('规范结构报告必须来自私有 runtime');
        $reference = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($reference['audit_version'] ?? 0) !== ($report['audit_version'] ?? null)) throw new RuntimeException('规范结构报告审计格式已过期，必须重新回放');
        if (($reference['source_checksums'] ?? null) !== $report['source_checksums']) throw new RuntimeException('规范结构报告的核心 SQL 源已变化，必须重新回放');
        $report['reference_difference'] = app\common\service\database\CoreDatabaseAudit::compare($reference, $report);
        if (in_array('--plan', $argv, true)) {
            $report['schema_plan'] = app\common\service\database\CoreSchemaPlan::build($reference, $report);
            if (!isset($reference['seed_rows'])) throw new RuntimeException('规范报告缺少根 SQL 回放种子，必须重新生成');
            $seedRows = app\common\service\database\CoreSeedPlan::readSnapshot($connection, $report['schema']);
            $seedPlan = app\common\service\database\CoreSeedPlan::build($reference['seed_rows'], $seedRows);
            $adoption = app\common\service\database\CoreAdoptionPlan::build($reference, $report, $seedRows);
            $report['adoption_plan_summary'] = ['digest' => $adoption['digest'], 'mode' => $adoption['mode'], 'impact' => $adoption['impact'], 'blocker_count' => count($adoption['blockers']), 'required_gates' => $adoption['required_gates'], 'apply_allowed' => false];
            $report['menu_diagnostics'] = app\common\service\database\CoreMenuPlan::diagnose($seedRows);
            $menuPlan = app\common\service\database\CoreMenuPlan::build($reference['seed_rows'], $seedRows);
            $report['menu_plan_summary'] = ['digest' => $menuPlan['digest'], 'updates' => count($menuPlan['updates']), 'blockers' => $menuPlan['blockers'], 'apply_allowed' => false];
            $report['seed_plan_summary'] = [
                'digest' => $seedPlan['digest'], 'inserts' => count($seedPlan['inserts']), 'updates' => count($seedPlan['updates']),
                'blocker_count' => count($seedPlan['blockers']), 'blockers' => $seedPlan['blockers'],
                'apply_allowed' => false,
            ];
        }
    }
    if (in_array('--save-conflicts', $argv, true) && isset($report['schema_plan'])) {
        $private = realpath(base_path('runtime/private'));
        if ($private === false) throw new RuntimeException('私有审计目录不存在');
        $path = $private . '/core-conflicts-' . bin2hex(random_bytes(8)) . '.json';
        $handle = fopen($path, 'x');
        if ($handle === false) throw new RuntimeException('无法独占创建审计报告');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('无法限制审计报告权限');
            $json = json_encode($report['schema_plan']['blockers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) !== strlen($json)) throw new RuntimeException('审计报告写入不完整');
        } finally { fclose($handle); }
        echo json_encode(['database' => $report['database'], 'path' => $path, 'blockers' => count($report['schema_plan']['blockers']), 'apply_allowed' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
        return;
    }
    if (in_array('--conflicts', $argv, true) && isset($report['schema_plan'])) {
        $compact = [];
        foreach ($report['schema_plan']['blockers'] as $issue) {
            $compact[] = [
                'code' => $issue['code'], 'table' => $issue['table'], 'name' => $issue['name'],
                'expected' => $issue['difference']['expected'], 'actual' => $issue['difference']['actual'],
            ];
        }
        foreach ($compact as $issue) {
            if ($issue['code'] === 'COLUMN_REQUIRES_REVIEW') {
                foreach (['expected', 'actual'] as $side) {
                    $item = $issue[$side];
                    $issue[$side] = $item === null ? null : [$item['COLUMN_TYPE'], $item['IS_NULLABLE'], $item['COLUMN_DEFAULT'], $item['EXTRA'], $item['COLLATION_NAME']];
                }
            }
            echo json_encode($issue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        }
        return;
    }
    $report['digest'] = hash('sha256', json_encode(array_diff_key($report, ['digest' => true]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    if (in_array('--summary', $argv, true)) {
        $report['table_count'] = count($report['schema']);
        $report['missing_table_count'] = count($report['missing_tables']);
        $report['missing_column_count'] = array_sum(array_map('count', $report['missing_columns']));
        $report['type_difference_count'] = array_sum(array_map('count', $report['column_type_differences']));
        if (isset($report['reference_difference'])) {
            $difference = $report['reference_difference'];
            $report['reference_summary'] = [
                'missing_tables' => count($difference['missing_tables']),
                'columns' => array_sum(array_map('count', $difference['columns'])),
                'indexes' => array_sum(array_map('count', $difference['indexes'])),
                'constraints' => array_sum(array_map('count', $difference['constraints'])),
                'checks' => array_sum(array_map('count', $difference['checks'])),
                'extra_tables' => $difference['extra_tables'], 'aligned' => $difference['aligned'],
            ];
        }
        if (isset($report['schema_plan'])) {
            $report['schema_plan_summary'] = [
                'digest' => $report['schema_plan']['digest'], 'operations' => count($report['schema_plan']['operations']),
                'blocker_count' => count($report['schema_plan']['blockers']),
                'apply_allowed' => $report['schema_plan']['apply_allowed'],
            ];
            unset($report['schema_plan']);
        }
        unset($report['schema'], $report['menus'], $report['migration_history'], $report['column_type_differences'], $report['missing_columns'], $report['missing_index_names'], $report['reference_difference']);
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $connection->rollBack();
}
