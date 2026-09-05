<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use think\App;

function pluginMigrationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pluginMigrationIdentifier(string $identifier): string
{
    pluginMigrationExpect((bool) preg_match('/^[a-z0-9_]+$/', $identifier), '数据库标识符不安全');
    return '`' . $identifier . '`';
}

$app = new App();
$app->initialize();
$config = config('database.connections.mysql');
$temporaryDatabase = 'funadmin_plugin_test_' . bin2hex(random_bytes(6));
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
    $server->exec('CREATE DATABASE ' . pluginMigrationIdentifier($temporaryDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database = new PDO("mysql:host={$host};port={$port};dbname={$temporaryDatabase};charset={$charset}", $username, $password, $options);
    $service = new MigrationService();
    $statements = new ReflectionMethod($service, 'statements');
    $statements->setAccessible(true);
    $forwardOnly = new ReflectionMethod($service, 'assertForwardOnly');
    $forwardOnly->setAccessible(true);
    $directory = dirname(__DIR__) . '/database/migrations';
    $files = [
        '001_core_schema.sql',
        '002_rbac_schema.sql',
        '003_dictionary_schema.sql',
        '004_organization_rbac_schema.sql',
        '005_plugin_lifecycle_schema.sql',
        '006_schema_integrity.sql',
        '007_plugin_registry_state.sql',
        '008_admin_web_menu_hierarchy.sql',
        '009_plugin_package_history.sql',
        '010_plugin_admin_web_permissions.sql',
        '011_admin_web_menu_icons.sql',
        '012_admin_log_audit.sql',
        '013_field_hygiene.sql',
        '014_user_management_menu.sql',
        '015_title_to_name.sql',
        '016_attachment_storage_drivers.sql',
        '017_legacy_naming_and_member_tags.sql',
        '018_schema_integrity_followup.sql',
        '019_member_group_icon.sql',
        '021_time_columns_no_default.sql',
        '022_laravel_field_cutover.sql',
        '023_plugin_legacy_cleanup.sql',
        '024_plugin_lifecycle_capabilities.sql',
        '025_plugin_adoption.sql',
        '026_plugin_resource_registry.sql',
        '027_plugin_purge_permission.sql',
        '029_plugin_registry_hardening.sql',
    ];

    foreach ($files as $name) {
        $file = $directory . '/' . $name;
        $sql = (string) file_get_contents($file);
        $forwardOnly->invoke($service, $sql, $file);
        if ($name === '025_plugin_adoption.sql') {
            $database->exec('CREATE TABLE `fun_addon` LIKE `fun_plugin`');
            $database->exec('ALTER TABLE `fun_addon` DROP INDEX `uk_plugin_name`');
            $database->exec("INSERT INTO `fun_addon` (`title`,`name`,`thumb`,`group`,`description`,`author`,`version`,`requires`,`website`,`is_hook`,`config`,`status`,`create_time`,`update_time`) VALUES ('旧插件','legacydemo','/unsafe.png','unsafe','legacy description','legacy author','1.0.0','7.0.0','https://legacy.example',1,'{\"safe\":true}',1,1700000000,1700000060),('旧重名插件','moderndemo','/legacy.png','legacy','legacy duplicate','legacy author','0.9.0','6.0.0','https://legacy.example',1,'{\"legacy\":true}',1,1700000000,1700000060)");
            $database->exec("INSERT INTO `fun_plugin` (`title`,`name`,`version`,`requires`,`config`,`status`,`lifecycle_state`,`manifest`,`needs_reinstall`) VALUES ('现代插件','moderndemo','2.0.0','8.1.0','{\"modern\":true}',1,'enabled','{\"schema_version\":1,\"name\":\"moderndemo\"}',0)");
        }
        foreach ($statements->invoke($service, $sql) as $statement) {
            $result = $database->query($statement);
            if ($result !== false) {
                $result->fetchAll();
                $result->closeCursor();
            }
        }
    }

    $pluginColumns = $database->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['package_hash', 'code_version', 'source', 'error_stage', 'recovery_path', 'needs_reinstall'] as $column) {
        pluginMigrationExpect(in_array($column, $pluginColumns, true), "插件生命周期字段缺失：{$column}");
    }
    $operationColumns = $database->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_operation'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['stage', 'progress', 'recovery_path'] as $column) {
        pluginMigrationExpect(in_array($column, $operationColumns, true), "插件操作历史字段缺失：{$column}");
    }
    $resourceIndexes = $database->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_resource'")->fetchAll(PDO::FETCH_COLUMN);
    pluginMigrationExpect(in_array('uk_plugin_resource_target', $resourceIndexes, true), '资源目标必须具备唯一归属索引');
    pluginMigrationExpect((int) $database->query("SELECT COUNT(*) FROM `fun_permission` WHERE `code`='backend/systemplugin:purge'")->fetchColumn() === 1, '必须创建独立 purge 权限');

    $legacy = $database->query("SELECT `thumb`,`group`,`is_hook`,`config`,`status`,`lifecycle_state`,`needs_reinstall` FROM `fun_plugin` WHERE `name`='legacydemo'")->fetch();
    pluginMigrationExpect($legacy !== false, '旧 fun_addon 安全记录必须被 adoption');
    pluginMigrationExpect($legacy['config'] === '{"safe":true}', '旧记录必须保留白名单 config');
    pluginMigrationExpect($legacy['thumb'] === '' && $legacy['group'] === '' && (int) $legacy['is_hook'] === 0, '旧记录非白名单运行时字段必须清除');
    pluginMigrationExpect((int) $legacy['status'] === 0 && $legacy['lifecycle_state'] === 'disabled' && (int) $legacy['needs_reinstall'] === 1, '旧记录必须禁用并要求重装');

    $modern = $database->query("SELECT `config`,`status`,`lifecycle_state`,`needs_reinstall` FROM `fun_plugin` WHERE `name`='moderndemo'")->fetch();
    pluginMigrationExpect($modern === ['config' => '{"modern":true}', 'status' => 0, 'lifecycle_state' => 'disabled', 'needs_reinstall' => 0], '同名现代记录必须保留现代配置且不要求重装');

    echo "plugin mysql migration tests: PASS\n";
} finally {
    $database = null;
    if (str_starts_with($temporaryDatabase, 'funadmin_plugin_test_')) {
        $server->exec('DROP DATABASE IF EXISTS ' . pluginMigrationIdentifier($temporaryDatabase));
    }
}
