-- Generated forward migration; review before applying.
CREATE TABLE IF NOT EXISTS `shop_product` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_product_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品';
-- funadmin-crud-schema: eyJmaWVsZHMiOnsiY3JlYXRlZF9hdCI6eyJkZWZpbml0aW9uIjoiZGF0ZXRpbWUgTlVMTCIsImluZGV4IjoiIn0sImRlbGV0ZWRfYXQiOnsiZGVmaW5pdGlvbiI6ImRhdGV0aW1lIE5VTEwiLCJpbmRleCI6IiJ9LCJpZCI6eyJkZWZpbml0aW9uIjoiYmlnaW50IHVuc2lnbmVkIE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5UIiwiaW5kZXgiOiIifSwibmFtZSI6eyJkZWZpbml0aW9uIjoidmFyY2hhcigxMjApIE5PVCBOVUxMIiwiaW5kZXgiOiJpbmRleCJ9LCJwcmljZSI6eyJkZWZpbml0aW9uIjoiZGVjaW1hbCgxMCwyKSBOT1QgTlVMTCBERUZBVUxUIDAuMDAiLCJpbmRleCI6IiJ9LCJzdGF0dXMiOnsiZGVmaW5pdGlvbiI6InRpbnlpbnQoMSkgTk9UIE5VTEwgREVGQVVMVCAxIiwiaW5kZXgiOiIifSwidXBkYXRlZF9hdCI6eyJkZWZpbml0aW9uIjoiZGF0ZXRpbWUgTlVMTCIsImluZGV4IjoiIn19LCJ0YWJsZSI6InNob3BfcHJvZHVjdCJ9
