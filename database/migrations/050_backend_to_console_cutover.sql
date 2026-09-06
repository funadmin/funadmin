-- 管理应用从 backend 完整硬切为 console。
-- 仅迁移应用标识与权限资源前缀；admin_web 来源标识和业务权限代码保持不变。

UPDATE `fun_permission`
SET `module` = 'console'
WHERE `module` = 'backend';

UPDATE `fun_permission`
SET `code` = REPLACE(`code`, 'backend/', 'console/')
WHERE `code` LIKE 'backend/%';

UPDATE `fun_permission`
SET `obj` = REPLACE(`obj`, 'backend/', 'console/')
WHERE `obj` LIKE 'backend/%';

UPDATE `fun_admin_menu`
SET `module` = 'console'
WHERE `module` = 'backend';

UPDATE `fun_admin_menu`
SET `href` = REPLACE(`href`, 'backend/', 'console/')
WHERE `href` = 'backend' OR `href` LIKE 'backend/%';

UPDATE `fun_casbin_rule`
SET `v2` = REPLACE(`v2`, 'backend/', 'console/')
WHERE `ptype` = 'p' AND `v2` LIKE 'backend/%';

UPDATE `fun_casbin_rule`
SET `rule_hash` = SHA2(CONCAT_WS(CHAR(31), `ptype`, `v0`, `v1`, `v2`, `v3`), 256)
WHERE `ptype` = 'p';
