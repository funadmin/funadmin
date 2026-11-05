# Webman 插件适配验证记录

> 历史验证记录。旧目录迁移兼容已按用户要求删除，当前目录规范见 `docs/plugins.md`，本轮验证见 `2026-11-05-plugin-layout-verification.md`。

日期：2026-11-05。修改前已运行全量基线，修改后再次运行相同命令。

## 结果

- 后端定向回归：106 项、99 通过、7 项 MySQL 环境测试跳过，1273 次断言。
- PHP 语法检查 `php tests/lint.php`：766 个文件通过。
- 前端类型检查 `npm run type-check`：通过。
- 前端构建 `npm run build:webman`：通过，输出到 `webman-admin/public/admin-web`。
- 路由、插件模块、表单组件加载器的定向 Vitest：7 个文件、31 项测试通过。
- 生成插件真实生命周期 HTTP 集成：安装、启用、普通用户访问、禁用、升级、再启用、卸载均通过（不含 SQL DDL）。
- 旧后台资源目录迁移、回滚、人工修改保护：通过。
- 普通用户模块发现与路由过滤、示例权限种子、禁用插件配置持久化：通过。
- `PLUGIN_ADMIN_WEB_PATH` 外部前端根目录配置：独立 PHP 进程验证通过。
- 后端全量 `php vendor/bin/phpunit`：684 项，1 个错误、2 个失败、98 项跳过；三个失败项均已存在于修改前基线。基线为 672 项、1 个错误、3 个失败、96 项跳过；缺少 SPA 构建产物造成的 InstallTest 失败已消失。
- 前端全量 `npm test -- --reporter=dot`：887 项，771 通过、116 失败；59 个文件失败。基线为 884 项，768 通过、116 失败。失败名称集合无新增，主要仍为旧目录路径和测试环境问题。

## 独立审查与处理

独立只读审查提出两个重要问题：模块目录接口要求插件中心权限、内置 example 缺少显式权限种子。均先复现再修复，对应 HTTP 回归通过。后续验证还修复了 Webman 根目录拼接及 Eloquent 配置写入问题。

保留 Manifest v2 和插件源包层级；后台发布到现有 business 模块目录，旧资源标识继续支持迁移和恢复。新增 SQL 仅登记 example 权限，不自动授予普通角色，也未在生产数据库执行。

## 验证边界

- 本机没有 Docker，真实 MySQL DDL 和对应隔离测试未运行。
- Worker 重载属于部署步骤，本次未向正在运行的服务发送重载信号。新增、更新 PHP 代码后需执行 `php start.php reload`；新增前端源码后需要重新构建。
- 外部配置路径已验证；尚未专门覆盖旧前端目录迁移与中断恢复的组合场景。已有资源发布恢复测试及完整新目录生命周期通过。
- 仓库中与本次调整无关的基线失败保留，名称见下文；未修改用户已有的管理员、AI 等工作。

## 后端保留的基线失败

- `Tests\Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`
- `Tests\AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`
- `Tests\LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`

原因依次为 AI 私有附件目录符号链接、authorization 路由摘要基线变化、插件之外仍残留的 ThinkPHP 引用。

## 前端保留的基线失败名称

以下同时列出失败套件和失败用例标题，方便定位现有测试债务。

- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 列表和单记录只解包一次，所有会话及分组入口统一 ID 类型`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 安全成功 mock 经真实 API 上传、下载、绑定；未知请求绝不回退网络`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 档案 API 使用真实路径、方法和 snake_case，模型 ID 不转整数`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 真实会话 PUT 保留覆盖及 null，不改写为 default 或省略`
- `src/admin/layout/components/Tabs.spec.ts [ src/admin/layout/components/Tabs.spec.ts ]`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 只允许 published 应用进入且不拼接 token`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 只展示 schema 支持的 visibility 并明确说明启动语义`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 启动被准入策略拒绝时明确显示 403 提示且不跳转`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 提供 API、mock 和中英文 i18n`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 提供卡片列表、统计搜索、应用管理和完整设置抽屉`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 一次性 secret 只保存在内存且关闭时清空`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 应用设置 OAuth tab 不再是假占位`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 提供 URI 校验、scope/grant、secret 与 key rotation`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > Identity API 类型和 mock 覆盖全部管理端点`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > SSO 页面提供三模式、固定保存与完整自检`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > 门户按 assignment 准入目录展示并通过 launch API 无 token 跳转`
- `src/admin/views/development/ai/aiPage.spec.ts [ src/admin/views/development/ai/aiPage.spec.ts ]`
- `src/admin/views/development/ai/components/AiComposer.spec.ts > 独立 composer 边界 > 页面应使用独立输入组件，不保留无锁的内联发送表单`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案使用紧凑响应式布局、真实 EP 表单与独立底栏，能力默认折叠`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案在 1080px 下应用紧凑间距及响应式字段列数`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案在 390px 下应用紧凑间距及响应式字段列数`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 矮窗口使用真实 Drawer DOM 和编译后 CSS，正文可整体滚动而非被固定栏夹断`
- `src/admin/views/development/business/components/GenerationPlanView.spec.ts > GenerationPlanView > 真实 PHP 授权预览响应能显示 SQL 和受管 Manifest 增量`
- `src/admin/views/development/business/records.spec.ts > BusinessRecords > 全局模式通过 modules API 选择模块，并展示模块名称标识与返回入口`
- `src/admin/views/form/FormBackendContract.spec.ts > 表单后端领域契约 > Repeatable 子表与父表使用同连接事务并校验子行归属`
- `src/admin/views/form/FormBackendContract.spec.ts > 表单后端领域契约 > 数据列表支持完整筛选操作符`
- `src/admin/views/form/components/SchemaForm.spec.ts > PHP 生成 schema 的 renderer 能力 > 字典与上传开关 false 保留关联、只读、日期能力`
- `src/admin/views/form/components/SchemaForm.spec.ts > PHP 生成 schema 的 renderer 能力 > 字典与上传开关 true 保留关联、只读、日期能力`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > hidden 字段即使配置 search 也不生成搜索绑定`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 关闭列表和详情仅影响展示，不作为 DTO 访问授权`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 分类树继承生成宿主，节点身份与来源版本不借动态入口`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 列表和详情特殊展示均可编译，详情选项中的插值符号与引号原样显示`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 发布版本失配禁止执行`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 后端 schema 接受 normal 和布尔 plain，拒绝错误类型和错误位置`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 回收站仅在能力启用时出现，恢复命中生成 API，显式空不补默认`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 安全元数据不重新引入敏感字段或关闭的列表详情字段`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 批删确认中选择或权限变化时取消，不使用确认后的新选择`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 批量回收站按钮使用独立批量权限而非单条权限`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 无批删能力仍可选择注册批动作，切换筛选后清空`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 显式空集合不恢复任何默认管理按钮`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 正式生成列表和详情接入共享字段展示 helper`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 正式生成默认工具栏按正常列表、回收站、新增、移入回收站、导入、导出顺序并保留样式元数据`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 生成 API、独立权限、snake/camel 主键与发布版本真实进入共享执行链`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 生成筛选转换为 snake 字段且变化清空注册选择`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 真实生成脚本与 API 在无批删、无软删和插件降级时通过类型检查`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 默认按钮真实渲染 plain、图标和禁用选择，状态入口重复点击不反转`
- `src/admin/views/form/runtime/listButtonWiring.spec.ts > 实际页面按钮接线 > 动态页共享组件接入行、顶部及工具，不保留硬编码编辑按钮`
- `src/admin/views/form/validation/formSchemaDataValidator.spec.ts [ src/admin/views/form/validation/formSchemaDataValidator.spec.ts ]`
- `src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts [ src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts ]`
- `src/admin/views/system/member/components/MemberV2.spec.ts [ src/admin/views/system/member/components/MemberV2.spec.ts ]`
- `src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts [ src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts ]`
- `src/admin/views/system/menu/index.spec.ts [ src/admin/views/system/menu/index.spec.ts ]`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > Builder 显式主键及分页开关编译后保真`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > CRUD 日期区间、主键、排序、树表及工具双端协议`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"columns":[{"key":"price","label":"价格","sortable":"custom"}]}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"list":{"tools":{"refresh":"yes"}}}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"list":{"tree":{"enabled":true}}}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"pagination":{"pageSize":20,"pageSizes":[20],"enabled":0}}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"primaryKey":"__proto__"}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 同一 JSON 输入由 PHP 与 TS 共同执行正反例矩阵`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 接收 PHP 编译产物，空集合保持为空`
- `src/shared/components/DataTable/unifiedPages.spec.ts > 统一页面入口 > data 列表只使用公共表格与按钮`
- `src/shared/components/DataTable/unifiedPages.spec.ts > 统一页面入口 > published 列表只使用公共表格与按钮`
- `src/shared/components/DataTable/unifiedPages.spec.ts > 统一页面入口 > published 详情复用共享展示并保持敏感字段过滤`
- `tests/contracts/admin-email-length.spec.ts > 会员分组关联后端契约 > Member 登录不写入不存在的 login_time 字段`
- `tests/contracts/admin-email-length.spec.ts > 会员分组关联后端契约 > SystemMember 通过关联表过滤并保存分组`
- `tests/contracts/admin-email-length.spec.ts > 会员分组关联后端契约 > SystemMemberGroup 通过关联表检查会员组引用`
- `tests/contracts/admin-email-length.spec.ts > 管理员邮箱最大长度契约 > 前端管理员表单限制邮箱最多 60 个字符`
- `tests/contracts/admin-email-length.spec.ts > 管理员邮箱最大长度契约 > 后端拒绝超过 60 个字符的管理员邮箱`
- `tests/contracts/auth-service-decomposition.spec.ts [ tests/contracts/auth-service-decomposition.spec.ts ]`
- `tests/contracts/backend-controller-layout.spec.ts [ tests/contracts/backend-controller-layout.spec.ts ]`
- `tests/contracts/columns-empty-submenu.spec.ts [ tests/contracts/columns-empty-submenu.spec.ts ]`
- `tests/contracts/console-commands.spec.ts [ tests/contracts/console-commands.spec.ts ]`
- `tests/contracts/crud-model-resolution.spec.ts [ tests/contracts/crud-model-resolution.spec.ts ]`
- `tests/contracts/dialog-viewport.spec.ts [ tests/contracts/dialog-viewport.spec.ts ]`
- `tests/contracts/element-button-theme.spec.ts [ tests/contracts/element-button-theme.spec.ts ]`
- `tests/contracts/element-select-focus.spec.ts [ tests/contracts/element-select-focus.spec.ts ]`
- `tests/contracts/field-hygiene-migration.spec.ts [ tests/contracts/field-hygiene-migration.spec.ts ]`
- `tests/contracts/form-error-typography.spec.ts [ tests/contracts/form-error-typography.spec.ts ]`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > CRUD Definition 接受完整筛选、格式化和布局元数据`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 动态发布 routePath 可命中发布宿主并传递 formKey 路径参数`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 动态发布与正式生成统一由 Business API 暴露`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 发布兜底宿主严格使用已发布 canonical FormSchema v2 运行态`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 基本信息提供所属菜单选择，并将选择纳入发布配置`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 多级表单控制器发布权限可被 nodeAccess 正确解析`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 提供发布状态迁移与模型 JSON 配置`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 正式生成复用服务端 operation，并在响应不确定时按 generationId 收敛`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 正式生成按钮维持 generate 与 apply-resources 权限边界`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 生成菜单优先独立源码并使用预置发布宿主兜底`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 表单设计器仅保留基础模式，移除高级模式专属入口和状态`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 设计器隔离动态发布与正式生成，并对冲突 fail closed`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 通过独立工厂把表单元数据转换为 CRUD Definition`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > ECharts 不注册未使用的图表和功能组件`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 列表缩略图使用懒加载`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 生产构建启用静态资源压缩`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 限制 keep-alive 实例数量`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 首次鉴权并行获取用户信息和菜单`
- `tests/contracts/laravel-field-cutover.spec.ts [ tests/contracts/laravel-field-cutover.spec.ts ]`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 会员 API 与前端通过 tagIds 管理标签关系`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 应用代码切换到规范管理员字段`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 语言和地区模型显式绑定规范表`
- `tests/contracts/legacy-time-columns-drop.spec.ts > legacy 时间列收口契约 > 最终基线直接使用 Laravel 字段且 legacy contract 已移除`
- `tests/contracts/legacy-time-columns-drop.spec.ts > legacy 时间列收口契约 > 运行时代码不再引用 legacy 时间列`
- `tests/contracts/login-brand-spacing.spec.ts [ tests/contracts/login-brand-spacing.spec.ts ]`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > Admin Web 源码与公开资源分别发布并要求重新构建`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > Manifest 与 Schema 只读取 adminWeb 并保留安全静态校验`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > plugin.json 只使用源码构建 adminWeb 契约`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 仅从构建期 src/modules 组件清单加载插件页面`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 使用 system/plugin 标准 API 文件并保留完整端点`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 插件 API 的未知异常不向客户端泄露内部消息`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 页面具备计划中的安装、配置、生命周期、市场和账号组件`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > M8 删除全部旧后台入口与认证兼容层`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 插件菜单与权限仅从 adminWeb 契约注册`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 普通 MigrationService 不扫描 maintenance contract 且 legacy contract 已移除`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 模型基类明确使用 bigint id 与 Laravel 时间字段`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 管理应用从 backend 完整硬切为 admin`
- `tests/contracts/member-group-icon.spec.ts > 会员组图标字段 > 前端弹窗提供图标选择且列表展示图标`
- `tests/contracts/member-group-icon.spec.ts > 会员组图标字段 > 后端接收、校验并返回 icon`
- `tests/contracts/member-registration-admin-scope.spec.ts [ tests/contracts/member-registration-admin-scope.spec.ts ]`
- `tests/contracts/menu-icon-field.spec.ts [ tests/contracts/menu-icon-field.spec.ts ]`
- `tests/contracts/migration-service-legacy-preflight.spec.ts [ tests/contracts/migration-service-legacy-preflight.spec.ts ]`
- `tests/contracts/operation-log-middleware.spec.ts [ tests/contracts/operation-log-middleware.spec.ts ]`
- `tests/contracts/operation-log-tag-columns.spec.ts [ tests/contracts/operation-log-tag-columns.spec.ts ]`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/attachment/components/AttachmentGroupDialog.vue 使用文案表示值 0`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/dept/components/DeptFormDialog.vue 使用文案表示值 0`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/menu/components/MenuFormDialog.vue 使用文案表示值 0`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/permission/components/PermissionFormDialog.vue 使用文案表示值 0`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > API 标识参数与更新检查 payload 使用 code`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > 前端插件业务类型只暴露 code/name`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > 动态模块 descriptor 的插件标识使用 code，Vue route name 保持不变`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > 插件中心表格与操作统一使用 row.code`
- `tests/contracts/plugin-page.spec.ts [ tests/contracts/plugin-page.spec.ts ]`
- `tests/contracts/plugin-permissions-registry.spec.ts [ tests/contracts/plugin-permissions-registry.spec.ts ]`
- `tests/contracts/plugin-route-helper.spec.ts [ tests/contracts/plugin-route-helper.spec.ts ]`
- `tests/contracts/schema-integrity-migration.spec.ts [ tests/contracts/schema-integrity-migration.spec.ts ]`
- `tests/contracts/search-form-button-order.spec.ts [ tests/contracts/search-form-button-order.spec.ts ]`
- `tests/contracts/service-cache-invalidation.spec.ts > Service 公共缓存失效能力 > AbstractService 提供受保护的全局缓存清理方法`
- `tests/contracts/service-cache-invalidation.spec.ts > Service 公共缓存失效能力 > PluginService 复用公共缓存清理能力`
- `tests/contracts/service-cache-invalidation.spec.ts > Service 公共缓存失效能力 > ResourceRegistryService 不再定义私有 clearCache`
- `tests/contracts/storage-driver.spec.ts > 附件存储驱动契约 > 上传结果返回实际存储驱动`
- `tests/contracts/storage-driver.spec.ts > 附件存储驱动契约 > 上传请求由浏览器生成 multipart boundary`
- `tests/contracts/storage-driver.spec.ts > 附件存储驱动契约 > 附件页展示并可选择后台存储驱动`
- `tests/contracts/title-to-name-migration.spec.ts > 015 title→name 改名迁移契约 > 前端路由转换从 routeName 取路由名、从 name 取标题`
- `tests/contracts/title-to-name-migration.spec.ts > 015 title→name 改名迁移契约 > 后端菜单 API 以 name 暴露名称并以 routeName 暴露路由名`
- `tests/contracts/title-to-name-migration.spec.ts > 015 title→name 改名迁移契约 > 角色与权限 API 统一使用 name 字段`
- `tests/contracts/top-menu-focus.spec.ts [ tests/contracts/top-menu-focus.spec.ts ]`
- `tests/contracts/upload-ext-contract.spec.ts [ tests/contracts/upload-ext-contract.spec.ts ]`
- `tests/contracts/upload-security-contract.spec.ts [ tests/contracts/upload-security-contract.spec.ts ]`
