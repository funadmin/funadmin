# 插件目录规范

插件包按项目相对路径组织，开发路径、PHP 命名空间和安装路径保持一致。以下以 `demo` 为例，列出完整目录结构。

`plugin:make` 和插件中心的“插件开发工具”只生成用得到的文件：各模块的控制器、入口与配置文件，以及迁移目录的占位文件 `database/migrations/.gitkeep`。迁移目录要保留占位，因为代码生成器会把迁移写到这里，而空目录不会进入插件包。

分层目录（`model/`、`service/`、`validate/`、`middleware/`、`view/`）在需要时直接新建文件即可。`storage/` 和公开资源目录是可选的：需要时在 `plugin.json` 中声明 `storage.path` 或 `resources.public`，并在目录中放入文件或 `.gitkeep`，否则打包后目录缺失，安装时会报“路径不存在”。

后台管理模块为 `admin/app/admin/plugin/`；已安装插件的后台业务为同级的 `admin/app/admin/plugins/{code}/`。插件源码包保留在根目录 `admin/plugins/{code}/`，包内使用相同的 `app/admin/plugins/{code}/` 相对路径。

```text
admin/plugins/demo/
├── plugin.json
├── Plugin.php
├── config.php
├── app/
│   ├── admin/
│   │   └── plugins/
│   │       └── demo/
│   │           ├── controller/
│   │           │   └── Index.php
│   │           ├── model/
│   │           ├── service/
│   │           ├── validate/
│   │           └── middleware/
│   └── demo/
│       ├── controller/
│       │   └── Index.php
│       ├── model/
│       ├── service/
│       ├── middleware/
│       ├── view/
│       ├── config/
│       │   └── app.php
│       ├── route/
│       │   └── app.php
│       ├── lang/
│       │   └── zh-cn.php
│       ├── event.php
│       └── provider.php
├── web/
│   └── src/
│       └── admin/
│           └── plugins/
│               └── demo/
│                   ├── api.ts
│                   ├── types.ts
│                   └── pages/
│                       ├── Index.vue
│                       └── components/
│                           └── EditDialog.vue
├── public/
│   └── plugin-assets/
│       └── demo/
│           └── public/
├── database/
│   └── migrations/
└── storage/
```

## 安装位置

| 包内相对路径 | 安装位置（相对于仓库根目录） |
|---|---|
| `app/admin/plugins/demo/` | `admin/app/admin/plugins/demo/` |
| `app/demo/` | `admin/app/demo/` |
| `web/src/admin/plugins/demo/` | `web/src/admin/plugins/demo/` |
| `public/plugin-assets/demo/public/` | `admin/public/plugin-assets/demo/public/` |

后台控制器的命名空间为 `app\admin\plugins\demo\controller`。独立应用控制器为 `app\demo\controller`。管理端模型和生成模型统一继承 `app\common\model\BaseModel`，已移除空包装类 `app\admin\model\BackendModel`。

插件源包保留在 `admin/plugins/demo/`。`plugin.json`、`Plugin.php`、`config.php` 保留在源包中；迁移 SQL 从 `database/migrations/` 执行；私有运行数据写入 `admin/runtime/plugins-data/demo/storage/`，不随源码发布覆盖。

## 声明和生成

```sh
cd admin
php webman plugin:make demo --title=示例
php webman plugin:validate demo
php webman plugin:package demo
```

Manifest 的前端入口固定为 `web/src/admin/plugins/demo`。公开资源的 `source` 使用 `public/plugin-assets/demo/public`，`target` 使用 `plugin-assets/demo/public`。声明迁移时路径固定为 `database/migrations`；无 SQL 插件可不声明 `migrations`，有迁移历史后必须保留声明。

CRUD、业务生成和 ZIP 打包使用同一套目录。校验、发布、修改检测和恢复只接受统一后的目录及资源标识。旧包结构和旧资源登记会明确报错，不自动转换或删除原文件。

## 部署

前端项目默认位于与 `admin` 同级的 `web`，可通过 `PLUGIN_ADMIN_WEB_PATH` 指定源码项目根目录。新增前端页面后重新运行 `npm run build:webman`。新增或更新 PHP 类和路由后运行 `php start.php reload`。纯启停通过激活清单即时控制。

页面默认部署在网站根目录 `/`，`web` 是源码项目名称。当前默认访问地址如下：

