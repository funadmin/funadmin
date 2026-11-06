# 移除 identity（企业身份中心）模块

日期：2026-11-06　状态：已实施（用户选择：删代码 + 删表；删表方式：限定的删表例外）

## 背景与决定

`identity` 是一套自建的企业身份中心：把 FunAdmin 变成 OAuth2 / OIDC 认证服务器，
并提供企业应用中心（应用、域名、应用角色权限、单点登录、Scope 与 Claim、会话与授权、签名密钥、登录审计、应用门户）。

用户确认：**暂时不会让其他系统用 FunAdmin 账号登录**，因此移除该模块。

实际使用情况（funadmin_rbac）：OAuth 客户端 0、令牌 0、企业应用 0、授权同意 0、签名密钥 0。
只有 `identity_user`/`identity_credential`/`identity_admin_link` 各 2 行，是两个管理员的影子副本。

## 范围

| 部分 | 内容 |
|---|---|
| 后端代码 | `app/identity/`（协议端点）、`app/admin/identity/`（8 个后台控制器、4 个命令）、`app/common/service/identity/`（20）、`app/common/model/identity/`（35） |
| 路由 | `config/route.php` 中 `/identity/*` 路由组与 `/identity/resource/identity` |
| 前端 | `web/src/admin/views/applications/` 全部页面、`web/src/admin/api/identity` |
| 菜单 | 「企业应用中心」及其 10 个子菜单（id 48–58） |
| 权限 | 约 56 条 `admin/identity.*` 与 `identity/*` 权限资源 |
| 数据表 | 31 张：`application_*`、`enterprise_application`、`identity_*`、`oauth_*`、`oidc_*`、`scope`、`client_scope` |

## 必须保留的行为（反目标）

- 管理员登录、会员登录、后台权限、会员 API 令牌不受任何影响。
- 不得修改已冻结的核心基线 `001_baseline.sql`（升级包按 checksum 校验）。
- 不删除任何业务数据：管理员、会员、部门、角色。

## 核心代码中的耦合点（需改为不再调用）

1. `MemberAuthService::login`：登录事务里调用 `MemberIdentityAdapter::shadowRead/sync`。
2. `MemberAccountService`（3 处）、`SystemMember`（4 处）：保存会员后 `MemberIdentityAdapter::sync`。
3. `SystemAdmin`（5 处）：保存管理员后 `syncIdentity`（`AdminIdentityAdapter`）。
4. `ExceptionHandle`：识别 `IdentityValidationException`。
5. `CoreSeedPlan` / `CoreMenuPlan`：种子与菜单计划中的 identity 条目。

这些调用只写影子副本，删除后不影响主流程。

## 方案对比

| 方案 | 做法 | 取舍 |
|---|---|---|
| **A. 停用 + 删代码，保留表（推荐）** | 与 026/035 移除本地市场的做法一致：新迁移把菜单、权限置为停用；删除代码与前端页面；数据表保留不动 | 与已有移除先例一致，可逆，不碰冻结基线；代价是库里留 31 张空表 |
| B. 停用 + 删代码 + 删表 | 在 A 基础上新增迁移 `DROP TABLE` | 库更干净；但基线仍会在新安装时建表，新老库结构不一致，`CoreSchemaPlan`/审计会报差异，需额外改审计逻辑 |
| C. 只隐藏菜单 | 仅停用菜单 | 改动最小，但代码与协议端点仍在线，没有达到"移除"目的 |

推荐 **A**：完全移除功能与代码入口，数据库只做与市场移除相同的停用处理，不触碰冻结基线和审计。

## 迁移编号

另一会话已占用核心 SQL 027、PHP 036。本次使用 **核心 SQL 028、PHP 037**。

## 测试

- 删除只测 identity 本身的测试：`IdentityTokenRuntimeTest`、`ScopeClaimHttpTest`、`Console/IdentityExecutionTest`、`Console/IdentityTransmissionTest`、`Console/MySqlIdentity*`、`tests/identity-http-smoke.php`。
- 更新顺带引用 identity 的测试（会员、管理员、菜单计划、种子计划、路由清单、权限快照），断言改为"不再调用身份同步、不存在 /identity 路由与企业应用中心菜单"。
- 新增：会员登录、管理员/会员保存在无 identity 时正常完成；迁移 028/037 可重复执行且只停用 identity 菜单与权限。
- 全量后端、前端测试与类型检查。

## 风险

- 改动约 120+ 文件，且其他会话仍在并行修改权限相关代码，提交前需重新跑全量测试。
- `CoreSeedPlan` 仍为 `scope` 写种子（依赖 `identity_tenant`）；方案 A 保留表，种子可保留，只移除对已删代码的引用。

## 实施结果（用户决定：方案 B，删代码 + 删表）

- 迁移：核心 SQL `028_remove_identity_module.sql`、PHP `037_remove_identity_module.php`。撤销授权、软删除 11 条菜单与 56 条权限，删除 35 张表。
- 只向前守卫：`MigrationService::RETIRED_CORE_TABLES` 列出可删的 35 张表，`assertForwardOnly` 仅放行这些表的 `DROP TABLE IF EXISTS`；其余表、`DROP COLUMN`、`TRUNCATE`、`RENAME` 仍被拒绝（`MigrationForwardOnlyGuardTest` 覆盖前缀伪装、一条语句多表等情况）。
- 审计：`CoreDatabaseAudit::manifest()` 从期望结构中剔除退役表，冻结基线 `001_baseline.sql` 未改动。
- 已在 funadmin_rbac 执行，执行前备份到 `.superpowers/backup/identity-removal/`。
