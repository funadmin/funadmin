-- 业务开发改为直接列表入口；仅匹配受管菜单稳定键，不修改权限、状态或业务数据。
UPDATE `fun_admin_menu`
SET `href` = 'business/mine',
    `query` = 'component=development/business/mine&name=BusinessDevelopment&type=C&permission=development:business:view&keepAlive=1'
WHERE `app_name` = 'console'
  AND `source_type` = 'admin_web'
  AND `source_name` = 'business_development'
  AND `href` = 'business';

-- 旧我的业务节点保留为目录 URL 兼容入口，避免与新的父菜单重复路径。
UPDATE `fun_admin_menu`
SET `href` = '/development/business/',
    `query` = CONCAT(`query`, '&hidden=1&redirect=/development/business/mine')
WHERE `app_name` = 'console'
  AND `source_type` = 'admin_web'
  AND `source_name` = 'business_development'
  AND `href` = 'mine';

-- 隐藏辅助页仍保留绝对 URL、组件和原权限绑定，避免继承列表路径。
UPDATE `fun_admin_menu`
SET `href` = CONCAT('/development/business/', `href`),
    `query` = CONCAT(`query`, '&hidden=1')
WHERE `app_name` = 'console'
  AND `source_type` = 'admin_web'
  AND `source_name` = 'business_development'
  AND `href` IN ('visual', 'database', 'records');
