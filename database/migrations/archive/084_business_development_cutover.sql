-- 084 Unified business development cutover; disable legacy menus and permissions after equivalent grants were copied by 083.
UPDATE `fun_admin_menu`
SET `status`=0,`updated_at`=NOW()
WHERE `source_type`='admin_web'
  AND `source_name` IN ('form_management','development_crud')
  AND `status`<>0;

UPDATE `fun_permission`
SET `status`=0,`updated_at`=NOW()
WHERE `source_type`='admin_web'
  AND `source_name` IN ('form_management','development_crud')
  AND `status`<>0;
