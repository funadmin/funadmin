<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = $root . '/database/migrations/archive/078_form_action_permission.sql';
if (!is_file($migration)) {
    throw new RuntimeException('动作权限迁移必须使用未占用的下一序号 078，隔离现有 077');
}
$sql = (string) file_get_contents($migration);
foreach (['console/form.data:action', "'console/form.data'", "'action'", 'INSERT IGNORE INTO `fun_permission`'] as $required) {
    if (!str_contains($sql, $required)) {
        throw new RuntimeException('动作权限迁移缺少：' . $required);
    }
}
if (!str_contains($sql, '@form_data_group_id IS NOT NULL')) {
    throw new RuntimeException('动作权限迁移必须在表单数据权限组存在时才插入');
}
if (str_contains($sql, 'CREATE TABLE') || str_contains($sql, 'ALTER TABLE')) {
    throw new RuntimeException('动作权限迁移必须与业务表结构隔离');
}

$listMigration = $root . '/database/migrations/archive/128_form_list_action_permissions.sql';
if (!is_file($listMigration)) throw new RuntimeException('缺少独立列表动作入口迁移');
$listSql = (string) file_get_contents($listMigration);
foreach (['console/form.data:listactions', 'console/form.data:listaction', '@form_data_group_id IS NOT NULL', "'route', 1, 0"] as $required) {
    if (!str_contains($listSql, $required)) throw new RuntimeException('列表权限迁移缺少：' . $required);
}
foreach (['fun_role_permission', 'fun_casbin_rule', 'is_public`=1', 'CREATE TABLE', 'ALTER TABLE'] as $forbidden) {
    if (str_contains($listSql, $forbidden)) throw new RuntimeException('入口迁移不得自动扩权或改变业务结构');
}
echo "form action permission migration tests: PASS\n";
