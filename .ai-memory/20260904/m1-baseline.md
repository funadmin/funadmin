# M1 现状清单、数据库映射与基线证据

初始证据时间点：本地时间 `2026-09-05T11:44:43+0800`，UTC `2026-09-05T03:44:43Z`。最终修正证据时间点：本地时间 `2026-09-05T12:11:21+0800`，UTC `2026-09-05T04:11:21Z`。

范围仅限 M1 调研、脱敏快照、基线验证，以及 `fun_field_verify` 模型与相关契约的必要修正。未执行迁移，未修改计划文件或数据库，未恢复历史 migration，未 commit/push。任何历史 migration 的删除、缺失或未跟踪状态均按现场保留；M1 明确不负责恢复历史 migration。

## 1. 工作区保护与可复现边界

- 当前 HEAD：`776d4cb6f2e2aa4c2186f551536505a8b812ff2c`。
- 分支：`Rbac`，跟踪 `origin/Rbac`。
- `git status --branch --porcelain=v1` 在上述证据时间点的 SHA-256：`e3f6c6aafbb43e046e24f8026c85083ab41b551f106dbf17435b1c97c708be01`。
- 完整 status 原文保存在同目录 `m1-command-output-summary.txt`，未包含 `.env` 内容、数据库密码、JWT、token 或其他密钥。
- 工作区存在大量用户未提交改动，且可能被用户、IDE 或其他进程并发修改。因此 HEAD、status、文件计数、数据库计数和测试结果都只代表标注的精确时间点；后续复核必须重新采集，不能将本快照视为持续不变的事实。
- 基线期间前端 build 临时刷新了 `public/admin-web`；由于执行前该目录不在 status 中，本次生成物已用限定路径恢复并清除，仅撤销本次验证产生的输出，未覆盖执行前已有用户改动。

## 2. 扫描范围矩阵

| 范围 | 时间点状态 | M1 结论 |
|---|---|---|
| `plugins/` | 目录存在，普通文件数 `0` | 文件系统插件目录当前为空；空目录不代表插件迁移逻辑可删除。 |
| `addons/` | 目录不存在 | 运行时目录已硬切为 `plugins/`；历史文本、IDE 元数据或兼容 migration 中仍可能出现 `addons`，不能据此恢复 `addons/`。 |
| `config/console.php` | 存在并已扫描 | 注册 `curd`、`menu`、`plugin`、`install`、`mcp` 命令；Laravel command 等价替代前保留。 |
| `app/backend/service/PluginService.php` | 存在并已扫描 | 当前插件安装、更新、迁移、启停、卸载及回滚编排的核心行为参照。 |
| `app/backend/service/ResourceRegistryService.php` | 存在并已扫描 | 菜单、权限与 Casbin 资源注册/移除入口；插件资源迁移必须保持幂等和缓存刷新语义。 |
| `extend/fun/helper/FormHelper.php` | 存在并已扫描 | 旧表单 HTML/Layui 实现仍存在，配置表单与附件选择兼容链未完全替代前不能删除。 |
| `admin-web/` | 目录存在；命令采集时含依赖共 `19560` 个普通文件 | Vue 3 管理端、插件中心、API、测试与构建配置均纳入扫描；文件数包含 `node_modules`，仅作为存在性证据。 |
| `public/admin-web/` | 部署构建目标 | 仅用于 build 验证；本次生成物已限定路径清理，未作为源码编辑。 |
| `app/backend/controller`、`app/backend/view`、`app/common/service` | 已扫描旧后台入口和生成路径 | 识别 Layui/RequireJS、旧页面控制器、插件管理和代码生成依赖。 |
| `extend/fun`、`config`、`database/migrations` | 已扫描插件、表单、console、配置与 schema | migration 仅作只读证据；未执行、未恢复、未改写。 |

## 3. Layui / RequireJS / 旧 curd 清单

### 必须迁移

