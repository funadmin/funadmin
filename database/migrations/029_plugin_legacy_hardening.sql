-- 强化旧插件 adoption：仅无 manifest 的记录保留安全元数据，现代记录不要求重装。
UPDATE `fun_plugin`
SET `thumb` = '',
    `group` = '',
    `is_hook` = 0,
    `status` = 0,
    `lifecycle_state` = 'disabled',
    `needs_reinstall` = 1
WHERE `manifest` IS NULL;

UPDATE `fun_plugin`
SET `needs_reinstall` = 0
WHERE `manifest` IS NOT NULL;
