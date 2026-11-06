-- 记录管理员已确认的插件 Composer 冲突清单指纹；不改写现有插件状态。
ALTER TABLE `fun_plugin`
  ADD COLUMN `composer_conflict_ack` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '已确认的 Composer 冲突清单 SHA-256';
