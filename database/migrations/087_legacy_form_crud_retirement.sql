-- 087 Retire legacy form management and CRUD Workbench product entries without removing business data or schemas.
SET @plugin_permission_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='plugin_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

-- Preserve the exact plugin options grants before changing permission ownership.
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT legacy.`ptype`,legacy.`v0`,legacy.`v1`,'console/development.devplugin','options','','',
       SHA2(CONCAT_WS(CHAR(31),legacy.`ptype`,legacy.`v0`,legacy.`v1`,'console/development.devplugin','options'),256)
FROM `fun_casbin_rule` legacy
WHERE legacy.`ptype`='p'
  AND legacy.`v2`='console/development.devplugin'
  AND legacy.`v3`='options';

UPDATE `fun_permission`
SET `pid`=@plugin_permission_id,`source_type`='admin_web',`source_name`='plugin_center',
    `status`=1,`deleted_at`=NULL,`updated_at`=NOW()
WHERE @plugin_permission_id IS NOT NULL
  AND `code`='development:plugin:options'
  AND `obj`='console/development.devplugin'
  AND `act`='options';

UPDATE `fun_admin_menu`
SET `status`=0,`deleted_at`=COALESCE(`deleted_at`,NOW()),`updated_at`=NOW()
WHERE `source_type`='admin_web'
  AND `source_name` IN ('form_list','form_designer','development_crud')
  AND (`status`<>0 OR `deleted_at` IS NULL);

UPDATE `fun_permission`
SET `status`=0,`deleted_at`=COALESCE(`deleted_at`,NOW()),`updated_at`=NOW()
WHERE `source_type`='admin_web'
  AND `source_name` IN ('form_management','development_crud')
  AND (`status`<>0 OR `deleted_at` IS NULL);

DELETE FROM `fun_casbin_rule`
WHERE `ptype`='p'
  AND `v2` IN ('console/form.designer','console/form.full-publish','form/publish','console/devcrud','development/crud');
