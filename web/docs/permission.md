# 权限 / 路由 / 菜单

> 项目权限模型分三层：**登录态（token）→ 路由权限（菜单驱动）→ 按钮权限（v-perm 指令）**。
> 全部走「**后端菜单 → 前端动态路由**」生成，前端不维护硬编码菜单。

---

## 一、整体流程

```
登录 (POST /auth/login)
  → 拿到 token，写 cookie/localStorage
  → router.beforeEach 触发
      → 已有 token 但 permissionStore.mounted == false
      → fetchUserInfo()       拿 roles / permissions（按钮权限标识）
      → fetchMenus()          拿菜单树
      → generateRoutes()      菜单树转 RouteRecordRaw
      → router.addRoute()     一条条挂到顶层
      → 注册 NotFound 通配（必须最后挂，否则会兜住业务路由）
      → next({ path: to.fullPath, replace: true }) 重新进入
  → 业务页面渲染
      → 模板里 v-perm="'system:user:add'" 控制按钮显隐
```

涉及文件：

| 文件 | 职责 |
| ---- | ---- |
| [`src/router/index.ts`](../src/router/index.ts) | createRouter + 注册 staticRoutes |
| [`src/router/routes.ts`](../src/router/routes.ts) | 静态路由（登录、Dashboard、Profile、403/500、Redirect） |
| [`src/router/dynamic.ts`](../src/router/dynamic.ts) | 后端菜单树 → vue-router 路由 |
| [`src/router/guard.ts`](../src/router/guard.ts) | 全局守卫，登录 / 拉菜单 / addRoute / NotFound |
| [`src/store/modules/user.ts`](../src/store/modules/user.ts) | token、userInfo、roles、permissions |
| [`src/store/modules/permission.ts`](../src/store/modules/permission.ts) | rawMenus、dynamicRoutes、mounted、reset |
| [`src/directives/permission.ts`](../src/directives/permission.ts) | `v-perm` 按钮指令 |

---

## 二、菜单数据结构（后端契约）

`API.MenuItem`（详见 [api.md](./api.md)）字段速查：

| 字段 | 类型 | 说明 |
| ---- | ---- | ---- |
| `id` | `number/string` | 主键 |
| `parentId` | `number/string` | 父级 id（顶层为 0/null） |
| `name` | `string` | **路由 name**，全站唯一 |
| `path` | `string` | 路由 path，可以以 `/` 开头（顶层）或不带 `/`（子级，自动拼父路径） |
| `component` | `string` | 见下方解析规则 |
| `redirect` | `string?` | 中间目录默认重定向；不填时自动跳第一个可见叶子 |
| `title` | `string` | 菜单显示名称 |
| `icon` | `string` | iconify 图标 class，如 `i-ep-user` |
| `type` | `'M' \| 'C' \| 'B'` | M=目录、C=菜单、B=按钮 |
| `permission` | `string?` | 按钮权限标识（type=B 时必填，如 `system:user:add`） |
| `hidden` | `boolean` | 不显示到侧栏，但路由有效 |
| `keepAlive` | `boolean` | 是否启用 KeepAlive 缓存 |
| `sort` | `number` | 同级排序，升序 |
| `children` | `MenuItem[]` | 子级 |

> **type 必须严格区分**：
> - `M` 目录 → 包 Layout / Blank，不挂业务组件；
> - `C` 菜单 → 挂 `component` 指向的 Vue 文件；
> - `B` 按钮 → 不进路由，只参与 `permissions[]` 收集（用于 `v-perm`）。

### `component` 字段解析规则

写在 [`dynamic.ts:resolveComponent`](../src/router/dynamic.ts)：

| 后端值 | 前端解析 |
| ----- | ------- |
| 空 / `'Layout'` | `@/layout/index.vue`（主布局壳） |
| `'Blank'` | `@/layout/blank.vue`（多级菜单空白壳） |
| `'system/user/index'` | `@/views/system/user/index.vue` |
| `'/views/system/user/index.vue'` | 同上（兼容写法） |

> ⚠️ **后端 component 字段一定要和 `src/views/**/*.vue` 路径对得上**。组件解析失败会 fallback 到 404 页面，控制台 `[router] 未找到组件: xxx`。

---

## 三、动态路由生成规则

[`generateRoutes()`](../src/router/dynamic.ts) 的产物形态：

### 3.1 顶层目录（M 或有 children）

```ts
{
  path: '/system',
  name: 'System',
  component: Layout,
  redirect: '/system/user', // 没填则自动取第一个可见叶子
  meta: { title: '系统管理', icon: 'i-ep-setting', ... },
  children: [
    { path: 'user', name: 'SystemUser', component: () => import('@/views/system/user/index.vue'), ... },
    { path: 'role', name: 'SystemRole', component: () => import('@/views/system/role/index.vue'), ... }
  ]
}
```

### 3.2 顶层就是单页面（type: 'C'）

会**自动套一层 Layout**，本身作为空 path 子路由：

```ts
{
  path: '/about',
  component: Layout,
  meta: { title: '关于', ... },
  children: [
    { path: '', name: 'About', component: AboutPage, meta: { activeMenu: '/about', ... } }
  ]
}
```

### 3.3 多级嵌套（>= 3 层）

中间层用 `Blank` 占位，自动 redirect 到第一个叶子；详见 `findFirstLeafRedirectPath`。

---

## 四、按钮权限 `v-perm`

源码：[`src/directives/permission.ts`](../src/directives/permission.ts)

### 用法

