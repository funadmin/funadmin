-- 将会员相关页面从系统管理中拆分到独立的用户管理目录。
-- 双态兼容：015 之前执行时写 title/create_time/update_time，015/034 之后执行时写 name/created_at/updated_at。
SET @schema_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin_menu' AND COLUMN_NAME = 'title'),
  'INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`) SELECT 0, 0, ''backend'', ''用户管理'', ''/users'', ''component=Layout&name=UserManagement&type=M&redirect=/users/member'', ''_self'', ''i-ep-user'', 1, 20, ''admin_web'', ''user_management'', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = ''admin_web'' AND `source_name` = ''user_management'')',
  'INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`module`,`name`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`) SELECT 0, 0, ''backend'', ''用户管理'', ''/users'', ''component=Layout&name=UserManagement&type=M&redirect=/users/member'', ''_self'', ''i-ep-user'', 1, 20, ''admin_web'', ''user_management'', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = ''admin_web'' AND `source_name` = ''user_management'')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @user_management_menu_id = (
    SELECT `id` FROM `fun_admin_menu`
    WHERE `source_type` = 'admin_web' AND `source_name` = 'user_management'
    ORDER BY `id` ASC
    LIMIT 1
);

SET @menu_ts = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin_menu' AND COLUMN_NAME = 'update_time'),
  '`update_time` = UNIX_TIMESTAMP()',
  '`updated_at` = NOW()'
);
SET @sql = CONCAT(
  'UPDATE `fun_admin_menu` SET `pid` = @user_management_menu_id, `href` = CASE `source_name` WHEN ''member_group'' THEN ''member-group'' WHEN ''member_level'' THEN ''member-level'' WHEN ''member'' THEN ''member'' ELSE `href` END, ',
  @menu_ts,
  ' WHERE `source_type` = ''admin_web'' AND `source_name` IN (''member_group'', ''member_level'', ''member'')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @menu_label = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin_menu' AND COLUMN_NAME = 'title'),
  '`title`',
  '`name`'
);
SET @sql = CONCAT(
  'UPDATE `fun_admin_menu` SET ', @menu_label, ' = ''管理员管理'', ', @menu_ts,
  ' WHERE `source_type` = ''admin_web'' AND `source_name` = ''user'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
