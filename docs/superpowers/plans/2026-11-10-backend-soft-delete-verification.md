# 后台数据全部软删除：验证记录

日期：2026-11-10　分支：Rbac　验证时 HEAD：`6668a0958`（文档提交）

计划：`docs/superpowers/plans/2026-11-10-backend-soft-delete.md`　设计：`docs/superpowers/specs/2026-11-10-backend-soft-delete-design.md`

## 与原计划的差异

- 取消自动清理（原 Task 5 删除）：软删除行一直保留，只在管理员手动操作回收站时真正删除，包括操作日志的 `force` 与 `clear-force`（都只作用于已删除记录），以及会员回收站的 `destroy`。
- 核心迁移 005：所有核心表都有 `id` 主键、`deleted_at` 和 `deleted_token`，唯一索引以 `deleted_token` 结尾。原先用自然主键的 12 张表新增 `id` 主键，原主键改为 `uk_<表>_pk`。006 登记操作日志的两个彻底删除权限码。
- 迁移守卫改为共用 SQL 词法器（`SqlLexer`），规则写在 `admin/README.md`“数据库迁移目录”一节。
- 文档：软删除规则、恢复冲突（409）、会员冷静期和升级说明写在 `admin/README.md`“软删除”一节。

## 1. 后端测试

| 命令（在 `admin/` 中执行） | 结果 |
| --- | --- |
| `vendor/bin/phpunit` | OK：1770 个测试，24056 个断言，跳过 110 个，0 失败，耗时 4 分 26 秒 |
| `vendor/bin/phpunit tests/Cms` | OK：322 个测试，10428 个断言，跳过 4 个，0 失败 |

基线（开始前）为 1483 个测试、跳过 107 个、0 失败。新增的测试都来自本工作和同期其他会话，没有新的失败。跳过的主要是只在 MySQL 下运行的测试（`TEST_MYSQL` / docker）。

## 2. 前端测试

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

**已知遗留，会影响行为**（来自执行台账的 minor (deferred)）

- 迁移守卫只是词法层面的安全网，看不到 `CHAR()`、十六进制、`UNHEX` 或从表中读出拼接的动态 SQL，也不检查 `SET GLOBAL init_connect`。
- 005 的 id 主键替换假设表上已有主键，结构漂移的安装可能失败。PHP 版 005 重建唯一索引时会丢掉 DESC、注释和 invisible 属性，目前没有表用到。SQLite 重建会丢掉排序规则、CHECK 和外键细节，目前也没有表用到。
- `TEST_MYSQL=1` 引导模式在 005 处对约 20 个测试类抛错，没有 CI 使用这个模式。
- 006 在推送后改过名，执行过原 006 的库需要手动补执行。按台账，没有共享库执行过它。
- 恢复相关：
  - 拿着过期模型实例调用 restore 不会生效（框架行为）。
  - 同一秒内编辑又删除，恢复时可能误匹配。
  - `afterRestore` 失败后，内存中的模型状态是旧的。
  - 恢复时间的时区未核对。
- 会员相关：
  - 旧数据 `member.group_id` 里残留的值，会在界面上看不出原因的情况下阻止删除分组。
  - 恢复被拒时，422 提示“修改会员等级”，但已删除的会员无法修改。
  - 注册提示会暴露账号已注销，这是设计要求。
- `system_secret` 的 `Db::table` 读取没有过滤 `deleted_at`，目前没有代码软删除它。
- `PluginInfrastructureService`、`Ai.php` 中有两处应改用 `SystemMigration::ledger()`。
- 已被 gitignore 的 `database/business/*_permissions*.sql` 旧文件仍写着 `deleted_at=NULL`，可能被 `assertSafe` 拒绝。
- 表上没有 `deleted_token` 列时（只剩插件表），每次删除或恢复都会查一次 `hasColumn`。
- 前端：
  - 操作日志页面的 http 层没有测试。
  - 详情抽屉重复打开存在竞态。
  - 生成页面的列表请求被更新的请求取代时会弹出刷新提示。
- 插件（CMS 等）不在本次范围内，CMS 运行时存在重复的 `whereNull`，不影响结果。
- 提交 `b0f3a9ad`（已推送）的提交说明声称清理、日志界面、会员冷静期都已完成。实际提交时只完成了 Task 1–2，需要用户知悉。
