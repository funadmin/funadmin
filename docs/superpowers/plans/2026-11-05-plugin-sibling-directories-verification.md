# 插件同级目录与公共模型验证

日期：2026-11-05。当前规范：`docs/plugins.md`。

## 最终结构

所有路径相对 `webman-admin/`：

- 框架插件管理：`app/admin/plugin/`。
- 已安装插件后台：`app/admin/plugins/{code}/`。
- 源包后台：`plugins/{code}/app/admin/plugins/{code}/`。
- 公共模型：`app/common/model/BaseModel.php`。

移除后台插件路径的 `business` 层。源包仍通过既有发布机制安装，不引入新的加载系统。URL 继续使用 `/admin/plugin/{code}/…`，权限资源规范化保留已有权限标识；前端目录和部署方式不变。

`app/admin/model/BackendModel.php` 原来只是公共 BaseModel 的空子类，现已删除。相关模型直接继承公共 BaseModel，CRUD 和插件生成模板同步调整。模板使用完整类名，避免生成名为 BaseModel 的实体时发生导入冲突。

旧 business 物理目录、资源登记和恢复材料被明确拒绝，不自动迁移或删除旧安装记录。卸载、升级和显式清理数据的既有策略未在本轮扩展。

## 验证结果

- 目录、脚手架、CRUD、发布、打包、生命周期、鉴权、插件中心、会员和 CMS 相关回归：266 项、2069 次断言，9 项跳过，其余通过。日志：`/tmp/funadmin-sibling-scoped.log`。
- CMS 专项：57 项、257 次断言，2 项跳过，其余通过。日志：`/tmp/funadmin-sibling-cms-current.log`。
- 最终单独重跑 `vendor/bin/phpunit tests/CommonModelInheritanceTest.php`：3 项、10 次断言全部通过，覆盖继承关系、生成模型写入、JSON 转换、软删除恢复和 BaseModel 实体命名。
- 最终 `php tests/lint.php`：794 个 PHP 文件通过。日志：`/tmp/funadmin-sibling-lint-final.log`。另对插件源包的 32 个 PHP 文件逐一执行 `php -l`，全部通过。
- 可执行 app/config/插件源包中没有 BackendModel 或旧 business 后台路径引用；测试中的旧路径拒绝用例保留。
- `git diff --check` 通过。
- 独立只读审查未发现 Important 或 Critical 问题，覆盖发布/恢复边界、命名空间归属、鉴权分派、权限规范化及公共模型继承。

## 最终全量后台快照

执行 `cd webman-admin && vendor/bin/phpunit`：814 项、6244 次断言，5 个错误、2 个失败、108 项跳过，退出码 2。日志：`/tmp/funadmin-sibling-full-final.log`。

以下既有或并行工作问题仍存在，全量不能标为通过：

- `AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request`：既有的私有目录符号链接错误。
- `CoreDatabaseAuditTest::test_manifest_reads_only_root_baseline_declarations_without_executing_seeds` 及 CoreMigrationSourceTest 的 3 项：并行数据库整理过程中，根目录 `database/migrations/001_baseline.sql` 已被移走，现有测试报“权威核心 SQL 目录不存在或基线不可读”。未恢复或修改另一会话正在整理的 SQL。
- `AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration`：既有 authorization 路由摘要差异。
- `LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references`：既有的插件改动范围之外的 ThinkPHP 引用。

此前 CMS 的 Finder `.DS_Store` 元数据错误已由目录验证与并行 CMS 元数据忽略处理覆盖，最终全量未再出现这两项错误。

保留并行 CMS、Hook、前端与数据库修改。未提交代码，未执行生产迁移，未重载服务。
