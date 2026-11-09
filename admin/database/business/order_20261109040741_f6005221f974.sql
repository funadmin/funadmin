-- funadmin-physical-table
-- Generated forward migration; review before applying.
CREATE TABLE IF NOT EXISTS `fun_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_1` varchar(255) NULL,
  `field_2` varchar(100) NULL,
  `field_3` varchar(255) NULL,
  `field_5` bigint unsigned NULL,
  `field_5_copy` bigint unsigned NULL,
  `field_4` varchar(255) NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fun_order_field_1` (`field_1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='order';
