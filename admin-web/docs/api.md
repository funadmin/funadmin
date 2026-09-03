# Admin Console 后端接口契约

> 适用版本：Admin Console 0.1.x。本文定义前端期望的通用 REST/RBAC 接口形态。

---

## 一、通用约定

### 1.1 基础信息

| 项 | 值 |
| ---- | ---- |
| 默认 base path | `/admin` |
| 协议 | HTTP / HTTPS |
| 数据格式 | JSON（`Content-Type: application/json`） |
| 认证方式 | Bearer Token（`Authorization: Bearer <accessToken>`） |
| 时间戳 | 秒级 Unix 时间戳 |

### 1.2 统一响应结构

所有接口（包括失败）必须返回如下结构。常规场景下 HTTP status 与 JSON `code` 保持一致；`4001 / 4002` 属于 Token 刷新业务码，可按后端认证方案选择 HTTP `200` 或 `401`。

```jsonc
{
  "code": 200,         // 业务码
  "msg": "success",    // 提示文案
  "data": { ... },     // 业务数据，可为对象 / 数组 / null
  "time": 1700000000   // 服务器时间戳（可选）
}
```

### 1.3 业务码

| code | 含义 | 前端动作 |
| ---- | ---- | ---- |
| `200` | 成功 | 解包 `data` 返回业务 |
| `400` | 普通业务失败 | `ElMessage.error(msg)` |
| `401` | 未登录 / Token 无效 | 清登录态，跳 `/login` |
| `403` | 无权限 | 跳 `/403` |
| `404` | 资源不存在 | 提示资源不存在 |
| `422` | 参数验证失败 | 提示校验失败，可读取 `data` 中的字段错误 |
| `500` | 服务端异常 | 提示服务器内部错误 |
| `4001` | AccessToken 过期 | 自动调 `/auth/refresh` 续期并重放原请求 |
| `4002` | RefreshToken 也过期 | 清登录态，跳 `/login` |

### 1.4 分页协议

**请求**：

```
?page=1&pageSize=20&keyword=xxx
```

**响应**：

```jsonc
{
  "code": 200,
  "msg": "success",
  "data": {
    "list": [ ... ],
    "total": 233,
    "page": 1,
    "pageSize": 20
  }
}
```

### 1.5 批量删除

为了兼容大多数 HTTP 框架对 `DELETE` body 的支持差异，统一走 query string：

```
DELETE /admin/system/user?ids[]=1&ids[]=2&ids[]=3
```

---

## 二、认证模块（`/auth`）

### 2.1 登录

`POST /auth/login`

**Request Body**

```jsonc
{
  "username": "admin",
  "password": "123456",
  "captcha": "1234",        // 可选
  "captchaId": "uuid-xxx"   // 可选
}
```

**Response Data**

```jsonc
{
  "token": "eyJhbGciOi...",
  "refreshToken": "eyJhbGciOi...",
  "expireIn": 7200          // 秒
}
```

### 2.2 退出登录

`POST /auth/logout`

**Response Data**：`null`

### 2.3 刷新 Token

`POST /auth/refresh`

**Request Body**

```jsonc
{ "refreshToken": "eyJhbGciOi..." }
```

**Response Data**：同 `2.1`。

### 2.4 验证码

`GET /auth/captcha`

**Response Data**

```jsonc
{
  "id": "uuid-xxx",
  "img": "data:image/png;base64,..."
}
```

### 2.5 当前用户信息

`GET /auth/me`

**Response Data**

```jsonc
{
  "id": 1,
  "username": "admin",
  "nickname": "超级管理员",
  "avatar": "https://.../avatar.png",
  "email": "admin@example.com",
  "mobile": "13800000000",
  "roles": ["admin"],
  "permissions": [
    "system:user:list",
    "system:user:add",
    "system:user:edit",
    "system:user:remove"
  ]
}
```

### 2.6 当前用户菜单

`GET /auth/menus`

