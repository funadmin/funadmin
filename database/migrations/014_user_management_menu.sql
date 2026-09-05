-- 将会员相关页面从系统管理中拆分到独立的用户管理目录。

INSERT INTO `fun_admin_menu`
(`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`)
SELECT 0, 0, 'backend', '用户管理', '/users',
       'component=Layout&name=UserManagement&type=M&redirect=/users/member',
       '_self', 'i-ep-user', 1, 20, 'admin_web', 'user_management', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM `fun_admin_menu`
    WHERE `source_type` = 'admin_web' AND `source_name` = 'user_management'
);

SET @user_management_menu_id = (
    SELECT `id` FROM `fun_admin_menu`
    WHERE `source_type` = 'admin_web' AND `source_name` = 'user_management'
    ORDER BY `id` ASC
    LIMIT 1
);

UPDATE `fun_admin_menu`
SET `pid` = @user_management_menu_id,
    `href` = CASE `source_name`
        WHEN 'member_group' THEN 'member-group'
        WHEN 'member_level' THEN 'member-level'
        WHEN 'member' THEN 'member'
        ELSE `href`
    END,
    `update_time` = UNIX_TIMESTAMP()
WHERE `source_type` = 'admin_web'
  AND `source_name` IN ('member_group', 'member_level', 'member');

UPDATE `fun_admin_menu`
SET `title` = '管理员管理',
    `update_time` = UNIX_TIMESTAMP()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'user';