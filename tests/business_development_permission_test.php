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

$remainingFile = $root . '/database/migrations/086_business_remaining_capabilities.sql';
businessPermissionExpect(is_file($remainingFile), '缺少 086 剩余能力迁移');
$remainingSql = (string) file_get_contents($remainingFile);
businessPermissionExpect(!preg_match('/[\x{4e00}-\x{9fff}]/u', preg_replace('/^--.*$/m', '', $remainingSql)), '086 SQL 中文必须使用 HEX');
businessPermissionExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|RENAME|UPDATE)\b/i', preg_replace('/^--.*$/m', '', $remainingSql)), '086 必须 forward-only');
foreach (['compileschema', 'exportschema', 'schemaversions', 'schemaversion', 'schemadiff', 'rollbackschema', 'databasetables', 'databasetableschema'] as $action) {
    businessPermissionExpect(str_contains($remainingSql, "'console/development.business:{$action}'"), '086 缺少 Business action：' . $action);
}
businessPermissionExpect(str_contains($remainingSql, "source_name` IN ('form_management','development_crud')"), '086 旧授权映射必须严格限定来源');

$auth = (string) file_get_contents($root . '/app/console/controller/auth/AdminAuth.php');
businessPermissionExpect(str_contains($auth, "'console/development.business:modules'") && str_contains($auth, "'console/development.business:fieldcapabilities'"), 'AdminAuth aliases 必须包含全部 Business actions');
foreach (['compileschema', 'exportschema', 'schemaversions', 'schemaversion', 'schemadiff', 'rollbackschema', 'databasetables', 'databasetableschema'] as $action) {
    businessPermissionExpect(str_contains($auth, "'console/development.business:{$action}'"), 'AdminAuth aliases 缺少：' . $action);
}
businessPermissionExpect(str_contains($auth, "'development:business:"), 'AdminAuth aliases 必须映射统一 business 权限');
businessPermissionExpect(!str_contains($auth, "'console/devcrud:"), 'AdminAuth 不得保留 DevCrud aliases');
businessPermissionExpect(str_contains($auth, "'console/development.business:recovergeneration' => 'development:business:recover'"), 'AdminAuth 缺少 recover 独立权限 alias');

$recoverFile = $root . '/database/migrations/089_business_generation_recover_permission.sql';
businessPermissionExpect(is_file($recoverFile), '缺少 089 recover 权限 migration');
$recoverSql = (string) file_get_contents($recoverFile);
businessPermissionExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|RENAME|UPDATE)\b/i', preg_replace('/^--.*$/m', '', $recoverSql)), '089 必须 forward-only');
businessPermissionExpect(str_contains($recoverSql, "'development:business:recover'") && str_contains($recoverSql, "'console/development.business:recovergeneration'"), '089 必须新增独立 recover 权限与 action');
businessPermissionExpect(!str_contains($recoverSql, 'allowOverwrite') && !str_contains($recoverSql, 'development:business:generate'), '089 不得引入 overwrite 或复用 generate 权限');

