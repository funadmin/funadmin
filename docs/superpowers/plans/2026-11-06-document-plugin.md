# FunAdmin 文档管理插件 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 构建一个可独立安装、停用、升级和打包的 FunAdmin 文档管理插件，统一管理 FunAdmin 主框架、插件开发规范、官方插件和第三方插件技术文档。

**Architecture:** 使用标准 FunAdmin 插件目录；后端在 `admin/plugins/document` 提供目录、文档、版本、搜索、导入导出和权限接口，前端在 `web/src/admin/modules/document` 提供三栏文档工作台。正文以 Tiptap JSON 作为主格式，发布时生成不可变修订，Markdown/HTML 只作为导入导出和安全预览格式。

**Tech Stack:** PHP 8.5 + Webman + MySQL migration + FunAdmin RBAC；Vue 3 + TypeScript + Vite + Element Plus + Tailwind CSS + Pinia + Axios + Tiptap + Vitest/Vue Test Utils。

**Spec:** `docs/superpowers/specs/2026-11-06-document-plugin-design.md`

## Global Constraints

- 插件必须遵守 `docs/plugins.md` 的标准目录、manifest、迁移、权限、构建和生命周期约定。
- 第一版只实现目录、文档 CRUD、Tiptap 编辑/预览、草稿、发布、归档、版本历史/恢复、搜索和 Markdown/HTML 导入导出；评论、文档级 ACL 和评审流延期。
- 文档正文主存储为 Tiptap JSON；后端只校验 JSON 节点、提取纯文本和保存，不保存客户端提交的 rendered HTML。
- Markdown 导入和 Markdown/HTML 导出全部在前端完成；第一版没有 public/member 阅读入口，`visibility` 固定为 `admin`。
- 发布修订不可变；恢复通过复制修订内容生成新的草稿，不删除历史修订。
- 保存使用 `lock_version` 乐观锁，冲突返回 HTTP 409；`slug` 全局唯一。
- 权限只使用现有插件 RBAC 权限：查看、编辑、发布、删除、目录管理；后端权限是最终授权边界。
- 文档空间是全局单空间；`scope` 固定为 `framework`、`plugin_dev`、`official_plugin`、`third_party`。
- 安装时只 seed 四类 scope 的目录树和入门文档；稳定 slug 已存在时不覆盖管理员内容，seed 来源为插件包 `resources/seed/`。
- 第一版正文不做多语言版本，只做 UI 和系统文案国际化。
- 第一版允许为插件向宿主 `web/package.json` 增加固定版本的编辑器扩展依赖，不在运行时动态安装依赖。
- 第一版只支持现有 MySQL 迁移链；搜索使用保存时生成的纯文本列和标题/摘要 `LIKE`。
- 图片和附件复用现有后台附件接口；第一版不实现受保护附件。
- Markdown 导入必须经过安全解析和净化；链接只允许 http(s)、mailto、站内路径和锚点。
- 未经用户授权不提交、推送或发布插件。

## Review Focus

- **并发编辑**：旧 `lock_version` 保存必须返回 409，不能静默覆盖他人草稿；Task 4 的服务测试覆盖。
- **权限越权**：没有查看/编辑/发布权限的账号即使直接调用 API 也必须被拒绝；Task 3 的 HTTP/RBAC 测试覆盖。
- **不安全内容**：Markdown 中的脚本、危险 URL、HTML 事件属性和 Mermaid 代码不能进入可执行预览；Task 5 的安全转换测试覆盖。
- **版本恢复**：恢复历史版本必须保留原修订，并把恢复内容作为新草稿；Task 4 的版本服务测试覆盖。
- **插件生命周期**：停用/卸载后菜单、路由和后端处理器不可继续工作，重新启用后数据不丢失；Task 2/8 的插件契约和验收覆盖。

---

## Task 1: 插件骨架、Manifest 与迁移基础

