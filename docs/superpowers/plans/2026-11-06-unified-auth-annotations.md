# 统一鉴权注解 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 后台与会员 API 用同一组注解声明登录与权限要求，接口与登录方式仍按身份分开，现有权限效果不变。

**Architecture:** 新增共享注解与一个按进程缓存的注解解析器 `AuthRequirementResolver`；会员 `ApiMiddleware` 与后台 `CheckAdminApiRoleMiddleware`、`CheckPluginPermissionMiddleware` 都通过它取得要求。后台未标注时沿用"推导资源查库"。先以全量权限快照锁住现有行为，再逐步替换硬编码。

**Tech Stack:** PHP 8.5、Webman、PHP 8 Attribute、PHPUnit 13、Casbin（不改）。

**Spec:** `docs/superpowers/specs/2026-11-06-unified-auth-annotations-design.md`

## Global Constraints

- 注解命名空间：`app\common\annotation`；中间件类名以 `Middleware` 结尾。
- 默认需要登录；解析失败按"需要登录且需要权限"处理（失败即安全）。
- 后台 `#[Permission]` 可选，未标注沿用数据库推导与 `is_public`。
- 不改数据库数据、Casbin、角色、响应格式、登录方式。
- 不提交、不推送（用户未要求）。共享工作区有并行会话，只改本计划列出的文件。

## Review Focus

1. 方法与类同时有注解时：方法注解必须覆盖类注解（如类 `#[NoNeedPermission]`、方法 `#[Permission]`）。
2. 后台超级管理员 `*` 权限：引入注解后仍全部放行。
3. 插件控制器同时有 `#[PluginPermission]` 与 `#[Permission]`：取方法级优先，二者等价。
4. 后台接口被标 `#[NoNeedLogin]`：未登录不得注入或伪造 `admin` 上下文，CSRF 与日志中间件仍执行。
5. `memberApiGroup()` 路由的控制器又继承 `ApiController`：鉴权只执行一次，`member` 注入一次。

---

### Task 1: 权限效果快照（锁住现状）

**Files:**
- Create: `admin/tests/Support/AuthRequirementSnapshot.php`
- Create: `admin/tests/AuthRequirementSnapshotTest.php`
- Create: `admin/tests/fixtures/auth-requirements.json`

**Interfaces:**
- Produces: `AuthRequirementSnapshot::collect(): array<string, array{login: bool, permission: string|array|null, public: bool}>`，键为 `HTTPMETHOD /path => Controller::action`。

- [ ] **Step 1:** 写 `collect()`：`Route::load`，遍历所有 `/admin/`、`/api/v2/` 路由，对每个回调按**当前**中间件逻辑静态计算要求（后台：是否插件控制器、推导资源 `PermissionResource::fromParts`、库内 `is_public`、`adminprofile`/`enabledmodules` 特例、市场映射；会员：`#[NoNeedLogin]`）。
- [ ] **Step 2:** 生成 `fixtures/auth-requirements.json`（运行一次 `collect()` 写入，人工抽查 10 条）。
- [ ] **Step 3:** 测试 `test_auth_requirements_match_snapshot`：`collect()` 结果与 fixture 逐键相等；任何差异打印键与前后值。
- [ ] **Step 4:** 运行 `vendor/bin/phpunit tests/AuthRequirementSnapshotTest.php`，PASS。

### Task 2: 共享注解与解析器，会员中间件改用

**Files:**
- Create: `admin/app/common/annotation/NoNeedPermission.php`
- Create: `admin/app/common/annotation/Permission.php`
- Create: `admin/app/common/auth/AuthRequirementResolver.php`
- Modify: `admin/app/common/middleware/ApiMiddleware.php`
- Test: `admin/tests/AuthRequirementResolverTest.php`

**Interfaces:**
- Produces:
  - `Permission(string|array $code)`，`public readonly string|array $code`。
  - `AuthRequirementResolver::for(string $controller, string $action): AuthRequirement`
  - `final readonly class AuthRequirement { bool $login; bool $permission; string|array|null $code; }`（`login=false` 表示 NoNeedLogin；`permission=false` 表示 NoNeedPermission；`code` 来自 `Permission` 或 `PluginPermission`）。

- [ ] **Step 1: 写失败测试** `AuthRequirementResolverTest`：
  - 无注解 → `login=true, permission=true, code=null`
  - 方法 `#[NoNeedLogin]` → `login=false`
  - 类 `#[NoNeedPermission]`、方法 `#[Permission('a:b:c')]` → `permission=true, code='a:b:c'`（方法覆盖类）
  - 父类 `#[NoNeedLogin]` → 子类方法 `login=false`
  - `#[PluginPermission('x:y:z')]` → `code='x:y:z'`
  - 不存在的类 → `login=true, permission=true, code=null`
- [ ] **Step 2:** 运行，FAIL（类不存在）。
- [ ] **Step 3:** 实现解析器（静态缓存 `controller::action`）；`ApiMiddleware::isPublic` 改为 `!$resolver->for(...)->login`，删除其内部反射代码。
- [ ] **Step 4:** 运行解析器测试、`tests/MemberIntegrationTest.php`、Task 1 快照测试，全部 PASS。

