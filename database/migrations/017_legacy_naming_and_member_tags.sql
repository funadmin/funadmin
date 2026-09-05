-- 017 历史命名与会员标签治理：新增规范结构、回填数据并保留旧结构兼容。
SET @schema_name = DATABASE();

-- 管理员与会员姓名字段统一为 real_name，管理员最后登录 IP 统一为 last_login_ip。
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin' AND COLUMN_NAME = 'real_name'), 'DO 0', 'ALTER TABLE `fun_admin` ADD COLUMN `real_name` varchar(50) NULL COMMENT ''真实姓名'' AFTER `email`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin' AND COLUMN_NAME = 'last_login_ip'), 'DO 0', 'ALTER TABLE `fun_admin` ADD COLUMN `last_login_ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''' COMMENT ''最后登录IP'' AFTER `ip`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE `fun_admin` SET `real_name` = COALESCE(`realname`, '') WHERE `real_name` IS NULL OR `real_name` = '';
UPDATE `fun_admin` SET `last_login_ip` = COALESCE(`lastloginip`, '') WHERE `last_login_ip` = '';

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND COLUMN_NAME = 'real_name'), 'DO 0', 'ALTER TABLE `fun_member` ADD COLUMN `real_name` varchar(50) NOT NULL DEFAULT '''' COMMENT ''真实姓名'' AFTER `email`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE `fun_member` SET `real_name` = COALESCE(`realname`, '') WHERE `real_name` = '';

-- 语言表统一使用单数名称。
CREATE TABLE IF NOT EXISTS `fun_language` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_language_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='语言';
-- 语言表回填：兼容源表仍持 legacy int 列或已切换 datetime 三件套两种状态。
SET @lang_c = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages' AND COLUMN_NAME='create_time'), 'FROM_UNIXTIME(NULLIF(`create_time`,0))', IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages' AND COLUMN_NAME='created_at'), '`created_at`', 'NULL'));
SET @lang_u = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages' AND COLUMN_NAME='update_time'), 'FROM_UNIXTIME(NULLIF(`update_time`,0))', IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages' AND COLUMN_NAME='updated_at'), '`updated_at`', 'NULL'));
SET @lang_d = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages' AND COLUMN_NAME='delete_time'), 'FROM_UNIXTIME(NULLIF(`delete_time`,0))', IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages' AND COLUMN_NAME='deleted_at'), '`deleted_at`', 'NULL'));
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_languages'), CONCAT('INSERT INTO `fun_language` (`id`,`name`,`is_default`,`status`,`created_at`,`updated_at`,`deleted_at`) SELECT `id`,`name`,`is_default`,`status`,', @lang_c, ',', @lang_u, ',', @lang_d, ' FROM `fun_languages` ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`is_default`=VALUES(`is_default`),`status`=VALUES(`status`),`updated_at`=VALUES(`updated_at`),`deleted_at`=VALUES(`deleted_at`)'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 原 provinces 实际承载省市区树，统一为 region。
CREATE TABLE IF NOT EXISTS `fun_region` (
  `id` int NOT NULL,
  `pid` int NOT NULL DEFAULT 0,
  `name` varchar(50) NOT NULL DEFAULT '',
  `short_name` varchar(50) NOT NULL DEFAULT '',
  `area_code` varchar(10) NOT NULL DEFAULT '',
  `zip_code` varchar(10) NOT NULL DEFAULT '',
  `pinyin` varchar(100) NOT NULL DEFAULT '',
  `longitude` decimal(10,6) DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `level` tinyint NOT NULL DEFAULT 1,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_region_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='省市区';
-- 省市区回填：兼容源表仍持 legacy delete_time 或已切换 deleted_at 两种状态。
SET @prov_d = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_provinces' AND COLUMN_NAME='delete_time'), 'FROM_UNIXTIME(NULLIF(`delete_time`,0))', IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_provinces' AND COLUMN_NAME='deleted_at'), '`deleted_at`', 'NULL'));
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_provinces'), CONCAT('INSERT INTO `fun_region` (`id`,`pid`,`name`,`short_name`,`area_code`,`zip_code`,`pinyin`,`longitude`,`latitude`,`level`,`sort_order`,`deleted_at`) SELECT `id`,`pid`,`name`,`short_name`,CAST(`areacode` AS CHAR),CAST(`zipcode` AS CHAR),`pinyin`,NULLIF(`lng`,''''),NULLIF(`lat`,''''),`level`,`sort`,', @prov_d, ' FROM `fun_provinces` ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`),`name`=VALUES(`name`),`short_name`=VALUES(`short_name`),`area_code`=VALUES(`area_code`),`zip_code`=VALUES(`zip_code`),`pinyin`=VALUES(`pinyin`),`longitude`=VALUES(`longitude`),`latitude`=VALUES(`latitude`),`level`=VALUES(`level`),`sort_order`=VALUES(`sort_order`),`deleted_at`=VALUES(`deleted_at`)'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 会员标签规范为实体表与多对多关联表。
CREATE TABLE IF NOT EXISTS `fun_member_tag` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_tag_name` (`name`),
  KEY `idx_member_tag_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员标签';
CREATE TABLE IF NOT EXISTS `fun_member_tag_relation` (
  `member_id` mediumint unsigned NOT NULL,
  `tag_id` int unsigned NOT NULL,
  `created_at` datetime NULL,
  PRIMARY KEY (`member_id`,`tag_id`),
  KEY `idx_member_tag_relation_tag` (`tag_id`,`member_id`),
  CONSTRAINT `fk_member_tag_relation_member` FOREIGN KEY (`member_id`) REFERENCES `fun_member` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_tag_relation_tag` FOREIGN KEY (`tag_id`) REFERENCES `fun_member_tag` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员与标签关联';
INSERT IGNORE INTO `fun_member_tag` (`name`,`status`,`sort_order`,`created_at`,`updated_at`)
SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`tags`, ',', seq.n), ',', -1)),1,0,NOW(),NOW()
FROM `fun_member` m JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32) seq ON seq.n <= 1 + LENGTH(m.`tags`) - LENGTH(REPLACE(m.`tags`, ',', ''))
WHERE TRIM(COALESCE(m.`tags`, '')) <> '' AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`tags`, ',', seq.n), ',', -1)) <> '';
INSERT INTO `fun_member_tag_relation` (`member_id`,`tag_id`,`created_at`)
SELECT m.`id`,t.`id`,NOW() FROM `fun_member` m JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32) seq ON seq.n <= 1 + LENGTH(m.`tags`) - LENGTH(REPLACE(m.`tags`, ',', '')) JOIN `fun_member_tag` t ON t.`name` = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`tags`, ',', seq.n), ',', -1)) WHERE TRIM(COALESCE(m.`tags`, '')) <> ''
ON DUPLICATE KEY UPDATE `created_at`=`fun_member_tag_relation`.`created_at`;
