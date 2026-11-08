# FunAdmin 文档管理插件设计

日期：2026-11-06  
状态：已确认，进入实现  
范围：FunAdmin 主框架技术文档与 FunAdmin 插件文档

## 1. 意图

为 FunAdmin 建立一个标准插件形式的技术文档中心，统一管理主框架、插件开发规范、官方插件和第三方插件文档。重点是研发阅读、编辑、评审、发布和版本追踪，不做通用网盘或办公知识库。

## 2. 目标

- 在同一个后台页面浏览 FunAdmin 主框架和插件文档。
- 用产品范围、项目、插件、文档类型和版本组织内容。
- 支持 Markdown/技术写作需要的标题、代码块、表格、任务列表、Mermaid、Callout、图片和附件。
- 支持草稿、评审中、已发布、已归档状态。
- 支持文档目录、全文搜索、版本历史、评论和权限。
- 文档页面与现有 FunAdmin 登录、RBAC、主题、国际化和插件生命周期集成。
- 插件可以独立安装、停用、升级和打包，不修改主框架核心业务代码。

## 3. 非目标

- 不实现多产品统一知识库。
- 不实现网盘、在线协同表格、实时多人光标协作。
- 第一版不实现复杂的 Git 双向同步；可以保留仓库、分支、版本等关联字段。
- 不把文档正文直接作为任意 HTML 执行，预览必须经过安全渲染和 URL 过滤。

## 4. 反目标

- 不把插件文档和主框架文档混成无层级的普通文件列表。
- 不使用动态下载并执行的前端脚本。
- 不允许普通管理员通过前端隐藏绕过后端文档权限。
- 不以 HTML 字符串作为唯一内容格式，避免编辑器扩展后丢失结构。

## 5. 实现方式与取舍

### 方案 A：主框架内置模块

开发最快、集成简单，但会让文档功能和主框架耦合，难以独立发布和卸载；不采用。

### 方案 B：标准 FunAdmin 插件（采用）

按现有插件规范提供后端、前端模块、迁移、菜单、权限和生命周期。插件可独立安装、停用、升级和打包，最符合本项目目标。

### 方案 C：文件型 Markdown 插件

适合 Git 管理和批量导出，但在线权限、评论、版本、检索和并发编辑需要额外设计；作为后续导入导出能力，不作为第一版主存储。

## 6. 前端技术

复用现有 `web` 项目：

- Vue 3 + TypeScript + Vite
- Element Plus + Tailwind CSS
- Pinia + Axios + Vue Router
- Tiptap Vue 3 作为编辑器基础
- Vitest + Vue Test Utils

编辑器扩展：标题、列表、任务列表、引用、链接、代码块、代码语言、表格、Callout、Mermaid、图片、附件。

文档正文主存储为 Tiptap JSON，前端编辑器/预览使用同一套 Tiptap schema；后端只校验 JSON 节点白名单并提取纯文本，不接受客户端提交的 rendered HTML。Markdown 导入、Markdown/HTML 导出在前端完成，不增加 PHP 端的第二套渲染器。现有富文本组件可以复用基础交互，但文档编辑器需要独立扩展，不能牺牲技术文档结构保真度。

## 7. 页面设计

### 7.1 文档首页

三栏布局：

- 左栏：技术文档、主框架、插件开发、官方插件、第三方插件、归档等目录。
- 中栏：当前目录文档列表，显示标题、范围、类型、版本、状态、最后修改时间。
- 右栏：正文阅读或编辑区，支持编辑、分栏、预览；右侧可展开文档信息和大纲。

顶部提供全局搜索、范围筛选、新建文档、导入、最近编辑和当前空间信息。

### 7.2 文档分类

```text
FunAdmin 文档
├── 快速开始
├── 主框架
│   ├── 架构设计
│   ├── 安装部署
│   ├── 后端开发
│   ├── 前端开发
│   ├── 数据库与迁移
│   ├── 权限与 RBAC
│   └── API 接口
├── 插件开发
│   ├── 插件目录结构
│   ├── Manifest
│   ├── 后端插件开发
│   ├── 前端插件开发
│   ├── 生命周期
│   ├── 权限与菜单
│   └── 发布流程
├── 官方插件
├── 第三方插件
└── 版本更新
```

### 7.3 文档编辑页

编辑页包含标题、摘要、正文编辑器、文档目录、保存状态、发布状态和元信息。元信息包括归属范围、插件、文档类型、版本、关联仓库、分支、Issue/PR 和可见范围。

## 8. 数据模型

核心实体：

- `documents`：标题、slug、summary、scope、plugin_code、type、status、visibility、current_revision_no、owner_id、content_json、plain_text、lock_version、timestamps。
- `document_revisions`：document_id、revision_no、content_json、change_summary、created_by、created_at。
- `document_categories`：树形目录、scope、parent_id、sort_order。
- `document_category_links`：文档与目录的关联。
- `document_events`：草稿保存、发布、归档、恢复、导入导出等生命周期记录。

正文以 JSON 为主；Markdown/HTML 为导出和兼容字段。渲染 HTML 不作为信任边界，服务端和前端都必须执行白名单净化。

## 9. 后端边界

插件提供：

- 文档、目录、版本、评论 CRUD
- 搜索和筛选
- 草稿保存、发布、归档、恢复
- Markdown/HTML 导入导出
- 权限检查和审计日志
- 插件关联及版本信息

所有写操作通过现有 Webman 管理会话、CSRF 和 RBAC 中间件；前端隐藏按钮不作为授权依据。

建议接口：

