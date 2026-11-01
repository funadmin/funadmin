-- Generated forward migration; review before applying.
CREATE TABLE IF NOT EXISTS `test` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_1` varchar(255) NULL,
  `field_2` varchar(500) NULL,
  `field_3` int NULL,
  `field_4` tinyint unsigned NULL,
  `field_5` varchar(255) NULL,
  `field_6` bigint unsigned NULL,
  `field_7` varchar(100) NULL,
  `field_8` tinyint(1) NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='test';
