# 插件目录规范

插件包按项目相对路径组织，开发路径、PHP 命名空间和安装路径保持一致。以下以 `demo` 为例，省略空目录中的 `.gitkeep`。

后台管理模块为 `webman-admin/app/admin/plugin/`；已安装插件的后台业务为同级的 `webman-admin/app/admin/plugins/{code}/`。插件源码包保留在根目录 `webman-admin/plugins/{code}/`，包内使用相同的 `app/admin/plugins/{code}/` 相对路径。

```text
webman-admin/plugins/demo/
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
├── admin-web/
│   └── src/
│       └── admin/
│           └── modules/
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
| `app/admin/plugins/demo/` | `webman-admin/app/admin/plugins/demo/` |
| `app/demo/` | `webman-admin/app/demo/` |
| `admin-web/src/admin/modules/demo/` | `admin-web/src/admin/modules/demo/` |
| `public/plugin-assets/demo/public/` | `webman-admin/public/plugin-assets/demo/public/` |

后台控制器的命名空间为 `app\admin\plugins\demo\controller`。独立应用控制器为 `app\demo\controller`。管理端模型和生成模型统一继承 `app\common\model\BaseModel`，已移除空包装类 `app\admin\model\BackendModel`。

插件源包保留在 `webman-admin/plugins/demo/`。`plugin.json`、`Plugin.php`、`config.php` 保留在源包中；迁移 SQL 从 `database/migrations/` 执行；私有运行数据写入 `webman-admin/runtime/plugins-data/demo/storage/`，不随源码发布覆盖。

## 声明和生成

```sh
cd webman-admin
php webman plugin:make demo --title=示例
php webman plugin:validate demo
php webman plugin:package demo
```

Manifest 的前端入口固定为 `admin-web/src/admin/modules/demo`。公开资源的 `source` 使用 `public/plugin-assets/demo/public`，`target` 使用 `plugin-assets/demo/public`。声明迁移时路径固定为 `database/migrations`；无 SQL 插件可不声明 `migrations`，有迁移历史后必须保留声明。

CRUD、业务生成和 ZIP 打包使用同一套目录。校验、发布、修改检测和恢复只接受统一后的目录及资源标识。旧包结构和旧资源登记会明确报错，不自动转换或删除原文件。

## 部署

前端项目默认位于与 `webman-admin` 同级的 `admin-web`，可通过 `PLUGIN_ADMIN_WEB_PATH` 指定源码项目根目录。新增前端页面后重新运行 `npm run build:webman`。新增或更新 PHP 类和路由后运行 `php start.php reload`。纯启停通过激活清单即时控制。

页面默认部署在网站根目录 `/`，`admin-web` 是源码项目名称。当前默认访问地址如下：

| 用途 | 地址 |
|---|---|
| 网站首页 | `/` |
| 后台登录 | `/#/admin/login` |
| demo 插件后台页面 | `/#/admin/plugin/demo/items` |
| demo 插件后台接口 | `/admin/plugin/demo/index` |
| demo 公开静态资源 | `/plugin-assets/demo/public/文件名` |

`VITE_APP_BASE` 控制页面与构建资源的部署目录；`VITE_APP_BASE_API` 控制管理接口及下载链接的前缀，默认 `/admin`。两者分别处理。`npm run build:webman` 输出到 `webman-admin/public/index.html` 和 `webman-admin/public/assets/`，保留已有安装锁、上传文件及插件资源。独立前端部署可运行 `npm run build:standalone` 输出到 `admin-web/dist/`。

开发环境由 Vite 将 `/admin/...`、Hook 描述接口及 `/plugin-assets/...` 等请求代理到 `VITE_APP_PROXY_TARGET`，默认 Webman 地址为 `http://127.0.0.1:8787`。同端口部署直接请求后端；独立前端域名部署需要配置对应接口和公开资源代理，参见 `admin-web/README.md`。

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
  "backendHooks": [
    {
      "id": "calculate",
      "point": "plugin.pricing.calculate",
      "type": "filter",
      "file": "hooks/pricing.php",
      "handler": "calculate",
      "priority": 100
    }
  ],
  "frontendHooks": [
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
