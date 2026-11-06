# 后台按钮权限

后台操作统一使用 `web/src/shared/utils/permission.ts`，权限来源是当前账号登录后下发的 `permissions`。前端不自行把菜单查看权限扩大为新增、修改或删除权限。

## 公共判断

```ts
import { hasPermission } from '@/utils/permission';

// 单个动作。
hasPermission('authorization:admin:update');

// 默认满足任一权限。
hasPermission(['authorization:admin:create', 'authorization:admin:update']);

// 必须同时满足全部权限。
hasPermission(['development:business:generate', 'development:business:apply-resources'], 'all');
```

`hasPermission()` 每次读取当前账号权限，可用于模板、computed 和事件处理器。`*`、`*:*:*` 是现有超级权限，其余权限精确匹配。`undefined`、`null`、空字符串表示可选权限未声明；需要保护的业务动作必须传入真实权限码。

纯 Schema 执行器已经持有明确权限上下文时，使用 `checkPermission(context.permissions, required, mode)` 复用相同规则；不要再复制 `permissions.some/includes` 判断。

## 按钮与操作入口

```vue
<el-button v-perm="'authorization:admin:create'" @click="create">新增</el-button>
<el-button v-perm:all="['action:execute', 'action:approve']" @click="execute">执行</el-button>
<el-switch :disabled="!hasPermission('authorization:admin:status')" />
```

`v-perm` 随权限授予、撤销和账号权限替换更新显示；重新授权后能恢复显示，并在组件卸载时释放监听。

业务入口同样检查权限，尤其是在确认弹窗、表单异步校验、读取文件等等待步骤之后、发请求之前重新检查：

```ts
async function remove() {
  if (!hasPermission('authorization:admin:delete')) return;
  await confirmRemoval();
  if (!hasPermission('authorization:admin:delete')) return;
  await removeRecord();
}
```

多文件顺序上传在每个文件请求前检查上传权限；已经发出的请求由后端决定是否允许，前端不会承诺撤销已完成的操作。

## 通用 CRUD

```ts
useCrud({
  api,
  initialQuery,
  permissions: {
    add: 'authorization:admin:create',
    edit: 'authorization:admin:update',
    delete: 'authorization:admin:delete'
  }
});
```

`permissions` 是可选配置，兼容已有由动态动作宿主管理权限的页面。声明后，新增/编辑入口和删除确认前后均检查对应权限。自定义保存、启停、上传等动作仍在其实际处理器检查。

## 权限码来源与范围（前后端同码）

- **每个后台方法就是一个权限**，权限码由控制器与方法自动推导：`{模块}:{资源}:{方法}`，全部 kebab-case（`admin/app/admin/authorization/service/PermissionCode.php`）。例：`SystemConfig::index` → `system:config:index`，`SystemAdmin::resetPassword` → `authorization:admin:reset-password`，插件 `market` 的 `OrderController::close` → `market:order:close`。
- 前端按钮使用它**实际调用的接口方法**的推导码，例如编辑页的"读取详情"用 `…:detail`、"保存"用 `…:update`，两者分别授权。登录后下发的 `permissions` 就是后端权限码本身，不再有 `webPermissions()` 对照表或别名。
- 不对应单个接口的业务能力使用能力权限码（如 `development:business:generate`），后端用 `AdminAuthorizationService::can('模块:资源:操作')` 判断，前端同码判断。
- 少数按钮一次操作涉及多个方法、或升级前由一个旧权限统一控制的，使用数组表示任一满足（如 AI 会话的 `['ai:ai:conversation-update', 'ai:ai:conversation-state-update', 'ai:ai:conversation-group-update']`），必要时用 `'all'` 要求全部。
- 插件后台方法同样按推导码鉴权，方法上不写权限注解；见 `docs/plugins.md`。
- 动态表单保留后端动作目录、注册能力、版本、可用动作和数据范围限制，公共判断只统一权限匹配。
- 新生成的 CRUD 页面和表单也使用公共判断，后续代码生成不会重新复制局部实现。
- 关闭、取消、返回、折叠等纯界面操作不新增业务权限；登录、安装、本人资料继续走其原有认证流程。

按钮控制用于交互一致性，接口访问仍由后端 RBAC、CSRF、插件激活及业务范围规则校验。本次不新增字段查看或字段编辑授权。
