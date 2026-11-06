-- 移除企业身份中心（identity）模块。与 PHP 迁移 037_remove_identity_module.php 等价，可重复执行。
-- 1) 撤销授权并软删除菜单与权限（保留行以便追溯）；2) 删除只属于身份中心的 35 张表（均为空或仅含影子副本）。

DELETE r FROM `fun_casbin_rule` r JOIN `fun_permission` p ON r.`ptype` = 'p' AND r.`v2` = p.`obj` AND r.`v3` = p.`act`
WHERE p.`app_name`='admin' AND (p.`obj` LIKE 'admin/identity.%' OR p.`obj` LIKE 'identity/%' OR p.`code` LIKE 'identity:%');
UPDATE `fun_admin_menu` SET `status`=0, `deleted_at`=COALESCE(`deleted_at`, CURRENT_TIMESTAMP), `updated_at`=CURRENT_TIMESTAMP WHERE `app_name`='admin' AND (`href`='/applications' OR `href` LIKE '/applications/%' OR `source_name` IN ('enterprise_application_center','oauth_client_console','sso_phase8_application_center'));
UPDATE `fun_permission` SET `status`=0, `deleted_at`=COALESCE(`deleted_at`, CURRENT_TIMESTAMP), `updated_at`=CURRENT_TIMESTAMP WHERE `app_name`='admin' AND (`obj` LIKE 'admin/identity.%' OR `obj` LIKE 'identity/%' OR `code` LIKE 'identity:%');

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `fun_application_assignment`;
DROP TABLE IF EXISTS `fun_application_database`;
DROP TABLE IF EXISTS `fun_application_domain`;
DROP TABLE IF EXISTS `fun_application_permission`;
DROP TABLE IF EXISTS `fun_application_role`;
DROP TABLE IF EXISTS `fun_application_role_permission`;
DROP TABLE IF EXISTS `fun_application_user_permission`;
DROP TABLE IF EXISTS `fun_application_user_role`;
DROP TABLE IF EXISTS `fun_backchannel_logout_delivery`;
DROP TABLE IF EXISTS `fun_client_grant`;
DROP TABLE IF EXISTS `fun_client_scope`;
DROP TABLE IF EXISTS `fun_client_secret`;
DROP TABLE IF EXISTS `fun_enterprise_application`;
DROP TABLE IF EXISTS `fun_identity_admin_link`;
DROP TABLE IF EXISTS `fun_identity_audit_log`;
DROP TABLE IF EXISTS `fun_identity_consent`;
DROP TABLE IF EXISTS `fun_identity_credential`;
DROP TABLE IF EXISTS `fun_identity_login_attempt`;
DROP TABLE IF EXISTS `fun_identity_member_link`;
DROP TABLE IF EXISTS `fun_identity_migration_audit`;
DROP TABLE IF EXISTS `fun_identity_sso_config`;
DROP TABLE IF EXISTS `fun_identity_tenant`;
DROP TABLE IF EXISTS `fun_identity_user`;
DROP TABLE IF EXISTS `fun_identity_user_department`;
DROP TABLE IF EXISTS `fun_oauth_authorization`;
DROP TABLE IF EXISTS `fun_oauth_authorization_code`;
DROP TABLE IF EXISTS `fun_oauth_authorization_scope`;
DROP TABLE IF EXISTS `fun_oauth_client`;
DROP TABLE IF EXISTS `fun_oauth_token`;
DROP TABLE IF EXISTS `fun_oauth_token_scope`;
DROP TABLE IF EXISTS `fun_oidc_client_session`;
DROP TABLE IF EXISTS `fun_oidc_session`;
DROP TABLE IF EXISTS `fun_oidc_signing_key`;
DROP TABLE IF EXISTS `fun_redirect_uri`;
DROP TABLE IF EXISTS `fun_scope`;
SET FOREIGN_KEY_CHECKS = 1;
