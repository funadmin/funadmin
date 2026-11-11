# 插件与生成业务表软删除：验证记录

日期：2026-11-11　分支：Rbac　验证时 HEAD：`8db49cefe`（加上本次的防回退测试与文档）

计划：`docs/superpowers/plans/2026-11-11-plugin-soft-delete.md`　设计：`docs/superpowers/specs/2026-11-11-plugin-soft-delete-design.md`

## 与原计划的差异（执行中的裁定）

- 方案 A：普通软删除，不做统一回收表。
- 只有存在非主键唯一键的表才加 `deleted_token`。OSS 配置保留字符串主键，问答保留原主键，两者只加 `deleted_at`。
- CMS 4 张原本没有主键的关系表新增自增 `id`，唯一键保持原名并以 `deleted_token` 结尾（计划里的 `uk_<表>_pk` 写法只用于 `document_category_links`）。
- 插件 CRUD 重新生成时旧表沿用旧唯一键形状（只补 `deleted_at` 索引），重建交给 `business:soft-delete-upgrade`；trait 在字符串主键且表上有 `row_no` 时自动以 `row_no` 为令牌来源。
- 规则写在 `admin/README.md`“软删除”一节的“插件与生成表”，各插件 README 写了本次迁移的升级说明。
- 新增 `admin/tests/PluginSoftDeleteGuardTest.php`（6 个测试，入库）：插件表结构、物理删除白名单（7 个具名的回收站彻底删除方法）、非豁免表模型使用 `LaravelSoftDelete`、三种生成器的建表 SQL。四种改坏方式（插件代码里加 `Db::table()->…->delete()`、QA 模型去掉 trait、QA 安装去掉软删除步骤、表单设计器唯一键去掉令牌）各自让对应测试失败，恢复后全部通过。

## 1. 测试结果

| 命令 | 结果 |
| --- | --- |
| `cd admin && vendor/bin/phpunit` | OK：1911 个测试，27254 个断言，跳过 119 个，0 失败，耗时 4 分 40 秒（Task 6 后为 1905/119，加本次守卫测试 6 个） |
| `cd admin && vendor/bin/phpunit tests/Cms tests/Document tests/Qa tests/Oss` | OK：555 个测试，12517 个断言，跳过 4 个（CMS 中只在 MySQL 下运行的用例） |
| `cd admin && vendor/bin/phpunit tests/PluginSoftDeleteGuardTest.php` | OK：6 个测试，272 个断言 |
| `cd web && npx vitest run` | 236 个文件、1987 个测试全部通过 |
| `cd web && npx vue-tsc --noEmit` | 0 个错误 |
| `cd admin && PATH=/tmp/fa-podman/bin:$PATH php tests/Console/run-mysql.php`（podman，MySQL 8.0） | OK：103 个测试，1480 个断言，耗时 10 分 6 秒；包含文档、问答、OSS、CMS 的 `MySql*SoftDeleteUpgradeTest`，以及业务表升级命令的 MySQL 测试 |
| CMS MySQL 测试（`/tmp/fa-final/run.sh` 方式：每个文件一个独立 mysql:8.0 容器，`funwebman-console:8.5` 中运行） | `MySqlLockTest`（24 个断言）、`MySqlSchemaTest`（3）、`MySqlSiteConfigurationTest`（5）、`MySqlCommunityUpgradeTest`（16）全部 OK，退出码均为 0 |

没有失败，因此不需要区分是本工作还是其他会话造成的失败。全部运行在同一工作树上，期间没有其他会话的未提交改动（开始时 `git status` 干净）。

## 2. 没有验证的内容

- 开发库没有执行任何迁移（见第 4 节），所以没有在真实开发数据上验证 CMS 007、文档 004 的升级耗时与锁表时间，也没有在升级后的开发环境里手动点回收站页面。
- 没有在浏览器里手动走一遍文档、CMS 回收站；只有 vitest 组件测试。
- 真实云 OSS、开启 `sql_generate_invisible_primary_key` 的 MySQL、MySQL 5.7 / MariaDB 都没有测试。
- 表单设计器在 MySQL 上带真实外键时的彻底删除分支、`ALTER` 子句顺序只在 SQLite 上覆盖（见下方遗留项）。
- `admin/tests/{Cms,Document,Qa,Oss}` 是本地忽略目录，其中新增的测试不入库；入库的覆盖是 `tests/Console/MySql*SoftDeleteUpgradeTest.php` 与本次的守卫测试。

## 3. 遗留的小问题（来自执行台账，均已评估为可延后）

**影响行为**

