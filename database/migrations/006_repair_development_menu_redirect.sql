-- 006：修复"开发工具"目录菜单的失效 redirect。
-- CRUD 生成器菜单（/development/crud）已于 2026-09-10 软删除退役，但父级目录菜单
-- query 元数据中仍存 redirect=/development/crud，点击面包屑"开发工具"跳转 404。
-- 现网第一个存活叶子是"业务开发"（/development/business/mine）。
-- 守卫式替换：仅当存储值仍为失效的 /development/crud 时才更新，幂等可重跑，不覆盖二开。
UPDATE `fun_admin_menu`
SET `query` = REPLACE(`query`, 'redirect=%2Fdevelopment%2Fcrud', 'redirect=%2Fdevelopment%2Fbusiness%2Fmine'),
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'development_tools'
  AND `query` LIKE '%redirect=%2Fdevelopment%2Fcrud%'
  AND `deleted_at` IS NULL;
