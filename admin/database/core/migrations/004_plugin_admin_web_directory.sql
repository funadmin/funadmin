-- 004 插件前端统一目录：插件后台前端从 web/src/admin/modules/{code} 迁到 web/src/admin/plugins/{code}。
-- 与 PHP 迁移 database/migrations/004_plugin_admin_web_directory.php 终态一致；只改发布登记路径，不改文件哈希；全部语句可重复执行。
-- 不迁移会导致插件生命周期校验登记时报「前端资源登记必须使用统一目录 src/admin/plugins/{code}/」。
UPDATE `fun_plugin_resource`
SET `target_path`=CONCAT('web:src/admin/plugins/', SUBSTRING(`target_path`, CHAR_LENGTH('web:src/admin/modules/') + 1)), `updated_at`=NOW()
WHERE `target_path` LIKE 'web:src/admin/modules/%';

UPDATE `fun_plugin_resource`
SET `source_path`=CONCAT('web/src/admin/plugins/', SUBSTRING(`source_path`, CHAR_LENGTH('web/src/admin/modules/') + 1)), `updated_at`=NOW()
WHERE `source_path` LIKE 'web/src/admin/modules/%';
