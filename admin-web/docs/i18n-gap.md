# i18n 缺失清单

> 自动生成自 `scripts/scan-i18n.mjs`，命中文件 **42** 个，去重短语共 **495** 条。
> 已跳过：`src/locales/**`、`src/mock/**`、`*.d.ts`、`*.spec.ts`、`__tests__/**` 与所有注释。
> 仅作"应当 i18n 但还没接入 vue-i18n"的盘点参考，不代表所有命中都必须改造。

## 现状

- 已有 i18n key：仅 `menu / layout / login` 三个命名空间（见 `src/locales/zh-CN.ts`）。
- 业务页面、通用组件、`ElMessage` / `ElMessageBox` 文案、表单校验提示几乎全部为硬编码中文。
- 推荐渐进式策略：
  1. **第 1 步（建议立刻做）**：把高频通用文案抽到 `common` 命名空间（确定/取消/操作/提示/成功/失败/请输入/请选择/重置/查询/新增/编辑/删除/批量删除/导入/导出/搜索/状态/启用/禁用）。
  2. **第 2 步**：按"系统管理"模块逐页改造（`system.user.*` / `system.role.*` / `system.menu.*` …）。
  3. **第 3 步**：把 `useCrud` / `DataTableShell` 等基础设施内部的 `ElMessage` 文案接入 `common`。

---

## 命中明细（按一级目录分组）

### `src/views/` — 19 个文件 / 366 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/views/dashboard/index.vue` | 80 | `欢迎回到`，`待办`，`消息`，`完成率`，`较上周`，`访问趋势`，`周日`，`周一` … |
| `src/views/profile/index.vue` | 30 | `用户名`，`手机`，`邮箱`，`最近登录`，`个人中心`，`管理你的基本信息、安全设置、密码与消息`，`热爱代码，热爱生活。`，`请输入昵称` … |
| `src/views/system/user/index.vue` | 30 | `用户管理`，`管理系统用户、分配角色与部门`，`账号`，`账号 / 昵称`，`状态`，`请选择`，`启用`，`禁用` … |
| `src/views/system/log/operation.vue` | 26 | `操作日志`，`记录系统关键操作（增/删/改）的执行情况与耗时`，`账号`，`操作账号`，`模块`，`全部模块`，`状态`，`全部` … |
| `src/views/system/menu/components/MenuFormDialog.vue` | 21 | `目录`，`菜单`，`按钮`，`类型`，`上级菜单`，`顶级`，`名称`，`显示名称` … |
| `src/views/system/user/components/UserFormDialog.vue` | 19 | `启用`，`禁用`，`取消`，`确定`，`账号`，`请输入账号`，`昵称`，`请输入昵称` … |
| `src/views/system/menu/index.vue` | 18 | `菜单管理`，`维护后端菜单与按钮权限`，`菜单名称`，`请输入菜单名称`，`菜单路由`，`请输入菜单路由`，`状态`，`请选择` … |
| `src/views/system/dept/components/DeptFormDialog.vue` | 17 | `启用`，`禁用`，`取消`，`确定`，`上级部门`，`不选则为顶级部门`，`部门名称`，`请输入部门名称` … |
| `src/views/system/dict/components/DictItemFormDialog.vue` | 17 | `启用`，`禁用`，`取消`，`确定`，`所属分类`，`请先在左侧选择分类`，`字典标签`，`如：男` … |
| `src/views/system/log/login.vue` | 17 | `登录日志`，`记录所有登录尝试，便于安全审计与异常排查`，`账号`，`登录账号`，`状态`，`全部`，`成功`，`失败` … |
| `src/views/system/role/index.vue` | 15 | `角色管理`，`维护角色及其权限`，`角色名称`，`请输入角色名称`，`标识`，`请输入标识`，`状态`，`请选择` … |
| `src/views/system/role/components/RoleFormDialog.vue` | 14 | `启用`，`禁用`，`取消`，`确定`，`名称`，`角色名称`，`标识`，`英文标识，如 admin / editor` … |
| `src/views/system/dept/index.vue` | 13 | `部门管理`，`维护组织架构与负责人信息`，`部门名称`，`请输入部门名称`，`状态`，`请选择`，`启用`，`禁用` … |
| `src/views/system/dict/components/DictTypeFormDialog.vue` | 13 | `启用`，`禁用`，`取消`，`确定`，`字典名称`，`如：用户性别`，`字典编码`，`如：sys_user_sex（建议小写下划线）` … |
| `src/views/system/dict/index.vue` | 13 | `字典管理`，`维护通用字典分类与字典项`，`字典名称`，`请输入名称`，`编码`，`请输入编码`，`确认删除分类「${row.name}」？该操作会一并删除其下所有字典项`，`提示` … |
| `src/views/error/components/ErrorPage.vue` | 8 | `返回首页`，`上一页`，`无权访问`，`抱歉，你没有访问该页面的权限`，`页面不存在`，`你访问的资源已被移除或暂时不可用`，`服务异常`，`服务器开了点小差，稍后再试` |
| `src/views/system/rich-editor-demo/index.vue` | 6 | `基础用法`，`加载示例`，`清空`，`富文本编辑器`，`零依赖、基于 contenteditable 的轻量富文本，支持图片上传 / 源码 / 全屏`，`<h2>这是一段只读内容</h2><p>当 <code>disabled</code> 为 true 时，工具栏与编辑区均不可交互。</p>` |
| `src/views/system/role/components/RolePermDrawer.vue` | 6 | `展开全部`，`折叠全部`，`全选`，`取消`，`父子独立`，`保存` |
| `src/views/system/upload-demo/index.vue` | 3 | `单图上传（image）`，`通用上传演示`，`单图 / 多图 / 文件 三模式 + 校验 + 业务分类` |

