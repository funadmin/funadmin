<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function coreGapExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
$numbers = [];
foreach ($migrationFiles as $file) {
    $name = pathinfo($file, PATHINFO_FILENAME);
    if (preg_match('/^(\d+)_/', $name, $matches)) {
        $numbers[$matches[1]][] = $name;
    }
}

foreach ($numbers as $number => $names) {
    coreGapExpect(count($names) === 1, 'migration 数字版本重复：' . $number);
}
coreGapExpect(is_file($root . '/database/migrations/021_time_columns_no_default.sql'), 'time-columns migration 必须使用唯一版本 021');
coreGapExpect(!is_file($root . '/database/migrations/020_time_columns_no_default.sql'), '不得保留 020 重号 migration');
coreGapExpect(!is_file($root . '/database/migrations/023_time_columns_no_default.sql'), '不得重复保留 time-columns migration');

$adoptionFiles = glob($root . '/database/migrations/*plugin_adoption*.sql') ?: [];
coreGapExpect(count($adoptionFiles) === 1, '必须提供唯一的旧 fun_addon 安全 adoption 补偿 migration');
$adoption = (string) file_get_contents($adoptionFiles[0]);
foreach (['needs_reinstall', 'lifecycle_state', "''disabled''", '`status` = 0', '@legacy_table_name', '@plugin_table_name'] as $required) {
    coreGapExpect(str_contains($adoption, $required), 'adoption migration 缺少安全约束：' . $required);
}
coreGapExpect(!preg_match('/RENAME\s+TABLE/i', $adoption), 'adoption 补偿不得重命名或执行旧插件代码');
coreGapExpect(!str_contains($adoption, 'legacy.`status`'), '不得采信旧插件运行状态');
coreGapExpect(str_contains($adoption, '`manifest` IS NULL'), '007 已重命名 fun_addon 时，adoption 必须将无新 manifest 的旧记录标记为需重装');

$lifecycleFiles = glob($root . '/database/migrations/*plugin_lifecycle_capabilities.sql') ?: [];
coreGapExpect(count($lifecycleFiles) === 1, '必须提供唯一 lifecycle capabilities 扩展 migration');
$lifecycle = (string) file_get_contents($lifecycleFiles[0]);
foreach (['package_hash', 'code_version', 'source', 'error_stage', 'recovery_path', 'needs_reinstall', 'stage', 'progress', 'max_db_version'] as $field) {
    coreGapExpect(str_contains($lifecycle, $field), 'lifecycle migration 缺少字段：' . $field);
}
coreGapExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', $lifecycle), 'lifecycle migration 必须只向前');

echo "plugin core gaps tests: PASS\n";