### Task 3: 后台中间件读注解，替换两处硬编码

**Files:**
- Modify: `admin/app/admin/middleware/CheckAdminApiRoleMiddleware.php`
- Modify: `admin/app/admin/authentication/controller/AdminProfile.php`（类加 `#[NoNeedPermission]`）
- Modify: `admin/app/admin/plugin/controller/SystemPlugin.php`（`enabledModules` 加 `#[NoNeedPermission]`）
- Modify: `admin/tests/Support/AuthRequirementSnapshot.php`（改为调用解析器 + 中间件的同一判定函数）
- Test: `admin/tests/AdminAuthAnnotationTest.php`

**Interfaces:**
- Consumes: `AuthRequirementResolver::for`。
- Produces: `CheckAdminApiRoleMiddleware::requirement(Request $request): AuthRequirement`（公开静态判定，快照与中间件共用）。

- [ ] **Step 1: 写失败测试**：
  - `AdminProfile` 类有 `NoNeedPermission`；`SystemPlugin::enabledModules` 有 `NoNeedPermission`。
  - 中间件源码不再包含字符串 `admin/adminprofile` 与 `enabledmodules`。
  - 匿名后台控制器方法标 `#[Permission('systemadmin:index')]`：持有该权限放行、无权限 403、超管 `*` 放行。
  - 方法 `#[NoNeedLogin]`：未登录放行且 `context['admin']` 未设置。
- [ ] **Step 2:** 运行，FAIL。
- [ ] **Step 3:** 中间件：取要求 → `login=false` 直接 `$handler`；未登录 401；`permission=false` 放行；`code!==null` 用注解码判定；否则走原推导查库逻辑。删除两处硬编码。
- [ ] **Step 4:** 运行新测试 + Task 1 快照（**快照必须零差异**）+ `tests/AdminHttpFoundationTest.php`、`tests/AdminProfileTest.php`，PASS。

### Task 4: 插件权限统一，市场映射改注解

**Files:**
- Modify: `admin/app/admin/plugin/middleware/CheckPluginPermissionMiddleware.php`
- Modify: `admin/app/admin/plugins/market/controller/{Category,Plugin,Order,Grant,Setting,Stat}Controller.php`（逐方法加 `#[PluginPermission]`，值取自现映射）
- Test: `admin/tests/PluginAuthAnnotationTest.php`

**Interfaces:**
- Consumes: `AuthRequirementResolver::for`（读取 `PluginPermission` 与 `Permission`）。

- [ ] **Step 1: 写失败测试**：市场 6 个控制器每个路由方法都有 `PluginPermission`，值与原映射一致（`index` → `market:plugin:view` 等，逐条列出）；中间件源码不含 `match ($matches[1])`；`#[Permission]` 标在插件方法上与 `PluginPermission` 等价。
- [ ] **Step 2:** 运行，FAIL。
- [ ] **Step 3:** 加注解；中间件改用解析器取 `code`，删除市场映射与内部反射。
- [ ] **Step 4:** 运行新测试 + 快照（零差异）+ `tests/Cms`、`tests/Qa`、`tests/Document`、`tests/Console/PluginRouteExecutionTest.php`，PASS。

### Task 5: 生成器与插件会员路由统一

**Files:**
- Modify: `admin/app/common/crud/ProductionTemplateContext.php`（会员控制器与 `consoleController=false` 控制器改继承 `ApiController`，去掉直接 `#[Middleware(ApiMiddleware::class)]` 与 `JsonResponse`）
- Modify: `admin/app/common/middleware/ApiMiddleware.php`（同一请求已鉴权则直接 `$next`）
- Test: `admin/tests/Console/CrudMemberExecutionTest.php`、`admin/tests/Console/PluginCrudExecutionTest.php`、`admin/tests/MemberIntegrationTest.php`

- [ ] **Step 1: 写失败测试**：
  - 生成源码包含 `extends ApiController`，不含 `#[Middleware(ApiMiddleware::class)]`。
  - 生成控制器真实请求：未登录 401；登录只见本人数据。
  - 给生成的 `index` 加 `#[NoNeedLogin]`：游客 200 且列表为空（`memberId()=-1`）。
  - `memberApiGroup` 路由 + 继承 `ApiController` 的控制器：`ApiMiddleware` 实际鉴权调用一次。
- [ ] **Step 2:** 运行，FAIL。
- [ ] **Step 3:** 实现（`ApiMiddleware` 用请求上下文标记已处理）。
- [ ] **Step 4:** 运行上述测试 + 快照，PASS。

### Task 6: 文档与全量验证

**Files:**
- Modify: `docs/openapi/README.md`（"认证与扩展字段"写清三种注解、默认规则、后台 `Permission` 可选）

- [ ] **Step 1:** 更新文档。
- [ ] **Step 2:** `cd admin && vendor/bin/phpunit`：0 失败 0 错误。
- [ ] **Step 3:** `cd web && npx vitest run --maxWorkers=1`：全部通过。
- [ ] **Step 4:** 重新生成 OpenAPI（`/tmp/full-openapi-gen.php`）：problems 0、operationId 无重复。
