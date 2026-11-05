# CMS 插件验证记录

日期：2026-11-05。用户已确认设计并授权开发。交付为源码插件，未安装到当前运行站点，未执行生产数据库迁移、DNS/证书配置、服务重载、提交或推送。

## 交付位置

- 源包：`webman-admin/plugins/cms/`，Manifest v2。
- 最新后台目录：包内 `app/admin/plugins/cms/`，命名空间 `app\admin\plugins\cms`；管理 URL 保持 `/admin/plugin/cms/...`。
- 网站应用：`app/cms/`；管理前端：`admin-web/src/admin/modules/cms/`；普通发行静态资源：`public/plugin-assets/cms/public/`。
- 安装、域名入口和自定义 PHP/后台标签说明：插件 README。

## 已实现

同一实现支持单站单语言、单站多语言、多站多语言及混合语言配置；站点域名、语言与管理员范围；栏目及译文；五种内置和自定义字段模型；不可变内容修订、独立译文发布、版本回滚、下架、预览；完整路径、自定义首页、旧路径及分页直接跳转；导航树；服务端标签解释器、PHP provider、版本化后台标签/片段；默认主题、SEO、hreflang、sitemap、robots；管理端真实 API 和编辑页面。

## 定向验证结果

| 检查 | 结果 |
|---|---|
| `TEST_MYSQL=0 php vendor/bin/phpunit tests/Cms` | 57 项：55 通过、2 项仅在指定隔离 MySQL 时执行；258 次断言 |
| 独立 MySQL：MySqlSchemaTest + MySqlLockTest | 2 项通过，15 次断言；17 张表实际建表，路径唯一约束、语言/栏目/路由的进程间站点锁均验证 |
| `npx vitest run tests/cms` | 4 文件、6 项通过；包括单站自动上下文、混合站点语言切换、发布锁、译文响应竞态、模型字段采用 |
| `node scripts/check-cms.mjs` | 宿主与插件源码的 Vue/TypeScript 类型检查通过 |
| `node scripts/build-cms.mjs` | CMS 管理模块生产编译通过，输出临时目录；没有覆盖正在使用的 public |
| PHP 语法检查 | 32 个插件 PHP 文件及 4 个涉及的宿主文件通过 |
| 原生发布及启停 | 临时目录中发布新目录、注解路由解析、公开/管理禁用拦截通过 |
| 真实后台鉴权 | 浏览器会话、普通角色功能权限、CSRF、站点范围通过；保存和发布权限分别校验 |
| 插件归档/暂存 | 使用真实 PluginArchiveService 与 PluginPackageService，61 个文件的开发归档通过暂存校验；临时产物已清理 |
| 默认主题 | 本机 Edge 打开实际 SSR 生成的首页，HTML/CSS、本地 Vue 和 Element Plus 资源加载成功，阅读布局及普通搜索控件已查看 |

所有 MySQL 测试库以随机 `funadmin_cms_test_` 名称创建，测试后删除；不读取生产凭据。默认测试的两个 MySQL skip 不代表未验证，已另行显式执行。

## 独立审查与修复

独立审查没有 Critical 发现，提出的 9 类 Important 均已复现并修复：

1. 译文加载使用请求代次保护，失败恢复已加载语言；保存不会把旧响应和新语言组合。
2. 不允许停用域名成为主域名。
3. 默认语言、栏目树和路由所有权变更通过同一站点行锁串行化；MySQL 子进程验证阻塞和释放。
4. 保留历史字段定义，同时提供明确的“采用新版字段定义”操作；兼容值保留，删除/变更字段先展示确认，历史记录仍保留。
5. 详情模板优先级：内容指定、当前语言栏目、本站模型、detail；切换模型启用状态保留原模板设置。
6. 历史分页直接跳到当前相同页码，缺少尾斜杠的分页转到正式地址。
7. 标签草稿预览加载本站/当前语言已发布的其他自定义标签，并替换自身定义。
8. 保留 storage、上传、系统页面和 sitemap/robots 尾斜杠变体的空间。
9. 请求输入错误为 400，模板/主题配置故障为 503；不存在为 404。

导航隐藏分支及语言首页链接按实际功能影响提升优先级并修复。另增加输出大小限制、uint64 字符串 ID、栏目归属随译文修订发布的回归。

## 实施决定