| 范围 | 现有入口/引用 | 迁移要求 |
|---|---|---|
| 旧后台壳与登录 | `app/backend/controller/Index.php`、`Login.php`、`Error.php` 及旧 view/layout | 新管理端接管登录、主布局、控制台、错误页和动态菜单后迁移。 |
| 旧插件管理 | `app/backend/controller/Plugin.php`、旧插件视图，以及 `PluginService` | 云市场、本地包、配置、启停、迁移、卸载、失败回滚能力等价后迁移。 |
| 插件命令与资源 | `config/console.php`、`ResourceRegistryService`、`extend/fun/curd/Plugin.php`、`extend/fun/functions/plugin.php` | 新 command 与资源注册机制验证前保留；不得恢复 `addons/`。 |
| 系统升级 | 数据菜单 `href=sys.upgrade`、旧权限对象 `backend/sys.upgrade` | 先实现备份、文件替换、数据库升级、失败回滚与权限门禁。 |
| 旧 AJAX/表单兼容 | `app/backend/controller/Ajax.php`、`backend/ajax:uploads`、`FormHelper`/FormBuilder | 先提供 Admin Web 等价附件选择和动态表单组件。 |
| 旧代码生成 | `app/common/service/McpService.php` 及旧 RequireJS/Layui 模板 | 生成目标切换为 API + Admin Web Vue。 |
| 旧菜单渲染 | `app/backend/service/AuthService.php` | 后端返回结构化菜单，前端负责渲染。 |
| Admin Web | `admin-web/src/api`、`src/views/system/plugin`、router、tests | 作为新前端承载层保留并持续验证。 |

### 迁移后删除

- 旧插件、后台壳、layout 与 Layui 页面；仅在 Admin Web 能力等价验收后删除。
- `public/static/js/require-plugins.js`、旧 AMD 资源与旧生成模板；仅在文件系统插件兼容审计后删除。
- `FormHelper`、Form/FormBuilder/TableBuilder 及 builder 资源；仅在所有核心和插件表单完成替代后删除。
- ThinkPHP 旧 HTML 渲染链；仅在 API 与 Admin Web 完成全量切换后删除。

### 保留

- `admin-web/` 与部署目标 `public/admin-web/`。
- `PluginService`、`ResourceRegistryService`、插件模型/registry/lifecycle、插件 package 与 migration 服务，作为行为参照和并行切换源。
- 面向 Admin Web 的 CRUD 生成路径。
- 菜单和权限的 `source_type/source_name`、插件 manifest/lifecycle/history 数据。

## 4. MySQL 只读结构证据

- 连接目标仅描述为本机 MySQL；未记录数据库用户名、密码、连接串或 `.env` 内容。
- 服务版本：MySQL `8.0.46`。
- 当前 `fun_` 表：`29` 张。
- 只执行 `information_schema` 与计数类 SELECT；未执行 DDL/DML。
- 实际外键仅 2 条：`fun_member_group_relation.member_id -> fun_member.id`、`group_id -> fun_member_group.id`。
- `fun_attach_group.sort` 实际为 `int`，`IS_NULLABLE=YES`，`COLUMN_DEFAULT=999`。Laravel 映射必须先保留“nullable、默认 999”的现状语义，除非后续 migration 明确完成数据清理和约束收紧；旧文档中的 `default 0` 不正确。
- `fun_field_verify.verify` 实际为 `varchar(50) NOT NULL`，`COLUMN_KEY=PRI`，对应唯一主键索引 `verify`，且不是自增列；模型已明确声明 `$pk = 'verify'` 与字符串类型。
- `database/migrations/013_field_hygiene.sql` 当前仍尝试新增 `id ... AUTO_INCREMENT PRIMARY KEY`。因为表已有 `verify` 主键，该语句会产生多主键冲突；**013 在改正该迁移方案前禁止执行**。本轮按约束不编辑、不执行 migration。
- `fun_plugin.status` 的 schema 与代码允许负值：migration 中存在 `status < 0` 的兼容分支，代码/生命周期迁移将其视为失败或异常状态。当前 `fun_plugin` 为 0 行，因此不能表述为“当前数据含 -1”；这只是 schema/代码允许的状态语义。

## 5. 逐表 Laravel 字段映射

下表仅描述迁移目标与阻塞点；普通字段必须按实际长度、nullable/default 保留。所有历史 migration 均只读，不在 M1 恢复或执行。

