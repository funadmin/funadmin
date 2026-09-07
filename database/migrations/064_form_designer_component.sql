-- 064 修正表单设计器动态组件入口。
-- 真实文件位于 admin-web/src/views/form/designer/index.vue。

UPDATE `fun_admin_menu`
SET `query` = 'component=form/designer/index&name=FormDesigner&type=C&hidden=1',
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'form_designer';