| 用途 | 地址 |
|---|---|
| 网站首页 | `/` |
| 后台登录 | `/#/admin/login` |
| demo 插件后台页面 | `/#/admin/plugins/demo/items` |
| demo 插件后台接口 | `/admin/plugins/demo/index` |
| demo 公开静态资源 | `/plugin-assets/demo/public/文件名` |

`VITE_APP_BASE` 控制页面与构建资源的部署目录；`VITE_APP_BASE_API` 控制管理接口及下载链接的前缀，默认 `/admin`。两者分别处理。`npm run build:webman` 输出到 `admin/public/index.html` 和 `admin/public/assets/`，保留已有安装锁、上传文件及插件资源。独立前端部署可运行 `npm run build:standalone` 输出到 `web/dist/`。

插件页面和管理接口统一使用 `/plugins/{code}/...`。单数 `/plugin/{code}/...` 不属于当前协议。插件源码、安装目录和 PHP 命名空间继续使用 `plugins/{code}` 隔离，不把插件文件发布到宿主控制器目录。

需要替换宿主路由时，在 `plugin.json` 声明 `routeOverrides`，不能直接覆盖 `admin/app` 下的宿主文件：

```json
{
  "routeOverrides": [
    {
      "methods": ["GET"],
      "path": "/admin/system/user/profile",
      "controller": "app\\admin\\plugins\\demo\\controller\\ProfileOverride",
      "action": "show",
      "priority": 100
    }
  ]
}
```

覆盖处理器必须属于当前插件的 `app\\admin\\plugins\\{code}\\controller` 命名空间，目标路由必须已经存在；同一个方法和路径不能被多个插件覆盖。卸载插件时只移除插件发布资源，宿主控制器文件不会被删除。

开发环境由 Vite 将 `/admin/...`、Hook 描述接口及 `/plugin-assets/...` 等请求代理到 `VITE_APP_PROXY_TARGET`，默认 Webman 地址为 `http://127.0.0.1:8787`。同端口部署直接请求后端；独立前端域名部署需要配置对应接口和公开资源代理，参见 `web/README.md`。

## 发布登记与手工同步

安装、更新和重新部署会把插件源复制到上表的安装位置，并在 `plugin_resource` 中登记每个文件的 `sha256`（原生 App 单元另记 `tree_hash`）。下一次更新以这份登记为基线判断安装位置是否被二次开发：

- 安装位置的文件等于登记基线，或等于即将发布的插件源文件（逐字节相同），都视为未修改。后者表示新版本已被手工同步，更新会直接接管并重写登记。
- 未登记但与插件源逐字节相同的公开资源或前端文件同样直接接管；内容不同仍拒绝覆盖（`拒绝覆盖未登记的核心文件`）。
- 既不等于基线也不等于新源的文件，以及插件源中没有的未登记文件，仍报 `原生 App 存在二次开发冲突`。

### `plugin:rebaseline`

手工复制过插件文件后，登记会停在旧版本。用下面的命令按某一侧重写该插件的全部登记行（路径、`sha256`、单元 `tree_hash`），哈希与真实发布使用同一套算法：

```sh
cd admin
php webman plugin:rebaseline cms --dry-run                 # 只列出差异与登记变更，不写库
php webman plugin:rebaseline cms                           # 默认 --from=installed
php webman plugin:rebaseline cms --from=source
php webman plugin:rebaseline cms --from=installed --force  # 安装位置与源不一致时仍以安装位置为准
```

- `--from=installed` 按安装位置的文件登记，`--from=source` 按插件源登记。
- 安装位置与插件源不一致（`modified:`、`missing:`、`extra:`、`tree:`）时默认拒绝，不写库；确认以所选一侧为准时加 `--force`。`--dry-run` 输出中的 `blocked` 表示正式执行会被拒绝。
- 插件必须已安装且没有进行中的生命周期操作。存在未完成的 publication journal 时会拒绝，先运行 `php webman plugin:publication-recover <token>`（`--dry-run` 的 `staleJournals` 列出 token）。
- 写入在生命周期锁、发布锁和数据库事务内完成，并在 `plugin_operation` 记录一条 `rebaseline` 审计。
- 只改写 `plugin_resource`，不改 `plugin.version`、不执行迁移、不登记权限或菜单。随后照常运行更新（版本高于记录时）或重新部署。

### `plugin:sync`（仅开发环境）

```sh
cd admin
php webman plugin:sync cms
```