- 删除时间精确到秒：同一秒内先删子记录（或编辑移除关联）、再删父记录，恢复父记录会把它一起带回（文档目录关联、CMS 导航项/提交/角色）。README 已说明。
- 文档：`hasLiveChildrenOrLinks` 的加锁读取会扫描目录表（`parent_id` 无索引），与 `update()` 并发时可能死锁；彻底删除文档保留 `document_revisions`/`document_events`（豁免表，按设计保留）。
- CMS：导航回收站的恢复/彻底删除不按语言过滤；因上级未恢复而跳过的导航项会一直留在已删除状态；回收站在混有 1.6.0 之前的行时按 UTC 显示时间；成员彻底删除可能一并清掉因编辑而软删除的角色行；成员回收站会读取该成员全部已删除角色关联（数量随时间增长）；关系绑定接口现在多返回 `id`、`deleted_at`（无害）。
- OSS：以后若增加配置删除，需要 `withTrashed` 解析与 `current_profile_id` 处理。
- 生成器：代码生成器生成的模型（`Crud` trait 的 strict 模式）不执行表单设计器的 `ON DELETE` 规则，仍拒绝任何引用；外键检测没有按 `REFERENCED_TABLE_SCHEMA` 过滤，未处理复合外键与 `foreign_key_checks`。
- 升级命令：DDL 之后失败会留下部分完成状态（重跑可恢复，README 已说明）；预检的锁检查不是原子的；非默认连接上基线前缀的处理；生成的插件迁移写死了带前缀的表名、只重建快照中的单列唯一键；迁移编号可能与上游版本冲突；应用已有快照文件后没有重新检查结构。

**维护**

- 部分前端测试在组件之后编写，没有 RED 记录（文档、CMS 回收站）；Task 6 修复轮也没有 RED 记录。
- 文档插件的 `tests/Document` 新测试只在本地（忽略目录）。
- 没有测试：问答 `ask()` 的 `withTrashed()`、升级命令写文件失败时的删除、MySQL 上表单设计器真实外键分支、MySQL 上 `ALTER` 子句顺序。
- QA `softDelete()` 的 SQLite 等价实现没有 `hasColumn` 守卫（只在全新安装时调用）。
- CMS 成员查询次数上限的测试较宽松；Task 4 的提交分组与简报不同；计划中 `uk_<表>_pk` 的措辞已过时（以裁定为准）。

## 4. 开发库状态

本次**没有**对开发库执行任何迁移或写入。只读查询（`/tmp/t7/ro/devdb-status.php`，只有 SELECT 与 information_schema 读取）的结果：

| 项目 | 状态 |
| --- | --- |
| 核心迁移 | 001–006 已执行（含核心 `005_soft_delete`） |
| CMS | 插件 1.5.1，已启用，`db_version` = `006_page_form`；**007 未执行**，`cms_site` 还没有 `deleted_token` |
| 技术文档 | 插件 1.4.0，已启用，`db_version` = `003_document_sort_order`；**004 未执行**，`documents` 还没有 `deleted_token` |
| 问答 | 开发库中没有插件记录，也没有 `qa_question` 表（虽然本地有安装副本 `admin/app/qa`）；以后安装时直接建成 1.1.0 结构 |
| OSS | 未安装 |
| 业务表 `fun_order` | 有 `deleted_at`，没有唯一键；升级命令只会补 `deleted_at` 索引 |

本地安装副本已经是新代码（CMS、文档读写依赖新列），所以在执行下面的升级之前，开发环境里的 CMS 和技术文档读写会报错。由用户按顺序执行：先备份；技术文档（停用、更新 004、启用）；CMS（停用、更新 007、启用；已装问答时先停用问答）；问答（若已安装，更新并启用）；最后 `business:soft-delete-upgrade --table=order --dry-run`，再正式执行。

1. **先备份开发库**（`admin/` 下，库名、账号取自 `.env`）：

   ```sh
   mysqldump --single-transaction --routines --triggers -h <DB_HOST> -P <DB_PORT> -u <DB_USERNAME> -p <DB_DATABASE> > ~/funadmin-dev-before-plugin-soft-delete.sql
   ```

2. **插件更新**（后台插件中心，按以下顺序）：
   1. 技术文档：停用 → 更新（执行 `004_soft_delete`，到 1.5.0）→ 启用。
   2. CMS：停用 → 更新（执行 `007_soft_delete`，到 1.6.0）→ 启用。问答依赖 CMS，插件服务在问答启用期间拒绝停用 CMS：**若已安装问答，先停用问答再停用 CMS**。CMS 007 会重建 6 张 TIMESTAMP 表和 4 张关系表，表很小，开发库上应很快。
   3. 问答（仅已安装时）：在 CMS 启用之后更新（执行 `002_soft_delete`，到 1.1.0）→ 启用。当前开发库未安装问答，跳过。
   OSS 未安装，不需要操作。

3. **业务表升级**（插件更新之后；先 `--dry-run` 看计划，再正式执行）：

   ```sh
   cd admin
   php webman business:soft-delete-upgrade --table=order --dry-run   # 预期只有 ADD INDEX idx_fun_order_deleted_at
   php webman business:soft-delete-upgrade --table=order
   ```

4. 升级后检查：`fun_system_migration` 出现 `plugin:document 004_soft_delete`、`plugin:cms 007_soft_delete`；后台技术文档与 CMS（导航、表单、成员）的回收站可以打开、恢复和彻底删除；再次运行升级命令输出“无需升级”。
