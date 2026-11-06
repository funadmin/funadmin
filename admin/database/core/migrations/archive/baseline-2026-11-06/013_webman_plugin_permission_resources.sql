-- 新增原生插件控制器只读资源，不改写旧资源、不复制 Casbin、不扩大任何普通角色授权。
INSERT INTO `fun_permission` (`pid`, `app_name`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`, `source_type`, `source_name`, `sort_order`)
SELECT 0, 'admin', desired.code, 'admin/plugin.systemplugin', desired.act, desired.name, 'route', 1, 0, 'admin_web', 'plugin_center', 30
FROM (
  SELECT 'plugin.systemplugin:installed' AS code, 'installed' AS act, '插件中心 / 已安装' AS name
  UNION ALL SELECT 'plugin.systemplugin:discovered', 'discovered', '插件中心 / 本地发现'
  UNION ALL SELECT 'plugin.systemplugin:localdetail', 'localdetail', '插件中心 / 本地详情'
  UNION ALL SELECT 'plugin.systemplugin:enabledmodules', 'enabledmodules', '插件中心 / 已启用模块'
) AS desired
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` existing WHERE existing.app_name = 'admin' AND existing.code = desired.code);