**Response Data**：菜单树数组，结构见 [§ 五、菜单](#五菜单模块systemmenu)。

---

## 三、用户模块（`/system/user`）

### 3.1 列表

`GET /system/user?page=1&pageSize=20&keyword=&status=`

**Response Data**：分页结构，list 项为 `UserModel`。

### 3.2 详情

`GET /system/user/:id`

### 3.3 新增

`POST /system/user`

**Request Body**

```jsonc
{
  "username": "alice",
  "password": "123456",
  "nickname": "Alice",
  "email": "alice@example.com",
  "mobile": "13900000000",
  "deptId": 1,
  "roleIds": [2, 3],
  "status": 1
}
```

### 3.4 修改

`PUT /system/user/:id`

Body 同 `3.3`，无 `password`。

### 3.5 删除（支持批量）

`DELETE /system/user?ids[]=1&ids[]=2`

### 3.6 重置密码

`POST /system/user/:id/reset-password`

```jsonc
{ "password": "new-pwd" }
```

### 3.7 切换状态

`POST /system/user/:id/status`

```jsonc
{ "status": 0 }  // 0 禁用 / 1 启用
```

### 3.8 UserModel

```ts
interface UserModel {
  id: number;
  username: string;
  nickname: string;
  email?: string;
  mobile?: string;
  status: 0 | 1;
  roleIds?: number[];
  deptId?: number;
  createdAt?: string;
  updatedAt?: string;
}
```

---

## 四、角色模块（`/system/role`）

### 4.1 列表

`GET /system/role?page=1&pageSize=20&keyword=`

### 4.2 全部（不分页，用于下拉）

`GET /system/role/all`

### 4.3 详情

`GET /system/role/:id`

### 4.4 新增

`POST /system/role`

```jsonc
{
  "name": "运营",
  "code": "operation",
  "remark": "运营人员",
  "status": 1,
  "menuIds": [1, 2, 3, 100, 101]
}
```

### 4.5 修改

`PUT /system/role/:id`，body 同 `4.4`。

### 4.6 删除

`DELETE /system/role?ids[]=1&ids[]=2`

### 4.7 分配菜单

`POST /system/role/:id/menus`

```jsonc
{ "menuIds": [1, 2, 3, 100, 101] }
```

### 4.8 RoleModel

```ts
interface RoleModel {
  id: number;
  name: string;
  code: string;
  remark?: string;
  status: 0 | 1;
  menuIds?: number[];
  createdAt?: string;
}
```

---

## 五、菜单模块（`/system/menu`）

### 5.1 树形菜单

`GET /system/menu/tree`

**Response Data**：`MenuItem[]`。

### 5.2 详情

`GET /system/menu/:id`

### 5.3 新增

`POST /system/menu`

```jsonc
{
  "parentId": 0,
  "name": "system",
  "path": "/system",
  "component": "Layout",
  "type": "M",
  "title": "系统管理",
  "icon": "i-ep-setting",
  "sort": 100,
  "hidden": false,
  "keepAlive": false,
  "affix": false,
  "permission": ""
}
```

### 5.4 修改

`PUT /system/menu/:id`，body 同 `5.3`。

### 5.5 删除

`DELETE /system/menu/:id`

### 5.6 MenuItem

```ts
interface MenuItem {
  id: number;
  parentId: number;
  name: string;          // route name，唯一
  path: string;          // 顶层 '/xxx'，子层 'xxx'
  component?: string;    // 'Layout' 或 'system/user/index'
  redirect?: string;
  type: 'M' | 'C' | 'B'; // 目录 / 页面 / 按钮
  icon?: string;         // 'i-ep-xxx' 或自定义 svg name
  title: string;
  sort: number;
  hidden: boolean;
  keepAlive: boolean;
  affix: boolean;
  permission?: string;   // 仅 type=B 时使用，如 'system:user:add'
  children?: MenuItem[];
}
```

`children` 可**递归嵌套**，支持**三级及以上**菜单：顶层目录仍挂主 `Layout`；子级目录在路由层使用 `Blank`（仅 `router-view`）承载子路由，叶子节点填写 `component` 指向页面。若中间目录未配置 `redirect`，前端会按 `sort` 自动跳到**第一个可见后代叶子**。

### 5.7 type 字段语义

| type | 含义 | 路由表现 |
| ---- | ---- | ---- |
| `M` | 目录 | **顶层**挂主 `Layout`；**嵌套的 M** 使用 `Blank` + 子路由；子 path 为相对段（如 `user`、`sub/leaf`） |
| `C` | 页面 | 异步组件（`src/views/${component}.vue`） |
| `B` | 按钮 | **不生成路由**，仅放进 `permissions` 给 `v-perm` 用 |

> ⚠️ 顶层 `type=C` 的页面前端会自动包一层 Layout，确保侧栏 / 标签页正常工作；后端**无需**做特殊处理。

### 5.8 与传统控制器式菜单如何对齐

后台常见两种「路由」，与 Admin Console 的用法不同，需要**在接口层做映射**，不要把控制器动作地址原样当 Vue 的 `path` 使用。

| 概念 | 传统后台典型形态 | Admin Console 需要的形态 |
| ---- | ------------------ | --------------------- |
| 菜单入口 | 控制器/方法风格地址，如 `auth.user/index` | `path` 为 **浏览器 URL 段**，如 `/system/user`；`component` 为 **视图文件**，如 `system/user/index` |
| 按钮 | 同一表里 `type=0` 的非菜单节点，或独立权限点 | `type: 'B'` + `permission`，如 `system:user:add` |
| 权限列表 | 规则 id 集合或权限码集合 | `GET /auth/me` 的 `permissions` 为 **字符串标识**（与 `v-perm`、菜单里 `permission` 一致） |
| HTTP 接口 | 框架路由或控制器路径 | 本文档 **§ 三～七** 的 REST 路径（如 `GET /system/user`），与菜单 `path` **不是同一套字符串** |

**推荐对接方式：**

1. **菜单树 `/auth/menus`、`/system/menu/tree`**：后端为 SPA 单独组装 `MenuItem`（或存扩展字段），把传统菜单**映射**为下表逻辑：
   - `name`：唯一路由名，可用 `PascalCase`（如 `SystemUser`），勿与 `href` 混用。
   - `path`：与前端路由一致（顶层 `/system`，子级 `user`）。
   - `component`：对应 `src/views` 下路径，无 `.vue` 后缀（见 `router/dynamic.ts` 的 `resolveComponent`）。
   - 按钮节点：`type: 'B'`，`permission` 填与 `GET /auth/me` 相同的标识。
2. **权限 `/auth/me` → `permissions`**：由后端把「当前用户拥有的规则」解析成**字符串数组**（或超管 `*:*:*`）。若库内只有规则数字 id，需要 **id → permission 字符串** 的映射表（可与菜单表同库维护）。
3. **REST API**：接口路径按本文档即可；与旧系统「控制器 URL」可能不同，以**本文档 + 前端 `VITE_APP_BASE_API`** 为准做反向代理或网关统一前缀。

**小结**：Layui 的 `href` 与 Vue 的 `path`/`component` **一一手工或表驱动映射**；权限从「规则 id 集合」映射到「`module:resource:action` 风格字符串」后，即可与 `v-perm` 和本文档一致。

---

## 六、部门模块（`/system/dept`）

### 6.1 树形

`GET /system/dept/tree` → `DeptModel[]`

### 6.2 CRUD

| 操作 | 方法 | 路径 |
| ---- | ---- | ---- |
| 详情 | GET | `/system/dept/:id` |
| 新增 | POST | `/system/dept` |
| 修改 | PUT | `/system/dept/:id` |
| 删除 | DELETE | `/system/dept/:id` |

### 6.3 DeptModel

```ts
interface DeptModel {
  id: number;
  parentId: number;
  name: string;
  sort: number;
  status: 0 | 1;
  leader?: string;
  phone?: string;
  email?: string;
  children?: DeptModel[];
}
```

---

## 七、字典模块（`/system/dict`）

### 7.1 单个字典选项

`GET /system/dict/:code/options`

**Response Data**

```jsonc
[
  { "label": "启用", "value": 1, "status": 1, "cssClass": "success" },
  { "label": "禁用", "value": 0, "status": 1, "cssClass": "danger" }
]
```

### 7.2 批量字典

`POST /system/dict/batch`

```jsonc
{ "codes": ["user_status", "gender"] }
```

**Response Data**

```jsonc
{
  "user_status": [ { "label": "启用", "value": 1 }, ... ],
  "gender": [ { "label": "男", "value": 1 }, ... ]
}
```

---

## 八、错误响应示例

### 8.1 业务失败

```jsonc
{ "code": 400, "msg": "用户名或密码错误", "data": null }
```

### 8.2 参数验证失败

```jsonc
{
  "code": 422,
  "msg": "参数验证失败",
  "data": {
    "username": ["用户名不能为空"]
  }
}
```

### 8.3 未登录

```jsonc
{ "code": 401, "msg": "请先登录", "data": null }
```

### 8.4 Token 过期

```jsonc
{ "code": 4001, "msg": "Token 已过期", "data": null }
```

前端会**自动**调 `/auth/refresh` 拿新 token 后**重放**原始请求；并发请求会合并到同一个刷新流程，避免重复刷新。

### 8.5 无权限

```jsonc
{ "code": 403, "msg": "无访问权限", "data": null }
```

### 8.6 服务端异常

```jsonc
{ "code": 500, "msg": "服务器内部错误", "data": null }
```

---

## 九、前端调用示例

```ts
import { authApi } from '@/api/auth';
import { userApi } from '@/api/system/user';

// 登录
const { token, refreshToken, expireIn } = await authApi.login({
  username: 'admin',
  password: '123456'
});

// 列表
const { list, total } = await userApi.list({ page: 1, pageSize: 20, keyword: '' });

// 删除
await userApi.remove([1, 2, 3]);
```

---

## 十、变更记录

| 版本 | 日期 | 说明 |
| ---- | ---- | ---- |
| 0.1.0 | 2026-04 | 初版，定义通用 REST/RBAC 接口风格 |
| 0.1.1 | 2026-04 | §5.8 补充传统控制器式路由与 MenuItem、权限的映射说明 |
| 0.1.2 | 2026-04 | 配套拆分 `list-page.md / form-dialog.md / components.md / theme.md / permission.md`，统一通过 [`index.md`](./index.md) 索引 |

---

## 十一、相关文档

- [`index.md`](./index.md) — 全部文档总索引
- [`list-page.md`](./list-page.md) — 列表页标准骨架 + CRUD 生命周期
- [`form-dialog.md`](./form-dialog.md) — 弹窗 / 抽屉 / 表单 / 校验
- [`components.md`](./components.md) — 通用组件 API（PageWrapper / SearchForm / DataTableShell / Echarts / v-perm）
- [`theme.md`](./theme.md) — 主题、主色、预设、暗黑、按钮焦点态规则
- [`permission.md`](./permission.md) — 菜单驱动动态路由 + 按钮权限
