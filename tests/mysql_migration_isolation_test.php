<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use think\App;

function migrationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function quoteIdentifier(string $identifier): string
{
    migrationExpect((bool) preg_match('/^[a-z0-9_]+$/', $identifier), '数据库标识符不安全');
    return '`' . $identifier . '`';
}

$app = new App();
$app->initialize();
$config = config('database.connections.mysql');
$sourceDatabase = (string) ($config['database'] ?? '');
$temporaryDatabase = 'funadmin_m2_test_' . bin2hex(random_bytes(6));

migrationExpect($sourceDatabase !== '', '项目数据库名不能为空');
migrationExpect($sourceDatabase !== $temporaryDatabase, '隔离测试库不得等于项目数据库');
migrationExpect(str_starts_with($temporaryDatabase, 'funadmin_m2_test_'), '隔离测试库名必须使用安全前缀');

$host = (string) ($config['hostname'] ?? '127.0.0.1');
$port = (string) ($config['hostport'] ?? '3306');
$charset = (string) ($config['charset'] ?? 'utf8mb4');
$username = (string) ($config['username'] ?? '');
$password = (string) ($config['password'] ?? '');
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];
$server = new PDO("mysql:host={$host};port={$port};charset={$charset}", $username, $password, $options);
$database = null;

try {
    $server->exec('CREATE DATABASE ' . quoteIdentifier($temporaryDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database = new PDO("mysql:host={$host};port={$port};dbname={$temporaryDatabase};charset={$charset}", $username, $password, $options);

    $directory = dirname(__DIR__) . '/database/migrations';
    $files = glob($directory . '/*.sql') ?: [];
    $service = new MigrationService();
    $versionSequence = new ReflectionMethod($service, 'assertVersionSequence');
    $versionSequence->setAccessible(true);
    $versionSequence->invoke($service, $files, 'core');
    $sortKey = new ReflectionMethod($service, 'migrationSortKey');
    $sortKey->setAccessible(true);
    usort($files, static fn (string $left, string $right): int => strcmp(
        $sortKey->invoke($service, $left),
        $sortKey->invoke($service, $right)
    ));
    $forwardOnly = new ReflectionMethod($service, 'assertForwardOnly');
    $forwardOnly->setAccessible(true);
    $statements = new ReflectionMethod($service, 'statements');
    $statements->setAccessible(true);

    foreach ($files as $file) {
        $sql = (string) file_get_contents($file);
        $forwardOnly->invoke($service, $sql, $file);
        if (basename($file) === '022_laravel_field_cutover.sql') {
            $database->exec("UPDATE `fun_admin_menu` SET `create_time`=1700000000, `update_time`=1700000060, `sort`=321 ORDER BY `id` LIMIT 1");
        }
        foreach ($statements->invoke($service, $sql) as $statement) {
            try {
                $result = $database->query($statement);
                if ($result !== false) {
                    $result->fetchAll();
                    $result->closeCursor();
                }
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    basename($file) . ' 执行失败：' . substr(preg_replace('/\s+/', ' ', $statement), 0, 240),
                    0,
                    $exception
                );
            }
        }
    }

    $fieldVerify = $database->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fun_field_verify' AND COLUMN_KEY = 'PRI' ORDER BY ORDINAL_POSITION")->fetchAll();
    migrationExpect($fieldVerify === [[
        'COLUMN_NAME' => 'id',
        'COLUMN_TYPE' => 'bigint unsigned',
        'IS_NULLABLE' => 'NO',
        'EXTRA' => 'auto_increment',
    ]], 'field_verify 必须使用 id bigint unsigned 自增单列主键');
    $verifyUnique = $database->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fun_field_verify' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'verify'")->fetchAll(PDO::FETCH_COLUMN);
    migrationExpect($verifyUnique !== [], 'field_verify.verify 必须保留唯一业务键');

    $menu = $database->query("SELECT create_time, update_time, sort, created_at, updated_at, sort_order FROM `fun_admin_menu` ORDER BY `id` LIMIT 1")->fetch();
    migrationExpect((int) $menu['create_time'] === 1700000000, 'legacy create_time 必须保留');
    migrationExpect((int) $menu['update_time'] === 1700000060, 'legacy update_time 必须保留');
    migrationExpect((int) $menu['sort'] === 321, 'legacy sort 必须保留');
    migrationExpect((string) $menu['created_at'] === '2023-11-14 22:13:20', 'created_at 回填必须与 Unix 秒一致');
    migrationExpect((string) $menu['updated_at'] === '2023-11-14 22:14:20', 'updated_at 回填必须与 Unix 秒一致');
    migrationExpect((int) $menu['sort_order'] === 321, 'sort_order 必须由 sort 回填');

    $requiredIndexes = [
        'fun_admin_menu' => ['idx_admin_menu_sort_order'],
        'fun_attach' => ['idx_attach_deleted_at', 'idx_attach_sort_order'],
        'fun_field_verify' => ['idx_field_verify_deleted_at'],
    ];
    foreach ($requiredIndexes as $table => $indexes) {
        $found = $database->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $database->quote($table))->fetchAll(PDO::FETCH_COLUMN);
        foreach ($indexes as $index) {
            migrationExpect(in_array($index, $found, true), "缺少索引 {$table}.{$index}");
        }
    }

    foreach (['022_laravel_field_cutover.sql', '030_laravel_schema_convergence.sql'] as $rerunnableMigration) {
        $rerunnable = (string) file_get_contents($directory . '/' . $rerunnableMigration);
        foreach ($statements->invoke($service, $rerunnable) as $statement) {
            $database->exec($statement);
        }
    }
    $menuAfterRerun = $database->query("SELECT create_time, update_time, sort, created_at, updated_at, sort_order FROM `fun_admin_menu` ORDER BY `id` LIMIT 1")->fetch();
    migrationExpect($menuAfterRerun === $menu, 'Laravel migration 重复执行必须保持回填结果稳定');
    migrationExpect((int) $database->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('create_time','update_time','delete_time','sort')")->fetchColumn() > 0, '本阶段必须保留 legacy 公共字段');

    echo "mysql migration isolation test passed; temporary database cleaned\n";
} finally {
    $database = null;
    if (str_starts_with($temporaryDatabase, 'funadmin_m2_test_')) {
        $server->exec('DROP DATABASE IF EXISTS ' . quoteIdentifier($temporaryDatabase));
    }
}