### `src/components/` — 10 个文件 / 75 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/components/RichEditor/index.vue` | 26 | `请输入内容…`，`撤销`，`重做`，`加粗`，`斜体`，`下划线`，`删除线`，`标题 1` … |
| `src/components/Setting/index.vue` | 25 | `主题方案`，`布局模式`，`布局设置`，`默认`，`卡片`，`简约`，`胶囊`，`渐隐` … |
| `src/components/MenuSearch/index.vue` | 7 | `选择`，`跳转`，`关闭`，`搜索菜单（标题 / 路径）`，`未找到匹配菜单`，`仪表盘`，`个人中心` |
| `src/components/DataTable/DataTableToolbar.vue` | 5 | `紧凑`，`默认`，`宽松`，`刷新`，`密度` |
| `src/components/Notification/index.vue` | 3 | `消息`，`通知`，`待办` |
| `src/components/Upload/index.vue` | 3 | `点击上传`，`文件 ${file.name} 超过 ${props.maxSize}MB`，`上传失败` |
| `src/components/InlineEdit/index.vue` | 2 | `请输入`，`保存失败` |
| `src/components/SearchForm/index.vue` | 2 | `重置`，`查询` |
| `src/components/Captcha/index.vue` | 1 | `点击刷新` |
| `src/components/IconSelect/index.vue` | 1 | `选择图标` |

### `src/layout/` — 6 个文件 / 13 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/layout/components/Tabs.vue` | 8 | `刷新当前`，`关闭其他`，`关闭左侧`，`关闭右侧`，`关闭全部`，`更多`，`刷新`，`关闭` |
| `src/layout/components/Breadcrumb.vue` | 1 | `首页` |
| `src/layout/components/ColumnsRail.vue` | 1 | `仪表盘` |
| `src/layout/components/Sidebar.vue` | 1 | `仪表盘` |
| `src/layout/components/TopMenu.vue` | 1 | `仪表盘` |
| `src/layout/index.vue` | 1 | `仪表盘` |

### `src/composables/` — 1 个文件 / 4 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/composables/useCrud.ts` | 4 | `确认删除该记录？此操作不可恢复`，`提示`，`请至少选择一项`，`确认删除选中的 ${selection.value.length} 条？此操作不可恢复` |

### `src/router/` — 3 个文件 / 8 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/router/routes.ts` | 5 | `登录`，`仪表盘`，`个人中心`，`无权访问`，`服务异常` |
| `src/router/guard.ts` | 2 | `页面不存在`，`获取用户权限失败，请重新登录` |
| `src/router/dynamic.ts` | 1 | `[router] 未找到组件: ${target}，请检查后端菜单 component 字段` |

### `src/store/` — 2 个文件 / 15 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/store/modules/app.ts` | 14 | `雅致`，`浅色 + 清新蓝`，`清新`，`浅色 + 青蓝`，`经典`，`深色侧栏 + 蓝`，`极简`，`浅色 + 石墨` … |
| `src/store/modules/tabs.ts` | 1 | `仪表盘` |

### `src/utils/` — 1 个文件 / 14 条短语

| 文件 | 命中 | 示例短语 |
| ---- | ---: | ---- |
| `src/utils/http/index.ts` | 14 | `请求失败`，`错误提示`，`网络异常`，`登录已失效，请重新登录`，`没有访问权限`，`请求资源不存在`，`服务器内部错误`，`网关错误` … |

---

## 建议补齐的 `common` 命名空间（草案）

```ts
// src/locales/zh-CN.ts
common: {
  ok: '确定', cancel: '取消', confirm: '确认', tip: '提示',
  success: '成功', failed: '失败', loading: '加载中',
  search: '查询', reset: '重置', refresh: '刷新',
  add: '新增', edit: '编辑', remove: '删除', batchRemove: '批量删除',
  import: '导入', export: '导出', upload: '上传', download: '下载',
  status: '状态', enable: '启用', disable: '禁用',
  pleaseInput: '请输入', pleaseSelect: '请选择',
  operation: '操作', detail: '详情', clear: '清空',
  saveSuccess: '保存成功', deleteSuccess: '删除成功',
  deleteConfirm: '确定删除该记录吗？', batchDeleteConfirm: '确定批量删除选中记录吗？'
}
```

改造时可优先把 `useCrud.ts` 内部的成功/失败 toast、`SearchForm` 的"查询/重置"按钮、`DataTableToolbar` 的工具栏标签接入 `common`，单点改动即可惠及所有列表页。
