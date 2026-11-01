-- 074 后续修正：仅使用正式字段，并规范合法开关值；不得修改已执行的 074。
INSERT INTO `fun_field_type` (`name`,`title`,`default_define`,`isoption`,`rules`,`status`,`sort_order`,`created_at`,`updated_at`)
SELECT 'date','日期','date NULL',0,'',1,24,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_field_type` WHERE `name`='date' AND `status`=1);
INSERT INTO `fun_field_type` (`name`,`title`,`default_define`,`isoption`,`rules`,`status`,`sort_order`,`created_at`,`updated_at`)
SELECT 'json','JSON','json NULL',0,'',1,25,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_field_type` WHERE `name`='json' AND `status`=1);

UPDATE `fun_config` SET `value`=IF(`value` IN ('1','true','on'),'1','0'), `extra`='' WHERE `code`='site_state' AND `type`='switch';
UPDATE `fun_config` SET `value`=IF(`value` IN ('1','true','on'),'1','0'), `extra`='' WHERE `code`='site_reloadiframe' AND `type`='switch';
