# 后台数据全部软删除：验证记录

日期：2026-11-10　分支：Rbac　初轮验证时 HEAD：`6668a0958`（文档提交）；本轮代码提交：`6ff990e6e`

计划：`docs/superpowers/plans/2026-11-10-backend-soft-delete.md`　设计：`docs/superpowers/specs/2026-11-10-backend-soft-delete-design.md`

## 与原计划的差异

- 取消自动清理（原 Task 5 删除）：软删除行一直保留，只在管理员手动操作回收站时真正删除，包括操作日志的 `force` 与 `clear-force`（都只作用于已删除记录），以及会员回收站的 `destroy`。
- 核心迁移 005：所有核心表都有 `id` 主键、`deleted_at` 和 `deleted_token`，唯一索引以 `deleted_token` 结尾。原先用自然主键的 12 张表新增 `id` 主键，原主键改为 `uk_<表>_pk`。006 登记操作日志的两个彻底删除权限码。
- 迁移守卫改为共用 SQL 词法器（`SqlLexer`），规则写在 `admin/README.md`“数据库迁移目录”一节。
- 文档：软删除规则、恢复冲突（409）、会员冷静期和升级说明写在 `admin/README.md`“软删除”一节。

## 1. 初轮后端测试（本轮复测见第 5 节）

| 命令（在 `admin/` 中执行） | 结果 |
| --- | --- |
| `vendor/bin/phpunit` | OK：1770 个测试，24056 个断言，跳过 110 个，0 失败，耗时 4 分 26 秒 |
| `vendor/bin/phpunit tests/Cms` | OK：322 个测试，10428 个断言，跳过 4 个，0 失败 |

基线（开始前）为 1483 个测试、跳过 107 个、0 失败。新增的测试都来自本工作和同期其他会话，没有新的失败。跳过的主要是只在 MySQL 下运行的测试（`TEST_MYSQL` / docker）。

## 2. 初轮前端测试（本轮全量见第 5 节）

| 命令（在 `web/` 中执行） | 结果 |
| --- | --- |
| `npx vitest run tests/views/operation-log-force-delete.spec.ts src/admin/views/system/member tests/contracts/member-group-icon.spec.ts tests/contracts/member-registration-admin-scope.spec.ts tests/api/memberAuth.spec.ts` | 9 个文件、47 个测试全部通过 |
| `npx vue-tsc --noEmit` | 0 个错误 |

## 3. 开发环境

- 开发库是 MySQL，服务在 `127.0.0.1:8787`（HTTP 200）。只读查询 `fun_system_migration` 的结果：core 范围只执行了 `001_baseline`、`002_code_generator`、`003_system_secret`、`004_plugin_admin_web_directory`。
- **005、006 在开发库上尚未执行**。本次没有对开发库执行迁移，也没有做任何改动，因为 005 会重建 12 张表，是否执行由用户决定。所以原计划 Step 2（迁移后用 HTTP 检查登录和菜单）没有做。
- 由用户执行时，在 `admin/` 中运行项目的常规迁移命令（MySQL 走核心 SQL 目录）：

  ```sh
  cd admin && php bin/console.php migrate
  ```

  预期输出 `005_soft_delete`、`006_operation_log_force_delete`。执行前建议先备份开发库。执行后检查后台登录、菜单、操作日志回收站和会员回收站。

## 4. 未验证 / 遗留

**未验证**

- 005/006 没有在 MySQL 8.0 上跑过。需要 docker，在 `admin/` 中执行 `php tests/Console/run-core-mysql.php`，发布前必须跑。本机只有非 8.0 的本地 MySQL 跑过 005。006 没有在 MySQL 测试里重放。
- AI 消费者的控制台测试（`AiProfileImageConsumer`、`AiSandboxConsumer`）在 MySQL 下经由 005 PHP 链路建表，这条路径没有验证。
- 并发保存加锁：`RelationRowSync` 和 `replacePolicies` 没有 `lockForUpdate`，两个并发保存可能合并出非预期的关联集合，未测试。
- 开发库升级后的人工检查（见第 3 节）。
- 其他只在 MySQL 下走的路径（Task 4 记录），在 SQLite 测试中没有覆盖。