把插件源完整同步到所有安装位置（原生 App 单元、公开资源和前端源码），然后按插件源重建登记。安装位置上的手工改动和多余文件会被插件源覆盖或删除。复制经由 publication journal 执行，失败时自动回滚。只有 `APP_DEBUG=true` 或 `APP_ENV` 为 `dev`、`development`、`local` 时可用，否则直接拒绝。同步后会重建激活快照；新增路由或 PHP 类仍需 `php start.php reload`，迁移、权限和菜单仍需更新或重新部署。

### 生命周期钩子不得修改数据库结构

`install()`、`enabled()`、`beforeUpdate()`、`afterUpdate()` 里不能建表、加列、加索引或执行其他 DDL。结构变更只写在 `database/migrations/` 里。钩子先建了列，之后执行同一迁移就会报 `Duplicate column` 而失败。钩子里可以只读地探测结构（`hasTable`、`hasColumn`），也可以写种子数据或补齐权限。

- `plugin:validate` 会做静态检查。它从这四个钩子出发，沿 `self::`、`static::`、`$this->`、`类名::` 和 `(new 类)->` 调用进入插件自带的类，查找以下写法：Schema Builder 的 `create`、`table`、`drop*`、`rename`，Schema 门面，`Blueprint`，以及 `statement()`、`unprepared()` 执行的 DDL。`statement()` 和 `unprepared()` 的 SQL 不是字面量时，无法证明它不是 DDL，因此同样拒绝。
- 运行时也有兜底。执行这四个钩子期间，默认连接上的 DDL 会在执行前被拒绝，生命周期操作随之失败。

### `plugin:migrate --adopt`（接管已生效的迁移）

迁移的效果有时已经存在，例如旧版钩子在运行时建好了列，迁移因此失败。这时可以核对当前结构，然后只登记、不执行：

```sh
cd admin
php webman plugin:migrate cms --adopt=005_form_fingerprint --dry-run   # 逐项列出效果及其状态，不写库
php webman plugin:migrate cms --adopt=005_form_fingerprint
```

- 每条语句都必须是能从结构证明已生效的新增，并逐项核对：
  - `CREATE TABLE`：表存在，表内的列、主键、索引也都存在；
  - `ADD [COLUMN]`：列存在，类型兼容，并且声明了可空性时可空性一致；
  - `ADD INDEX` / `ADD UNIQUE` / `CREATE INDEX`：存在列顺序完全相同、唯一性也相同的索引。
- 其他语句一律拒绝，包括数据变更、`MODIFY` / `CHANGE`、外键、生成列和动态 SQL。只要有一项效果缺失或不一致，就拒绝整个文件，什么也不写。
- 只能按版本顺序接管：目录中更早的版本还没有执行或接管时也拒绝（`pendingEarlier` 列出这些版本）。
- `--dry-run` 中 `adoptable: false` 表示正式执行会被拒绝，此时退出码为 1，JSON 照常输出。
- 类型比较方式取决于驱动：
  - MySQL / MariaDB 比较完整的列类型，忽略整数显示宽度：`bigint(20) unsigned` 等于 `bigint unsigned`，`int(11)` 等于 `int`。
  - `tinyint(1)` 是布尔约定（`boolean` 的写法），保留宽度，不与普通 `tinyint` 互相接管。
  - MariaDB 上 `json` 等于 `longtext`（MariaDB 的 JSON 是 LONGTEXT 的别名）；MySQL 上二者不同。
  - 其他驱动按类型族比较。
- 文件的编码检查、只向前守卫和 checksum 与正常执行时相同，登记的 checksum 就是正常执行会写入的值。版本已登记且 checksum 相同时，命令什么也不做；checksum 不同则拒绝。
- 插件必须已安装。写入在生命周期锁和迁移锁内完成，并在 `plugin_operation` 记录一条 `migration_adopt` 审计。
- 命令只登记 `system_migration`，不改插件状态。之后照常更新或迁移，已登记的版本会被跳过。

## 后端钩子、前端钩子与跨插件调用

插件可仅提供钩子，不必包含 `app/`、控制器、菜单或路由。安装和启用仍通过原有插件生命周期；停用插件、依赖失效或激活快照损坏时，不会加载该插件的处理器。

后端共享入口可在控制器、服务、模板、CLI、队列及其他插件中使用：

```php
use app\common\plugin\hook\Hook;

Hook::action('orders.completed', ['id' => $orderId]);
$amount = Hook::filter('plugin.pricing.calculate', $amount, ['orderId' => $orderId]);
```

调用点不必预先登记到核心白名单。动作忽略返回值，出错记录后继续；过滤器将返回值传给下一个处理器，保留 false、null、0，异常则中断。排序为 priority 升序（默认 100）、插件标识、声明 id。没有监听器时动作无操作、过滤器返回原值。

