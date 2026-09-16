-- 将 Admin Web 系统功能归入统一目录，供混合布局与双列布局按一级/二级菜单正确分栏。

INSERT INTO `fun_admin_menu`
(`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`)
SELECT 0, 0, 'backend', '系统管理', '/system',
       'component=Layout&name=SystemManagement&type=M&redirect=/system/user',
       '_self', 'i-ep-setting', 1, 10, 'admin_web', 'system_management', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM `fun_admin_menu`
    WHERE `source_type` = 'admin_web' AND `source_name` = 'system_management'
);

SET @system_menu_id = (
    SELECT `id` FROM `fun_admin_menu`
    WHERE `source_type` = 'admin_web' AND `source_name` = 'system_management'
    ORDER BY `id` ASC
    LIMIT 1
);

UPDATE `fun_admin_menu`
SET `pid` = @system_menu_id,
    `href` = CASE `id`
        WHEN 20 THEN 'dict'
        WHEN 21 THEN 'role'
        WHEN 22 THEN 'dept'
        WHEN 23 THEN 'user'
        WHEN 24 THEN 'menu'
        WHEN 25 THEN 'log/operation'
        WHEN 26 THEN 'permission'
        WHEN 27 THEN 'blacklist'
        WHEN 28 THEN 'language'
        WHEN 29 THEN 'member-group'
        WHEN 30 THEN 'member-level'
        WHEN 31 THEN 'member'
        WHEN 32 THEN 'attachment'
        WHEN 33 THEN 'config'
        ELSE `href`
    END,
    `query` = CASE `id`
        WHEN 20 THEN 'component=system/dict/index&name=SystemDict'
        ELSE `query`
    END,
    `update_time` = UNIX_TIMESTAMP()
WHERE (`id` BETWEEN 20 AND 33)
  AND `source_type` = 'admin_web';