**已知风险与行为限制**（来自执行台账的 minor (deferred)；不影响当前行为的事项另列于下文）

- 迁移守卫只是词法层面的安全网，看不到 `CHAR()`、十六进制、`UNHEX` 或从表中读出拼接的动态 SQL，也不检查 `SET GLOBAL init_connect`。
- 005 的 id 主键替换假设表上已有主键，结构漂移的安装可能失败。
- `TEST_MYSQL=1` 引导模式在 005 处对约 20 个测试类抛错，没有 CI 使用这个模式。
- 恢复相关：
  - 拿着过期模型实例调用 restore 不会生效（框架行为）。
  - 同一秒内编辑又删除，恢复时可能误匹配。
  - `afterRestore` 失败后，内存中的模型状态是旧的。
  - 恢复时间的时区未核对。
- 会员相关：
  - 旧数据 `member.group_id` 里残留的值，会在界面上看不出原因的情况下阻止删除分组。
  - 恢复被拒时，422 提示“修改会员等级”，但已删除的会员无法修改。
- `system_secret` 的 `Db::table` 读取没有过滤 `deleted_at`，目前没有代码软删除它。
- `PluginInfrastructureService`、`Ai.php` 中有两处应改用 `SystemMigration::ledger()`。
- 已被 gitignore 的 `database/business/*_permissions*.sql` 旧文件仍写着 `deleted_at=NULL`，可能被 `assertSafe` 拒绝。
- 前端：
  - 操作日志页面的 http 层没有测试。
  - 详情抽屉重复打开存在竞态。
  - 生成页面的列表请求被更新的请求取代时会弹出刷新提示。

**不影响当前行为的维护 / 历史事项**

- 插件（CMS 等）不在本次范围内，CMS 运行时存在重复的 `whereNull`，不影响结果。
- PHP 版 005 重建唯一索引时丢弃 DESC、注释和 invisible 属性，SQLite 重建丢弃排序规则、CHECK 和外键细节；目前没有表依赖这些属性（有结构漂移的安装仍须单独验证）。
- 表上没有 `deleted_token` 列时（插件兼容路径）会额外查 `hasColumn`，属于性能开销，不改变软删除结果。
- 注册提示会暴露账号已注销，这是设计要求，不是恢复缺陷。
- 006 在推送后改过名，但按台账没有共享库执行过旧名，属于发布记录提醒；执行过旧名的安装仍需补执行。
- 提交 `b0f3a9ad`（已推送）的提交说明声称清理、日志界面、会员冷静期都已完成。实际提交时只完成了 Task 1–2，属于历史提交说明不准确。


## 5. 本轮文档审查补漏（2026-11-10）

### 实现与审计