Manifest 的可选声明示例：

```json
{
  "serverHooks": [
    {
      "id": "calculate",
      "point": "plugin.pricing.calculate",
      "type": "filter",
      "file": "hooks/pricing.php",
      "handler": "calculate",
      "priority": 100
    }
  ],
  "clientHooks": [
    {
      "id": "dashboard-card",
      "point": "dashboard.cards",
      "type": "component",
      "component": "PricingCard"
    },
    {
      "id": "decorate",
      "point": "plugin.pricing.decorate",
      "type": "filter",
      "file": "hooks.ts",
      "export": "decorate",
      "audiences": ["public", "member", "admin"]
    }
  ]
}
```

> 钩子字段为 `serverHooks` / `clientHooks`；`plugin.json` 不接受其他顶层字段，未知字段在 Schema 校验时被拒绝。激活快照当前 `schema_version` 为 3，快照格式变化后必须执行 `php webman plugin:activation-cache` 重建一次，否则旧快照会被判定为不可信。

私有 `hooks/pricing.php` 返回具名 callable 映射：

```php
<?php
return [
    'calculate' => static fn ($amount, array $context) =>
        $context['hooks']->filter('plugin.tax.calculate', $amount, $context),
];
```

映射值可为函数、闭包、类方法或可调用对象。处理器源码只在通过激活检查后加载，每个工作进程只加载一次；更新 PHP 源码后必须 reload。运行时快照绑定私有文件哈希，文件缺失、越界、符号链接或内容不匹配会被拒绝。

前端使用共享模块，任何页面或插件都可导入：

```ts
import { hooks, PluginSlot } from '@/shared/plugins/hooks';
import type { HookContext } from '@/shared/plugins/hooks';

await hooks.action('orders.selected', { id: 1 });
const actions = await hooks.filter('table.actions', baseActions);

export function decorate(value: string, context: HookContext) {
  context.onDispose(() => { /* 注销监听器、清理定时器 */ });
  return context.hooks.filter('plugin.other.decorate', value);
}
```

```vue
<PluginSlot name="dashboard.cards" :context="{ section: 'overview' }" />
```

组件引用 `adminWeb.components` 中声明的 Vue 组件。函数引用 `adminWeb.source` 下的 TS/JS 模块具名导出；只提供函数时 components 可以是空对象，menu/routes 可以为空数组。源码仍须进入前端构建，不从接口地址动态执行 JS。

处理器嵌套调用其他扩展点时，后端使用 `$context['hooks']`，前端使用 `context.hooks`；它们继承调用链与取消作用域，深度上限 32。全局入口用于宿主发起新调用。可选跨插件协作无需依赖声明；必需的协作用 `requires.plugins` 声明版本依赖。

前端 `audiences` 默认为 admin。公共页、会员页、后台页精确匹配 public、member、admin，不自动互相继承；共用贡献应显式声明多个值。带 permission 的贡献只允许 admin，业务接口自身仍须执行后端授权。

前端读取描述入口为：

- 公共/会员：`GET /plugins/hooks/frontend?audience=public|member`，会员使用现有 Bearer 认证。
- 后台：`GET /admin/plugins/hooks/frontend?audience=admin`，沿用 Path=/admin 的浏览器会话 Cookie。
- 原 `/admin/system/plugin/modules/enabled` 保留数组协议，增加纯钩子插件描述。

登录、页面会话切换、当前页面插件启停后、窗口获得焦点及可见页面每 30 秒同步一次。失效请求和迟到的异步导入不能恢复已禁用贡献；禁用、登出、页面作用域取消后运行 onDispose。新建前端源码需重新构建，已收录代码的启停只需同步描述。

首批宿主扩展点：

| 扩展点 | 类型 | 触发位置 |
|---|---|---|
| upload.completed | 后端 action | 每次成功附件上传，新建/复用均触发；未完成分块不触发 |
| upload.result | 后端 filter | 普通后台上传响应，只允许增加 extensions.{插件标识} |
| dashboard.cards | 前端 component | 仪表盘卡片区域 |
| dashboard.loaded | 前端 action | 仪表盘激活及钩子描述更新 |
| dashboard.quickActions | 前端 filter | 仪表盘快捷入口 |

`upload.completed` 提供 created/reused 标记，size 沿用附件模型的 KB 单位；复用记录时不应重复处理文件。动作报错不撤销已保存文件。上传响应过滤失败保留已保存附件，并按现有后台协议返回可恢复错误（业务 code=422）。

