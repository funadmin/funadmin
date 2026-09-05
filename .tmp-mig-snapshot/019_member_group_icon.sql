-- 会员组图标：存储 i-ep-* 图标类名，空字符串表示不设置图标
ALTER TABLE `fun_member_group` ADD COLUMN `icon` varchar(50) NOT NULL DEFAULT '' COMMENT '分组图标(i-ep-*图标类名)' AFTER `name`;