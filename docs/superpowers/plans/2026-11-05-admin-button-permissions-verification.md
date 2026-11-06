# 后台公共按钮权限验证记录

## 范围与结果

- 公共入口：`web/src/shared/utils/permission.ts` 的 `hasPermission` 与 `checkPermission`；`v-perm` 复用同一判断。
- 覆盖系统管理、应用中心、业务开发、AI、动态表单、插件中心及插件业务操作；共享 CRUD、上传组件和新生成代码同样接入。
- 字段授权不在本次范围；保留原有业务规则、数据范围及插件激活限制。
- `npm run build:standalone`（含 vue-tsc 类型检查）：通过。
- `git diff --check`：通过。
- 后端权限相关定向 PHPUnit：44 tests、132 assertions，通过（包括六个插件开发动作别名的独立授权检查）。
- 独立审查确认设计入口误依赖删除权限、上传循环等待期间撤权等问题已修复，单独复测 35 项通过。

## 前端全量回归

`npm test -- --reporter=json`：971 tests，871 passed，100 failed。

与本任务前保存的基线相比，新增失败 0 项。任务前基线为 917 tests、799 passed、118 failed；本轮还修复了直接用于真实生成权限行为验证的测试路径，18 个生成宿主用例恢复执行。

以下公共与代表业务权限测试均通过：

| 文件 | 通过 |
| --- | --- |
| `tests/composables/useCrud.spec.ts` | 18 |
| `tests/cms/commonPermission.spec.ts` | 6 |
| `src/shared/directives/permission.spec.ts` | 4 |
| `src/shared/utils/permission.spec.ts` | 6 |
| `src/admin/views/applications/actionPermission.spec.ts` | 4 |
| `src/admin/modules/cms/components/permission.spec.ts` | 4 |
| `src/admin/modules/market/components/permission.spec.ts` | 2 |
| `src/admin/views/form/runtime/generatedListButtons.spec.ts` | 18 |
| `src/admin/views/system/attachment/uploadPermission.spec.ts` | 2 |
| `src/admin/views/system/config/actionPermission.spec.ts` | 3 |
| `src/admin/views/system/plugin/permission.spec.ts` | 3 |
| `src/admin/views/system/user/statusPermission.spec.ts` | 6 |
| `src/admin/views/form/designer/components/VersionHistoryDrawer.permission.spec.ts` | 1 |
| `src/admin/views/system/config/components/ConfigValueDialog.permission.spec.ts` | 5 |

## 前端剩余失败名称

这些失败主要涉及迁移前的文件路径、PHP 测试工作目录、Storage 环境和旧路由期望值；本次未做全仓历史测试迁移。

### tests/contracts/admin-email-length.spec.ts

- 会员分组关联后端契约 SystemMember 通过关联表过滤并保存分组
- 会员分组关联后端契约 Member 登录不写入不存在的 login_time 字段
- 会员分组关联后端契约 SystemMemberGroup 通过关联表检查会员组引用
- 管理员邮箱最大长度契约 前端管理员表单限制邮箱最多 60 个字符
- 管理员邮箱最大长度契约 后端拒绝超过 60 个字符的管理员邮箱

### tests/contracts/auth-service-decomposition.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/admin/route/app.php'

### tests/contracts/backend-controller-layout.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/admin/route/app.php'

### tests/contracts/columns-empty-submenu.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/layout/index.vue'

### tests/contracts/console-commands.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/config/console.php'

### tests/contracts/crud-model-resolution.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/common/traits/Crud.php'

### tests/contracts/dialog-viewport.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/styles/index.scss'

### tests/contracts/element-button-theme.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/styles/index.scss'

### tests/contracts/element-select-focus.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/styles/index.scss'

### tests/contracts/field-hygiene-migration.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/database/migrations/archive/013_field_hygiene.sql'

### tests/contracts/form-error-typography.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/styles/index.scss'

### tests/contracts/form-publish.spec.ts