```text
GET    /admin/plugin/document/tree
GET    /admin/plugin/document/documents
POST   /admin/plugin/document/documents
GET    /admin/plugin/document/documents/{id}
PUT    /admin/plugin/document/documents/{id}
DELETE /admin/plugin/document/documents/{id}
POST   /admin/plugin/document/documents/{id}/publish
POST   /admin/plugin/document/documents/{id}/archive
GET    /admin/plugin/document/documents/{id}/revisions
POST   /admin/plugin/document/documents/{id}/revisions/{revisionNo}/restore
GET    /admin/plugin/document/search
```

Markdown 导入和 Markdown/HTML 导出在浏览器端完成：导入文件先在前端解析为 Tiptap JSON，再通过普通文档保存接口提交；导出从已加载的 JSON 生成下载文件。第一版不提供公开或会员阅读入口，`visibility` 固定为 `admin`，所有接口都在后台会话下。

## 10. 插件目录

```text
admin/plugins/document/
├── plugin.json
├── Plugin.php
├── config.php
├── app/admin/plugins/document/
│   ├── controller/
│   ├── model/
│   ├── service/
│   └── middleware/
├── database/migrations/
├── web/src/admin/plugins/document/
│   ├── api.ts
│   ├── types.ts
│   ├── Index.vue
│   ├── pages/
│   ├── components/
│   └── editor/
└── storage/
```

## 11. 第一阶段范围

1. 插件骨架、Manifest、迁移和菜单权限。
2. 主框架 / 插件文档目录树。
3. 文档列表、新建、编辑、删除、草稿保存。
4. Tiptap 技术文档编辑器和安全预览。
5. 发布、归档、版本历史。
6. 搜索、Markdown 导入导出。
7. 自动测试、插件校验和浏览器验收。

评论、精细权限、Git 关联同步可以在第一版基础能力稳定后实现；数据模型预留字段和表结构。

## 12. 验收标准

- 插件可以通过 FunAdmin 标准流程安装、启用、停用和打包。
- 主框架文档和插件文档能在同一页面中按范围和目录浏览。
- 技术文档中的代码块、表格、任务列表、Callout 和 Mermaid 不会在编辑后丢失。
- 未授权用户无法通过 API 读取或修改受保护文档。
- 草稿、发布、归档和版本恢复有清晰状态及审计记录。
- 导入的 Markdown 不执行脚本；图片、链接和附件经过安全校验。
- 前端类型检查、组件测试、后端插件测试和浏览器基本流程通过。

## 13. 第一版决定（实现者决定，用户可推翻）

审查后为让第一版可落地，做出以下决定，覆盖前文中与之冲突的描述：

1. **范围**：第一版只做目录、文档 CRUD、编辑器、预览、发布/归档、版本历史与恢复、搜索、Markdown 导入导出。评论、文档级 ACL、评审流程不做，表也暂不建；状态只有 `draft / published / archived`。
2. **版本语义**：文档本身保存当前草稿（`content_json` + `lock_version`）。每次"发布"生成一条不可变修订 `document_revisions`（自增 `revision_no`）。"恢复"把某个修订的内容复制回草稿，不删除任何修订。`applies_to` 是自由文本的"适用版本"（如 `v2.0`），与修订号无关。保存用 `lock_version` 做乐观锁，冲突返回 409。`slug` 在全局唯一。
3. **权限**：只用 FunAdmin 现有 RBAC 的插件权限，粒度为 查看 / 编辑 / 发布 / 删除 / 目录管理 五项，后端用 `#[PluginPermission]` 校验。
4. **空间**：单一全局文档空间，不建空间实体。`scope` 固定为 `framework / plugin_dev / official_plugin / third_party` 四类；`plugin_code` 为可选自由文本，插件卸载不影响文档。
5. **内容与安全**：正文只存 Tiptap JSON，不存可执行 HTML。后端校验节点类型白名单；预览由前端 Tiptap 从 JSON 生成 HTML，链接只允许 http(s)、mailto、站内路径和锚点。新增依赖：Tiptap 表格、任务列表、代码块高亮（lowlight）；Callout、Mermaid 为自定义节点，Mermaid 只存源码，预览时渲染。Markdown 导入走 marked + DOMPurify 再交给 Tiptap 解析，导出由 JSON 生成；不保证与原 Markdown 字节一致。
6. **附件**：复用后台现有附件上传接口，图片和附件按现有规则是公开 URL；第一版不做受保护附件。
7. **审计**：沿用全局操作日志；保存接口的正文较大，第一版接受日志记录正文，后续如有体积问题再做字段摘要。
8. **数据库**：只支持 MySQL（与现有迁移服务一致）。搜索第一版用标题/摘要/纯文本列 `LIKE`，纯文本由保存时从 JSON 提取。
9. **初始化内容**：安装时只 seed 四类 scope 的目录树和少量入门文档；seed 带稳定 slug 和 source 标记，仅在 slug 不存在时插入，后续升级不会覆盖管理员编辑过的文档。插件包内的 `resources/seed/` 是唯一来源。
10. **国际化**：第一版只做界面和系统文案国际化，文档正文不做多语言版本；正文语言由作者自行在文档中维护。
11. **可见性**：第一版固定为后台管理员可见，不提供 public/member 阅读路由，不把公开 URL 作为文档访问方式。
12. **前端依赖**：标准插件源码在宿主 `web` 构建期收录，第一版允许为文档插件向宿主 `web/package.json` 增加固定版本的 Tiptap 扩展、Markdown 解析、代码高亮、Mermaid 和净化依赖；不在运行时动态安装或执行插件依赖。插件 manifest 声明最低宿主前端能力版本。
13. **渲染职责**：后端只校验 JSON、提取纯文本和保存；Tiptap JSON 到 HTML/Markdown 的渲染全部在前端完成，不保存客户端提交的 rendered HTML。