- 在现有 Rbac 分支按 CMS 路径隔离新增工作：依赖用户尚未提交的新插件框架；另建干净 worktree 会遗漏这些改动。代价是验证需要跟进工作区的并行规范变化。
- 保留原模型版本，升级为显式操作：避免历史数据丢失；编辑者需要在希望使用新字段时主动采用。
- 不提交或重置共享暂存区：其中包含其他会话的改动；本次源代码保留在工作区供用户统一提交。
- 导航分支隐藏和当前语言品牌首页链接作为功能要求处理并修复，没有遗留 Minor 项。
- 跟随最新 `app/admin/plugins/{code}` 目录规范，业务 URL 保持不变；避免新规范下无法发布和发现权限。
- `.DS_Store` 仅作为普通 Finder 元数据排除，Manifest、原生/静态资源发布和修改检测保持一致；代码、目录、符号链接仍受原有规则约束。此修复由反复重现的实际安装校验失败驱动，并有回归测试。

审查未覆盖的事项：真实 DNS/证书属于部署；原生打包/构建和浏览器检查由本会话补验；并行宿主变更及第三方库内部实现没有作为 CMS 范围修改。整页缓存首版未开启，避免发布失效一致性问题。

## 宿主全量检查的现存失败

本次没有将全量检查标为通过。下面是最终工作区快照的实际结果；CMS 文件没有失败项。

- 后端：814 项、6243 次断言，5 个错误、2 个失败、108 项跳过。
- 前端：146 个文件，62 文件失败、84 文件通过；118 用例失败、789 用例通过。部分失败套件在收集阶段即出错。
- 相比最初已有的后端 3 项失败，当前工作区的权威核心 SQL 根目录迁移新增了 4 项路径/契约错误；并行目录迁移也影响前端旧路径契约。本任务没有还原这些用户变更。

### 后端失败名称

- `Tests\Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`
- `Tests\Console\CoreDatabaseAuditTest::test_manifest_reads_only_root_baseline_declarations_without_executing_seeds`
- `Tests\Console\CoreMigrationSourceTest::test_default_source_is_repository_root_not_webman_php_migrations`
- `Tests\Console\CoreMigrationSourceTest::test_upgrade_manifest_requires_complete_signed_authoritative_core_sql`
- `Tests\Console\CoreMigrationSourceTest::test_signed_zip_is_rejected_without_core_contract_and_uses_mounted_sql_not_local_php`
- `Tests\AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`
- `Tests\LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`

原因分别为 AI 私有目录符号链接、权威核心 SQL 目录缺失/路径契约、authorization 路由摘要变化，以及仍存在的 ThinkPHP 引用。它们位于 CMS 源包之外。