- 统一表单发布引擎契约 提供发布状态迁移与模型 JSON 配置
- 统一表单发布引擎契约 通过独立工厂把表单元数据转换为 CRUD Definition
- 统一表单发布引擎契约 动态发布与正式生成统一由 Business API 暴露
- 统一表单发布引擎契约 多级表单控制器发布权限可被 nodeAccess 正确解析
- 统一表单发布引擎契约 CRUD Definition 接受完整筛选、格式化和布局元数据
- 统一表单发布引擎契约 基本信息提供所属菜单选择，并将选择纳入发布配置
- 统一表单发布引擎契约 设计器隔离动态发布与正式生成，并对冲突 fail closed
- 统一表单发布引擎契约 表单设计器仅保留基础模式，移除高级模式专属入口和状态
- 统一表单发布引擎契约 正式生成复用服务端 operation，并在响应不确定时按 generationId 收敛
- 统一表单发布引擎契约 正式生成按钮维持 generate 与 apply-resources 权限边界
- 统一表单发布引擎契约 生成菜单优先独立源码并使用预置发布宿主兜底
- 统一表单发布引擎契约 动态发布 routePath 可命中发布宿主并传递 formKey 路径参数
- 统一表单发布引擎契约 发布兜底宿主严格使用已发布 canonical FormSchema v2 运行态

### tests/contracts/frontend-performance.spec.ts

- 前端性能契约 生产构建启用静态资源压缩
- 前端性能契约 首次鉴权并行获取用户信息和菜单
- 前端性能契约 限制 keep-alive 实例数量
- 前端性能契约 列表缩略图使用懒加载
- 前端性能契约 ECharts 不注册未使用的图表和功能组件

### tests/contracts/laravel-field-cutover.spec.ts

- ENOENT: no such file or directory, scandir '/Users/admin/min/funadmin/database/migrations/archive'

### tests/contracts/legacy-schema-debt.spec.ts

- 017 历史命名与会员标签治理 新增规范管理员字段并回填旧数据
- 017 历史命名与会员标签治理 创建单数语言表和语义准确的地区表并迁移数据
- 017 历史命名与会员标签治理 语言数据回填兼容 legacy 与 Laravel 时间字段
- 017 历史命名与会员标签治理 会员标签使用标签表和关联表承载
- 017 历史命名与会员标签治理 应用代码切换到规范管理员字段
- 017 历史命名与会员标签治理 语言和地区模型显式绑定规范表
- 017 历史命名与会员标签治理 会员 API 与前端通过 tagIds 管理标签关系

### tests/contracts/legacy-time-columns-drop.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/database/migrations/001_baseline.sql'

### tests/contracts/login-brand-spacing.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/login/index.vue'

### tests/contracts/m6-plugin-standard.spec.ts

- M6 插件标准 Admin Web 契约 使用 system/plugin 标准 API 文件并保留完整端点
- M6 插件标准 Admin Web 契约 plugin.json 只使用源码构建 adminWeb 契约
- M6 插件标准 Admin Web 契约 Admin Web 源码与公开资源分别发布并要求重新构建
- M6 插件标准 Admin Web 契约 仅从构建期 src/modules 组件清单加载插件页面
- M6 插件标准 Admin Web 契约 Manifest 与 Schema 只读取 adminWeb 并保留安全静态校验
- M6 插件标准 Admin Web 契约 插件 API 的未知异常不向客户端泄露内部消息
- M6 插件标准 Admin Web 契约 页面具备计划中的安装、配置、生命周期、市场和账号组件

### tests/contracts/m6-upgrade.spec.ts

- M6 系统升级契约 通过 forward migration 注册规范权限和菜单字段

### tests/contracts/m7-cutover.spec.ts

- M7 Laravel 字段与旧入口收缩契约 管理应用从 backend 完整硬切为 admin
- M7 Laravel 字段与旧入口收缩契约 模型基类明确使用 bigint id 与 Laravel 时间字段
- M7 Laravel 字段与旧入口收缩契约 M8 删除全部旧后台入口与认证兼容层
- M7 Laravel 字段与旧入口收缩契约 插件菜单与权限仅从 adminWeb 契约注册
- M7 Laravel 字段与旧入口收缩契约 普通 MigrationService 不扫描 maintenance contract 且 legacy contract 已移除