- `SystemConfig` 的配置项 / 分组普通删除改用共享 trait；分组保留“有未删除配置项则拒绝”的行为，不连带删配置项。已删除配置项不再永久阻止删除分组。配置 code、分组 name 的新建 / 编辑查重只看未删除行，支持删除后同键重建。
- `SystemLanguage` 同事务软删除语言和所有未删除译文，不改已有墓碑。语言 name 查重只看未删除行；`saveLine` 优先更新未删除同键译文，没有时才恢复最近删除的一条，避免重建后误恢复旧行造成冲突。
- `SystemAttachment` 只软删除附件行，**停止删除本地和云端存储文件**，也不再因驱动停用或历史对象 key 缺失而阻止软删除。保留成功的 removed 计数、失败 removed / failed 响应及原路由 / 权限。
- `auth:prune` 调用 `AuthPruneService`，通过三个认证模型的批量 delete 只标记过期未删除行；旧墓碑和未过期记录不变，命令帮助和输出明确写“软删除、记录保留”。
- `RestoreConflictException` 改继承 `DomainException`，保留 code 409 和原消息。通用 `Crud::restore` 的冲突由 `ExceptionHandle` 统一返回 HTTP / JSON 409。排查全部 RuntimeException / DomainException catch 后，补上宿主 `DevPlugin`、`SystemPlugin` 包装器的专门 409 catch，防止其兜底 Throwable 误转 500；其他 catch 不包围恢复调用，无需改动。
- 核对迁移 005：配置 code、分组 name、语言 name、译文 locale/key 的 UNIQUE 都以 deleted_token 结尾；测试使用真实迁移后结构验证同键重建。
- 额外发现 `ProductionTemplateContext` 生成的语言资源卸载说明仍建议 `DELETE FROM fun_language_line`；改成带 `deleted_at IS NULL` 的 UPDATE，同时写 deleted_at / deleted_token=id。未修改插件或已生成业务模块。
- 搜索范围为 `admin/app`、`admin/bin`、`admin/support`，排除任务列出的插件 / 生成模块目录。剩余物理删除仅有：`Crud` 的显式回收站 destroy / 无软删除模型兼容分支、会员 / 日志回收站彻底删除、`MemberCascade` 和 `MemberReferenceGuard` 在显式彻底删除时的关联清理、`PluginService::restoreDeploymentState` 首次安装回滚，以及范围外的 `FormDataService` 业务表操作。核心模型均带共享 trait，普通路径不进入无软删除模型的兼容分支。未发现其他核心表 Db::table 物理删除、raw DELETE FROM、TRUNCATE 或 detach。
- 文件层面的 ftruncate 是附件上传游标 / 确认令牌文件写入，不是核心表删除；上传失败时清理尚未成功入库的临时存储对象不属于已保存附件删除。

### 本轮验证与共享工作树边界

- 后端执行包含**其他会话尚未提交的测试改动**（例如大量测试改用 `Tests\Support\TestDatabase::migrate()`、tests/bootstrap.php 和测试库模板工具）；没有提交这些文件。CMS / OSS 插件测试按规则只运行、不修改。
- 新增测试覆盖配置 / 分组软删除与同键重建、语言 / 译文连带软删除与同键重建及 live 译文优先、附件文件保留及不可用云驱动、auth:prune 过期边界 / 旧墓碑 / 幂等、通用恢复 409、宿主插件控制器包装器 409。原 trait 测试改断言 DomainException。
- 前端 CRUD 契约原来硬编码 PHPUnit 恰好 2 个测试；本轮增加通用 409 测试后改为匹配 OK 的测试数量，不把新增测试误判为失败。

| 本轮命令 | 结果 |
| --- | --- |
| `cd admin && vendor/bin/phpunit` | 1780 测试、24712 断言，5 errors、5 failures、110 skipped，耗时 3 分 58.872 秒；本轮触发的生成语言 SQL hash 断言已随后修正并定向通过，4 个 OSS 旧删除契约因保留文件的新规则不再成立（按任务禁止修改插件测试）；其他 5 个 errors 属于其他会话范围，明细如下。未宣称全量通过 |
| 后端定向：`vendor/bin/phpunit tests/RemainingSoftDeleteGapTest.php tests/Console/CrudTraitExecutionTest.php tests/SoftDeleteTraitTest.php tests/ConfigIntegrationTest.php tests/LanguageIntegrationTest.php tests/ExceptionTest.php tests/AttachmentDeleteTest.php` | OK：49 测试、234 断言 |
| 宿主插件 / 资源生成定向：`vendor/bin/phpunit tests/PluginCenterHttpTest.php tests/PluginComposerHttpTest.php tests/PluginHttpActivationTest.php tests/Console/BusinessGenerationResourceTest.php` | OK：9 测试、95 断言 |
| `vendor/bin/phpunit tests/Cms` | 322 测试、10415 断言，1 error、4 skipped；其他会话范围内 `SiteRoleDelegationTest.php:18` 把 91 / 92 / 93 三个角色都命名为 Delegate，违反已有 name/deleted_token 唯一键，未改该文件 |
| `cd web && npx vitest run`（修正硬编码测试数后完整重跑） | 234 文件：227 通过、7 失败；1956 测试：1944 通过、12 失败，耗时 55.95 秒 |
| `cd web && npx vitest run tests/contracts/crud-model-resolution.spec.ts` | 1 文件、7 测试通过 |
| `cd web && npx vue-tsc --noEmit` | 退出 0，无类型错误 |
| 本轮 9 个生产 PHP 文件 `php -l` / `git diff --check` | 全部通过 |

