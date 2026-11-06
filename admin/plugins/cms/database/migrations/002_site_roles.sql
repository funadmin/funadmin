-- CMS site roles; generated from SiteRoleSchema.php.
create table `fun_cms_site_role` (`id` bigint unsigned not null auto_increment primary key, `site_id` bigint unsigned not null, `code` varchar(64) not null, `name` varchar(100) not null, `status` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null, `deleted_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `fun_cms_site_role` add unique `cms_site_role_site_id_code_unique`(`site_id`, `code`);
create table `fun_cms_site_role_permission` (`site_id` bigint unsigned not null, `role_id` bigint unsigned not null, `permission_code` varchar(100) not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `fun_cms_site_role_permission` add unique `cms_role_permission_unique`(`site_id`, `role_id`, `permission_code`);
create table `fun_cms_site_role_member` (`site_id` bigint unsigned not null, `role_id` bigint unsigned not null, `admin_id` bigint unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `fun_cms_site_role_member` add unique `cms_role_member_unique`(`site_id`, `role_id`, `admin_id`);
alter table `fun_cms_site_role_member` add index `cms_site_role_member_site_id_admin_id_index`(`site_id`, `admin_id`);
create table `fun_cms_site_auth_audit` (`id` bigint unsigned not null auto_increment primary key, `site_id` bigint unsigned not null, `actor_admin_id` bigint unsigned not null, `target_type` varchar(30) not null, `target_id` bigint unsigned not null, `operation` varchar(40) not null, `before_state` json not null, `after_state` json not null, `created_at` datetime not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `fun_cms_site_auth_audit` add index `cms_site_auth_audit_site_id_created_at_index`(`site_id`, `created_at`);
