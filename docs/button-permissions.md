# 后台按钮权限

后台操作统一使用 `web/src/shared/utils/permission.ts`，权限来源是当前账号登录后下发的 `permissions`。前端不自行把菜单查看权限扩大为新增、修改或删除权限。

## 公共判断

```ts
import { hasPermission } from '@/utils/permission';

// 单个动作。
hasPermission('system:user:edit');

// 默认满足任一权限。
hasPermission(['system:user:add', 'system:user:edit']);

// 必须同时满足全部权限。
hasPermission(['development:business:generate', 'development:business:apply-resources'], 'all');
```

`hasPermission()` 每次读取当前账号权限，可用于模板、computed 和事件处理器。`*`、`*:*:*` 是现有超级权限，其余权限精确匹配。`undefined`、`null`、空字符串表示可选权限未声明；需要保护的业务动作必须传入真实权限码。

纯 Schema 执行器已经持有明确权限上下文时，使用 `checkPermission(context.permissions, required, mode)` 复用相同规则；不要再复制 `permissions.some/includes` 判断。

## 按钮与操作入口

```vue
<el-button v-perm="'system:user:add'" @click="create">新增</el-button>
<el-button v-perm:all="['action:execute', 'action:approve']" @click="execute">执行</el-button>
<el-switch :disabled="!hasPermission('system:user:status')" />
```

`v-perm` 随权限授予、撤销和账号权限替换更新显示；重新授权后能恢复显示，并在组件卸载时释放监听。

业务入口同样检查权限，尤其是在确认弹窗、表单异步校验、读取文件等等待步骤之后、发请求之前重新检查：

```ts
async function remove() {
  if (!hasPermission('system:user:delete')) return;
  await confirmRemoval();
  if (!hasPermission('system:user:delete')) return;
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
    add: 'system:user:add',
    edit: 'system:user:edit',
    delete: 'system:user:delete'
  }
});
```

`permissions` 是可选配置，兼容已有由动态动作宿主管理权限的页面。声明后，新增/编辑入口和删除确认前后均检查对应权限。自定义保存、启停、上传等动作仍在其实际处理器检查。

## 权限码来源与范围

- 系统业务别名由 `admin/app/admin/authorization/model/Permission.php::webPermissions()` 下发；该方法保留原始动作码。
- 多级控制器的原始动作码依据 `PermissionResource::fromRequest/fromParts()`，包含模块命名空间；不要从菜单标题或 URL 随意拼接。
- 插件后台方法以 `#[Permission('code')]` 声明权限码，码须在插件清单注册；未声明的方法一律拒绝。市场插件已逐方法声明，不再依赖中间件内的动作映射。
- 动态表单保留后端动作目录、注册能力、版本、可用动作和数据范围限制，公共判断只统一权限匹配。
- 新生成的 CRUD 页面和表单也使用公共判断，后续代码生成不会重新复制局部实现。
- 关闭、取消、返回、折叠等纯界面操作不新增业务权限；登录、安装、本人资料继续走其原有认证流程。

按钮控制用于交互一致性，接口访问仍由后端 RBAC、CSRF、插件激活及业务范围规则校验。本次不新增字段查看或字段编辑授权。
