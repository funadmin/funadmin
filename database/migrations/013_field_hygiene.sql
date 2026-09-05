-- 013 字段规范化：类型、长度、引擎与主键统一；仅向前兼容增强。
-- 约束：不使用破坏性语句；所有表名保持可直接替换前缀的字面量。

-- 会员手机号：空串会占用唯一索引槽位，归一为 NULL 并允许 NULL。
UPDATE `fun_member` SET `mobile` = NULL WHERE TRIM(COALESCE(`mobile`, '')) = '';
ALTER TABLE `fun_member` MODIFY COLUMN `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号码';

-- 省份表：邮编与区号 int 存储会丢失前导零，改为字符串。
ALTER TABLE `fun_provinces`
  MODIFY COLUMN `areacode` varchar(10) NOT NULL DEFAULT '' COMMENT '区域编码',
  MODIFY COLUMN `zipcode` varchar(10) NOT NULL DEFAULT '' COMMENT '邮政编码';

-- 附件表：宽高与时长原为字符串，先清理脏数据再统一为整数。
UPDATE `fun_attach` SET `width` = '0' WHERE `width` IS NULL OR `width` NOT REGEXP '^[0-9]+$';
UPDATE `fun_attach` SET `height` = '0' WHERE `height` IS NULL OR `height` NOT REGEXP '^[0-9]+$';
UPDATE `fun_attach` SET `duration` = '0' WHERE `duration` IS NULL OR `duration` NOT REGEXP '^[0-9]+$';
ALTER TABLE `fun_attach`
  MODIFY COLUMN `width` int unsigned NOT NULL DEFAULT 0 COMMENT '宽度',
  MODIFY COLUMN `height` int unsigned NOT NULL DEFAULT 0 COMMENT '高度',
  MODIFY COLUMN `duration` int unsigned NOT NULL DEFAULT 0 COMMENT '音视频时长秒';

-- 验证规则表：原表无主键，补充自增主键。
SET @schema_name = DATABASE();
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_field_verify' AND COLUMN_NAME = 'id'),
  'DO 0',
  'ALTER TABLE `fun_field_verify` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- MyISAM 表统一转换为 InnoDB，与全库引擎一致。
ALTER TABLE `fun_field_type` ENGINE=InnoDB;
ALTER TABLE `fun_provinces` ENGINE=InnoDB;

-- 黑名单 IP 与全库统一：varchar(45) ascii。
ALTER TABLE `fun_blacklist` MODIFY COLUMN `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'IP或规则';

-- 配置验证规则长度与 fun_field_verify.verify 同步为 50。
ALTER TABLE `fun_config` MODIFY COLUMN `verify` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '验证规则';

-- 插件需求版本：清理单空格脏默认值；该语义已由 plugin.json 承接。
UPDATE `fun_plugin` SET `requires` = '' WHERE `requires` = ' ';
ALTER TABLE `fun_plugin` MODIFY COLUMN `requires` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '需求版本(废弃,见plugin.json)';

-- 配置分组状态补充有意义注释并显式非空。
ALTER TABLE `fun_config_group` MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用';
