-- 002：表单数据批量删除/恢复/永久删除/导入路由权限。
-- 列表按钮默认动作（批量删除、回收站、导入）接通后端新端点所需的 route 权限资源；
-- 超管不受影响，细粒度角色需显式授予后才可使用新能力。
SET @pid = (SELECT id FROM (SELECT id FROM fun_permission WHERE source_name = 'form_data' AND resource_type = 'group' AND deleted_at IS NULL LIMIT 1) t);

INSERT INTO fun_permission (pid, app_name, code, obj, act, name, resource_type, status, is_public, source_type, source_name, created_at, updated_at, sort_order)
SELECT @pid, 'admin', 'form.data:batchremove', 'admin/form.data', 'batchremove', '批量删除表单数据', 'route', 1, 0, 'admin_web', 'form_data', NOW(), NOW(), 46
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM fun_permission WHERE code = 'form.data:batchremove') t);

INSERT INTO fun_permission (pid, app_name, code, obj, act, name, resource_type, status, is_public, source_type, source_name, created_at, updated_at, sort_order)
SELECT @pid, 'admin', 'form.data:restore', 'admin/form.data', 'restore', '恢复表单数据', 'route', 1, 0, 'admin_web', 'form_data', NOW(), NOW(), 47
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM fun_permission WHERE code = 'form.data:restore') t);

INSERT INTO fun_permission (pid, app_name, code, obj, act, name, resource_type, status, is_public, source_type, source_name, created_at, updated_at, sort_order)
SELECT @pid, 'admin', 'form.data:destroy', 'admin/form.data', 'destroy', '永久删除表单数据', 'route', 1, 0, 'admin_web', 'form_data', NOW(), NOW(), 48
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM fun_permission WHERE code = 'form.data:destroy') t);

INSERT INTO fun_permission (pid, app_name, code, obj, act, name, resource_type, status, is_public, source_type, source_name, created_at, updated_at, sort_order)
SELECT @pid, 'admin', 'form.data:import', 'admin/form.data', 'import', '导入表单数据', 'route', 1, 0, 'admin_web', 'form_data', NOW(), NOW(), 49
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM fun_permission WHERE code = 'form.data:import') t);