**Files:**
- Create: `admin/plugins/document/plugin.json`
- Create: `admin/plugins/document/Plugin.php`
- Create: `admin/plugins/document/config.php`
- Create: `admin/plugins/document/resources/seed/categories.json`
- Create: `admin/plugins/document/resources/seed/documents/*.json`
- Create: `admin/plugins/document/database/migrations/001_document_baseline.sql`
- Create: `admin/plugins/document/app/admin/plugins/document/controller/Index.php`
- Create: `admin/plugins/document/app/admin/plugins/document/service/DocumentPluginService.php`
- Create: `admin/plugins/document/web/src/admin/modules/document/api.ts`
- Create: `admin/plugins/document/web/src/admin/modules/document/types.ts`
- Create: `admin/plugins/document/web/src/admin/modules/document/Index.vue`
- Test: `admin/tests/DocumentPluginLayoutTest.php`
- Test: `admin/tests/DocumentMigrationTest.php`

**Interfaces:**
- Produces plugin code `document`、菜单入口、五项权限声明和前端入口 `web/src/admin/modules/document`。
- Produces migration scope `plugin:document`，创建目录、文档、修订和文档日志所需的第一版表结构。

- [ ] **Step 1: Write the failing plugin contract tests**
  - 验证 manifest 的 code/title/version、adminWeb source、menu、permissions、migrations 声明。
  - 验证迁移创建 `document_categories`、`documents`、`document_revisions`、`document_events`，并包含 `scope`、`plugin_code`、`slug`、`content_json`、`plain_text`、`lock_version`、`revision_no`。
- [ ] **Step 2: Run the focused tests and confirm they fail**
  - Run: `cd admin && vendor/bin/phpunit tests/DocumentPluginLayoutTest.php tests/DocumentMigrationTest.php`
  - Expected: plugin files or migration contract missing。
- [ ] **Step 3: Implement the minimal standard plugin skeleton**
  - Manifest 使用现有插件格式，声明 `document:doc:view`、`document:doc:edit`、`document:doc:publish`、`document:doc:delete`、`document:category:manage`。
  - 迁移使用 MySQL `JSON`/`LONGTEXT`：JSON 正文存 `content_json`，可检索纯文本存 `plain_text`；文档软删除，修订不可变。
  - 安装 seed 只插入不存在的稳定 slug；升级不覆盖已编辑文档。seed 的四类 scope 和入门正文从 `resources/seed/` 读取。
  - 前端先提供可被动态路由加载的空入口和 API/types 边界，不实现业务交互。
- [ ] **Step 4: Run tests and plugin validation**
  - Run: `cd admin && vendor/bin/phpunit tests/DocumentPluginLayoutTest.php tests/DocumentMigrationTest.php`
  - Run: `php webman plugin:validate document`
  - Expected: PASS。

## Task 2: 文档目录与后端领域模型

**Files:**
- Create: `admin/plugins/document/app/admin/plugins/document/model/DocumentCategory.php`
- Create: `admin/plugins/document/app/admin/plugins/document/model/Document.php`
- Create: `admin/plugins/document/app/admin/plugins/document/model/DocumentRevision.php`
- Create: `admin/plugins/document/app/admin/plugins/document/service/DocumentCategoryService.php`
- Create: `admin/plugins/document/app/admin/plugins/document/service/DocumentQueryService.php`
- Modify: `admin/plugins/document/app/admin/plugins/document/controller/Index.php`
- Test: `admin/tests/DocumentCategoryServiceTest.php`
- Test: `admin/tests/DocumentQueryTest.php`

**Interfaces:**
- `DocumentCategoryService::tree(): array`、`create(array $payload): array`、`update(int $id, array $payload): array`、`delete(int $id): void`。
- `DocumentQueryService::list(array $query): array`、`find(int $id): array`、`search(string $keyword, array $filters): array`。
- DTO 至少包含 `id`、`title`、`slug`、`summary`、`scope`、`pluginCode`、`type`、`status`、`appliesTo`、`categoryIds`、`updatedAt`。

- [ ] **Step 1: Write failing tests**
  - 测试四种 scope、文档类型、状态、目录树排序、全局 slug 唯一、软删除不出现在默认列表。
  - 测试按 scope/pluginCode/type/status/category/keyword 筛选。
- [ ] **Step 2: Run focused tests and verify failure**
  - Run: `cd admin && vendor/bin/phpunit tests/DocumentCategoryServiceTest.php tests/DocumentQueryTest.php`
- [ ] **Step 3: Implement models, repositories/services and DTO mapping**
  - 所有模型继承 `app\common\model\BaseModel`；查询默认排除软删除。
  - 目录采用 `parent_id` 树结构和 `sort_order`；删除非空目录必须拒绝或要求先迁移子文档。
