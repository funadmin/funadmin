-- 016 附件存储驱动：补充对象键、扩展路径长度并修正上传配置。

ALTER TABLE `fun_attach`
  ADD COLUMN `storage_key` varchar(768) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '存储对象键' AFTER `name`,
  MODIFY COLUMN `thumb` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '缩略图',
  MODIFY COLUMN `path` varchar(768) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '访问路径',
  MODIFY COLUMN `url` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '完整地址',
  MODIFY COLUMN `ext` varchar(20) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT '后缀',
  MODIFY COLUMN `driver` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'local' COMMENT '存储驱动';

UPDATE `fun_attach`
SET `storage_key` = TRIM(LEADING '/' FROM REPLACE(`path`, '/storage/', ''))
WHERE `driver` = 'local' AND `storage_key` = '' AND `path` LIKE '/storage/%';

UPDATE `fun_config`
SET `value` = 'local', `type` = 'select', `extra` = 'local:本地存储', `remark` = '附件存储驱动'
WHERE `code` = 'upload_driver' AND `group` = 'upload';

UPDATE `fun_config`
SET `value` = 'mp4,mp3,png,gif,jpg,jpeg,webp,rar,zip,7z,tar,gz,csv,xls,xlsx,pdf,doc,docx,ppt,pptx,txt',
    `remark` = '允许上传的文件扩展名'
WHERE `code` = 'upload_file_type' AND `group` = 'upload';

INSERT IGNORE INTO `fun_permission`
(`id`, `pid`, `module`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`, `sort`, `source_type`, `source_name`, `create_time`, `update_time`)
VALUES
(308, 283, 'backend', 'backend/systemstorage:index', 'backend/systemstorage', 'index', 'List storage drivers', 'route', 1, 0, 70, 'admin_web', 'attachment', 0, NULL),
(309, 283, 'backend', 'backend/systemstorage:update', 'backend/systemstorage', 'update', 'Configure storage driver', 'route', 1, 0, 80, 'admin_web', 'attachment', 0, NULL);
