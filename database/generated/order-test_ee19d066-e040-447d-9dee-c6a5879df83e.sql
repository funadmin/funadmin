-- funadmin-physical-table
-- Generated forward migration; review before applying.
CREATE TABLE IF NOT EXISTS `fun_test` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_1` varchar(255) NULL,
  `field_2` varchar(500) NULL,
  `field_3` varchar(20) NULL,
  `field_4` varchar(100) NULL,
  `field_5` bigint unsigned NULL,
  `field_6` json NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='测试可视化';