- [ ] **Step 4: Run focused tests**
  - Expected: all category/query tests PASS。

## Task 3: 权限、路由和文档 CRUD API

**Files:**
- Create: `admin/plugins/document/app/admin/plugins/document/controller/DocumentController.php`
- Create: `admin/plugins/document/app/admin/plugins/document/controller/CategoryController.php`
- Create: `admin/plugins/document/app/admin/plugins/document/service/DocumentAuthorizationService.php`
- Modify: `admin/plugins/document/plugin.json`
- Test: `admin/tests/DocumentHttpTest.php`
- Test: `admin/tests/DocumentAuthorizationTest.php`

**Interfaces:**
- `DocumentAuthorizationService::assert(string $permission, ?int $documentId = null): void`。
- `DocumentController` 提供：
  - `GET /admin/plugin/document/tree`
  - `GET /admin/plugin/document/documents`
  - `POST /admin/plugin/document/documents`
  - `GET /admin/plugin/document/documents/{id}`
  - `PUT /admin/plugin/document/documents/{id}`
  - `DELETE /admin/plugin/document/documents/{id}`
  - `GET /admin/plugin/document/search`
- `CategoryController` 提供目录 CRUD。

- [ ] **Step 1: Write failing HTTP and authorization tests**
  - 超管和具备对应插件权限的账号可访问；无查看权限返回 403；所有响应只能通过后台会话访问，`visibility` 固定为 `admin`。
  - 未登录、缺 CSRF、错误 HTTP method、越界文档 id 均返回正确状态。
  - 创建/更新/删除只改变授权范围内资源，前端隐藏不作为测试依据。
- [ ] **Step 2: Run focused tests and verify failure**
  - Run: `cd admin && vendor/bin/phpunit tests/DocumentHttpTest.php tests/DocumentAuthorizationTest.php`
- [ ] **Step 3: Implement controllers with existing middleware/contracts**
  - 使用现有注解路由、`AdminApiController`、插件权限声明、Cookie 会话、CSRF 和统一错误响应。
  - 不在 controller 中直接拼 SQL；写操作进入 service 并写 `document_events`/现有 SystemLog。
- [ ] **Step 4: Run focused tests and route/manifest validation**
  - Expected: HTTP/RBAC tests PASS，插件路由可被动态发现。

## Task 4: 草稿、发布、版本历史与恢复

**Files:**
- Create: `admin/plugins/document/app/admin/plugins/document/service/DocumentVersionService.php`
- Modify: `admin/plugins/document/app/admin/plugins/document/controller/DocumentController.php`
- Modify: `admin/plugins/document/app/admin/plugins/document/model/Document.php`
- Test: `admin/tests/DocumentVersionServiceTest.php`
- Test: `admin/tests/DocumentConcurrencyTest.php`

**Interfaces:**
- `DocumentVersionService::saveDraft(int $id, array $content, int $lockVersion): array`。
- `DocumentVersionService::publish(int $id, string $changeSummary): array`。
- `DocumentVersionService::archive(int $id): void`。
- `DocumentVersionService::revisions(int $id, int $page, int $pageSize): array`。
- `DocumentVersionService::restore(int $id, int $revisionNo, int $lockVersion): array`。
- API：`POST /documents/{id}/publish`、`POST /documents/{id}/archive`、`GET /documents/{id}/revisions`、`POST /documents/{id}/revisions/{revisionNo}/restore`。

- [ ] **Step 1: Write failing version/concurrency tests**
  - 发布创建递增 `revision_no` 的不可变修订并更新 `current_revision_no`。
  - 归档不删除修订；恢复复制历史内容到草稿、递增 `lock_version`，不修改原修订。
  - 使用旧 `lock_version` 保存或恢复返回 409，数据库内容不变。
- [ ] **Step 2: Run focused tests and verify failure**
  - Run: `cd admin && vendor/bin/phpunit tests/DocumentVersionServiceTest.php tests/DocumentConcurrencyTest.php`
- [ ] **Step 3: Implement transactional version service**
  - 在同一事务中锁定文档行、检查 `lock_version`、更新草稿或插入修订。
  - 草稿状态为 `draft`；发布后状态为 `published`；归档后状态为 `archived`；重新编辑归档文档先恢复为草稿。
