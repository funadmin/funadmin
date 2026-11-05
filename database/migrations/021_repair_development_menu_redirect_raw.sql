-- 021：补偿修复“开发工具”目录的失效 redirect。
-- 006 只匹配 URL 编码后的 %2F；历史安装数据也可能保存原始斜杠。
-- 仅替换仍指向已退役 CRUD 生成器的受管菜单，避免覆盖二次开发配置。
UPDATE `fun_admin_menu`
SET `query` = REPLACE(
        REPLACE(`query`, 'redirect=%2Fdevelopment%2Fcrud', 'redirect=%2Fdevelopment%2Fbusiness%2Fmine'),
        'redirect=/development/crud',
        'redirect=/development/business/mine'
    ),
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'development_tools'
  AND (
      `query` LIKE '%redirect=/development/crud%'
      OR `query` LIKE '%redirect=%2Fdevelopment%2Fcrud%'
  )
  AND `deleted_at` IS NULL;
