-- 096 企业 SSO Phase 2 企业应用中心；只向前、可重复执行。
CREATE TABLE IF NOT EXISTS `fun_enterprise_application` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `public_id` char(36) NOT NULL,
  `code` varchar(64) NOT NULL, `name` varchar(100) NOT NULL, `description` varchar(500) NOT NULL DEFAULT '',
  `icon` varchar(255) NULL, `sort_order` int NOT NULL DEFAULT 0, `visibility` enum('private','tenant','public') NOT NULL DEFAULT 'tenant',
  `runtime_type` enum('internal','plugin','standalone') NOT NULL, `database_mode` enum('shared','dedicated','external') NOT NULL DEFAULT 'shared',
  `launch_url` varchar(2048) NOT NULL, `base_url` varchar(2048) NULL, `owner` varchar(100) NULL,
  `status` enum('draft','published','disabled') NOT NULL DEFAULT 'draft', `logo_url` varchar(255) NULL,
  `brand_config` json NULL, `oauth_config` json NULL, `published_at` datetime NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_enterprise_application_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_enterprise_application_public` (`tenant_id`,`public_id`),
  UNIQUE KEY `uk_enterprise_application_code` (`tenant_id`,`code`), KEY `idx_enterprise_application_status` (`tenant_id`,`status`,`deleted_at`),
  CONSTRAINT `fk_enterprise_application_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业应用目录';

CREATE TABLE IF NOT EXISTS `fun_application_database` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL,
  `mode` enum('shared','dedicated','external') NOT NULL DEFAULT 'shared', `credential_ref` varchar(255) NULL,
  `health_path` varchar(1024) NULL, `health_status` enum('unknown','healthy','unhealthy') NOT NULL DEFAULT 'unknown',
  `last_checked_at` datetime NULL, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_database_app` (`tenant_id`,`application_id`), KEY `idx_application_database_app` (`application_id`),
  CONSTRAINT `fk_application_database_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_application_database_app_tenant` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业应用数据模式';

CREATE TABLE IF NOT EXISTS `fun_application_assignment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL,
  `subject_type` enum('all','user','department','role') NOT NULL, `subject_id` bigint unsigned NULL,
  `subject_key` bigint unsigned GENERATED ALWAYS AS (IFNULL(`subject_id`,0)) STORED,
  `effect` enum('allow','deny') NOT NULL DEFAULT 'allow', `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_assignment` (`tenant_id`,`application_id`,`subject_type`,`subject_key`,`effect`),
  KEY `idx_application_assignment_subject` (`tenant_id`,`subject_type`,`subject_id`,`status`), KEY `idx_application_assignment_app` (`application_id`),
  CONSTRAINT `fk_application_assignment_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_application_assignment_app_tenant` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_application_assignment_subject` CHECK ((`subject_type`='all' AND `subject_id` IS NULL) OR (`subject_type`<>'all' AND `subject_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业应用访问范围';

CREATE TABLE IF NOT EXISTS `fun_application_domain` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL,
  `domain_type` enum('web','admin','api','identity_callback','logout_callback') NOT NULL DEFAULT 'web',
  `scheme` enum('https','http') NOT NULL DEFAULT 'https', `host` varchar(253) NOT NULL, `port` smallint unsigned NOT NULL DEFAULT 443,
  `path` varchar(1024) NOT NULL DEFAULT '/', `identity_callback_path` varchar(1024) NULL, `logout_callback_path` varchar(1024) NULL,
  `is_primary` tinyint NOT NULL DEFAULT 0, `verified_at` datetime NULL, `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_domain_exact` (`tenant_id`,`application_id`,`domain_type`,`scheme`,`host`,`port`,`path`(191)),
  KEY `idx_application_domain_app` (`tenant_id`,`application_id`,`status`),
  CONSTRAINT `fk_application_domain_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_application_domain_app_tenant` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业应用受控域名';

SET @console_root_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='console_root' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@console_root_id,0),'console',NULL,'','',CONVERT(X'E4BC81E4B89AE5BA94E794A8E4B8ADE5BF83' USING utf8mb4),'group',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),70,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `resource_type`='group');
SET @application_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@application_group_id,'console','console/identity.enterpriseapplication:index','console/identity.enterpriseapplication','index','应用列表','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),10,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:detail','console/identity.enterpriseapplication','detail','应用详情','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),11,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:save','console/identity.enterpriseapplication','save','保存应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),20,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:update','console/identity.enterpriseapplication','update','更新应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),21,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:delete','console/identity.enterpriseapplication','delete','删除应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),22,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:publish','console/identity.enterpriseapplication','publish','发布应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),30,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:disable','console/identity.enterpriseapplication','disable','禁用应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),40,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:launch','console/identity.enterpriseapplication','launch','进入应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),41,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:assignments','console/identity.enterpriseapplication','assignments','访问范围','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),50,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:saveassignments','console/identity.enterpriseapplication','saveassignments','保存访问范围','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),51,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:domains','console/identity.enterpriseapplication','domains','域名设置','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),60,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:savedomains','console/identity.enterpriseapplication','savedomains','保存域名','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),61,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:database','console/identity.enterpriseapplication','database','数据模式','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),70,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:savedatabase','console/identity.enterpriseapplication','savedatabase','保存数据模式','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),71,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:health','console/identity.enterpriseapplication','health','健康检查','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),80,NULL),
(@application_group_id,'console','identity:application:view','identity/application','view','查看应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),110,NULL),
(@application_group_id,'console','identity:application:manage','identity/application','manage','管理应用','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),120,NULL);
SET @application_view_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.enterpriseapplication:index' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,@application_view_id,'console',CONVERT(X'E4BC81E4B89AE5BA94E794A8E4B8ADE5BF83' USING utf8mb4),'/applications','component=Layout&name=EnterpriseApplications&type=M&redirect=/applications/center&permission=identity:application:view','_self','i-ep-grid',1,'admin_web','enterprise_application_center',NOW(),NOW(),15,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `href`='/applications');
SET @application_menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `href`='/applications' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @application_menu_id,@application_view_id,'console',CONVERT(X'E5BA94E794A8E4B8ADE5BF83' USING utf8mb4),'center','component=applications/index&name=EnterpriseApplicationCenter&type=C&permission=identity:application:view','_self','i-ep-menu',1,'admin_web','enterprise_application_center',NOW(),NOW(),10,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `href`='center');
