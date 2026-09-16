-- 清理旧版 system/core 权限树，并将 Admin Web 权限资源名称统一为中文。
DELETE FROM `fun_admin_menu`
WHERE `source_type` = 'system'
  AND `source_name` = 'core';

DELETE FROM `fun_permission`
WHERE `source_type` = 'system'
  AND `source_name` = 'core';

UPDATE `fun_permission`
SET `name` = CASE `source_name`
    WHEN 'system' THEN '系统管理'
    WHEN 'role' THEN '角色管理'
    WHEN 'department' THEN '部门管理'
    WHEN 'user' THEN '管理员管理'
    WHEN 'menu' THEN '菜单管理'
    WHEN 'profile' THEN '个人资料'
    WHEN 'operation_log' THEN '操作日志'
    WHEN 'permission' THEN '权限资源'
    WHEN 'blacklist' THEN '黑名单管理'
    WHEN 'language' THEN '多语言管理'
    WHEN 'member_group' THEN '会员组管理'
    WHEN 'member_level' THEN '会员等级管理'
    WHEN 'member' THEN '会员管理'
    WHEN 'attachment' THEN '附件管理'
    WHEN 'attachment_group' THEN '附件分组管理'
    WHEN 'config' THEN '配置管理'
    WHEN 'dictionary' THEN '字典管理'
    WHEN 'plugin_center' THEN '插件中心'
    WHEN 'development_crud' THEN 'CRUD 生成器'
    ELSE `name`
END,
`updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `resource_type` = 'group';

UPDATE `fun_permission`
SET `name` = CASE `act`
    WHEN 'index' THEN '查看列表'
    WHEN 'tree' THEN '查看树形数据'
    WHEN 'detail' THEN '查看详情'
    WHEN 'options' THEN '查看选项'
    WHEN 'create' THEN '新增'
    WHEN 'update' THEN '编辑'
    WHEN 'delete' THEN '删除'
    WHEN 'status' THEN '修改状态'
    WHEN 'recycle' THEN '查看回收站'
    WHEN 'restore' THEN '恢复'
    WHEN 'destroy' THEN '彻底删除'
    WHEN 'import' THEN '导入'
    WHEN 'export' THEN '导出'
    WHEN 'permissions' THEN '分配权限'
    WHEN 'resetpassword' THEN '重置密码'
    WHEN 'password' THEN '修改密码'
    WHEN 'upload' THEN '上传文件'
    WHEN 'types' THEN '查看字典类型'
    WHEN 'createtype' THEN '新增字典类型'
    WHEN 'updatetype' THEN '编辑字典类型'
    WHEN 'deletetype' THEN '删除字典类型'
    WHEN 'deletetypes' THEN '批量删除字典类型'
    WHEN 'items' THEN '查看字典项'
    WHEN 'createitem' THEN '新增字典项'
    WHEN 'updateitem' THEN '编辑字典项'
    WHEN 'deleteitem' THEN '删除字典项'
    WHEN 'deleteitems' THEN '批量删除字典项'
    WHEN 'batch' THEN '批量查询字典'
    WHEN 'groups' THEN '查看配置分组'
    WHEN 'creategroup' THEN '新增配置分组'
    WHEN 'updategroup' THEN '编辑配置分组'
    WHEN 'deletegroup' THEN '删除配置分组'
    WHEN 'value' THEN '修改配置值'
    WHEN 'rename' THEN '重命名'
    WHEN 'move' THEN '移动'
    WHEN 'connections' THEN '查看数据源'
    WHEN 'tables' THEN '查看数据表'
    WHEN 'tableschema' THEN '查看表结构'
    WHEN 'infer' THEN '推断字段'
    WHEN 'validate' THEN '验证定义'
    WHEN 'preview' THEN '预览生成结果'
    WHEN 'generate' THEN '生成文件'
    WHEN 'generationdetail' THEN '查看生成记录'
    WHEN 'list' THEN '使用功能'
    WHEN 'overwrite' THEN '覆盖文件'
    ELSE `name`
END,
`updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `resource_type` = 'route';
