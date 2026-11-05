# Admin Console 开发文档

> Vue 3 + Element Plus + Vite 8 + Pinia 3 + TypeScript 的中后台管理框架
> 本目录文档面向 **二次开发者**：解释「怎么做一个标准列表页 / 弹窗 / 接入接口 / 改主题 / 加权限按钮」。

---

## 一、文档导航

| 文档 | 说明 | 适合谁看 |
| ---- | ---- | -------- |
| [list-page.md](./list-page.md) | **列表页开发指南**：`PageWrapper` + `SearchForm` + `DataTableShell` + `el-table` + 分页 + CRUD 全流程 | 新增业务模块的人 |
| [form-dialog.md](./form-dialog.md) | **弹窗 / 抽屉 / 表单 / 校验**：新增编辑、批量、权限分配抽屉等模式 | 写表单的人 |
| [components.md](./components.md) | **通用组件 API**：`PageWrapper`、`SearchForm`、`DataTable*`、`Upload`、`RichEditor`、`InlineEdit`、`Echarts` | 调用方 / 维护组件的人 |
| [theme.md](./theme.md) | **主题配置指南**：主色、预设方案、暗黑模式、布局、设置面板、CSS 变量 | 改样式 / 做品牌定制 |
| [api.md](./api.md) | **后端接口契约**：响应结构、分页、Token 刷新、各模块 REST 路径 | 对接后端 |
| [permission.md](./permission.md) | **权限 / 路由 / 菜单**：动态路由、`v-perm`、菜单生成、权限标识 | 做权限模型的人 |
| [i18n-gap.md](./i18n-gap.md) | **i18n 缺失清单**：`pnpm scan:i18n` 自动产出，盘点硬编码中文 | 推进国际化的人 |

---

## 二、约定一句话回顾

- **数据**：业务列表 = `PageWrapper > DataTableShell > el-table + el-pagination`。
- **接口**：所有请求走 `@/utils/http`，自动解包 `data`，code=4001 自动刷新 Token。
- **路由**：业务路由由 **后端菜单驱动**（`/auth/menus`），前端不写业务路由表。
- **权限**：菜单权限走路由生成，按钮权限用 `v-perm="'system:user:add'"`。
- **主题**：改 `useAppStore().setPrimaryColor('#xxxxxx')` 即可全站换色，已派生 light-1~9 / dark-2 / `--el-color-primary-rgb`。
- **文档**：除非明确要求，否则不主动新增 / 改文档；本目录是「源仓库的开发说明」，不是产出物。

---

## 三、典型目录结构

```
src/
├── api/                # 接口模块（与后端 REST 路径一一对应）
│   ├── auth.ts
│   ├── system/
│   │   ├── user.ts
│   │   ├── role.ts
│   │   ├── menu.ts
│   │   └── ...
│   └── index.ts
├── components/         # 通用组件
│   ├── PageWrapper/    # 页面壳：标题 + 主体 + footer
│   ├── SearchForm/     # 行内搜索表单（含「重置 / 查询」）
│   ├── DataTable/      # 表格壳：搜索槽 + 工具栏 + 默认槽
│   ├── Echarts/        # ECharts 5 封装（自动跟随主题色）
│   └── ...
├── views/              # 页面（与 RouteRecordRaw.component 对应）
│   └── system/user/index.vue
├── store/modules/
│   ├── app.ts          # 主题 / 布局 / 偏好
│   ├── user.ts         # token / userInfo / permissions
│   └── permission.ts   # 动态路由 / 后端原始菜单
├── router/
│   ├── routes.ts       # 静态路由（白名单）
│   ├── dynamic.ts      # 后端菜单 → vue-router 转换
│   └── guard.ts        # 路由守卫（鉴权 + 拉菜单 + addRoute）
├── directives/
│   └── permission.ts   # v-perm 按钮权限指令
├── utils/
│   ├── http/           # axios 封装（统一响应 + 刷新 Token）
│   ├── auth.ts         # token 存取
│   └── ...
├── styles/             # 全局样式 + Element Plus 覆盖
└── main.ts
```

---

## 四、版本

| 版本 | 日期 | 说明 |
| ---- | ---- | ---- |
| 0.1.x | 2026-04 | 初版文档（本目录），覆盖列表页、表单、组件、主题、API、权限 |
| 0.2.0 | 2026-04 | 列表页新增 §11 CSV 导入导出（`src/utils/csv.ts`）、§12 行内编辑（`InlineEdit`）；CRUD 抽象 `useCrud`，新增 `system/dept`、`system/dict` 双列页；批量删除全模块拉齐 |
| 0.3.0 | 2026-04 | 通用 `Upload` 组件（单图 / 多图 / 文件） + 演示页 + 上传 API/Mock；操作日志 + 登录日志双页（详情抽屉、批量删、清空）；个人中心改造为四 tab（资料 / 安全 / 密码 / 消息），新增密码强度与消息中心；零依赖 `RichEditor` + 演示页；`vite.config.ts` 函数式 `manualChunks` 路由懒加载分组；Vitest 接入，`useCrud / csv / tree` 单测覆盖；新增 `pnpm scan:i18n` 自动盘点硬编码中文，输出 `docs/i18n-gap.md` |
| 0.3.1 | 2026-04 | 全量 lint：修复 `dashboard` ChartTokens 字段对齐、`DeptFormDialog` el-tree-select props、`dict` 查询 status 类型、`upload` mock FormData 判断；构建警告清零：`Layout` 静态导入统一（routes.ts ↔ dynamic.ts）、`manualChunks` 取消 utils 分组消除循环 chunk、修正 `i-ep-time` → `i-ep-clock`、`i-ep-mobile-phone` → `i-ep-iphone` |
| 0.3.2 | 2026-04 | `RichEditor` 基于 WangEditor v5（`@wangeditor/editor` + `@wangeditor/editor-for-vue`），保留 `v-model`、图片 `uploadApi.upload`、`disabled`、源码与全屏 |
| 0.4.0 | 2026-04 | **依赖全面升级**：TypeScript 5.6→6.0、Vite 5→8（启动 ~3s）、Pinia 2→3、vue-i18n 9→11、ECharts 5→6、@vueuse/core 11→14、Tailwind CSS 4、vitest 2→4、vue-tsc 2→3、unplugin-auto-import 0.18→21、unplugin-vue-components 0.27→32、@iconify/vue 4→5；新增 SortableJS 拖拽示例页 + 路由 |
