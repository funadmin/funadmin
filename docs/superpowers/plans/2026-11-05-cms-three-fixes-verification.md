# CMS 三项缺陷修复验证

日期：2026-11-05。范围为用户上一轮确认的三个检查问题，未执行生产迁移、安装或服务重载。

## 修复

1. `ContentPanel.vue` 保存时固定请求数据、编辑对象和编辑代次。响应仅在原编辑对象仍打开且代次一致时回填 ID 与版本指针。切换语言、重新打开另一篇文章后，旧请求不会污染当前表单；保持原编辑器时仍正确更新乐观锁和已保存修订。
2. `TemplateEngine.php` 用边界扫描提取 literal 块，普通部分再分词，避免大 literal 正文触发正则回溯。分词返回 false 时抛出 RuntimeException，不再转换成空节点。未闭合 literal 明确拒绝。
3. `ModelService.php` 的必填检查拒绝空图库数组；可选图库仍允许空数组，合法布尔 false、数字 0 保持通过。

目录迁移后的 `admin/.gitignore` 忽略了整个 CMS 源码包。本次恢复仅针对 `plugins/cms/` 的例外，其他本地安装插件继续忽略，源码与回归测试可以一并审阅。没有暂存、提交或覆盖无关改动。

## 回归测试

新增测试先在原代码上观察到失败，再修改实现验证通过：

- 保存期间切换语言：发布仍使用英文内容的 ID 与修订。
- 保存期间打开另一篇文章：发布仍使用新文章的 ID 与修订。
- 原编辑器保持打开：成功保存后版本指针和乐观锁正常更新。
- 1,100,000 字节 literal 正文完整输出，变量、Vue 插值和标签示例保持原样。
- 强制正则资源失败时明确抛错，不返回空白页面；测试结束恢复 PCRE 配置。
- 必填图库拒绝 []；可选图库、false、0 的行为不变。

## 验证结果

- CMS 后端：61 项，59 项通过、2 项隔离 MySQL 专用测试按默认配置跳过，266 次断言。没有数据库结构变更，本轮没有重复创建 MySQL 测试库。
- CMS 前端：4 个文件、9 项通过。
- `node scripts/check-cms.mjs`：通过。
- `node scripts/build-cms.mjs`：生产编译通过，产物在临时目录，没有覆盖在线 public。
- 两个修改 PHP 文件的语法检查通过；涉及已跟踪文件的 whitespace 检查通过。

## 全量结果及范围外问题

全量后端：828 项，6298 次断言，5 个错误、2 个失败、108 项跳过。
全量前端：146 文件，62 文件失败、84 文件通过；118 用例失败、792 用例通过。

CMS 定向用例均通过；全量没有 CMS 失败项。全量仍存在工作区原有的 AI 私有目录、核心 SQL 路径、路由快照、ThinkPHP 残留、旧路径契约等问题，没有改动其实现。具体失败名称如下。

### 后端

- `Tests\Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`
- `Tests\Console\CoreDatabaseAuditTest::test_manifest_reads_only_root_baseline_declarations_without_executing_seeds`
- `Tests\Console\CoreMigrationSourceTest::test_default_source_is_repository_root_not_webman_php_migrations`
- `Tests\Console\CoreMigrationSourceTest::test_upgrade_manifest_requires_complete_signed_authoritative_core_sql`
- `Tests\Console\CoreMigrationSourceTest::test_signed_zip_is_rejected_without_core_contract_and_uses_mounted_sql_not_local_php`
- `Tests\AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`
- `Tests\LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`

### 前端

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
- `src/admin/views/development/ai/aiPage.spec.ts [ src/admin/views/development/ai/aiPage.spec.ts ]`
- `src/admin/views/form/validation/formSchemaDataValidator.spec.ts [ src/admin/views/form/validation/formSchemaDataValidator.spec.ts ]`
- `src/admin/views/system/menu/index.spec.ts [ src/admin/views/system/menu/index.spec.ts ]`
- `src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts [ src/admin/views/system/member/components/MemberFormDialog.integration.spec.ts ]`
- `src/admin/views/system/member/components/MemberV2.spec.ts [ src/admin/views/system/member/components/MemberV2.spec.ts ]`
- `src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts [ src/admin/views/system/menu/components/MenuFormDialog.permission.spec.ts ]`
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
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 安全成功 mock 经真实 API 上传、下载、绑定；未知请求绝不回退网络`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 档案 API 使用真实路径、方法和 snake_case，模型 ID 不转整数`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 真实会话 PUT 保留覆盖及 null，不改写为 default 或省略`
- `src/admin/api/development/ai.spec.ts > AI 真实 HTTP 响应边界 > 列表和单记录只解包一次，所有会话及分组入口统一 ID 类型`
- `src/admin/views/development/business/records.spec.ts > BusinessRecords > 全局模式通过 modules API 选择模块，并展示模块名称标识与返回入口`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 一次性 secret 只保存在内存且关闭时清空`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 提供 URI 校验、scope/grant、secret 与 key rotation`
- `src/admin/views/applications/oauth/oauth-client.spec.ts > OAuth Client 管理 > 应用设置 OAuth tab 不再是假占位`
- `src/admin/views/form/components/SchemaForm.spec.ts > PHP 生成 schema 的 renderer 能力 > 字典与上传开关 false 保留关联、只读、日期能力`
- `src/admin/views/form/components/SchemaForm.spec.ts > PHP 生成 schema 的 renderer 能力 > 字典与上传开关 true 保留关联、只读、日期能力`
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
- `src/admin/views/development/ai/components/AiComposer.spec.ts > 独立 composer 边界 > 页面应使用独立输入组件，不保留无锁的内联发送表单`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 矮窗口使用真实 Drawer DOM 和编译后 CSS，正文可整体滚动而非被固定栏夹断`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案使用紧凑响应式布局、真实 EP 表单与独立底栏，能力默认折叠`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案在 1080px 下应用紧凑间距及响应式字段列数`
- `src/admin/views/development/ai/components/aiComponents.spec.ts > AI Development components > 档案在 390px 下应用紧凑间距及响应式字段列数`
- `src/admin/views/development/business/components/GenerationPlanView.spec.ts > GenerationPlanView > 真实 PHP 授权预览响应能显示 SQL 和受管 Manifest 增量`
