<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use think\App;
use think\facade\Db;

function phase4MigrationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase4MigrationQuote(string $identifier): string
{
    phase4MigrationExpect(preg_match('/^[a-z0-9_]+$/', $identifier) === 1, '数据库名非法');
    return '`' . $identifier . '`';
}

function phase4MigrationDirectory(string $source): string
{
    $target = sys_get_temp_dir() . '/funadmin_ai_phase4_migrations_' . bin2hex(random_bytes(5));
    phase4MigrationExpect(mkdir($target, 0700), '无法创建隔离 migration 目录');
    foreach (['109_ai_phase4_change_sets.sql', '110_ai_phase4_preview_permission.sql'] as $name) {
        phase4MigrationExpect(copy($source . '/' . $name, $target . '/' . $name), '无法复制 migration：' . $name);
    }
    return $target;
}

function phase4MigrationBaseSchema(): void
{
    Db::execute("CREATE TABLE fun_system_migration (id bigint unsigned NOT NULL AUTO_INCREMENT, scope varchar(100) NOT NULL, version varchar(190) NOT NULL, checksum char(64) NOT NULL, executed_at int unsigned NOT NULL, PRIMARY KEY(id), UNIQUE KEY uk_scope_version(scope,version)) ENGINE=InnoDB");
    Db::execute("CREATE TABLE fun_ai_change_set (id bigint unsigned NOT NULL AUTO_INCREMENT, created_by bigint unsigned NOT NULL, conversation_id bigint unsigned NOT NULL, status varchar(32) NOT NULL DEFAULT 'proposed', digest char(64) NOT NULL DEFAULT '', base_digest char(64) NOT NULL DEFAULT '', base_file_hashes json NOT NULL, recovery_status varchar(32) NOT NULL DEFAULT 'none', PRIMARY KEY(id)) ENGINE=InnoDB");
    Db::execute("CREATE TABLE fun_permission (id bigint unsigned NOT NULL AUTO_INCREMENT, pid bigint unsigned NOT NULL DEFAULT 0, app_name varchar(50) NOT NULL DEFAULT 'console', code varchar(255) NULL, obj varchar(190) NOT NULL DEFAULT '', act varchar(100) NOT NULL DEFAULT '', name varchar(100) NOT NULL DEFAULT '', resource_type varchar(20) NOT NULL DEFAULT 'route', status tinyint NOT NULL DEFAULT 1, is_public tinyint NOT NULL DEFAULT 0, source_type varchar(20) NOT NULL DEFAULT 'system', source_name varchar(100) NOT NULL DEFAULT '', created_at datetime NULL, updated_at datetime NULL, sort_order int NOT NULL DEFAULT 999, deleted_at datetime NULL, PRIMARY KEY(id), UNIQUE KEY uk_permission_code(code)) ENGINE=InnoDB");
    Db::execute("CREATE TABLE fun_admin_menu (id bigint unsigned NOT NULL AUTO_INCREMENT, source_type varchar(20) NOT NULL, source_name varchar(100) NOT NULL, href varchar(255) NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB");
    Db::execute("INSERT INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order) VALUES (0,'console',NULL,'','','AI 开发','group',1,0,'admin_web','ai_development',90)");
    Db::execute("INSERT INTO fun_admin_menu (source_type,source_name,href) VALUES ('admin_web','ai_development','/development/ai')");
}

function phase4MigrationAddCompleteSchema(): void
{
    Db::execute("ALTER TABLE fun_ai_change_set ADD COLUMN plan_digest char(64) NOT NULL DEFAULT '' AFTER base_digest, ADD COLUMN selection json NULL AFTER base_file_hashes, ADD COLUMN recovery_version int unsigned NOT NULL DEFAULT 0 AFTER recovery_status, ADD KEY idx_ai_change_set_owner_status(created_by,conversation_id,status,id)");
}

