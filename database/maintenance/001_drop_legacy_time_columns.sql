-- M7 maintenance contract：仅供显式维护窗口使用，普通 MigrationService 不扫描本目录。
-- 执行前必须完成备份、migration repository 审计与 Laravel 字段一致性校验。

SET @schema_name = DATABASE();

-- 具体 contract DDL 在 M8 经维护入口批准后补充并执行。
-- 契约占位示例（不可直接执行）：ALTER TABLE `<table>` DROP COLUMN `<legacy_column>`;
