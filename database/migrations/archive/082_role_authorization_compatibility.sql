-- 082 兼容旧角色授权策略，仅向前复制新查看与保存策略；复制授权保持独立。
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT `ptype`,`v0`,`v1`,`v2`,'authorization','','',
       SHA2(CONCAT_WS(CHAR(31),`ptype`,`v0`,`v1`,`v2`,'authorization'),256)
FROM `fun_casbin_rule`
WHERE `ptype`='p' AND `v2`='console/systemrole' AND `v3`='permissions';

INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT `ptype`,`v0`,`v1`,`v2`,'saveauthorization','','',
       SHA2(CONCAT_WS(CHAR(31),`ptype`,`v0`,`v1`,`v2`,'saveauthorization'),256)
FROM `fun_casbin_rule`
WHERE `ptype`='p' AND `v2`='console/systemrole' AND `v3`='permissions';
