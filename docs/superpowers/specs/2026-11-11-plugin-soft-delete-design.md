# 插件与生成业务表软删除设计

## 目标

把核心表已经完成的软删除规则推广到四个插件（CMS、文档、问答、OSS）和代码生成器。用户要求“按 Rbac 的做法继续改插件”。核心规则见 `admin/README.md` 的“软删除”一节和 `docs/superpowers/specs/2026-11-10-backend-soft-delete-design.md`。

需要遵循的核心规则：

- 每张表都有自增 `id` 主键、`deleted_at DATETIME NULL`（建索引）。
- **只有带唯一索引的表**才加 `deleted_token BIGINT UNSIGNED NOT NULL DEFAULT 0`，且每个唯一索引都以 `deleted_token` 结尾，删除后可以用相同的唯一值重建（用户确认：没有唯一索引的表不加，减少复杂度；trait 发现没有这一列时只写 `deleted_at`，已支持）。
- 模型使用 `LaravelSoftDelete`。恢复时撞上唯一值返回 409。
- 已软删除的数据一律保留，不做自动删除。真实删除只在管理员的回收站手动操作中发生。
- 通过 `Db::table`、联表或原生 SQL 读取数据时，要显式过滤 `deleted_at IS NULL`。
- 关系表的保存按差异同步，只改动变化的部分。

## 我替用户做的决定（用户中途打断了选择题，按推荐方案执行，可推翻）

1. **只追加、从不删除的日志和版本表豁免**：`cms_form_event`、`cms_site_auth_audit`、`cms_model_version`、`cms_content_revision`、`cms_template_tag_version`、`document_events`、`document_revisions`。原因：这些表没有删除功能，加列只会让最大的几张表锁得更久。它们的主表被删除后，它们作为历史保留，不连带删除。表中如有唯一索引，保持原样（只追加、不会删除，所以不会冲突）。
2. **OSS 配置表 `oss_profile` 主键是 16 位随机字符串**：保留字符串主键，只加 `deleted_at` 及索引，不加 surrogate id 和 `deleted_token`。原因：没有其他唯一索引，id 随机生成永不复用，且被 `current_profile_id`、附件驱动名 `oss_<id>`、加密引用；改主键需要重命名列或搬数据，迁移守卫不允许。`oss_setting` 是单行设置表，豁免。（执行中调整）
3. **问答表 `qa_question` 的主键复用 CMS 提交记录的 id，不自增**：`deleted_token` 写入的是这一行的主键值，主键本身是数字且唯一，规则照样成立，不需要改主键。
4. **CMS 中 4 张没有主键的关系表**（`site_admin`、`site_model`、`site_role_permission`、`site_role_member`），以及文档的 `document_category_links`（复合主键）：新增自增 `id` 作为主键，原唯一键或主键改为以 `deleted_token` 结尾的唯一索引。
5. **`deleted_at` 由 TIMESTAMP 改为 DATETIME**，与核心保持一致。用 `MODIFY` 修改，会重建表，迁移说明中写明。
6. **代码生成器**：`deleted_token` 统一存数字。对有唯一字段的 UUID 主键表，生成器额外加一个自增序号列 `row_no`，作为 `deleted_token` 的来源；没有唯一字段的 UUID 表不需要。
7. **表单设计器生成的外键**：软删除父记录时，由程序按外键的配置处理子记录：级联则一起软删除，置空则把子记录的外键字段置空，禁止则拒绝删除。数据库里的真实外键只在回收站彻底删除时生效。
8. **已生成上线的业务表（例如订单表）**：提供一条带检查的升级命令，补上新列、索引，并重建唯一索引。

## 迁移

各插件新增一个迁移文件，写法与核心 005 相同：

- 用 information_schema 检查后再执行，同一张表的改动合并成一条 ALTER，可以重复执行。
- 语句里只用 ASCII，PREPARE 语句中只允许 `DROP INDEX` / `DROP PRIMARY KEY`。
- 已有的迁移文件不修改，它们受校验和锁定。

| 插件 | 新迁移文件 | 新版本 |
|---|---|---|
| CMS | `007_soft_delete.sql` | 1.6.0 |
| 文档 | `004_soft_delete.sql` | 1.5.0 |
| 问答 | `002_soft_delete.sql` | 1.1.0 |
| OSS | `002_soft_delete.sql` | 1.1.0 |

插件的 SQL 文件原本由 PHP Schema 类导出，测试会核对两者完全一致。这次的 PREPARE 迁移无法由建表蓝图生成，因此做法与核心一样：

- 手写 `.sql` 文件。
- Schema 类里写一个等价实现，供测试在 SQLite 上建表使用。
- 新增一致性测试：在 MySQL 上，SQL 迁移和 Schema 安装得到的表结构必须一致。

## 代码

- **CMS**：
  - 所有模型启用 `LaravelSoftDelete`。
  - `withTrashed()->update`、`updateOrCreate` 一类的写入改为只针对未删除的行；删除后同名重建时新建一行，不复活旧行。
  - 表单、提交、菜单、站点角色等的普通删除改为软删除。
  - 表单提交删除时不再删除磁盘上的上传文件，文件只在回收站彻底删除时才删除。
  - 整组替换型的写入（菜单项、角色权限、成员角色）改为按差异同步。
  - 联表和 `Db::table` 读取都补上 `deleted_at` 过滤。
- **文档**：
  - 删除文档改用 trait；分类关联同时软删除，恢复文档时一起恢复。
  - 新增文档恢复功能。
  - 分类删除、内置分类退役改为软删除。
- **问答 / OSS**：模型启用 trait。目前没有删除功能，不新增。
- **回收站**：每个插件给需要的资源提供回收站接口，包括列表、恢复、彻底删除，每个操作有独立权限并要求二次确认。前端页面按插件逐个补充。文件只在彻底删除时清理。

## 生成器

- `ProductionTemplateContext`、`FormDesignerService::createSql`、`PluginCrudTarget` 生成的表都带 `deleted_at` 索引；表里有唯一字段时才加 `deleted_token`，唯一索引以 `deleted_token` 结尾。
- `assertPendingCreateTables` 的检查同步修改。
- `deleted_token` 加入受管字段列表，并加入模型的 `$hidden`。
- 外键按上面第 7 条处理。

## 验证

- 每个插件：迁移一致性测试；同名删除后重建成功，未删除的重复值仍被拒绝；恢复冲突返回 409；回收站接口的权限和作用范围；原有插件测试全部通过。
- 在 MySQL 8.0（podman）上验证插件迁移，测试从当前版本带数据升级。
- 跑完整的后端、前端测试和类型检查。

## 协调

CMS 一直由 funadmin-56 会话负责。动 CMS 之前先确认它没有未提交的 CMS 改动；先做文档、问答、OSS 和生成器部分。