可直接安装的无应用示例和跨插件示例位于 [examples/plugin-hooks](examples/plugin-hooks/README.md)。

## Composer 依赖

插件有两种 Composer 依赖方式，可以组合使用。宿主不会在后台执行任何 Composer 命令。

### 宿主提供依赖（B）

在 `plugin.json` 中声明：

```json
{
  "requires": {
    "plugins": {},
    "composer": {
      "guzzlehttp/guzzle": "^7.0",
      "monolog/monolog": ">=3.0, <4.0"
    }
  }
}
```

这里只认宿主 `admin/vendor/composer/installed.php` 中的包，其他插件的 vendor 不会代替宿主满足依赖。安装、更新、启用和历史重部署前检查；缺少或版本不满足时拒绝，并提供需由管理员手动执行的 `composer require` 命令。执行目录为 `admin/`，之后需重启/重载 Webman worker。禁用、卸载不做这项检查。

版本约束支持 `^`、`>=`、`>`、`<=`、`<`、`=` 和精确版本；逗号或空格表示 AND。不支持 `~`、`||`、通配符或 dev/预发布版本；Composer 依赖的 `^0.2.1` 只接受 `<0.3.0`，`^0.0.1` 只接受 `<0.0.2`。`php` 用已有的 `requires.php` 声明；`ext-*` 不可放入 `requires.composer`，PHP 扩展由部署环境另行确保。

### 插件自带依赖（A）

在插件开发目录维护自己的 `composer.json`、`composer.lock`，构建时运行：

```sh
composer install --no-dev --optimize-autoloader
```

将 `vendor/` 和锁文件一起打入插件 ZIP。无需在控制器或钩子里手动 require `vendor/autoload.php`；宿主在 worker 启动、插件应用守卫、钩子执行前及创建生命周期入口时统一加载。插件 ClassLoader 追加注册，普通未加载类优先由宿主解析；不同插件同时提供同名类时，由先注册/加载的版本提供。启动时插件按 code 字母序注册，但运行中新增插件或此前已加载的类仍会影响结果。**安装后启用、更新、重部署或禁用后应重载 worker**，PHP 无法卸载已加载的类和函数。

启动时的注解路由扫描也会先检查激活状态：停用或状态不可信的插件控制器不会被反射/加载，避免它们继承自带库基类时使整个 worker 启动失败。冷启动时被跳过的插件后来启用，需要重载 worker 才会注册对应注解路由；禁用仍由运行时守卫立即阻断请求。

这不是命名空间隔离：不能保证同一个进程并存同一库的多个版本。Composer 的 `autoload.files` 会在加载时立即执行，不受 ClassLoader 追加顺序保护；同名函数、动态类加载及全局状态也可能冲突。对于需要不同版本的依赖，插件作者应先自行用 **php-scoper** 加前缀（例如 `plugins\sample\Vendor\...`），在插件构建环境中配置后运行 `php-scoper add-prefix`，再生成匹配的新 autoload，并测试反射、字符串类名及运行时加载。不建议宿主自动执行 scoper；每个库的配置不同。

不要将宿主框架再次打入插件。path repository 如果生成符号链接，安装会被拒绝；需要使用复制模式。安装包上限为压缩后 100MB、解压后 500MB。

### 冲突确认

安装、更新、启用和重部署时，将自带包与宿主、其他已启用插件比对；同主版本默认视为兼容，0.x 比较主次版本，dev/预发布版本按兼容性未知处理。**这只是兼容性预警，不是完整 Composer 依赖求解**，同主版本也可能因为新增 API 或间接依赖而不兼容。

发现冲突时，服务端返回 HTTP/业务码 `409`，`data.reason` 为 `composer_conflict`，`data.conflicts` 包含 `package`、`pluginVersion`、`otherVersion`、`source`（`host` / `plugin:{code}`）和 `reason`。后台显示明细，可选择“仍然继续”或“取消”。前端确认后以 `acceptComposerConflicts=true` 重发原操作（云包会重新下载，本地 ZIP 会重新上传）。取消或首次拦截不会记成生命周期失败。

成功执行后会保存冲突清单指纹到 `plugin.composer_conflict_ack`，并记录警告及操作人。同一清单之后不再重复提示；冲突版本/来源变化会再次提示。仅有确认并不代表依赖兼容。`plugin:validate` 对宿主依赖不满足返回失败，对自带包冲突只在 JSON 输出的 `warnings` 字段中提示，不改变成功退出码。

