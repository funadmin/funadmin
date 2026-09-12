<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use think\App;
use think\facade\Db;

function phase4MysqlExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase4MysqlQuote(string $identifier): string
{
    phase4MysqlExpect(preg_match('/^[a-z0-9_]+$/', $identifier) === 1, '数据库名非法');
    return '`' . $identifier . '`';
}

function phase4MigrationDirectory(string $source, int $maximum): string
{
    $target = sys_get_temp_dir() . '/funadmin_phase4_migrations_' . bin2hex(random_bytes(5));
    phase4MysqlExpect(mkdir($target, 0700), '无法创建隔离 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        if ((int) substr(basename($file), 0, 3) <= $maximum) {
            phase4MysqlExpect(copy($file, $target . '/' . basename($file)), '无法复制 migration 链');
        }
    }
    return $target;
}

function phase4AssertProtocolSchema(): void
{
    $tables = ['oauth_authorization', 'oauth_authorization_scope', 'oauth_authorization_code', 'oauth_token', 'oauth_token_scope'];
    foreach ($tables as $table) {
        phase4MysqlExpect(in_array('fun_' . $table, Db::connect()->getTables(), true), '缺少协议表：' . $table);
    }
    $hashColumns = Db::query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='fun_oauth_authorization' AND COLUMN_NAME IN ('transaction_hash','redirect_uri_hash')) OR (TABLE_NAME='fun_oauth_authorization_code' AND COLUMN_NAME IN ('code_hash','redirect_uri_hash')) OR (TABLE_NAME='fun_oauth_token' AND COLUMN_NAME='token_hash'))");
    phase4MysqlExpect(count($hashColumns) === 5, '协议哈希字段不完整');
    foreach ($hashColumns as $column) {
        phase4MysqlExpect(strtolower((string) $column['COLUMN_TYPE']) === 'char(64)', $column['TABLE_NAME'] . '.' . $column['COLUMN_NAME'] . ' 必须为 char(64)');
    }
    $foreignKeys = array_column(Db::query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('fun_oauth_authorization','fun_oauth_authorization_scope','fun_oauth_authorization_code','fun_oauth_token','fun_oauth_token_scope')"), 'CONSTRAINT_NAME');
    $expectedForeignKeys = ['fk_oauth_authorization_tenant', 'fk_oauth_authorization_client_tenant', 'fk_oauth_authorization_user', 'fk_oauth_authorization_scope_tenant', 'fk_oauth_authorization_scope_authorization', 'fk_oauth_authorization_scope_scope', 'fk_oauth_authorization_code_tenant', 'fk_oauth_authorization_code_authorization', 'fk_oauth_authorization_code_client', 'fk_oauth_authorization_code_user', 'fk_oauth_token_tenant', 'fk_oauth_token_client', 'fk_oauth_token_user', 'fk_oauth_token_authorization', 'fk_oauth_token_parent', 'fk_oauth_token_scope_tenant', 'fk_oauth_token_scope_token', 'fk_oauth_token_scope_scope'];
    phase4MysqlExpect(array_diff($expectedForeignKeys, $foreignKeys) === [], '协议表 FK 创建不完整：' . implode(',', array_diff($expectedForeignKeys, $foreignKeys)));
    $indexRows = Db::query("SELECT DISTINCT TABLE_NAME,INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('fun_oauth_authorization','fun_oauth_authorization_scope','fun_oauth_authorization_code','fun_oauth_token','fun_oauth_token_scope')");
    $indexes = array_map(static fn (array $row): string => $row['TABLE_NAME'] . '.' . $row['INDEX_NAME'], $indexRows);
    $expectedIndexes = ['fun_oauth_authorization.PRIMARY', 'fun_oauth_authorization.uk_oauth_authorization_tenant_id', 'fun_oauth_authorization.uk_oauth_authorization_transaction', 'fun_oauth_authorization.idx_oauth_authorization_client_status', 'fun_oauth_authorization.idx_oauth_authorization_user', 'fun_oauth_authorization_scope.PRIMARY', 'fun_oauth_authorization_scope.uk_oauth_authorization_scope', 'fun_oauth_authorization_scope.idx_oauth_authorization_scope_scope', 'fun_oauth_authorization_code.PRIMARY', 'fun_oauth_authorization_code.uk_oauth_authorization_code_tenant_id', 'fun_oauth_authorization_code.uk_oauth_authorization_code_hash', 'fun_oauth_authorization_code.idx_oauth_authorization_code_consume', 'fun_oauth_authorization_code.idx_oauth_authorization_code_user', 'fun_oauth_token.PRIMARY', 'fun_oauth_token.uk_oauth_token_tenant_id', 'fun_oauth_token.uk_oauth_token_hash', 'fun_oauth_token.idx_oauth_token_lookup', 'fun_oauth_token.idx_oauth_token_client', 'fun_oauth_token.idx_oauth_token_user', 'fun_oauth_token.idx_oauth_token_family', 'fun_oauth_token.idx_oauth_token_parent', 'fun_oauth_token_scope.PRIMARY', 'fun_oauth_token_scope.uk_oauth_token_scope', 'fun_oauth_token_scope.idx_oauth_token_scope_scope'];
    phase4MysqlExpect(array_diff($expectedIndexes, $indexes) === [], '协议表 index 创建不完整：' . implode(',', array_diff($expectedIndexes, $indexes)));
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$emptyDatabase = 'funadmin_identity_phase4_' . bin2hex(random_bytes(5));
$upgradeDatabase = 'funadmin_identity_phase4_upgrade_' . bin2hex(random_bytes(5));
$through099 = phase4MigrationDirectory($root . '/database/migrations', 99);
$through098 = phase4MigrationDirectory($root . '/database/migrations', 98);
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
try {
    $server->execute('CREATE DATABASE ' . phase4MysqlQuote($emptyDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->execute('CREATE DATABASE ' . phase4MysqlQuote($upgradeDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $emptyConfig = $original;
    $emptyConfig['connections']['mysql']['database'] = $emptyDatabase;
    $app->config->set($emptyConfig, 'database');
    Db::connect('mysql', true);
    $migration = new MigrationService();
    $executed = $migration->runDirectory($through099, 'core');
    phase4MysqlExpect(end($executed) === '099_oauth_protocol_core', '空库 migration 必须执行到 099');
    phase4MysqlExpect($migration->runDirectory($through099, 'core') === [], '空库重复执行必须幂等跳过');
    phase4AssertProtocolSchema();

    $upgradeConfig = $original;
    $upgradeConfig['connections']['mysql']['database'] = $upgradeDatabase;
    $app->config->set($upgradeConfig, 'database');
    Db::connect('mysql', true);
    $through098Executed = $migration->runDirectory($through098, 'core');
    phase4MysqlExpect(end($through098Executed) === '098_oauth_client_console', '升级库必须先完整执行到 098');
    phase4MysqlExpect($migration->runDirectory($through099, 'core') === ['099_oauth_protocol_core'], '098 升级必须只执行 099');
    phase4MysqlExpect($migration->runDirectory($through099, 'core') === [], '升级库重复执行必须幂等跳过');
    phase4AssertProtocolSchema();
    echo "identity phase4 mysql tests passed; temporary databases cleaned\n";
} finally {
    foreach ([$through099, $through098] as $directory) {
        foreach (glob($directory . '/*') ?: [] as $file) {
            if (is_file($file)) unlink($file);
        }
        if (is_dir($directory)) rmdir($directory);
    }
    $app->config->set($serverConfig, 'database');
    $cleanup = Db::connect('mysql', true);
    $cleanup->execute('DROP DATABASE IF EXISTS ' . phase4MysqlQuote($emptyDatabase));
    $cleanup->execute('DROP DATABASE IF EXISTS ' . phase4MysqlQuote($upgradeDatabase));
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