**归属更正（补漏复核）**：此前把以下全部前端失败归为其他会话范围不准确。`admin-email-length`、`field-hygiene-migration`、`legacy-schema-debt`、`schema-integrity-migration` 四个文件中共 7 项失败，实际由本次软删除改造后契约与夹具未同步导致，由本任务负责，已在第 6 节修复；不是其他会话的问题。以下保留的是当时失败清单，最新结果见第 6 节：

- `web/tests/contracts/admin-email-length.spec.ts`：2 个源码契约（会员关联保存 / 分组引用保护）。
- `web/tests/contracts/field-hygiene-migration.spec.ts`：3 个源码契约（FieldVerify 主键、会员邮箱查重、角色 name/code 查重）。
- `web/tests/contracts/legacy-schema-debt.spec.ts`：1 个会员标签契约。
- `web/tests/contracts/member-registration-admin-scope.spec.ts`：1 个运行时契约，简化表重复添加 nickname（duplicate column name）。
- `web/tests/contracts/schema-integrity-migration.spec.ts`：1 个 pivot 源码契约。
- `web/tests/contracts/service-cache-invalidation.spec.ts`：3 个公共缓存清理能力源码契约；本轮只改宿主插件控制器的冲突 catch，没有修改其检查的服务缓存方法。
- `web/tests/contracts/title-to-name-migration.spec.ts`：1 个菜单名称映射源码契约。

首次全前端运行是 226 文件 / 1943 测试通过、8 文件 / 13 测试失败，其中 CRUD 测试数硬编码由本轮新增测试触发，已修正并完整重跑，上表是最终结果，不将该项归于其他会话。

### 前一轮后端全量失败归属（历史结果，最新复测见第 6 节）

全量运行不是绿色，不能套用初轮 1770 OK 的结论：

- 本轮发现并修复：`admin/tests/Console/CodeGeneratorRelationsTest.php` 的语言资源 SQL hash 随卸载说明从 DELETE 改 UPDATE 必须更新。随后完整定向运行该文件 **11 测试、98 断言全部通过**。全量运行在该修正前完成，未重跑第二遍全量。
- 本轮规则变更导致、受禁止修改插件测试约束而保留：`admin/tests/Oss/DeletionTest.php` 的 4 项旧契约要求云驱动失败时阻止删除、或真实删除云对象。现在正确行为是 200 软删除行、驱动完全不被调用、存储对象保留。本轮新核心测试覆盖本地文件保留和不可用云驱动仍可软删除。不能把这 4 个失败误归为其他会话造成。
- 其他会话 / 本轮范围之外的 5 个 errors（未修改相关文件）：
  - `admin/tests/Cms/SiteRoleDelegationTest.php:18`：三个角色使用相同 name=Delegate，触发 auth_group 唯一键；CMS 单独复测也报同一错误。
  - `admin/tests/Console/AiProfileImageConsumerTest.php:31` → `AiSandboxConsumerExecutionTest.php:35`：重复创建 fun_ai_change_set；前者已有其他会话的未提交改动。
  - `admin/tests/Console/BusinessGenerationRecoveryTest.php:28` → `BusinessGenerationFixture.php:14`：重复创建 fun_form，属测试库结构 / 简化夹具冲突。
  - `admin/tests/Console/BusinessGenerationStateTest.php:97`：重复创建 fun_form_schema_version；该文件已有其他会话未提交改动。
  - `admin/tests/PluginCenterIntegrationTest.php:28`：插入 plugin_operation 未提供不可空 source；该文件已有其他会话未提交改动。