| 旧表 -> Laravel 表 | 主键/关联 | 时间字段 | sort / 状态重点 |
|---|---|---|---|
| `fun_admin` -> `admins` | `id/dept_id` 统一同型 bigint | Unix 秒转 timestamps + softDeletes | `status` 二值但当前 nullable 需先定 NULL 语义 |
| `fun_admin_log` -> `admin_logs` | `id bigint` 保留，`admin_id` 同型 | Unix 秒转 timestamps + softDeletes | 当前库结构与未应用 migration 目标不得混淆 |
| `fun_admin_menu` -> `admin_menus` | `id/pid/permission_id` 同型 | create/update 转 timestamps | `sort default 999`；`status` boolean |
| `fun_attach` -> `attachments` | admin/member/group 关联同型 | timestamps + softDeletes | `sort int`；`status` boolean |
| `fun_attach_group` -> `attachment_groups` | `id/pid` 同型、自关联 | timestamps + softDeletes | **实际 `sort int NULL DEFAULT 999`** |
| `fun_auth_group` -> `auth_groups` | `id/pid` 同型 | timestamps + softDeletes | `data_scope` 是业务枚举 |
| `fun_auth_group_department` | role/dept 复合键同型 | create_time -> created_at | 无 boolean |
| `fun_auth_group_inherit` | role/parent_role 复合键同型 | create_time -> created_at | 无 boolean |
| `fun_blacklist` -> `blacklists` | `id` -> bigint | timestamps + softDeletes | `status` boolean |
| `fun_casbin_rule` -> `casbin_rules` | bigint 保留 | 无 | `rule_hash` unique 保留 |
| `fun_config`、`fun_config_group` | `id` -> bigint | timestamps + softDeletes | status/is_system 按二值映射 |
| `fun_department` | id/pid 同型、自关联 | timestamps + softDeletes | sort unsigned；status boolean |
| `fun_dict_type`、`fun_dict_item` | id/type_id 同型 | timestamps + softDeletes | sort unsigned；status boolean |
| `fun_field_type` | `id` -> bigint | delete_time -> softDeletes | isoption/status 二值 |
| `fun_field_verify` | 实际 `verify varchar(50)` 为非自增 PK；模型显式声明 `verify` 主键及 string 类型 | delete_time -> softDeletes | 013 的新增 `id PRIMARY KEY` 方案与现有 PK 冲突，修复前禁止执行 013 |
| `fun_languages` | `id` -> bigint | timestamps + softDeletes | is_default/status 二值 |
| `fun_member` | member/level/地区 ID 同型；group_id 待淘汰 | timestamps；birthday/last_login 专门转换 | sex 三态，不转 boolean |
| `fun_member_group`、`fun_member_level` | `id` -> bigint | timestamps + softDeletes | member_level.sort 当前 nullable 需保留 |
| `fun_member_group_relation` | member/group 复合键同型 | create_time -> created_at | 当前两条真实 FK 位于此表 |
| `fun_permission` | id/pid 同型、自关联 | create/update -> timestamps | sort default 999；二值字段映射 |
| `fun_plugin` | id bigint；name unique | lifecycle 时间转 timestamps | status 允许 `-1/0/1` 语义，不能转 boolean；当前表为空 |
| `fun_plugin_operation` | id bigint；plugin_name 逻辑关联 | timestamps | status 二值，result 是业务状态 |
| `fun_plugin_version_history` | id bigint；插件关联待定 | timestamps | signature_verified/status 二值 |
| `fun_provinces` | 编码型 id/pid 保留同型 | delete_time -> softDeletes | level 不是 boolean |
| `fun_schema_integrity_guard_006` | 不迁移为业务表 | 无 | Laravel migration 用断言替代 |
| `fun_system_migration` | bigint；scope+version 唯一 | executed_at -> timestamp | 与 Laravel migrations 合并前保留审计字段 |

### 类型与约束阻塞点

1. 父子列必须在同一 migration 批次保持同型，之后才能建立 FK。
2. 除会员-会员组关系外，多数关联是逻辑关联；加 FK 前必须清理孤儿数据。
3. Unix 秒不能直接改 timestamp；需定义 0/NULL 语义并做显式数据转换。
4. `fun_plugin.status` 只表示 schema/代码允许多态状态，当前空表没有 `-1` 数据事实。
5. `fun_member.group_id varchar(50)` 与关系表重复，验证后以关系表为真源。
6. `fun_admin_log` 当前结构与未应用 migration 目标必须分别记录，不能假设 migration 已生效。
7. `fun_attach_group.sort` 当前 nullable/default 是 `YES/999`，不得写成 non-null 或 default 0。
8. `fun_field_verify.verify` 是现有字符串主键；013 不能再新增第二个 `PRIMARY KEY`，迁移修复前禁止执行。

## 6. 菜单、权限、插件脱敏快照

快照仅包含计数和非敏感结构信息，排除密码、token、插件 config/manifest 内容、操作令牌、环境变量和密钥。

| 数据集 | 当前行数 |
|---|---:|
| `fun_admin_menu` | 20 |
| `fun_permission` | 168 |
| `fun_plugin` | 0 |
| `fun_plugin_operation` | 0 |
| `fun_plugin_version_history` | 0 |
| `fun_casbin_rule` | 2 |