- [ ] **Step 4: Run tests**
  - Expected: version and concurrency tests PASS。

## Task 5: 安全内容转换、Tiptap 扩展和导入导出

**Files:**
- Create: `web/src/admin/modules/document/editor/extensions.ts`
- Create: `web/src/admin/modules/document/editor/content.ts`
- Create: `web/src/admin/modules/document/editor/DocumentEditor.vue`
- Create: `web/src/admin/modules/document/editor/DocumentPreview.vue`
- Create: `web/src/admin/modules/document/editor/DocumentOutline.vue`
- Create: `web/src/admin/modules/document/editor/markdown.ts`
- Modify: `web/package.json`
- Modify: `web/package-lock.json`
- Modify: `web/src/admin/modules/document/api.ts`
- Modify: `admin/plugins/document/app/admin/plugins/document/controller/DocumentController.php`
- Test: `web/src/admin/modules/document/editor/content.spec.ts`
- Test: `admin/tests/DocumentContentSecurityTest.php`

**Interfaces:**
- `sanitizeDocumentUrl(value: string): string | null`。
- `extractPlainText(content: JSONContent): string`。
- `parseImportedMarkdown(markdown: string): JSONContent`。
- `exportDocumentMarkdown(content: JSONContent): string`。
- `exportDocumentHtml(content: JSONContent): string`。
- 后端接口只接受/返回 JSON 正文，不提供 PHP 端 HTML renderer；浏览器导入导出使用 `markdown.ts`。

- [ ] **Step 1: Write failing conversion/security tests**
  - Markdown 标题、代码块、任务列表、表格、Callout、Mermaid 源码可往返；不要求 Markdown 字节级一致。
  - `<script>`、事件属性、`javascript:`、危险外链被移除；普通 http(s)、mailto、站内路径和锚点保留。
  - Mermaid 只作为源码节点保存，不接受任意 SVG/HTML 注入。
- [ ] **Step 2: Add exact frontend dependencies and run the focused test**
  - Add fixed versions of Tiptap table/task-list/code-block-lowlight extensions, Markdown parser/serializer, Mermaid renderer and DOM sanitizer to the host `web` package. The plugin declares the minimum host frontend capability version; it never installs packages at runtime。
  - Run: `cd web && npm test -- --run src/admin/modules/document/editor/content.spec.ts`
- [ ] **Step 3: Implement extensions and conversion boundary**
  - JSON schema 白名单限制节点；编辑器使用 custom Callout/Mermaid nodes；预览只渲染允许节点。
  - 导入先在浏览器解析/净化，再转换为 JSON 并调用普通保存接口；导出从当前 JSON 在浏览器生成 Markdown/HTML 下载文件；上传沿用现有附件 API。
  - 后端测试只验证 JSON 节点白名单、plain text 提取和危险 URL 不被持久化，不实现第二套 PHP HTML renderer。
- [ ] **Step 4: Run frontend/backend security tests**
  - Expected: content conversion and security tests PASS。

## Task 6: 三栏文档工作台前端

**Files:**
- Create: `web/src/admin/modules/document/Index.vue`
- Create: `web/src/admin/modules/document/components/DocumentSidebar.vue`
- Create: `web/src/admin/modules/document/components/DocumentList.vue`
- Create: `web/src/admin/modules/document/components/DocumentMetaPanel.vue`
- Create: `web/src/admin/modules/document/components/DocumentVersionDrawer.vue`
- Create: `web/src/admin/modules/document/store.ts`
- Modify: `web/src/admin/modules/document/api.ts`
- Modify: `web/src/admin/modules/document/types.ts`
- Test: `web/src/admin/modules/document/Index.spec.ts`
- Test: `web/src/admin/modules/document/components/DocumentList.spec.ts`

**Interfaces:**
- Store actions：`loadTree()`、`loadList(query)`、`openDocument(id)`、`saveDraft()`、`publish()`、`archive()`、`restoreRevision(revisionNo)`。
- Store state 至少包含 `selectedCategoryId`、`documents`、`currentDocument`、`editorMode`、`saving`、`lastSavedAt`、`conflict`。

