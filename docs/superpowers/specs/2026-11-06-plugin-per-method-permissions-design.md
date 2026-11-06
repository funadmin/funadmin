# 后台权限按方法细分、宿主与插件统一推导 — 设计

日期：2026-11-06
状态：已确认（方案 1 代码即权限；宿主与插件同一推导规则；用户授权"全部处理"）

## 背景与目的

后台权限现在有三套口径：宿主后台按"控制器+方法"推导出 `systemconfig:index` 这类码，前端却用 `system:config:list`，靠 `Permission::webPermissions()` 约 180 条手写对照；插件多个方法共用粗粒度权限码（`market:plugin:manage` 覆盖 9 个接口），无法细分授权；322 个宿主接口中有 185 个推导出的权限从未登记，普通管理员无法被授权。

目标：**每个后台方法一个权限，权限码由同一规则自动推导，前后端用同一个码**；管理员可以只授予"查看订单"不授予"关闭订单"，"读取"与"保存"分开。

## 目标

- 统一推导：`{模块}:{资源}:{操作}`，全部 kebab-case，宿主与插件同一函数。
  - 宿主：模块 = `app\admin\{模块}\controller` 中的模块目录名；资源 = 类名去掉 `System` 前缀与 `Controller` 后缀；操作 = 方法名。
  - 插件：模块 = 插件名；资源与操作同上。
  - 例：`system\controller\SystemConfig::index` → `system:config:index`；`authorization\controller\SystemAdmin::resetPassword` → `authorization:admin:reset-password`；插件 `market` 的 `OrderController::close` → `market:order:close`。
- 代码即权限：同步服务扫描全部后台路由，按推导结果 upsert 权限行；插件安装/升级/启用与核心迁移后自动执行，并提供命令。
- 前后端同码：删除 `webPermissions()` 对照表与推导例外映射，用户信息直接返回推导码。
- 后台只保留一个权限中间件（删除 `CheckPluginPermissionMiddleware`）。

## 非目标

- 会员 API、浏览器认证与个人资料（不经过后台权限中间件）不涉及。
- 不改 Casbin 模型与角色/部门数据结构。
- 能力权限（`capability`，不对应单个接口、由业务代码判断）保留其业务含义，只统一命名格式与代码引用方式。

## 反目标

- 升级后任何角色能访问的接口集合发生变化（多或少）。
- 方法改名时自动删除旧权限，导致已授权角色无声失权。
- 插件能借用宿主或其它插件命名空间的权限码。
- 菜单因权限码变更而对有权用户消失。

## 约束

- 推导码满足 `^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$`。权限行 `obj={模块}/{资源}`、`act={操作}`。
- 迁移在 MySQL 核心 SQL 与 PHP 迁移两条轨道各一份；生产以 MySQL 为准，用 `tests/Console/run-core-mysql.php` 的 Docker MySQL 验证。
- 共享工作区有并行会话：只改本计划列出的文件；插件目录变更需通知 `funadmin-5f`。
- 不提交、不推送。

## 方案

选定"代码即权限"：路由是权限的唯一来源；清单/基线只为推导码提供中文名称。另一方案"清单为来源+测试兜底"因每加方法都需手动登记而未采用。

## 已决定的设计

### 推导与同步
- `PermissionCode::derive(string $controller, string $action): ?string` 统一推导；无法推导（非后台控制器）返回 null。
- `PermissionSyncService::sync(?string $plugin = null): SyncReport` 扫描路由 → 推导 → upsert；报告"表中存在但已无对应方法"的旧码，不删除。
- 名称来源：插件清单 `adminWeb.permissions[].name`、宿主基线已有中文名（迁移时随改名保留）、缺省"模块 / 资源:操作"。

### 授权等价迁移
- 生成映射：对每个后台路由方法，旧所需权限（迁移前快照：宿主为推导出的旧 `obj/act` 对应行，插件为注解码）→ 新推导码。
- 对每个持有旧权限的角色，授予映射到的全部新权限，再撤销旧授权；旧权限行停用（保留以便追溯）。
- 一个旧权限对应多个方法（插件粗码、宿主推导例外）时，展开为多个新权限，角色能力不变。
- 未登记的旧推导（185 个）迁移前对非超管不可访问，迁移后新码只登记、不授予任何角色，等价保持。

### 能力权限
- 29 个 `capability` 码改为三段 kebab 格式（多数已是），业务代码中 `nodeAccess('development/business/generate')` 等写死字符串改为按权限码判断的 `can('development:business:generate')`。

### 菜单
- 菜单 `permission_id` 指向的旧权限行 → 对应新权限行（菜单页面对应的列表方法）。

### 前端
- 宿主与插件页面中 `v-perm` / `hasPermission` 的旧码按"按钮实际调用的接口方法"替换为推导码。

### 中间件
- `CheckAdminApiRoleMiddleware`：有 `#[Permission]` 用注解码，否则用推导码查表 → Casbin；插件控制器额外要求码属于插件命名空间且 `source_name` 匹配。删除 `CheckPluginPermissionMiddleware`、市场控制器重复挂载、插件控制器上的 `#[Permission]`。

### 验证
- 授权等价：迁移前后，每个角色 × 每个后台路由的"能否访问"完全一致（SQLite 全量 + Docker MySQL）。
- 快照：路由所需码变为推导码（预期变化，人工确认后更新）。
- 同步：新增方法自动登记；改名报告旧码不删除；清单声明不存在的码报错。

## 分期（每期可独立验收）
1. 统一推导 + 同步服务（只新增登记，不改授权与前端）。
2. 插件迁移：授权等价迁移、插件前端、中间件合并。
3. 宿主迁移：改名、授权等价迁移、菜单、前端、能力权限、删除对照表与推导例外。