- 菜单和权限详细 hash 属于先前快照；本次未重新生成内容 hash，因此不沿用可能过时的 hash 数字。
- 插件三张表当前均为空；这不代表文件系统、市场包、未来安装记录或生命周期迁移逻辑可以删除。
- `plugins/` 当前为空，`addons/` 当前不存在；两者是文件系统事实，与数据库空表事实分别记录。

## 7. 当前基线验证

所有结果均为本轮重新执行结果，不沿用旧数字。每条命令、退出码和关键原始输出见 `m1-command-output-summary.txt`。

| 检查 | 退出码 | 当前结果 |
|---|---:|---|
| HEAD、时间、status 与 status hash | 0 | HEAD 与时间点如第 1 节；完整 status 已保存，hash 为 `e3f6c6...be01`。 |
| 扫描范围存在性/文件计数 | 0 | `plugins files=0`；`addons absent`；指定 4 个 PHP 文件存在；`admin-web files=19560`（含依赖）。 |
| 数据库字段核验 | 0 | `fun_attach_group.sort`: `YES / 999 / int`；`fun_field_verify.verify`: `varchar(50) / NOT NULL / PRI / 非自增`。 |
| 数据库版本、表与行计数 | 0 | MySQL 8.0.46；29 张 `fun_` 表；菜单 20、权限 168、插件 0、操作 0、历史 0、Casbin 2。 |
| PHP lint | 0 | `files=232 failures=0`，使用本机 PHP 8.1.32 可执行文件。 |
| 后端聚焦测试 | 0 | `scripts=12 failures=0`；认证 HTTP 子场景因未设置测试账号而 SKIPPED，脚本总体 PASS。 |
| Admin Web 聚焦契约 | 0 | `field-hygiene`、`install-environment`、`member-registration-admin-scope` 共 3 文件、13 测试全部通过。 |
| Admin Web type-check | 0 | `vue-tsc --noEmit` 通过。 |
| Admin Web test | 1 | 当前并发快照为 35 个文件中 34 通过、1 失败；142 个测试中 139 通过、3 失败。原两个基线失败均通过；新增失败全部来自并发加入的 `storage-driver.spec.ts`。 |
| Admin Web build | 0 | `2698 modules transformed`，Vite build 成功。因执行前 `public/admin-web` 已有大量用户/并发改动，本轮不执行 restore/clean，避免覆盖用户改动。 |

### 当前测试结论

1. 原失败 `tests/contracts/install-environment.spec.ts` 当前 2/2 通过；生产实现通过 `buildEnvironmentChecks()` 的结构化条目覆盖全部检查，不再为旧字符串快照改写架构。
2. 原失败 `tests/contracts/member-registration-admin-scope.spec.ts` 当前 2/2 通过；生产实现顺序为角色层级校验、目标管理员数据范围校验、越权拒绝、事务保存。
3. 全量测试期间工作区发生并发变化：先短暂出现 `plugin-page.spec.ts` 单项失败，当前快照聚焦重跑已 9/9 通过；随后新增 `storage-driver.spec.ts`，其 3 项契约失败稳定复现。该文件及其功能不属于指定的两个 M1 前端基线失败，本轮不越界修改。
4. `admin-email-length` 与 `operation-log-middleware` 继续通过。

## 8. M2 前阻塞与审查结论

- 先确定 Laravel 目标版本、包边界、认证、RBAC/Casbin 和并行切流策略。
- 定义 Unix 时间的 0/NULL 转换规则，清理孤儿数据后再统一 bigint/FK。
- 明确接管 `fun_admin_log` 的唯一 migration 路径，避免双重迁移。
- 原两个 Admin Web 基线契约失败已消除；但当前全量测试仍被并发新增的 `storage-driver.spec.ts` 3 项失败阻塞，尚不能宣告全量绿线。
- `fun_field_verify` 模型映射已更正；013 的双主键冲突尚未在 migration 中修复，因此 013 继续禁止执行。
- 插件数据库与目录为空仍不构成删除依据；必须覆盖命令、生命周期、资源注册、市场包、回滚及旧资源兼容审查。
- 系统升级、附件选择器与旧 FormHelper 尚无完整等价替代，继续阻塞旧链路删除。
- M1 不执行、不恢复、不重写历史 migration；不 commit、不 push；不开展 M2。
- 工作区可能并发变化，独立审查应先比对 HEAD、精确时间、完整 status 或其 SHA-256，再复跑基线命令。