### 前端失败套件/用例名称

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
- `tests/contracts/laravel-field-cutover.spec.ts [ tests/contracts/laravel-field-cutover.spec.ts ]`
- `tests/contracts/legacy-time-columns-drop.spec.ts [ tests/contracts/legacy-time-columns-drop.spec.ts ]`
- `tests/contracts/login-brand-spacing.spec.ts [ tests/contracts/login-brand-spacing.spec.ts ]`
- `tests/contracts/member-registration-admin-scope.spec.ts [ tests/contracts/member-registration-admin-scope.spec.ts ]`
- `tests/contracts/menu-icon-field.spec.ts [ tests/contracts/menu-icon-field.spec.ts ]`
- `tests/contracts/migration-service-legacy-preflight.spec.ts [ tests/contracts/migration-service-legacy-preflight.spec.ts ]`
- `tests/contracts/operation-log-middleware.spec.ts [ tests/contracts/operation-log-middleware.spec.ts ]`
- `tests/contracts/operation-log-tag-columns.spec.ts [ tests/contracts/operation-log-tag-columns.spec.ts ]`
- `tests/contracts/plugin-page.spec.ts [ tests/contracts/plugin-page.spec.ts ]`
- `tests/contracts/plugin-permissions-registry.spec.ts [ tests/contracts/plugin-permissions-registry.spec.ts ]`
- `tests/contracts/plugin-route-helper.spec.ts [ tests/contracts/plugin-route-helper.spec.ts ]`
- `tests/contracts/schema-integrity-migration.spec.ts [ tests/contracts/schema-integrity-migration.spec.ts ]`
- `tests/contracts/search-form-button-order.spec.ts [ tests/contracts/search-form-button-order.spec.ts ]`
- `tests/contracts/time-columns-no-default.spec.ts [ tests/contracts/time-columns-no-default.spec.ts ]`
- `tests/contracts/title-to-name-migration.spec.ts [ tests/contracts/title-to-name-migration.spec.ts ]`
- `tests/contracts/top-menu-focus.spec.ts [ tests/contracts/top-menu-focus.spec.ts ]`
- `tests/contracts/upload-ext-contract.spec.ts [ tests/contracts/upload-ext-contract.spec.ts ]`
- `tests/contracts/upload-security-contract.spec.ts [ tests/contracts/upload-security-contract.spec.ts ]`
- `tests/contracts/user-management-menu.spec.ts [ tests/contracts/user-management-menu.spec.ts ]`
- `src/admin/layout/components/Tabs.spec.ts [ src/admin/layout/components/Tabs.spec.ts ]`
- `src/admin/views/form/validation/formSchemaDataValidator.spec.ts [ src/admin/views/form/validation/formSchemaDataValidator.spec.ts ]`
- `src/admin/views/development/ai/aiPage.spec.ts [ src/admin/views/development/ai/aiPage.spec.ts ]`
- `src/admin/views/system/menu/index.spec.ts [ src/admin/views/system/menu/index.spec.ts ]`
- `src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts [ src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts ]`
- `src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts [ src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts ]`
- `src/admin/views/system/member/components/MemberV2.spec.ts [ src/admin/views/system/member/components/MemberV2.spec.ts ]`
- `tests/contracts/admin-email-length.spec.ts > 会员分组关联后端契约 > SystemMember 通过关联表过滤并保存分组`
- `tests/contracts/admin-email-length.spec.ts > 会员分组关联后端契约 > Member 登录不写入不存在的 login_time 字段`
- `tests/contracts/admin-email-length.spec.ts > 会员分组关联后端契约 > SystemMemberGroup 通过关联表检查会员组引用`
- `tests/contracts/admin-email-length.spec.ts > 管理员邮箱最大长度契约 > 前端管理员表单限制邮箱最多 60 个字符`
- `tests/contracts/admin-email-length.spec.ts > 管理员邮箱最大长度契约 > 后端拒绝超过 60 个字符的管理员邮箱`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 提供发布状态迁移与模型 JSON 配置`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 通过独立工厂把表单元数据转换为 CRUD Definition`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 动态发布与正式生成统一由 Business API 暴露`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 多级表单控制器发布权限可被 nodeAccess 正确解析`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > CRUD Definition 接受完整筛选、格式化和布局元数据`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 基本信息提供所属菜单选择，并将选择纳入发布配置`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 设计器隔离动态发布与正式生成，并对冲突 fail closed`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 表单设计器仅保留基础模式，移除高级模式专属入口和状态`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 正式生成复用服务端 operation，并在响应不确定时按 generationId 收敛`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 正式生成按钮维持 generate 与 apply-resources 权限边界`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 生成菜单优先独立源码并使用预置发布宿主兜底`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 动态发布 routePath 可命中发布宿主并传递 formKey 路径参数`
- `tests/contracts/form-publish.spec.ts > 统一表单发布引擎契约 > 发布兜底宿主严格使用已发布 canonical FormSchema v2 运行态`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 生产构建启用静态资源压缩`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 首次鉴权并行获取用户信息和菜单`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 限制 keep-alive 实例数量`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > 列表缩略图使用懒加载`
- `tests/contracts/frontend-performance.spec.ts > 前端性能契约 > ECharts 不注册未使用的图表和功能组件`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 新增规范管理员字段并回填旧数据`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 创建单数语言表和语义准确的地区表并迁移数据`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 语言数据回填兼容 legacy 与 Laravel 时间字段`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 会员标签使用标签表和关联表承载`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 应用代码切换到规范管理员字段`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 语言和地区模型显式绑定规范表`
- `tests/contracts/legacy-schema-debt.spec.ts > 017 历史命名与会员标签治理 > 会员 API 与前端通过 tagIds 管理标签关系`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 使用 system/plugin 标准 API 文件并保留完整端点`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > plugin.json 只使用源码构建 adminWeb 契约`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > Admin Web 源码与公开资源分别发布并要求重新构建`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 仅从构建期 src/modules 组件清单加载插件页面`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > Manifest 与 Schema 只读取 adminWeb 并保留安全静态校验`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 插件 API 的未知异常不向客户端泄露内部消息`
- `tests/contracts/m6-plugin-standard.spec.ts > M6 插件标准 Admin Web 契约 > 页面具备计划中的安装、配置、生命周期、市场和账号组件`
- `tests/contracts/m6-upgrade.spec.ts > M6 系统升级契约 > 通过 forward migration 注册规范权限和菜单字段`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 管理应用从 backend 完整硬切为 admin`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 模型基类明确使用 bigint id 与 Laravel 时间字段`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > M8 删除全部旧后台入口与认证兼容层`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 插件菜单与权限仅从 adminWeb 契约注册`
- `tests/contracts/m7-cutover.spec.ts > M7 Laravel 字段与旧入口收缩契约 > 普通 MigrationService 不扫描 maintenance contract 且 legacy contract 已移除`
- `tests/contracts/member-group-icon.spec.ts > 会员组图标字段 > migration 为会员组表新增 icon 列`
- `tests/contracts/member-group-icon.spec.ts > 会员组图标字段 > 后端接收、校验并返回 icon`
- `tests/contracts/member-group-icon.spec.ts > 会员组图标字段 > 前端弹窗提供图标选择且列表展示图标`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/menu/components/MenuFormDialog.vue 使用文案表示值 0`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/permission/components/PermissionFormDialog.vue 使用文案表示值 0`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/dept/components/DeptFormDialog.vue 使用文案表示值 0`
- `tests/contracts/parent-select-label.spec.ts > 父级选择器顶级文案 > src/views/system/attachment/components/AttachmentGroupDialog.vue 使用文案表示值 0`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > 前端插件业务类型只暴露 code/name`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > API 标识参数与更新检查 payload 使用 code`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > 插件中心表格与操作统一使用 row.code`
- `tests/contracts/plugin-code-name-cutover.spec.ts > 插件 code/name 字段硬切契约 > 动态模块 descriptor 的插件标识使用 code，Vue route name 保持不变`
- `tests/contracts/service-cache-invalidation.spec.ts > Service 公共缓存失效能力 > AbstractService 提供受保护的全局缓存清理方法`
- `tests/contracts/service-cache-invalidation.spec.ts > Service 公共缓存失效能力 > ResourceRegistryService 不再定义私有 clearCache`
- `tests/contracts/service-cache-invalidation.spec.ts > Service 公共缓存失效能力 > PluginService 复用公共缓存清理能力`
- `tests/contracts/storage-driver.spec.ts > 附件存储驱动契约 > 上传请求由浏览器生成 multipart boundary`
- `tests/contracts/storage-driver.spec.ts > 附件存储驱动契约 > 附件页展示并可选择后台存储驱动`
- `tests/contracts/storage-driver.spec.ts > 附件存储驱动契约 > 上传结果返回实际存储驱动`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 安全成功 mock 经真实 API 上传、下载、绑定；未知请求绝不回退网络`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 档案 API 使用真实路径、方法和 snake_case，模型 ID 不转整数`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 真实会话 PUT 保留覆盖及 null，不改写为 default 或省略`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 列表和单记录只解包一次，所有会话及分组入口统一 ID 类型`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 只允许 published 应用进入且不拼接 token`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 启动被准入策略拒绝时明确显示 403 提示且不跳转`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 提供卡片列表、统计搜索、应用管理和完整设置抽屉`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 只展示 schema 支持的 visibility 并明确说明启动语义`
- `src/admin/views/applications/application-center.spec.ts > 企业应用中心 > 提供 API、mock 和中英文 i18n`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > SSO 页面提供三模式、固定保存与完整自检`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > 门户按 assignment 准入目录展示并通过 launch API 无 token 跳转`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > Identity API 类型和 mock 覆盖全部管理端点`
- `src/admin/views/applications/phase8.spec.ts > SSO Phase 8 管理控制台与应用门户 > migration 菜单复用既有权限并匹配真实控制器 action`
- `src/admin/views/form/FormBackendContract.spec.ts > 表单后端领域契约 > Repeatable 子表与父表使用同连接事务并校验子行归属`
- `src/admin/views/form/FormBackendContract.spec.ts > 表单后端领域契约 > 数据列表支持完整筛选操作符`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > CRUD 日期区间、主键、排序、树表及工具双端协议`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > Builder 显式主键及分页开关编译后保真`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"primaryKey":"__proto__"}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"columns":[{"key":"price","label":"价格","sortable":"custom"}]}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"list":{"tree":{"enabled":true}}}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"list":{"tools":{"refresh":"yes"}}}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 两端拒绝非法 CRUD 展示能力 {"pagination":{"pageSize":20,"pageSizes":[20],"enabled":0}}`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 接收 PHP 编译产物，空集合保持为空`
- `src/shared/components/DataTable/pageSchema.spec.ts > 公共页面声明 > 同一 JSON 输入由 PHP 与 TS 共同执行正反例矩阵`
- `src/shared/components/DataTable/unifiedPages.spec.ts > 统一页面入口 > data 列表只使用公共表格与按钮`
- `src/shared/components/DataTable/unifiedPages.spec.ts > 统一页面入口 > published 列表只使用公共表格与按钮`
- `src/shared/components/DataTable/unifiedPages.spec.ts > 统一页面入口 > published 详情复用共享展示并保持敏感字段过滤`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 一次性 secret 只保存在内存且关闭时清空`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 提供 URI 校验、scope/grant、secret 与 key rotation`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 应用设置 OAuth tab 不再是假占位`
- `src/admin/views/form/components/SchemaForm.spec.ts > PHP 生成 schema 的 renderer 能力 > 字典与上传开关 false 保留关联、只读、日期能力`
- `src/admin/views/form/components/SchemaForm.spec.ts > PHP 生成 schema 的 renderer 能力 > 字典与上传开关 true 保留关联、只读、日期能力`
- `src/admin/views/development/business/records.spec.ts > BusinessRecords > 全局模式通过 modules API 选择模块，并展示模块名称标识与返回入口`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 正式生成列表和详情接入共享字段展示 helper`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 列表和详情特殊展示均可编译，详情选项中的插值符号与引号原样显示`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 安全元数据不重新引入敏感字段或关闭的列表详情字段`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 关闭列表和详情仅影响展示，不作为 DTO 访问授权`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > hidden 字段即使配置 search 也不生成搜索绑定`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 正式生成默认工具栏按正常列表、回收站、新增、移入回收站、导入、导出顺序并保留样式元数据`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 后端 schema 接受 normal 和布尔 plain，拒绝错误类型和错误位置`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 真实生成脚本与 API 在无批删、无软删和插件降级时通过类型检查`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 默认按钮真实渲染 plain、图标和禁用选择，状态入口重复点击不反转`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 批删确认中选择或权限变化时取消，不使用确认后的新选择`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 生成 API、独立权限、snake/camel 主键与发布版本真实进入共享执行链`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 分类树继承生成宿主，节点身份与来源版本不借动态入口`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 无批删能力仍可选择注册批动作，切换筛选后清空`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 回收站仅在能力启用时出现，恢复命中生成 API，显式空不补默认`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 批量回收站按钮使用独立批量权限而非单条权限`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 生成筛选转换为 snake 字段且变化清空注册选择`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 发布版本失配禁止执行`
- `src/admin/views/form/runtime/generatedListButtons.spec.ts > PHP 真实生成页面消费正式宿主 > 显式空集合不恢复任何默认管理按钮`
- `src/admin/views/form/runtime/listButtonWiring.spec.ts > 实际页面按钮接线 > 动态页共享组件接入行、顶部及工具，不保留硬编码编辑按钮`
- `src/admin/views/development/business/components/GenerationPlanView.spec.ts > GenerationPlanView > 真实 PHP 授权预览响应能显示 SQL 和受管 Manifest 增量`
- `src/admin/views/development/ai/components/AiComposer.spec.ts > 独立 composer 边界 > 页面应使用独立输入组件，不保留无锁的内联发送表单`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 矮窗口使用真实 Drawer DOM 和编译后 CSS，正文可整体滚动而非被固定栏夹断`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案使用紧凑响应式布局、真实 EP 表单与独立底栏，能力默认折叠`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案在 1080px 下应用紧凑间距及响应式字段列数`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案在 390px 下应用紧凑间距及响应式字段列数`

## 使用前的部署步骤

在插件中心安装并启用 CMS，发布管理端源码后构建现有后台，按部署流程重载新 PHP 路由。创建站点并绑定域名，配置 DNS、HTTPS 与 Nginx；配置示例见插件 README。当前工作区的核心 SQL 路径问题应在实际安装前完成对应宿主迁移。
