<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use think\App;
use think\facade\Db;

function legacyPreflightExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new MigrationService();
$preflight = new ReflectionMethod($service, 'preflightSchemaIntegrity006');
$preflight->setAccessible(true);

// 非目标 scope/version 必须在访问数据库前返回，因此该测试不连接或执行用户数据库。
$preflight->invoke($service, 'plugin:example', '006_schema_integrity');
$preflight->invoke($service, 'core', '005_plugin_lifecycle_schema');
$preflight->invoke($service, 'core', '006_schema_integrity_followup');

$source = (string) file_get_contents(dirname(__DIR__) . '/app/common/service/MigrationService.php');
$runStart = strpos($source, 'public function runDirectory');
$nextMethod = strpos($source, '/**', $runStart + 1);
$runDirectory = $runStart === false ? '' : substr($source, $runStart, $nextMethod - $runStart);
$recordCheckAt = strpos($runDirectory, 'if ($record)');
$preflightAt = strpos($runDirectory, '$this->preflightSchemaIntegrity006($scope, $version)');
$readAt = strpos($runDirectory, 'file_get_contents($file)');

legacyPreflightExpect($recordCheckAt !== false, '必须先检查 migration 是否已登记');
legacyPreflightExpect($preflightAt !== false && $preflightAt > $recordCheckAt, 'preflight 必须位于已登记检查之后');
legacyPreflightExpect($readAt !== false && $readAt > $preflightAt, 'preflight 必须位于读取 006 主体之前');
legacyPreflightExpect(str_contains($source, "config('database.connections.mysql.prefix')"), 'preflight 必须支持自定义表前缀');
legacyPreflightExpect(!str_contains($source, 'UPDATE `fun_member` SET `mobile` = NULL'), 'preflight 不得硬编码 fun_member');
legacyPreflightExpect((bool) preg_match('/UPDATE[\s\S]*NULL[\s\S]*TRIM\([\s\S]*COALESCE/i', $source), 'preflight 必须幂等归一空白手机号');
legacyPreflightExpect((bool) preg_match('/ALTER TABLE[\s\S]*MODIFY COLUMN[\s\S]*NULL DEFAULT NULL/i', $source), 'preflight 必须确保 mobile 可空');
legacyPreflightExpect((bool) preg_match('/information_schema\.STATISTICS[\s\S]*uk_member_mobile[\s\S]*ADD UNIQUE KEY/i', $source), 'preflight 必须幂等建立 mobile 唯一索引以绕过 006 的 NULL guard');

if (getenv('LEGACY_PREFLIGHT_MYSQL') === '1') {
    $app = new App();
    $app->initialize();
    $baseConfig = config('database');
    $mysql = $baseConfig['connections']['mysql'];
    $mysql['hostname'] = getenv('DB_HOST') ?: $mysql['hostname'];
    $mysql['username'] = getenv('DB_USER') ?: $mysql['username'];
    $mysql['password'] = getenv('DB_PASS') ?: $mysql['password'];
    $mysql['database'] = '';
    $mysql['prefix'] = 'legacy_';
    $databaseName = 'funadmin_legacy_preflight_' . bin2hex(random_bytes(6));
    $serverConfig = $baseConfig;
    $serverConfig['connections']['mysql'] = $mysql;
    $app->config->set($serverConfig, 'database');
    $server = Db::connect('mysql', true);
    $directory = sys_get_temp_dir() . '/funadmin-legacy-preflight-' . bin2hex(random_bytes(6));
    mkdir($directory);

    try {
        $server->execute("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $mysql['database'] = $databaseName;
        $databaseConfig = $baseConfig;
        $databaseConfig['connections']['mysql'] = $mysql;
        $app->config->set($databaseConfig, 'database');
        Db::connect('mysql', true);

        $migrationDirectory = dirname(__DIR__) . '/database/migrations';
        foreach (range(1, 5) as $number) {
            $matches = glob($migrationDirectory . '/' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '_*.sql') ?: [];
            copy($matches[0], $directory . '/' . basename($matches[0]));
        }
        (new MigrationService())->runDirectory($directory, 'core');
        Db::execute("INSERT INTO `legacy_member` (`mobile`) VALUES (?), (?)", ['', '   ']);
        $preflight->invoke($service, 'core', '006_schema_integrity');
        legacyPreflightExpect((int) Db::query("SELECT COUNT(*) AS aggregate FROM `legacy_member` WHERE `mobile` IS NULL")[0]['aggregate'] === 2, 'preflight 必须直接归一两个空白手机号');
        Db::execute("ALTER TABLE `legacy_member` DROP INDEX `uk_member_mobile`");
        Db::execute("UPDATE `legacy_member` SET `mobile` = ?", ['']);
        legacyPreflightExpect((int) Db::query("SELECT COUNT(*) AS aggregate FROM `legacy_member` WHERE TRIM(COALESCE(`mobile`, '')) = ''")[0]['aggregate'] === 2, '全链前必须存在两个空手机号');
        copy($migrationDirectory . '/006_schema_integrity.sql', $directory . '/006_schema_integrity.sql');

        try {
            $executed = (new MigrationService())->runDirectory($directory, 'core');
        } catch (Throwable $exception) {
            $remaining = Db::query("SELECT COUNT(*) AS aggregate FROM `legacy_member` WHERE TRIM(COALESCE(`mobile`, '')) = ''")[0]['aggregate'];
            throw new RuntimeException("006 全链失败，剩余空手机号：{$remaining}", 0, $exception);
        }
        legacyPreflightExpect($executed === ['006_schema_integrity'], '首次执行必须运行 006');
        legacyPreflightExpect((int) Db::query("SELECT COUNT(*) AS aggregate FROM `legacy_member` WHERE `mobile` IS NULL")[0]['aggregate'] === 2, '两个空白手机号必须归一为 NULL');
        $column = Db::query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", ['legacy_member', 'mobile']);
        legacyPreflightExpect(($column[0]['IS_NULLABLE'] ?? '') === 'YES', 'mobile 必须可空');
        Db::execute("DELETE FROM `legacy_member`");
        Db::execute("ALTER TABLE `legacy_member` MODIFY COLUMN `mobile` varchar(20) NOT NULL DEFAULT ''");
        legacyPreflightExpect((new MigrationService())->runDirectory($directory, 'core') === [], '已登记 006 必须跳过且不再触发 preflight');
        $columnAfterRerun = Db::query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", ['legacy_member', 'mobile']);
        legacyPreflightExpect(($columnAfterRerun[0]['IS_NULLABLE'] ?? '') === 'NO', '已登记 006 不得再次修改 mobile');
    } finally {
        $app->config->set($serverConfig, 'database');
        Db::connect('mysql', true)->execute("DROP DATABASE IF EXISTS `{$databaseName}`");
        foreach (glob($directory . '/*.sql') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}

echo "migration service legacy preflight test passed\n";