部署此功能先在 `admin/` 执行 `php bin/console.php migrate`：MySQL 的该字段已并入核心基线 `001_baseline.sql`（原增量 `023_plugin_composer_conflict_ack.sql` 见归档），其他受支持驱动的该字段已并入 PHP 基线 `001_baseline.php`（原 `032_plugin_composer_conflict_ack.php` 见归档）。勿直接修改已执行迁移文件。之后重载 worker。

## 接口权限（代码即权限）

插件后台控制器继承 `app\common\controller\AdminController`，**每个后台方法就是一个独立权限**，权限码由控制器与方法自动推导，不需要也不支持手写：

```
{插件}:{资源}:{方法}      全部 kebab-case；资源 = 类名去掉 Controller 后缀
app\admin\plugins\cms\controller\Contributions::review    → cms:contributions:review
app\admin\plugins\cms\controller\Content::saveDraft           → cms:content:save-draft
app\admin\plugins\shop\controller\admin\OrderController::close → shop:admin-order:close   （子目录名并入资源名）
```

- 宿主与插件使用同一套推导规则（`app\admin\authorization\service\PermissionCode`），统一由 `CheckAdminApiRoleMiddleware` 校验，插件没有专用权限中间件。
- 插件后台方法总是要求登录，并且要求**归属本插件**（`source_type=plugin`、`source_name=插件名`）的已启用权限码；方法上的 `#[Permission]`、`#[NoNeedLogin]`、`#[NoNeedPermission]` 对插件后台控制器不生效，不能借用宿主或其它插件的权限。
- 安装、启用、升级插件时，宿主扫描 `app/admin/plugins/{插件}/controller` 中带路由注解的方法，自动登记推导出的权限码（名称默认取方法 OpenAPI 的 `summary`）；方法删除或改名后，旧权限码在下次启用时停用。
- 读取与保存是两个方法，因此是两个权限：例如编辑页的"读取详情"和"保存"分别授权。

`plugin.json` 的 `adminWeb.permissions` 现在有两种用途：

1. **给推导码命名**：`code` 与某个方法的推导码相同时，只设置它在角色授权树里显示的名称；
2. **能力权限**：`code` 不对应任何方法时登记为能力权限（`capability`），用于接口以外的业务判断，例如 CMS 的站点操作 `cms:content:publish`。

菜单 `menu[].permission` 与前端路由 `routes[].meta.permission` 应绑定该页面列表接口的推导码（例如 `qa:moderation:index`），前端按钮用 `v-perm` / `hasPermission` 检查对应方法的推导码。`admin/tests/PluginFrontendPermissionContractTest.php` 会检查前端与清单里使用的插件权限码都真实存在。

插件闭包路由（`PluginRoute::adminGroup`）没有控制器方法可推导，仍使用清单中声明的权限码。

### 权限资源树中的分组

权限资源树按权限码 `模块:资源:操作` 自动分组，结构是：插件分组 → 资源分组 → 接口或能力权限。宿主的 `PermissionTreeService` 会在同步权限和插件登记资源后自动整理。

- 插件分组名取插件名称。
- 资源分组名优先取 `adminWeb.permissionGroups` 里的声明（资源标识 → 名称），其次取绑定菜单的名称，最后退回资源标识本身。
- 多个资源声明同一个名称时合并为一组。比如站点操作的能力权限 `cms:site:*` 和对应接口 `cms:sites:*` 都映射为“站点管理”，就会落在同一个分组里。

```json
"adminWeb": {
  "permissions": [ ... ],
  "permissionGroups": { "site": "站点管理", "sites": "站点管理", "moderation": "问答审核" }
}
```

分组行归属本插件（`source_type=plugin`、`source_name=插件名`），卸载插件时随插件权限一并删除。

CRUD 生成器与插件脚手架生成的插件控制器不带权限注解，生成的清单与前端使用推导码；插件 CRUD 的 `permissionPrefix` 必须等于 `{插件}:{资源}`。

## 中间件命名

项目自有 PHP 中间件类统一以 `Middleware` 结尾，文件名与类名一致，例如 `CheckAdminApiRoleMiddleware.php`、`PluginRoutePermissionMiddleware.php`。引用和代码生成模板使用完整的新类名；`middleware.php` 等框架约定配置文件保留原名。第三方依赖遵循其自身命名。命名清单与管线可加载性由 `admin/tests/MiddlewareNamingTest.php` 检查。
