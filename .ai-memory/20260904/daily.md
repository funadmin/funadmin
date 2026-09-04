
## [10:43] - 配置变更/数据库迁移: 完成 RBAC、Admin Web CRUD 与旧后台模块清理

- **文件**: app/backend/controller/AdminProfile.php; app/backend/controller/SystemOperationLog.php; app/backend/service/{AuthService,DataScopeService,RoleGuardService}.php; app/backend/route/app.php; database/migrations/002_rbac_schema.sql; database/migrations/004_organization_rbac_schema.sql; admin-web/src/views/profile/index.vue; admin-web/src/views/system/log/operation.vue; admin-web/scripts/crud-gen.mjs
- **决策**: 角色继承与数据范围分离；非超级管理员按继承分支和等级双重限制；CRUD 统一使用 JSON 配置生成 Admin Web API 与 Vue 页面；旧管理员、角色、操作日志 Layui 入口删除，仍有调用方的权限资源和其他业务 Layui 暂保留。
- **验证**: PHP 8.4 全量 lint 192 个文件通过；Admin Web Vitest 23/23 通过；vue-tsc、Vite production build、Composer validate、migration 安全/结构扫描、CRUD dry-run、HTTP 未登录 401 JSON 契约均通过。

## [11:12] - 功能清理: 删除 Admin Web 演示功能与已下线旧模块残留

- **文件**: admin-web/src/{api,components,mock,views}; admin-web/package.json; admin-web/package-lock.json; app/backend/lang/zh-cn/{auth,sys}; public/admin-web
- **决策**: 删除无真实后端支撑的通知、第三方绑定、登录日志、上传/富文本/拖拽演示；保留真实业务使用的 Upload、SortableJS、ECharts；保留尚未迁移模块依赖的 Layui 公共运行时。
- **验证**: Vitest 23/23、vue-tsc、Vite build、191 个 PHP lint、git diff --check、旧引用扫描全部通过；生产资源 19 个、public/admin-web 约 2.2MB。

## [12:45] - 功能迁移: 权限资源与黑名单切换到 Admin Web

- **文件**: `app/backend/controller/SystemPermission.php`、`app/backend/controller/SystemBlacklist.php`、`app/backend/route/app.php`、`app/backend/controller/AdminAuth.php`、`admin-web/src/api/system/{permission,blacklist}.ts`、`admin-web/src/views/system/{permission,blacklist}/`、`admin-web/src/mock/modules/{permission,blacklist}.ts`、`database/migrations/{002_rbac_schema,004_organization_rbac_schema}.sql`
- **决策**: 权限资源仅超级管理员维护；黑名单保留软删、回收站、恢复、永久删除及 CSV 导入导出；删除旧 `auth.Auth` 与 `sys.Blacklist` Layui 实现；保留配置权限 ID 163；HTTP 验证固定使用 PHP 8.4，避免 PHP 8.5 与 ThinkPHP 8.1.3 不兼容。
- **验证**: 前端 23/23 测试通过，`vue-tsc` 通过，Vite 构建通过；195 个 PHP 文件在 PHP 8.4 下 lint 通过；跨 migration 135 个权限/20 个菜单主键、父子与绑定校验通过；新权限与黑名单路由未登录均返回 401 JSON；剩余 Layui 相关文件 58 个。

## [16:40] - 功能迁移/清理: 完成会员、附件库与配置管理迁移并下线旧入口

- **文件**: `app/backend/controller/{SystemMember,SystemMemberLevel,SystemAttachment,SystemAttachmentGroup,SystemConfig,AdminUpload}.php`、`app/backend/route/app.php`、`app/backend/controller/AdminAuth.php`、`admin-web/src/{api,mock,views}/system/{member,member-level,attachment,config}`、`database/migrations/{002_rbac_schema,004_organization_rbac_schema}.sql`、旧 Layui 会员/配置/附件分组入口
- **决策**: 保留会员/附件/配置运行时模型；附件旧文件选择器兼容链路暂留；附件物理删除增加共享路径保护；配置值保持字符串语义并按 `config.group` 保护分组；插件管理和系统升级因涉及远程包、文件覆盖、数据库迁移及回滚暂不清理。
- **验证**: Admin Web 28/28 测试、`vue-tsc`、Vite production build、PHP 8.4 全量 lint 131 个文件、RBAC/旧引用/diff 校验通过；PHP 8.4 临时服务验证上传、附件、附件分组、配置、配置分组 5 个路由未登录均返回 HTTP 401 JSON；临时服务和 `public/install.lock` 已清理。
