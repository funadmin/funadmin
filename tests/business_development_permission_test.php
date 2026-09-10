<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;

function businessPermissionExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$migrations = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
sort($migrations, SORT_STRING);
$names = array_values(array_filter($migrations, static fn (string $name): bool => str_starts_with($name, '083_')));
businessPermissionExpect($names === ['083_business_development_permissions.sql'], '必须使用下一个空闲编号 083 且唯一');

$file = $root . '/database/migrations/083_business_development_permissions.sql';
businessPermissionExpect(is_file($file), '缺少 083 Business 权限 migration');
$sql = (string) file_get_contents($file);
businessPermissionExpect(!preg_match('/[\x{4e00}-\x{9fff}]/u', preg_replace('/^--.*$/m', '', $sql)), 'SQL 中文必须使用 HEX');
businessPermissionExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|RENAME)\b/i', preg_replace('/^--.*$/m', '', $sql)), '权限 migration 必须 forward-only');

foreach (['development:business:view', 'development:business:generate', 'development:business:apply-resources', 'development:business:save', 'development:business:publish', 'development:business:inspect', 'development:business:records'] as $permission) {
    businessPermissionExpect(str_contains($sql, "'{$permission}'"), '缺少统一权限：' . $permission);
}
foreach (['业务开发', 'mine', 'visual', 'database', 'records'] as $menuKey) {
    businessPermissionExpect(str_contains($sql, $menuKey) || str_contains(strtolower($sql), bin2hex($menuKey)), '缺少业务开发菜单契约：' . $menuKey);
}
businessPermissionExpect(str_contains($sql, 'source_name') && str_contains($sql, 'admin_web') && str_contains($sql, 'business_development'), '菜单/权限来源必须为 admin_web/business_development');
businessPermissionExpect(str_contains($sql, 'INSERT IGNORE') || str_contains($sql, 'NOT EXISTS'), '权限与菜单必须幂等');
businessPermissionExpect(str_contains($sql, 'form_management') && str_contains($sql, 'development_crud'), '必须兼容映射旧权限组');
businessPermissionExpect(str_contains($sql, 'development:business:'), '旧权限映射必须指向统一 business 权限');
businessPermissionExpect(substr_count($sql, "'console/development.business'") >= 17, '必须为全部 Business 路由创建独立权限记录');
businessPermissionExpect(str_contains($sql, "source_name` IN ('form_management','development_crud')") || str_contains($sql, "source_name IN ('form_management','development_crud')"), '旧角色授权映射必须严格限定来源');
businessPermissionExpect(!preg_match('/UPDATE\s+`fun_permission`[\s\S]*?WHERE[\s\S]*?source_name`\s*<>\s*\x27(?:form_management|development_crud)/i', $sql), '不得跨来源扩大或覆盖旧权限');

$auth = (string) file_get_contents($root . '/app/console/controller/auth/AdminAuth.php');
businessPermissionExpect(str_contains($auth, "'console/development.business:modules'") && str_contains($auth, "'console/development.business:fieldcapabilities'"), 'AdminAuth aliases 必须包含全部 Business actions');
businessPermissionExpect(str_contains($auth, "'development:business:"), 'AdminAuth aliases 必须映射统一 business 权限');
businessPermissionExpect(str_contains($auth, "'console/devcrud:tableschema'") && str_contains($auth, "'console/devcrud:preview'"), '既有 DevCrud aliases 必须暂时保留');

