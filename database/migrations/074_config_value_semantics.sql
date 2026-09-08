-- 配置项按业务语义收敛字段类型、选项定义和值，避免设置值页面退化为纯文本输入。
INSERT INTO `fun_field_type` (`name`,`title`,`sort`,`default_define`,`isoption`,`rules`,`status`,`sort_order`,`created_at`,`updated_at`)
SELECT 'date','日期',24,'date NULL',0,'',1,24,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_field_type` WHERE `name`='date' AND `status`=1);
INSERT INTO `fun_field_type` (`name`,`title`,`sort`,`default_define`,`isoption`,`rules`,`status`,`sort_order`,`created_at`,`updated_at`)
SELECT 'json','JSON',25,'json NULL',0,'',1,25,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_field_type` WHERE `name`='json' AND `status`=1);

UPDATE `fun_config` SET `type`='switch', `value`='0', `extra`='' WHERE `code`='site_state';
UPDATE `fun_config` SET `type`='switch', `value`=IF(`value` IN ('1','true','on'),'1','0'), `extra`='' WHERE `code`='app_debug';
UPDATE `fun_config` SET `type`='switch', `value`=IF(`value` IN ('1','true','on'),'1','0'), `extra`='' WHERE `code`='site_tabicon';
UPDATE `fun_config` SET `type`='image' WHERE `code`='site_licence';
UPDATE `fun_config` SET `type`='tags' WHERE `code`='upload_file_type';
UPDATE `fun_config` SET `type`='number' WHERE `code`='upload_file_max';
UPDATE `fun_config` SET `type`='select', `value`=IF(`value` IN ('0','1','2'),`value`,'0'), `extra`='0:关闭\n1:图片水印\n2:文字水印' WHERE `code`='upload_water';
UPDATE `fun_config` SET `type`='select' WHERE `code`='upload_water_position';
UPDATE `fun_config` SET `type`='number' WHERE `code`='upload_water_alpha';
UPDATE `fun_config` SET `type`='number' WHERE `code`='upload_water_size';
UPDATE `fun_config` SET `type`='switch' WHERE `code`='upload_chunk';
UPDATE `fun_config` SET `type`='number' WHERE `code`='upload_chunksize';
UPDATE `fun_config` SET `type`='switch', `value`='0', `extra`='0:关闭\n1:开启' WHERE `code`='site_reloadiframe';
UPDATE `fun_config` SET `type`='select' WHERE `code`='upload_editor';
UPDATE `fun_config` SET `type`='select' WHERE `code`='site_layer_offset';
UPDATE `fun_config` SET `type`='select', `extra`='0:默认\nslideLeft:左侧滑入' WHERE `code`='site_layer_anim';
UPDATE `fun_config` SET `type`='select' WHERE `code`='export_type';
UPDATE `fun_config` SET `verify`='email' WHERE `code`='site_email';
