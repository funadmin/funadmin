# 统一按方法推导的后台权限 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 宿主与插件后台每个方法一个权限，权限码由同一规则推导，前后端同码，升级前后每个角色的可访问接口集合不变。

**Architecture:** 新增 `PermissionCode::derive` 与 `PermissionSyncService`（路由即权限来源）。授权迁移先生成"旧权限 → 新推导码"映射，再在两条迁移轨道上把角色授权展开到新码。中间件合并为一个。每期以"角色×路由可访问性"等价测试兜底。

**Tech Stack:** PHP 8.5、Webman、Eloquent、Casbin、PHPUnit 13、Vitest、MySQL（Docker）/SQLite。

**Spec:** `docs/superpowers/specs/2026-11-06-plugin-per-method-permissions-design.md`

## Global Constraints

- 推导码 `^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$`；`obj={模块}/{资源}`，`act={操作}`。
- 升级前后每个角色能访问的后台接口集合必须完全一致（反目标 1）。
- 方法改名只报告旧码，不自动删除权限行或授权。
- 迁移两轨：`admin/database/core/migrations/*.sql`（MySQL）与 `admin/database/migrations/*.php`；不改已执行迁移。
- 不提交、不推送；共享工作区只改计划列出的文件，插件目录改动通知 `funadmin-5f`。

## Review Focus

1. 一个旧权限覆盖多个方法（插件粗码、宿主 `clear→delete` 等例外）：展开后角色仍能访问全部这些方法。
2. 未登记的宿主旧推导（185 个）：迁移后仍不被任何非超管角色访问（新码只登记不授予）。
3. 菜单：有权用户迁移后仍能看到同一菜单；无权用户仍看不到。
4. 插件命名空间：插件方法推导码前缀即插件名，无法与宿主或其它插件重名。
5. 前端：每个改名的按钮在拥有对应接口权限时可见，无权时隐藏。

---

### Task 1: 统一推导函数

**Files:** Create `admin/app/admin/authorization/service/PermissionCode.php`; Test `admin/tests/PermissionCodeTest.php`

**Interfaces:** Produces `PermissionCode::derive(string $controller, string $action): ?string`；`PermissionCode::resource(string $code): array{obj:string,act:string}`。

- [ ] 测试（字面期望）：`app\admin\system\controller\SystemConfig::index`→`system:config:index`；`app\admin\authorization\controller\SystemAdmin::resetPassword`→`authorization:admin:reset-password`；`app\admin\plugins\market\controller\OrderController::close`→`market:order:close`；`app\admin\plugins\cms\controller\Sites::saveSettings`→`cms:sites:save-settings`；`app\admin\ai\controller\Ai::conversationGroupDelete`→`ai:ai:conversation-group-delete`；非后台类→null；`resource('market:order:close')`→`['obj'=>'market/order','act'=>'close']`。
- [ ] 运行失败 → 实现 → 通过。

### Task 2: 角色×路由可访问性快照（等价门禁）

**Files:** Create `admin/tests/Support/AccessMatrix.php`、`admin/tests/AccessMatrixTest.php`、`admin/tests/fixtures/access-matrix-before.json`

**Interfaces:** Produces `AccessMatrix::collect(): array<string role, list<string routeKey>>`（每个角色可访问的后台路由键集合，超管单列）。

- [ ] 在迁移前用当前中间件逻辑生成 fixture：基线数据 + 一组覆盖各类权限的测试角色（每个已登记权限各授予一个独立角色，另有多权限角色）。
- [ ] 测试：当前逻辑结果 == fixture（建立基线）。

### Task 3: 同步服务与命令

**Files:** Create `admin/app/admin/authorization/service/PermissionSyncService.php`、`admin/app/admin/authorization/command/PermissionSync.php`；Modify 插件安装/升级/启用流程调用同步；Test `admin/tests/PermissionSyncTest.php`

**Interfaces:** Consumes `PermissionCode`。Produces `PermissionSyncService::sync(?string $plugin = null): array{added:list<string>, stale:list<string>}`。

- [ ] 测试：新方法自动登记；重复执行幂等；改名后旧码出现在 `stale` 且行未删除；插件清单声明不存在的码 → 抛错。
- [ ] 实现；只新增登记，不授予任何角色（Task 2 矩阵必须不变）。

### Task 4: 授权映射与等价迁移（插件）

**Files:** Create `admin/app/admin/authorization/service/PermissionMigrationPlan.php`；Create migrations `admin/database/core/migrations/025_plugin_permission_per_method.sql`、`admin/database/migrations/034_plugin_permission_per_method.php`；Test `admin/tests/PluginPermissionMigrationTest.php`

**Interfaces:** Produces `PermissionMigrationPlan::plugin(): array<oldCode, list<newCode>>`。

- [ ] 测试：迁移后 Task 2 矩阵对插件路由完全一致；持有 `market:plugin:manage` 的角色获得其 9 个新码；旧行停用。
- [ ] 实现映射与迁移。

### Task 5: 插件中间件合并与前端

**Files:** Modify `CheckAdminApiRoleMiddleware.php`；Delete `CheckPluginPermissionMiddleware.php`；Modify 市场 6 控制器、`PluginRoute.php`、插件控制器去掉 `#[Permission]`；Modify `web/src/admin/plugins/**`；Tests：快照更新、`PluginRouteExecutionTest`、前端契约。

- [ ] 测试：插件路由所需码 = 推导码；跨插件码拒绝；Task 2 矩阵不变；前端按钮码 ∈ 推导码集合。
- [ ] 实现；通知 `funadmin-5f`。

### Task 6: 宿主改名与等价迁移

**Files:** Extend `PermissionMigrationPlan::host()`；migrations `026_host_permission_per_method.sql`、`035_host_permission_per_method.php`；Modify `PermissionResource.php`（推导改用 `PermissionCode`，删除例外）；Test `admin/tests/HostPermissionMigrationTest.php`

- [ ] 测试：Task 2 矩阵对宿主路由完全一致；菜单 `permission_id` 指向新行且菜单可见性不变；185 个新码已登记且无角色授予。
- [ ] 实现。

### Task 7: 能力权限与代码引用

**Files:** Modify `AdminAuthorizationService`（新增 `can(string $code): bool`）、`Ai.php`、`Business.php`、`Profiles.php`、`Data.php`、`BusinessTargetService.php`；Test `admin/tests/CapabilityPermissionTest.php`

- [ ] 测试：每个原 `nodeAccess('...')`/`can('...')` 调用点在相同授权下结果不变。
- [ ] 实现。

### Task 8: 前后端同码，删除对照表

**Files:** Modify `Permission::webPermissions()`（返回推导码本身）、宿主前端 `web/src/admin/views/**`、`web/src/admin/router/**` 中的权限码；Tests：前端全量、`FrontendApiRouteContractTest`。

- [ ] 测试：前端每个按钮码 ∈ 后端可登记码集合；无权隐藏、有权显示（抽样组件测试）。
- [ ] 实现。

### Task 9: 全量验证与文档

- [ ] `docs/button-permissions.md`、`docs/plugins.md`、`docs/openapi/README.md` 更新。
- [ ] 后端全量、前端全量、Docker MySQL 核心迁移测试、Task 2 矩阵等价。