- [ ] **Step 1: Write failing component tests**
  - 渲染主框架、插件开发、官方插件、第三方插件四类 scope。
  - 点击目录刷新列表，点击文档加载正文，编辑/分栏/预览切换可用。
  - 保存状态显示已保存/保存中/冲突；409 提示用户重新载入或另存草稿。
  - 无权限时不显示对应按钮，但组件不能代替后端授权。
- [ ] **Step 2: Run focused frontend tests and verify failure**
  - Run: `cd web && npm test -- --run src/admin/modules/document/Index.spec.ts src/admin/modules/document/components/DocumentList.spec.ts`
- [ ] **Step 3: Implement the three-column workspace**
  - 左栏目录树和范围筛选；中栏文档列表/搜索/状态；右栏标题、摘要、编辑器、预览、大纲、元信息和版本抽屉。
  - 使用现有 Element Plus、主题变量、响应式布局、动态路由和 i18n；移动宽度退化为目录抽屉 + 文档列表/正文切换。
- [ ] **Step 4: Run component tests**
  - Expected: focused component tests PASS。

## Task 7: 菜单、国际化、构建和插件生命周期

**Files:**
- Modify: `admin/plugins/document/plugin.json`
- Create: `web/src/admin/modules/document/locales/zh-CN.ts`
- Create: `web/src/admin/modules/document/locales/en-US.ts`
- Test: `admin/tests/DocumentPluginLifecycleTest.php`
- Test: `web/src/admin/modules/document/i18n.spec.ts`

- [ ] **Step 1: Write failing lifecycle/i18n tests**
  - 启用后菜单和路由可见，停用后不可访问；重新启用后文档数据仍在。
  - 安装 seed 生成四类 scope 的默认目录和入门文档；升级重复执行 seed 不覆盖管理员修改。
  - 所有第一版用户可见文案有中英文键，文档正文不做多语言版本。
- [ ] **Step 2: Implement manifest/menu/i18n wiring**
  - 菜单入口指向 `/admin/plugin/document`；权限 code 与后端一致；不把实现源码作为运行时动态脚本下载。
- [ ] **Step 3: Run lifecycle and build checks**
  - Run: `cd admin && vendor/bin/phpunit tests/DocumentPluginLifecycleTest.php`
  - Run: `cd web && npm run type-check && npm test -- --run src/admin/modules/document/i18n.spec.ts`
  - Run: `cd admin && php webman plugin:validate document`
  - Expected: PASS。

## Task 8: 端到端验收与文档

**Files:**
- Create: `docs/verification/document-plugin.md`
- Create: `admin/tests/DocumentE2ETest.php` or existing browser test location selected by project harness
- Modify: `docs/plugins.md` only if the plugin adds a reusable convention

- [ ] **Step 1: Add end-to-end acceptance scenarios**
  - 安装/启用插件，进入文档首页。
  - 创建“FunAdmin 主框架 / 架构设计”文档和“官方插件 / 示例插件”文档。
  - 编辑代码块、任务列表、表格、Callout、Mermaid，保存草稿并刷新确认内容保留。
  - 发布、查看修订、恢复旧修订、归档、搜索、导入 Markdown、导出 Markdown/HTML。
  - 用无权限账号验证页面和直接 API 都不能越权。
- [ ] **Step 2: Run complete verification**
  - `cd admin && vendor/bin/phpunit tests/Document*.php`
  - `cd web && npm run type-check && npm test -- --run src/admin/modules/document`
  - `cd admin && php webman plugin:validate document && php webman plugin:package document`
  - Run the browser acceptance flow against a fresh test database and record actual results in `docs/verification/document-plugin.md`。
- [ ] **Step 3: Review changed files and leave the branch uncommitted**
  - Confirm no generated build output, credentials, runtime data or test database files are included。
  - Do not commit/push until the user explicitly authorizes integration。

## Self-review coverage

- Scope, versions, drafts, publish, archive, restore, lock conflicts: Tasks 2–4。
- Plugin RBAC and lifecycle: Tasks 3 and 7。
- Tiptap extensions, Markdown/HTML conversion, Mermaid and URL safety: Task 5。
- Three-column UI and responsive behavior: Task 6。
- Import/export endpoints, attachments and acceptance evidence: Tasks 5 and 8。
