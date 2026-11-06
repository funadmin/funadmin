-- Local operator/server module retired. Historical data and compatibility token tables remain.
UPDATE `fun_permission` SET `status`=0, `updated_at`=CURRENT_TIMESTAMP
WHERE `app_name`='admin' AND ((`source_type`='plugin' AND `source_name`='market') OR `code` LIKE 'market:%');
UPDATE `fun_admin_menu` SET `status`=0, `updated_at`=CURRENT_TIMESTAMP
WHERE `app_name`='admin' AND ((`source_type`='plugin' AND `source_name`='market')
 OR `href`='/plugin/market' OR `href` LIKE '/plugin/market/%'
 OR `href`='/admin/plugin/market' OR `href` LIKE '/admin/plugin/market/%');
