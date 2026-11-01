-- 管理员主部门保留在 fun_admin.dept_id，全部任职部门规范化到关联表。
CREATE TABLE IF NOT EXISTS `fun_admin_department` (
  `admin_id` bigint unsigned NOT NULL COMMENT '管理员ID',
  `dept_id` bigint unsigned NOT NULL COMMENT '任职部门ID',
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`admin_id`,`dept_id`),
  KEY `idx_admin_department_dept` (`dept_id`),
  CONSTRAINT `fk_admin_department_admin` FOREIGN KEY (`admin_id`) REFERENCES `fun_admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admin_department_dept` FOREIGN KEY (`dept_id`) REFERENCES `fun_department` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员任职部门关系';

INSERT IGNORE INTO `fun_admin_department` (`admin_id`, `dept_id`, `created_at`, `updated_at`)
SELECT `id`, `dept_id`, NOW(), NOW() FROM `fun_admin` WHERE `dept_id` > 0;
