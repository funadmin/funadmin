-- funadmin-physical-table
-- Generated forward migration; review before applying.
CREATE TABLE IF NOT EXISTS `example_test` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_1` varchar(255) NULL,
  `field_2` varchar(500) NULL,
  `field_3` varchar(100) NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='test';
-- funadmin-crud-schema: eyJmaWVsZHMiOnsiY3JlYXRlZF9hdCI6eyJkZWZpbml0aW9uIjoiZGF0ZXRpbWUgTlVMTCIsImluZGV4IjoiIn0sImRlbGV0ZWRfYXQiOnsiZGVmaW5pdGlvbiI6ImRhdGV0aW1lIE5VTEwiLCJpbmRleCI6IiJ9LCJmaWVsZF8xIjp7ImRlZmluaXRpb24iOiJ2YXJjaGFyKDI1NSkgTlVMTCIsImluZGV4IjoiIn0sImZpZWxkXzIiOnsiZGVmaW5pdGlvbiI6InZhcmNoYXIoNTAwKSBOVUxMIiwiaW5kZXgiOiIifSwiZmllbGRfMyI6eyJkZWZpbml0aW9uIjoidmFyY2hhcigxMDApIE5VTEwiLCJpbmRleCI6IiJ9LCJpZCI6eyJkZWZpbml0aW9uIjoiYmlnaW50IHVuc2lnbmVkIE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5UIiwiaW5kZXgiOiIifSwidXBkYXRlZF9hdCI6eyJkZWZpbml0aW9uIjoiZGF0ZXRpbWUgTlVMTCIsImluZGV4IjoiIn19LCJ0YWJsZSI6ImV4YW1wbGVfdGVzdCJ9
