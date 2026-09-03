-- 迁移 004 不提供自动 DOWN：旧 CSV 数据已不再写入，无法无损逆向恢复。
-- 回滚方式：恢复执行迁移 004 前的数据库备份，并回滚应用代码。
SELECT 'restore the pre-migration database backup to roll back migration 004' AS `manual_rollback_required`;