if (extension_loaded('pdo_mysql') && getenv('BUSINESS_PERMISSION_TEST_DB_HOST')) {
    $host = (string) getenv('BUSINESS_PERMISSION_TEST_DB_HOST');
    $port = (string) (getenv('BUSINESS_PERMISSION_TEST_DB_PORT') ?: '3306');
    $user = (string) (getenv('BUSINESS_PERMISSION_TEST_DB_USER') ?: 'root');
    $pass = (string) (getenv('BUSINESS_PERMISSION_TEST_DB_PASS') ?: '');
    $databaseName = 'funadmin_business_permission_' . bin2hex(random_bytes(5));
    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try {
        $server->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $database = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec(<<<'SQL'
CREATE TABLE fun_permission (id int unsigned NOT NULL AUTO_INCREMENT,pid int unsigned NOT NULL DEFAULT 0,app_name varchar(50) NOT NULL DEFAULT 'console',code varchar(255) NULL,obj varchar(190) NOT NULL DEFAULT '',act varchar(100) NOT NULL DEFAULT '',name varchar(100) NOT NULL DEFAULT '',resource_type enum('group','route') NOT NULL DEFAULT 'route',status tinyint NOT NULL DEFAULT 1,is_public tinyint NOT NULL DEFAULT 0,source_type varchar(20) NOT NULL DEFAULT 'system',source_name varchar(100) NOT NULL DEFAULT '',created_at datetime NULL,updated_at datetime NULL,sort_order int NOT NULL DEFAULT 999,deleted_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uk_permission_code(code));
CREATE TABLE fun_admin_menu (id int unsigned NOT NULL AUTO_INCREMENT,pid int unsigned NOT NULL DEFAULT 0,permission_id int unsigned NULL,app_name varchar(50) NOT NULL DEFAULT 'console',name varchar(100) NOT NULL DEFAULT '',href varchar(255) NOT NULL DEFAULT '',query varchar(250) NOT NULL DEFAULT '',target varchar(20) NOT NULL DEFAULT '_self',icon varchar(100) NOT NULL DEFAULT '',status tinyint NOT NULL DEFAULT 1,source_type varchar(20) NOT NULL DEFAULT 'system',source_name varchar(100) NOT NULL DEFAULT '',created_at datetime NULL,updated_at datetime NULL,sort_order int NOT NULL DEFAULT 999,deleted_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uk_menu_location(app_name,href,query));
CREATE TABLE fun_casbin_rule (id bigint unsigned NOT NULL AUTO_INCREMENT,ptype varchar(10) NOT NULL,v0 varchar(190) NOT NULL DEFAULT '',v1 varchar(190) NOT NULL DEFAULT '',v2 varchar(190) NOT NULL DEFAULT '',v3 varchar(190) NOT NULL DEFAULT '',v4 varchar(190) NOT NULL DEFAULT '',v5 varchar(190) NOT NULL DEFAULT '',rule_hash char(64) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uk_rule_hash(rule_hash));
INSERT INTO fun_permission (id,code,obj,act,name,resource_type,source_type,source_name) VALUES
(1,NULL,'','','Console','group','admin_web','console_root'),(2,NULL,'','','Development','group','admin_web','development_tools'),
(10,'console/form.designer:index','console/form.designer','index','Form list','route','admin_web','form_management'),
(11,'console/form.designer:save','console/form.designer','save','Form save','route','admin_web','form_management'),
(12,'console/devcrud:preview','console/devcrud','preview','CRUD preview','route','admin_web','development_crud'),
(13,'development:crud:apply-resources','development/crud','apply-resources','CRUD apply','route','admin_web','development_crud');
INSERT INTO fun_casbin_rule (ptype,v0,v1,v2,v3,rule_hash) VALUES
('p','role-form','console','console/form.designer','save',SHA2(CONCAT_WS(CHAR(31),'p','role-form','console','console/form.designer','save'),256)),
('p','role-crud','console','console/devcrud','preview',SHA2(CONCAT_WS(CHAR(31),'p','role-crud','console','console/devcrud','preview'),256)),
('p','role-apply','console','development/crud','apply-resources',SHA2(CONCAT_WS(CHAR(31),'p','role-apply','console','development/crud','apply-resources'),256));
SQL);
        $service = new MigrationService();
        $statements = new ReflectionMethod($service, 'statements');
        $statements->setAccessible(true);
        foreach ([1, 2] as $_run) foreach ($statements->invoke($service, $sql) as $statement) $database->exec($statement);
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_permission WHERE source_name='business_development'")->fetchColumn() === 25, '083 必须幂等创建 1 group、17 route 和 7 capability');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_admin_menu WHERE source_name='business_development'")->fetchColumn() === 5, '083 必须幂等创建 5 个菜单');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-form' AND v2='development/business' AND v3='save'")->fetchColumn() === 1, '旧表单保存角色必须映射新 save capability');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-crud' AND v2='console/development.business' AND v3='previewformalgeneration'")->fetchColumn() === 1, '旧 CRUD preview 角色只能映射新 preview route');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-crud' AND v2='development/business' AND v3='generate'")->fetchColumn() === 0, '旧 CRUD preview 角色不得获得 generate capability');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-apply' AND v2='console/development.business' AND v3='retryresources'")->fetchColumn() === 1, '旧 resource apply 角色必须获得等价 retry route');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-form' AND v2='console/development.business' AND v3='adoptresolvedbaseline'")->fetchColumn() === 0, '旧表单保存不得扩大为 baseline 采纳权限');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-form' AND v2='development/business' AND v3='generate'")->fetchColumn() === 0, '旧授权不得跨能力扩大');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_permission WHERE source_name IN ('form_management','development_crud') AND status=1")->fetchColumn() === 4, '旧权限必须保留启用');
    } finally {
        $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    }
}

echo "business development permission tests: PASS\n";