### tests/contracts/member-group-icon.spec.ts

- 会员组图标字段 migration 为会员组表新增 icon 列
- 会员组图标字段 后端接收、校验并返回 icon
- 会员组图标字段 前端弹窗提供图标选择且列表展示图标

### tests/contracts/member-registration-admin-scope.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/common/model/Member.php'

### tests/contracts/menu-icon-field.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/system/menu/components/MenuFormDialog.vue'

### tests/contracts/migration-service-legacy-preflight.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/common/service/MigrationService.php'

### tests/contracts/operation-log-middleware.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/admin/middleware/SystemLog.php'

### tests/contracts/operation-log-tag-columns.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/system/log/operation.vue'

### tests/contracts/parent-select-label.spec.ts

- 父级选择器顶级文案 src/views/system/menu/components/MenuFormDialog.vue 使用文案表示值 0
- 父级选择器顶级文案 src/views/system/permission/components/PermissionFormDialog.vue 使用文案表示值 0
- 父级选择器顶级文案 src/views/system/dept/components/DeptFormDialog.vue 使用文案表示值 0
- 父级选择器顶级文案 src/views/system/attachment/components/AttachmentGroupDialog.vue 使用文案表示值 0

### tests/contracts/plugin-code-name-cutover.spec.ts

- 插件 code/name 字段硬切契约 前端插件业务类型只暴露 code/name
- 插件 code/name 字段硬切契约 API 标识参数与更新检查 payload 使用 code
- 插件 code/name 字段硬切契约 插件中心表格与操作统一使用 row.code
- 插件 code/name 字段硬切契约 动态模块 descriptor 的插件标识使用 code，Vue route name 保持不变

### tests/contracts/plugin-page.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/system/plugin/index.vue'

### tests/contracts/plugin-permissions-registry.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/admin/service/ResourceRegistryService.php'

### tests/contracts/plugin-route-helper.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/common/plugin/sdk/PluginRoute.php'

### tests/contracts/schema-integrity-migration.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/database/migrations/archive/006_schema_integrity.sql'

### tests/contracts/search-form-button-order.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/components/SearchForm/index.vue'

### tests/contracts/service-cache-invalidation.spec.ts

- Service 公共缓存失效能力 AbstractService 提供受保护的全局缓存清理方法
- Service 公共缓存失效能力 ResourceRegistryService 不再定义私有 clearCache
- Service 公共缓存失效能力 PluginService 复用公共缓存清理能力

### tests/contracts/storage-driver.spec.ts

- 附件存储驱动契约 上传请求由浏览器生成 multipart boundary
- 附件存储驱动契约 附件页展示并可选择后台存储驱动
- 附件存储驱动契约 上传结果返回实际存储驱动

### tests/contracts/time-columns-no-default.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/database/migrations/archive/021_time_columns_no_default.sql'

### tests/contracts/title-to-name-migration.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/database/migrations/archive/015_title_to_name.sql'

### tests/contracts/top-menu-focus.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/styles/index.scss'

### tests/contracts/upload-ext-contract.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/common/service/UploadService.php'

### tests/contracts/upload-security-contract.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/app/common/service/UploadService.php'

### tests/contracts/user-management-menu.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/database/migrations/archive/014_user_management_menu.sql'

### src/admin/api/development/ai.spec.ts

- AI 真实 HTTP 响应边界 安全成功 mock 经真实 API 上传、下载、绑定；未知请求绝不回退网络
- AI 真实 HTTP 响应边界 档案 API 使用真实路径、方法和 snake_case，模型 ID 不转整数
- AI 真实 HTTP 响应边界 真实会话 PUT 保留覆盖及 null，不改写为 default 或省略
- AI 真实 HTTP 响应边界 列表和单记录只解包一次，所有会话及分组入口统一 ID 类型

