<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = $root . '/database/migrations/078_form_action_permission.sql';
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

echo "form action permission migration tests: PASS\n";
