-- 060 同步表单多级控制器权限资源到 Casbin 策略。

UPDATE `fun_casbin_rule`
SET `v2` = CASE
      WHEN `v2` = 'console/formdesigner' THEN 'console/form.designer'
      WHEN `v2` = 'console/formdata' THEN 'console/form.data'
      ELSE `v2`
    END
WHERE `ptype` = 'p'
  AND `v2` IN ('console/formdesigner', 'console/formdata');

UPDATE `fun_casbin_rule`
SET `rule_hash` = SHA2(CONCAT_WS(CHAR(31), `ptype`, `v0`, `v1`, `v2`, `v3`), 256)
WHERE `ptype` = 'p'
  AND `v2` IN ('console/form.designer', 'console/form.data');