### src/admin/layout/components/Tabs.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/admin/styles/ep-icons.css'

### src/shared/components/DataTable/pageSchema.spec.ts

- 公共页面声明 CRUD 日期区间、主键、排序、树表及工具双端协议
- 公共页面声明 Builder 显式主键及分页开关编译后保真
- 公共页面声明 两端拒绝非法 CRUD 展示能力 {"primaryKey":"__proto__"}
- 公共页面声明 两端拒绝非法 CRUD 展示能力 {"columns":[{"key":"price","label":"价格","sortable":"custom"}]}
- 公共页面声明 两端拒绝非法 CRUD 展示能力 {"list":{"tree":{"enabled":true}}}
- 公共页面声明 两端拒绝非法 CRUD 展示能力 {"list":{"tools":{"refresh":"yes"}}}
- 公共页面声明 两端拒绝非法 CRUD 展示能力 {"pagination":{"pageSize":20,"pageSizes":[20],"enabled":0}}
- 公共页面声明 接收 PHP 编译产物，空集合保持为空
- 公共页面声明 同一 JSON 输入由 PHP 与 TS 共同执行正反例矩阵

### src/shared/components/DataTable/unifiedPages.spec.ts

- 统一页面入口 data 列表只使用公共表格与按钮
- 统一页面入口 published 列表只使用公共表格与按钮
- 统一页面入口 published 详情复用共享展示并保持敏感字段过滤

### src/admin/views/applications/application-center.spec.ts

- 企业应用中心 只允许 published 应用进入且不拼接 token
- 企业应用中心 启动被准入策略拒绝时明确显示 403 提示且不跳转
- 企业应用中心 提供卡片列表、统计搜索、应用管理和完整设置抽屉
- 企业应用中心 只展示 schema 支持的 visibility 并明确说明启动语义
- 企业应用中心 提供 API、mock 和中英文 i18n

### src/admin/views/applications/phase8.spec.ts

- SSO Phase 8 管理控制台与应用门户 SSO 页面提供三模式、固定保存与完整自检
- SSO Phase 8 管理控制台与应用门户 门户按 assignment 准入目录展示并通过 launch API 无 token 跳转
- SSO Phase 8 管理控制台与应用门户 Identity API 类型和 mock 覆盖全部管理端点
- SSO Phase 8 管理控制台与应用门户 migration 菜单复用既有权限并匹配真实控制器 action

### src/admin/views/form/FormBackendContract.spec.ts

- 表单后端领域契约 Repeatable 子表与父表使用同连接事务并校验子行归属
- 表单后端领域契约 数据列表支持完整筛选操作符

### src/admin/views/development/ai/aiPage.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/development/ai/index.vue'

### src/admin/views/development/business/records.spec.ts

- BusinessRecords 全局模式通过 modules API 选择模块，并展示模块名称标识与返回入口

### src/admin/views/applications/oauth/oauth-client.spec.ts

- OAuth Client 管理 一次性 secret 只保存在内存且关闭时清空
- OAuth Client 管理 提供 URI 校验、scope/grant、secret 与 key rotation
- OAuth Client 管理 应用设置 OAuth tab 不再是假占位

### src/admin/views/form/runtime/listButtonWiring.spec.ts

- 实际页面按钮接线 动态页共享组件接入行、顶部及工具，不保留硬编码编辑按钮

### src/admin/views/form/validation/formSchemaDataValidator.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/tests/fixtures/form-schema-v2-validation.json'

### src/admin/views/form/components/SchemaForm.spec.ts

- PHP 生成 schema 的 renderer 能力 字典与上传开关 false 保留关联、只读、日期能力
- PHP 生成 schema 的 renderer 能力 字典与上传开关 true 保留关联、只读、日期能力

### src/admin/views/system/menu/index.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/system/menu/index.vue'

### src/admin/views/development/ai/components/AiComposer.spec.ts

- 独立 composer 边界 页面应使用独立输入组件，不保留无锁的内联发送表单

