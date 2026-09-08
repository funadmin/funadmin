-- 收敛插件 publication registry 根前缀；开发阶段不保留旧命名兼容。
UPDATE `fun_plugin_resource`
SET `target_path` = CONCAT('public:', `target_path`)
WHERE `resource_type` = 'file'
  AND `target_path` NOT LIKE 'public:%'
  AND `target_path` NOT LIKE 'admin-web:%';

UPDATE `fun_plugin_resource`
SET `target_path` = CONCAT('application:', SUBSTRING(`target_path`, 5)),
    `publication_unit` = CONCAT('application:', SUBSTRING(`publication_unit`, 5))
WHERE `resource_type` = 'native_app'
  AND `publication_unit` LIKE 'app:%'
  AND `target_path` LIKE 'app/%';

UPDATE `fun_plugin_resource`
SET `target_path` = CONCAT(
      REPLACE(`publication_unit`, 'console:', 'console-plugin:'),
      '/',
      SUBSTRING(`target_path`, LENGTH(CONCAT(
        'app/console/',
        SUBSTRING_INDEX(SUBSTRING_INDEX(`publication_unit`, ':', 2), ':', -1),
        '/plugin/',
        SUBSTRING_INDEX(`publication_unit`, ':', -1),
        '/'
      )) + 1)
    ),
    `publication_unit` = REPLACE(`publication_unit`, 'console:', 'console-plugin:')
WHERE `resource_type` = 'native_app'
  AND `publication_unit` LIKE 'console:%'
  AND `target_path` LIKE 'app/console/%';