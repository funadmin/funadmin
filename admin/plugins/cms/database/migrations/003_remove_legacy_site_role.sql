-- CMS 1.3.3：移除旧版「兼容旧成员」(legacy) 站点角色。站点成员只通过角色分配获得权限。
-- 只删除没有成员的 legacy 角色及其权限行；仍有成员的保留为普通自定义角色，避免静默撤销已授予的站点权限。
DELETE FROM `fun_cms_site_role_permission` WHERE `role_id` IN (SELECT `r`.`id` FROM `fun_cms_site_role` `r` WHERE `r`.`code`='legacy' AND NOT EXISTS (SELECT 1 FROM `fun_cms_site_role_member` `m` WHERE `m`.`role_id`=`r`.`id`));
DELETE FROM `fun_cms_site_role` WHERE `code`='legacy' AND NOT EXISTS (SELECT 1 FROM `fun_cms_site_role_member` `m` WHERE `m`.`role_id`=`fun_cms_site_role`.`id`);
