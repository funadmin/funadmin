-- 显式来源 + routeName + component 三重绑定；保留 ID、父级、名称、停用状态及全部授权。
UPDATE `fun_admin_menu` AS menu
JOIN (
  SELECT 'SystemDict' route_name, 'system/dict/index' component, 'dictionary' source_name, '/system/dict' path, 'systemdict:types' permission_code
  UNION ALL SELECT 'SystemRole','system/role/index','role','/system/role','systemrole:index'
  UNION ALL SELECT 'SystemDept','system/dept/index','department','/system/dept','systemdepartment:tree'
  UNION ALL SELECT 'SystemUser','system/user/index','user','/system/user','systemadmin:index'
  UNION ALL SELECT 'SystemMenu','system/menu/index','menu','/system/menu','systemmenu:tree'
  UNION ALL SELECT 'SystemLogOperation','system/log/operation','operation_log','/system/log/operation','systemoperationlog:index'
  UNION ALL SELECT 'SystemPermission','system/permission/index','permission','/system/permission','systempermission:tree'
  UNION ALL SELECT 'SystemBlacklist','system/blacklist/index','blacklist','/system/blacklist','systemblacklist:index'
  UNION ALL SELECT 'SystemLanguage','system/language/index','language','/system/language','systemlanguage:index'
  UNION ALL SELECT 'SystemMemberGroup','system/member-group/index','member_group','/users/member-group','systemmembergroup:index'
  UNION ALL SELECT 'SystemMemberLevel','system/member-level/index','member_level','/users/member-level','systemmemberlevel:index'
  UNION ALL SELECT 'SystemMember','system/member/index','member','/users/member','systemmember:index'
  UNION ALL SELECT 'SystemAttachment','system/attachment/index','attachment','/system/attachment','systemattachment:index'
  UNION ALL SELECT 'SystemConfig','system/config/index','config','/system/config','systemconfig:index'
  UNION ALL SELECT 'SystemPlugin','system/plugin/index','plugin_center','/plugin','plugin.systemplugin:installed'
  UNION ALL SELECT 'SystemUpgrade','system/upgrade/index','system_upgrade','/system/upgrade','systemupgrade:status'
  UNION ALL SELECT 'BusinessDevelopment','development/business/mine','business_development','/development/business/mine','development.business:modules'
  UNION ALL SELECT 'BusinessMine','development/business/mine','business_development','/development/business/','development.business:modules'
  UNION ALL SELECT 'BusinessVisual','development/business/visual','business_development','/development/business/visual','development.business:createvisual'
  UNION ALL SELECT 'BusinessDatabase','development/business/database','business_development','/development/business/database','development.business:inspectdatabase'
  UNION ALL SELECT 'BusinessRecords','development/business/records','business_development','/development/business/records','development.business:generations'
  UNION ALL SELECT 'AiDevelopment','development/ai/index','ai_development','/development/ai','development.ai:conversationindex'
  UNION ALL SELECT 'ApplicationList','applications/index','enterprise_application_center','/applications/center','identity.enterpriseapplication:index'
  UNION ALL SELECT 'OAuthClientManagement','applications/oauth/index','oauth_client_console','/applications/oauth-client','identity.oauthclient:index'
  UNION ALL SELECT 'DomainManagement','applications/identity-management','sso_phase8_application_center','/applications/domains','identity.enterpriseapplication:domains'
  UNION ALL SELECT 'SsoConfiguration','applications/sso/index','sso_phase8_application_center','/applications/sso','identity.ssoconfiguration:config'
  UNION ALL SELECT 'ScopeClaimManagement','applications/identity-management','sso_phase8_application_center','/applications/scopes','identity.scopeclaim:index'
  UNION ALL SELECT 'IdentityUsers','applications/identity-management','sso_phase8_application_center','/applications/users','identity.identityuser:index'
  UNION ALL SELECT 'IdentitySessions','applications/identity-management','sso_phase8_application_center','/applications/sessions','identity.oidcsession:index'
  UNION ALL SELECT 'SigningKeys','applications/identity-management','sso_phase8_application_center','/applications/signing-keys','identity.oidcsigningkey:index'
  UNION ALL SELECT 'IdentityAudit','applications/identity-management','sso_phase8_application_center','/applications/audit','identity.identityaudit:index'
  UNION ALL SELECT 'ApplicationPortal','applications/portal','sso_phase8_application_center','/applications/portal','identity.enterpriseapplication:portal'
) AS mapping ON menu.source_name = mapping.source_name
  AND LOCATE(CONCAT('&name=', mapping.route_name, '&'), CONCAT('&', menu.query, '&')) > 0
  AND (LOCATE(CONCAT('&component=', mapping.component, '&'), CONCAT('&', menu.query, '&')) > 0
    OR LOCATE(CONCAT('&component=', REPLACE(mapping.component, '/', '%2F'), '&'), CONCAT('&', menu.query, '&')) > 0)
JOIN `fun_permission` AS permission ON permission.app_name = 'admin'
  AND permission.code = mapping.permission_code AND permission.resource_type = 'route'
  AND permission.status = 1 AND permission.deleted_at IS NULL
SET menu.href = mapping.path,
    menu.permission_id = permission.id,
    menu.query = CONCAT(TRIM(BOTH '&' FROM REGEXP_REPLACE(menu.query, '(^|&)permission=[^&]*', '')), '&permission=', permission.code)
WHERE menu.app_name = 'admin' AND menu.source_type = 'admin_web';