### src/admin/views/development/ai/components/aiComponents.spec.ts

- AI Development components 矮窗口使用真实 Drawer DOM 和编译后 CSS，正文可整体滚动而非被固定栏夹断
- AI Development components 档案使用紧凑响应式布局、真实 EP 表单与独立底栏，能力默认折叠
- AI Development components 档案在 1080px 下应用紧凑间距及响应式字段列数
- AI Development components 档案在 390px 下应用紧凑间距及响应式字段列数

### src/admin/views/development/business/components/GenerationPlanView.spec.ts

- GenerationPlanView 真实 PHP 授权预览响应能显示 SQL 和受管 Manifest 增量

### src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts

- Command failed: php -r require 'vendor/autoload.php'; echo json_encode(\app\admin\service\MemberFormDefinition::build(['groups'=>[['id'=>7,'name'=>'会员组']], 'levels'=>[['id'=>3,'name'=>'会员等级']], 'tags'=>[['id'=>5,'name'=>

### src/admin/views/system/member/components/MemberV2.spec.ts

- Command failed: php -r require 'vendor/autoload.php'; echo json_encode(\app\admin\service\MemberFormDefinition::build(['groups'=>[['id'=>7,'name'=>'组']], 'levels'=>[['id'=>3,'name'=>'等级']], 'tags'=>[]]), JSON_THROW_ON_ER

### src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts

- ENOENT: no such file or directory, open '/Users/admin/min/funadmin/web/src/views/system/menu/components/MenuFormDialog.vue'

## 后端全量回归

`vendor/bin/phpunit --no-progress`：854 tests、6400 assertions，5 errors、4 failures、108 skipped；本次相关权限和生成用例无失败。

剩余失败/错误涉及以下测试（完整名称供复查）：

- `Tests\Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`
- `Tests\Console\CoreDatabaseAuditTest::test_manifest_reads_only_root_baseline_declarations_without_executing_seeds`
- `Tests\Console\CoreMigrationSourceTest::test_default_source_is_repository_root_not_webman_php_migrations`
- `Tests\Console\CoreMigrationSourceTest::test_upgrade_manifest_requires_complete_signed_authoritative_core_sql`
- `Tests\Console\CoreMigrationSourceTest::test_signed_zip_is_rejected_without_core_contract_and_uses_mounted_sql_not_local_php`
- `Tests\AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`
- `Tests\BusinessModuleLayoutTest::test_backend_application_imports_resolve_to_declared_classes`
- `Tests\Console\UpgradeRecoveryExecutionTest::test_pre_deploy_stale_task_releases_slot_and_repeat_is_noop`
- `Tests\LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`

主要原因：macOS 私有附件临时目录经过符号链接；核心 SQL 目录仍指向迁移前路径；授权路由摘要基线变化；CMS 并行改动中的组合导入被旧静态检查识别；升级恢复任务租约用例失败；残留 ThinkPHP 引用。没有为本任务调整这些无关协议或修改测试断言掩盖错误。

## 当前工作区限制

- `etst` 仅找到已发布前端，没有找到可核对的后端控制器/插件清单；保留其既有 `etst:item:list` 权限，不猜测新增动作码。
- CMS 源插件正被并行改为独立页面和站点角色体系，当前发布运行副本仍是旧结构。两侧各自接入适合其权限来源的公共判断；没有把新版源目录覆盖到旧运行目录。后续安装/更新 CMS 时需要按该插件自己的迁移与发布流程验证。
- CMS 源使用 `checkPermission` 检查当前站点有效权限和宿主权限上限；保留站点切换、超级管理员及角色授权边界。
- 应用中心组合保存包含多个后端请求；等待期间撤权会阻止后续请求，已经完成的请求不会由前端回滚。
- 按钮会响应当前前端权限状态的更新。服务器仍逐请求执行 RBAC；本任务不新增后端权限变更推送机制。
- 本次没有 commit 或 push；用户已有其他未提交工作保留。
