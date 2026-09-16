-- 仅隐藏业务创建侧栏项；保留路由、权限绑定、状态及其他查询元数据。
-- 末尾标记由 parse_str 解析为最终值，重复执行不再追加。
UPDATE `fun_admin_menu`
SET `query` = CONCAT(`query`, '&hidden=1')
WHERE `app_name` = 'console'
  AND `source_type` = 'admin_web'
  AND `source_name` = 'business_development'
  AND `href` IN ('visual', 'database')
  AND `query` NOT LIKE '%&hidden=1';