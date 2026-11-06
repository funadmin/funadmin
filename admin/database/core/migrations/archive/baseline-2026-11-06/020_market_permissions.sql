INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`sort_order`)
SELECT 0,'admin',CONCAT('market:',desired.object,':',desired.action),CONCAT('market/',desired.object),desired.action,CONCAT('插件市场 / ',desired.object,':',desired.action),'route',1,0,'plugin','market',30
FROM (
  SELECT 'plugin' AS object,'view' AS action
  UNION ALL SELECT 'plugin','manage'
  UNION ALL SELECT 'category','manage'
  UNION ALL SELECT 'grant','manage'
  UNION ALL SELECT 'order','view'
  UNION ALL SELECT 'order','manage'
  UNION ALL SELECT 'setting','manage'
  UNION ALL SELECT 'stat','view'
) AS desired
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` existing WHERE existing.app_name='admin' AND existing.code=CONCAT('market:',desired.object,':',desired.action));
