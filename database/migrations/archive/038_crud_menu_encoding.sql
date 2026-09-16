-- 修复 CRUD Workbench 菜单名称曾以错误连接字符集写入后产生的乱码。
-- 使用稳定的 source_type/source_name 定位，避免依赖环境相关的自增 ID。
UPDATE `fun_admin_menu`
SET `name` = CONVERT(0xE5BC80E58F91E5B7A5E585B7 USING utf8mb4), `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'development_tools'
  AND BINARY `name` <> 0xE5BC80E58F91E5B7A5E585B7;

UPDATE `fun_admin_menu`
SET `name` = CONVERT(0x43525544E7949FE68890E599A8 USING utf8mb4), `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'development_crud'
  AND BINARY `name` <> 0x43525544E7949FE68890E599A8;