```vue
<!-- 单权限 -->
<el-button type="primary" plain v-perm="'system:user:add'" @click="onAdd">新增</el-button>

<!-- 任意一个匹配即可 -->
<el-button v-perm="['system:user:edit', 'system:user:add']">编辑/新增</el-button>

<!-- 必须全部匹配（注意指令参数 :all） -->
<el-button v-perm:all="['order:export', 'order:read']">导出</el-button>
```

### 行为

- 无权限时**直接从 DOM 移除**（`el.parentNode?.removeChild(el)`），不留占位，不挤布局。
- `permissions` 数组里包含 `*` 或 `*:*:*` 视为**超级权限**，全通过。
- 指令在 `mounted` 和 `updated` 都会重判，权限实时变更（如切角色）也会响应。

### 常见权限命名

```
{module}:{resource}:{action}

system:user:list      用户列表查询
system:user:add       用户新增
system:user:edit      用户编辑
system:user:delete    用户删除
system:user:reset-pwd 重置密码
system:role:assign    角色分配
order:export          订单导出
```

> 命名一定要和**后端菜单 type=B 的 `permission` 字段**保持一致，否则前端 `v-perm` 会判失败。

---

## 五、路由守卫细节（坑点合集）

[`src/router/guard.ts`](../src/router/guard.ts)

```ts
router.beforeEach(async (to, from, next) => {
  // 1. 进度条 + 改 document.title
  // 2. 取 token，无 token 跳登录（白名单除外）
  // 3. 已登录访问 /login -> 跳 /dashboard
  // 4. 已挂载动态路由 -> next()
  // 5. 否则：fetchUserInfo + fetchMenus + addRoute + 注册 NotFound
  //    最后用 next({ path: to.fullPath, replace: true }) 重进
});
```

### 易错点

1. **`NotFound` 必须在动态路由 `addRoute` 之后再注册**。
   早注册会变成「只要顶层路由没匹配上就被 NotFound 兜走」，业务路由全部跳 404。

2. **重进入用 `path: to.fullPath`，不要用 `...to` 展开**。
   首次拉路由时 `to` 已被解析为 `NotFound`，spread 会带着 `name: 'NotFound'`，导致即便重进还是 404。

3. **`permissionStore.reset()` 必须卸载已挂载路由**。
   切换账号 / 重新登录时不卸载，老菜单残留 → 新账号能看到不该看的页面。

4. **白名单 `ROUTE_WHITELIST`** 在 `src/config/index.ts` 维护，登录 / 注册 / 找回密码等公共页放这里。

---

## 六、面包屑 / 标签页 / 缓存怎么联动？

| 行为 | 来源 | 实现 |
| ---- | ---- | ---- |
| **侧栏菜单** | `permissionStore.menus`（getter） | layout 渲染 `el-menu`，过滤 `meta.hidden` |
| **面包屑** | 当前 `route.matched` | `Breadcrumb` 组件递归 matched，跳过 `meta.hidden` |
| **标签页** | 路由进入后 push tab | tabs store + `meta.affix`（固定不可关闭）|
| **KeepAlive** | `meta.keepAlive` | RouterView 包 `<keep-alive :include="cachedNames">`，names 来自 store |
| **当前激活菜单** | `route.meta.activeMenu` | 详情页用 `activeMenu` 指回列表页 path |

### 详情页 / 编辑页 不在菜单里？

后端菜单**不要**挂 `/order/detail/:id`。前端在静态/业务页面里自由跳即可，但 `meta` 写：

```ts
meta: {
  title: '订单详情',
  hidden: true,            // 不出现在侧栏
  activeMenu: '/order/list' // 进入详情时仍高亮「订单列表」菜单
}
```

---

## 七、登录 / 登出最小代码

```ts
import { useUserStore } from '@/store/modules/user';
import { usePermissionStore } from '@/store/modules/permission';
import { useRouter } from 'vue-router';

const userStore = useUserStore();
const permissionStore = usePermissionStore();
const router = useRouter();

async function onLogin() {
  await userStore.login({ username: 'admin', password: 'xxx', captcha: '...' });
  // 不需要手动拉菜单，guard.beforeEach 会处理
  router.push({ path: '/dashboard' });
}

async function onLogout() {
  await userStore.logout();
  permissionStore.reset();
  router.push({ path: '/login' });
}
```

---

## 八、新增一个业务模块的标准步骤

1. **后端**：在菜单管理新增菜单（type=M 目录 + type=C 菜单 + type=B 按钮）。
   - `path`、`name`、`component` 严格按约定填；
   - 按钮型菜单的 `permission` 写规范的三段式（如 `order:list:export`）。
2. **前端**：在 `src/views/<模块>/<页面>/index.vue` 创建对应文件。
3. **前端**：在 `src/api/<模块>.ts` 写接口模块（参考 [api.md](./api.md)）。
4. **页面模板**：照抄 [list-page.md](./list-page.md) 骨架，业务按钮挂 `v-perm`。
5. 重新登录或刷新 → 守卫自动拉新菜单 → 路由生效，无需重启 dev server。

---

## 九、常见问题排查

| 现象 | 排查 |
| ---- | ---- |
| 登录成功但页面一直 404 | 后端 `component` 字段写错 → 控制台搜 `[router] 未找到组件` |
| 切角色后还能看到老菜单 | `permissionStore.reset()` 没调到 / 或 `mounted` 没置 false |
| 按钮始终显示 / 始终不显示 | 排查 `userStore.permissions` 是否拿到了 / `v-perm` 字符串是否拼错 |
| 侧栏出现重复菜单 | 后端菜单树有重复 `name` / 静态路由和动态路由 `name` 冲突 |
| 进入详情页后侧栏菜单没有高亮 | 详情路由 `meta.activeMenu` 没指向列表 path |
| 刷新页面后白屏 | 查询接口报错 → guard 走到 catch → 跳登录；看 Network |