$retirementFile = $root . '/database/migrations/087_legacy_form_crud_retirement.sql';
businessPermissionExpect(is_file($retirementFile), '缺少 087 旧产品入口退役 migration');
$retirementSql = (string) file_get_contents($retirementFile);
businessPermissionExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', preg_replace('/^--.*$/m', '', $retirementSql)), '087 不得删除或重命名 schema');
businessPermissionExpect(str_contains($retirementSql, "'development:plugin:options'") && str_contains($retirementSql, "'console/development.devplugin'") && str_contains($retirementSql, "'options'"), '087 必须迁移插件 options 权限');
businessPermissionExpect(str_contains($retirementSql, "`source_name`='plugin_center'") && str_contains($retirementSql, '`status`=1') && str_contains($retirementSql, '`deleted_at`=NULL'), '插件 options 必须归属 plugin_center 并启用');
businessPermissionExpect(str_contains($retirementSql, "('form_list','form_designer','development_crud')"), '087 必须软删除旧菜单');
businessPermissionExpect(str_contains($retirementSql, "('form_management','development_crud')"), '087 必须软删除旧权限');
businessPermissionExpect(preg_match('/DELETE\s+FROM\s+`fun_casbin_rule`/i', $retirementSql) === 1, '087 必须删除旧 Casbin p 策略');
businessPermissionExpect(str_contains($retirementSql, "`ptype`='p'"), '087 只能清理 Casbin p 策略');
businessPermissionExpect(str_contains($retirementSql, "'console/form.designer'") && str_contains($retirementSql, "'console/form.full-publish'") && str_contains($retirementSql, "'console/devcrud'") && str_contains($retirementSql, "'development/crud'"), '087 必须覆盖所有旧授权资源');
businessPermissionExpect(str_contains($retirementSql, 'INSERT IGNORE INTO `fun_casbin_rule`'), '087 必须先等价迁移插件 options 授权');

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
INSERT INTO fun_permission (id,pid,code,obj,act,name,resource_type,status,source_type,source_name) VALUES
(1,0,NULL,'','','Console','group',1,'admin_web','console_root'),(2,1,NULL,'','','Development','group',1,'admin_web','development_tools'),
(3,2,NULL,'','','Plugin center','group',1,'admin_web','plugin_center'),
(10,2,'console/form.designer:index','console/form.designer','index','Form list','route',1,'admin_web','form_management'),
(11,2,'console/form.designer:save','console/form.designer','save','Form save','route',1,'admin_web','form_management'),
(12,2,'console/devcrud:preview','console/devcrud','preview','CRUD preview','route',1,'admin_web','development_crud'),
(13,2,'development:crud:apply-resources','development/crud','apply-resources','CRUD apply','route',1,'admin_web','development_crud'),
(14,2,'development:plugin:options','console/development.devplugin','options','Plugin options','route',0,'admin_web','development_crud');
INSERT INTO fun_admin_menu (id,pid,name,href,query,status,source_type,source_name) VALUES
(1,0,'Form list','/form/list','',1,'admin_web','form_list'),
(2,0,'Form designer','/form/designer','',1,'admin_web','form_designer'),
(3,0,'CRUD Workbench','/development/crud','',1,'admin_web','development_crud');
INSERT INTO fun_casbin_rule (ptype,v0,v1,v2,v3,rule_hash) VALUES
('p','role-form','console','console/form.designer','save',SHA2(CONCAT_WS(CHAR(31),'p','role-form','console','console/form.designer','save'),256)),
('p','role-crud','console','console/devcrud','preview',SHA2(CONCAT_WS(CHAR(31),'p','role-crud','console','console/devcrud','preview'),256)),
('p','role-apply','console','development/crud','apply-resources',SHA2(CONCAT_WS(CHAR(31),'p','role-apply','console','development/crud','apply-resources'),256)),
('p','role-plugin','console','console/development.devplugin','options',SHA2(CONCAT_WS(CHAR(31),'p','role-plugin','console','console/development.devplugin','options'),256)),
('g','admin-user','role-form','','',SHA2(CONCAT_WS(CHAR(31),'g','admin-user','role-form','',''),256));
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
        foreach ([1, 2] as $_run) foreach ($statements->invoke($service, $remainingSql) as $statement) $database->exec($statement);
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-form' AND v2='console/development.business' AND v3='compileschema'")->fetchColumn() === 0, '无 compile 旧授权时不得扩大授权');
        foreach ([1, 2] as $_run) foreach ($statements->invoke($service, $recoverSql) as $statement) $database->exec($statement);
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_permission WHERE source_name='business_development' AND code IN ('development:business:recover','console/development.business:recovergeneration')")->fetchColumn() === 2, '089 必须幂等创建独立 recover capability 与 route');
        foreach ([1, 2] as $_run) foreach ($statements->invoke($service, $retirementSql) as $statement) $database->exec($statement);
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_permission WHERE code='development:plugin:options' AND pid=3 AND source_name='plugin_center' AND status=1 AND deleted_at IS NULL")->fetchColumn() === 1, '087 必须启用插件 options 并归属 plugin_center');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-plugin' AND v2='console/development.devplugin' AND v3='options'")->fetchColumn() === 1, '087 必须等价保留插件 options 授权且幂等');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_admin_menu WHERE source_name IN ('form_list','form_designer','development_crud') AND status=0 AND deleted_at IS NOT NULL")->fetchColumn() === 3, '087 必须软删除并禁用全部旧菜单');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_permission WHERE source_name IN ('form_management','development_crud') AND status=0 AND deleted_at IS NOT NULL")->fetchColumn() === 4, '087 必须软删除并禁用全部旧权限');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE ptype='p' AND v2 IN ('console/form.designer','console/form.full-publish','form/publish','console/devcrud','development/crud')")->fetchColumn() === 0, '087 必须清理全部旧 Casbin p 策略');
        businessPermissionExpect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE ptype='g' AND v0='admin-user' AND v1='role-form'")->fetchColumn() === 1, '087 不得删除 Casbin g 角色关系');
    } finally {
        $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    }
}

echo "business development permission tests: PASS\n";