修改宿主插件包装器和附件失败响应后，又跑了上述 49 项定向测试，全部通过；全量可能在开始收集之后才看到部分修改，最终行为以定向复测和已提交代码为准。

测试输出保留在本机 `/tmp/task9-backend-full.log`、`/tmp/task9-cms.log`、`/tmp/task9-frontend-final.log`、`/tmp/task9-typecheck.log`（临时文件，不入库）。共享工作树内其他会话的改动完整保留，本轮代码提交不包含它们；未执行开发库迁移、未推送。


## 6. 补漏复核：语言历史隔离与前端行为契约

### 修复与归属更正

- 语言重命名之前使用 `LanguageLine::withTrashed()` 批量改 locale，会搬走同名旧语言的已删译文，使新语言 `saveLine` 复活旧行。改为仅更新未删除译文，墓碑完全不动。回归按“建 fr-fr + 译文 → 删语言 → 重建 fr-fr → 改名 de-de → 保存同 key”重现，修复前明确失败于旧 locale 被改；修复后验证旧行完整属性（含 locale / deleted_at / deleted_token）不变、有效译文被正确移动、新保存不复活旧 id。
- 四个前端旧契约由本次软删除改造导致失配，之前归入其他会话不准确，现予纠正；不回退生产软删除逻辑，也不只是删除旧断言。
- `admin-email-length.spec.ts` 改成验证 `SystemMember` 委托 `RelationRowSync` 后真实执行的差异同步行为：未变化行 id / created_at 不变、移除行写两列软删除、新增行插入、已有墓碑保留、再次保存幂等。分组引用保护检查委托 `MemberReferenceGuard`，运行控制器 / 模型实际拒绝有效引用、删除会员后允许删除分组的测试。管理员邮箱长度与会员登录字段断言全部保留。
- `schema-integrity-migration.spec.ts` 对分组、标签均运行上述真实控制器关系同步测试，核验新增 created_at、旧 created_at 与墓碑不变、没有 create_time 写入；保留迁移前缀 / 查询下推断言。
- `legacy-schema-debt.spec.ts` 标签同步改验证同样的运行时差异行为；其余 API / UI 断言全部保留。
- `field-hygiene-migration.spec.ts` 用实例 `FieldVerify::getKeyName()` 验证 id；注册测试复用真实迁移夹具（含 login_attempts），覆盖有效会员重复邮箱拒绝、冷静期内拒绝、期满后同邮箱可注册且旧行保留、60 字符限制及空值规范化；角色测试覆盖未删除 name / code 重复拒绝、软删除后相同值可重用，并保留数据库唯一冲突异常处理源码断言及实际数据库唯一约束验证。
- 运行时行为测试统一位于 `admin/tests/RemainingSoftDeleteGapTest.php`，四个前端文件调用其对应定向 PHPUnit 测试，避免复制不完整的手建表夹具。
- CMS / OSS 的 gitignored 测试由其他会话先行修复；本任务只读复核并运行两文件，5 测试、46 断言通过，没有覆盖其修改。其他会话修正的五类夹具也以当前工作树为准，不纳入本任务提交。

### 定向验证

| 命令 | 结果 |
| --- | --- |
| `cd admin && vendor/bin/phpunit tests/RemainingSoftDeleteGapTest.php --filter test_language_rename`（先红后绿） | 修复前 1 测试失败（旧译文 locale 被移动）；修复后 1 测试、7 断言通过 |
| `cd admin && vendor/bin/phpunit tests/RemainingSoftDeleteGapTest.php` | 11 测试、128 断言全部通过 |
| `cd web && npx vitest run tests/contracts/admin-email-length.spec.ts tests/contracts/schema-integrity-migration.spec.ts tests/contracts/legacy-schema-debt.spec.ts tests/contracts/field-hygiene-migration.spec.ts` | 4 文件、17 测试全部通过 |

完整前后端复测仍包含其他会话的未提交迁移 / 测试 / 模板工具改动；本任务只提交语言重命名修复、RemainingSoftDeleteGapTest、四个前端契约及本验证文档，未提交共享修改，未推送。


