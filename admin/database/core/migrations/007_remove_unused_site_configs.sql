-- funadmin-physical-prefix
-- 清理 site 组中 Layui 框架遗留的零引用配置项。
-- 这些 key 在前端/后端均无运行时读取，仅存在于历史播种迁移中。
-- site_theme        : Layui 后台主题切换（已迁移至 Vue SPA，无读取方）
-- site_reloadiframe : Layui iframe 重载开关（admin-web 不使用 iframe 布局）
-- site_layer_offset : Layui layer 弹窗位置（无读取方）
-- site_layer_width  : Layui layer 弹窗宽度（无读取方）
-- site_layer_height : Layui layer 弹窗高度（无读取方）
-- site_layer_anim   : Layui layer 弹窗动画（无读取方）
-- export_type       : Layui 导出类型选择（无读取方）

DELETE FROM `fun_config` WHERE `group` = 'site' AND `code` IN (
    'site_theme',
    'site_reloadiframe',
    'site_layer_offset',
    'site_layer_width',
    'site_layer_height',
    'site_layer_anim',
    'export_type'
) AND deleted_at IS NULL;