function phase4MigrationAssertCanonical(string $scenario): void
{
    $columns = Db::query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND COLUMN_NAME IN ('plan_digest','selection','recovery_version')");
    $byName = [];
    foreach ($columns as $column) {
        $byName[$column['COLUMN_NAME']] = $column;
    }
    phase4MigrationExpect(strtolower((string) ($byName['plan_digest']['COLUMN_TYPE'] ?? '')) === 'char(64)' && ($byName['plan_digest']['IS_NULLABLE'] ?? '') === 'NO' && ($byName['plan_digest']['COLUMN_DEFAULT'] ?? null) === '', $scenario . ' plan_digest 未收敛');
    phase4MigrationExpect(strtolower((string) ($byName['selection']['COLUMN_TYPE'] ?? '')) === 'json' && ($byName['selection']['IS_NULLABLE'] ?? '') === 'YES', $scenario . ' selection 未收敛');
    phase4MigrationExpect(strtolower((string) ($byName['recovery_version']['COLUMN_TYPE'] ?? '')) === 'int unsigned' && ($byName['recovery_version']['IS_NULLABLE'] ?? '') === 'NO' && (string) ($byName['recovery_version']['COLUMN_DEFAULT'] ?? '') === '0', $scenario . ' recovery_version 未收敛');

    $index = array_column(Db::query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='idx_ai_change_set_owner_status' ORDER BY SEQ_IN_INDEX"), 'COLUMN_NAME');
    phase4MigrationExpect($index === ['created_by', 'conversation_id', 'status', 'id'], $scenario . ' owner/status 索引未收敛');

    $expected = [
        'console/development.ai:changesetpreview' => ['changesetpreview', 50],
        'console/development.ai:changesetapply' => ['changesetapply', 51],
        'console/development.ai:changesetrecover' => ['changesetrecover', 52],
        'console/development.ai:crudproposalpreview' => ['crudproposalpreview', 53],
        'console/development.ai:crudproposalapply' => ['crudproposalapply', 54],
    ];
    $rows = Db::query("SELECT pid,app_name,code,obj,act,resource_type,status,is_public,source_type,source_name,sort_order,deleted_at FROM fun_permission WHERE code LIKE 'console/development.ai:%' AND code IN ('console/development.ai:changesetpreview','console/development.ai:changesetapply','console/development.ai:changesetrecover','console/development.ai:crudproposalpreview','console/development.ai:crudproposalapply')");
    phase4MigrationExpect(count($rows) === 5, $scenario . ' 权限数量不正确');
    $groupId = (int) Db::query("SELECT id FROM fun_permission WHERE source_type='admin_web' AND source_name='ai_development' AND resource_type='group'")[0]['id'];
    foreach ($rows as $row) {
        [$act, $sortOrder] = $expected[$row['code']];
        phase4MigrationExpect((int) $row['pid'] === $groupId && $row['app_name'] === 'console' && $row['obj'] === 'console/development.ai' && $row['act'] === $act && $row['resource_type'] === 'route' && (int) $row['status'] === 1 && (int) $row['is_public'] === 0 && $row['source_type'] === 'admin_web' && $row['source_name'] === 'ai_development' && (int) $row['sort_order'] === $sortOrder && $row['deleted_at'] === null, $scenario . ' 错误同 code 权限被静默保留：' . $row['code']);
    }
}

$root = dirname(__DIR__);
$host = getenv('AI_TEST_DB_HOST') ?: getenv('AI_PHASE4_DB_HOST');
if ($host === false || $host === '') {
    echo "AI phase 4 MySQL integration: SKIP (AI_TEST_DB_HOST not configured)\n";
    return;
}

$app = new App($root);
$app->initialize();
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, "AI phase 4 MySQL integration: FAIL ({$exception->getMessage()})\n");
    exit(1);
});
if (!extension_loaded('pdo_mysql')) {
    throw new RuntimeException('AI phase 4 MySQL integration: FAIL (pdo_mysql unavailable)');
}
$original = (array) config('database');
$mysql = $original['connections']['mysql'];
$mysql['hostname'] = $host;
$mysql['hostport'] = (string) (getenv('AI_TEST_DB_PORT') ?: getenv('AI_PHASE4_DB_PORT') ?: '3306');
$mysql['username'] = (string) (getenv('AI_TEST_DB_USER') ?: getenv('AI_PHASE4_DB_USER') ?: 'root');
$mysql['password'] = (string) (getenv('AI_TEST_DB_PASS') ?: getenv('AI_PHASE4_DB_PASS') ?: '');
$serverConfig = $original;
$serverConfig['connections']['mysql'] = $mysql;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
$migrations = phase4MigrationDirectory($root . '/database/migrations');
$databases = [];

try {
    foreach (['fresh', 'complete_without_record', 'partial', 'permission_menu_and_column_drift'] as $scenario) {
        $database = 'funadmin_ai_p4_' . $scenario . '_' . bin2hex(random_bytes(4));
        $databases[] = $database;
        $server->execute('CREATE DATABASE ' . phase4MigrationQuote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $isolated = $serverConfig;
        $isolated['connections']['mysql']['database'] = $database;
        $app->config->set($isolated, 'database');
        Db::connect('mysql', true);
        phase4MigrationBaseSchema();

        if ($scenario === 'complete_without_record') {
            phase4MigrationAddCompleteSchema();
            Db::execute("INSERT INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order) SELECT id,'console','console/development.ai:changesetpreview','console/development.ai','changesetpreview','预览变更集','route',1,0,'admin_web','ai_development',50 FROM fun_permission WHERE resource_type='group'");
        } elseif ($scenario === 'partial') {
            Db::execute("ALTER TABLE fun_ai_change_set ADD COLUMN plan_digest varchar(32) NULL AFTER base_digest, ADD COLUMN selection json NULL AFTER base_file_hashes");
            Db::execute("INSERT INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order) VALUES (0,'wrong','console/development.ai:changesetapply','wrong','wrong','漂移','route',0,1,'legacy','legacy',999)");
        } elseif ($scenario === 'permission_menu_and_column_drift') {
            Db::execute("ALTER TABLE fun_ai_change_set ADD COLUMN plan_digest varchar(255) NULL AFTER base_digest, ADD COLUMN selection text NULL AFTER base_file_hashes, ADD COLUMN recovery_version bigint NULL DEFAULT NULL AFTER recovery_status");
            foreach (['changesetpreview', 'changesetapply', 'changesetrecover', 'crudproposalpreview', 'crudproposalapply'] as $act) {
                Db::execute("INSERT INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order,deleted_at) VALUES (0,'api',?,'drifted',?,'漂移','group',0,1,'legacy','legacy',999,NOW())", ['console/development.ai:' . $act, 'wrong']);
            }
        }

        $migration = new MigrationService();
        $first = $migration->runDirectory($migrations, 'core');
        phase4MigrationExpect($first === ['109_ai_phase4_change_sets', '110_ai_phase4_preview_permission'], $scenario . ' 首次执行记录不正确');
        phase4MigrationExpect($migration->runDirectory($migrations, 'core') === [], $scenario . ' 第二次执行必须幂等跳过');
        phase4MigrationAssertCanonical($scenario);
    }
    echo "AI phase 4 migration reentrancy tests: PASS\n";
} finally {
    $app->config->set($serverConfig, 'database');
    $cleanup = Db::connect('mysql', true);
    foreach ($databases as $database) {
        $cleanup->execute('DROP DATABASE IF EXISTS ' . phase4MigrationQuote($database));
    }
    foreach (glob($migrations . '/*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_dir($migrations)) rmdir($migrations);
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