### 全前端复测与剩余分类（当前工作树）

- `cd web && npx vitest run`：234 文件中 **231 通过、3 失败**；1956 测试中 **1951 通过、5 失败**，耗时 63.29 秒。上述四个文件的 17 个测试在全量中也通过。
- `cd web && npx vue-tsc --noEmit`：退出 0，无类型错误。外层串行脚本的最终退出码是类型检查的 0，**不代表 Vitest 全量通过**；以 Vitest 的上述统计为准。
- 后端扩展定向复测：`vendor/bin/phpunit tests/LanguageIntegrationTest.php tests/MemberCoolingPeriodTest.php tests/Console/CrudTraitExecutionTest.php tests/RemainingSoftDeleteGapTest.php`，38 测试、332 断言通过。

剩余 5 个前端失败逐项分类（没有简单归咎于其他会话，也没有证明它们是生产错误）：

1. `member-registration-admin-scope.spec.ts` 的默认分组注册测试：**迁移后夹具失配**。完整迁移已有 nickname，测试仍无条件 `schema()->table('member')` 添加 nickname / motto / real_name 等列，先于注册断言即报 duplicate column name: nickname。该文件与本轮指定的四个契约不同，未修改；和本轮已修的邮箱夹具属于类似的过时夹具债务，不能靠该失败推断注册行为错误。
2. `service-cache-invalidation.spec.ts` 的 AbstractService 测试：**旧缓存架构源码契约**，要求 `use support\Cache` 和 `clearApplicationCache()` 全局清理接口；当前实现没有该接口，断言在运行时验证前失败。未触及该服务。
3. 同文件 ResourceRegistryService 测试：**旧缓存架构源码契约**，要求调用 `$this->clearApplicationCache()`，与现有定向鉴权缓存失效实现不符。未修改该实现。
4. 同文件 PluginService 测试：**旧缓存架构源码契约**，同样硬编码公共全局缓存清理调用。此前只修改其宿主控制器的恢复冲突 catch，未修改服务缓存逻辑。
5. `title-to-name-migration.spec.ts` 的菜单 API 测试：**源码签名契约过时**，硬编码 `$this->menuData($menu)`，当前实际为 `$this->menuData($menu, $code($menu))`，新增明确权限参数；在 JSON 行为断言前失败。未修改菜单服务。其后迁移夹具也需按当前已播种管理员调整，不能仅放宽一处字符串就宣称通过。

这些余项保持为明确未通过项，未在本任务中扩展修改范围；建议后续分别改为与当前结构和缓存语义一致的行为测试。


### 全后端复测与提交

- `cd admin && vendor/bin/phpunit`：**退出 0；1787 测试、25224 断言、110 skipped，0 errors / 0 failures**，耗时 3 分 35.495 秒。运行包含其他会话尚未提交的核心迁移 / 测试夹具改动；此前 CMS、OSS 和五类夹具错误在当前共享工作树中已消失，不再列为当前遗留。
- 补充 `vendor/bin/phpunit tests/SoftDeleteTraitTest.php tests/ExceptionTest.php`：17 测试、84 断言通过。
- 本轮生产 PHP / 测试 PHP 语法检查及**本任务路径**的 `git diff --check` 均通过；整个共享工作树曾在其他会话的 `MySqlSoftDeleteUpgradeTest.php:122` 发现尾空格，未越界修改，也不据此宣称全仓库 diff 无警告。
- 代码 / 测试提交：`9efcba603`（`fix: 语言重命名保留译文墓碑，更新软删除行为契约`）。文档随后单独提交；没有推送，没有对开发库执行迁移。
- 当前重要遗留为上文 **3 个前端文件中的 5 项失败**，不是全后端失败。本任务指定的 4 个前端契约均已通过；未以回退软删除逻辑或放宽状态码的方式让测试变绿。

本轮完整日志：`/tmp/task9-followup-backend-full.log`、`/tmp/task9-followup-frontend-full.log`、`/tmp/task9-followup-typecheck.log`（临时文件，不入库）。
