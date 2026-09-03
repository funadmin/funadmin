# 通用组件 API

> 本文记录 `src/components/` 下被业务页面**反复使用**的组件，列出 Props / Slots / Emits 与最佳实践。
> 没列入此文档的组件，要么是局部使用，要么需要直接查源码。

---

## 一、`PageWrapper` — 页面壳

源码：[`src/components/PageWrapper/index.vue`](../src/components/PageWrapper/index.vue)

提供**统一的页面卡片布局**：标题行 + 主体 + 底部 footer。所有业务列表 / 详情 / 表单页都应放在 `PageWrapper` 内。

### Props

| 名称 | 类型 | 默认 | 说明 |
| ---- | ---- | ---- | ---- |
| `title` | `string` | — | 页面主标题，15px / 600 |
| `subtitle` | `string` | — | 副标题，13px / secondary 色 |

### Slots

| 名称 | 说明 |
| ---- | ---- |
| `default` | 主体内容（表格 / 表单 / 图表） |
| `header` | 完全自定义标题区，传入后 `title/subtitle` 失效 |
| `extra` | 标题右侧操作区（如「返回」/「全屏」/「下载模板」） |
| `footer` | 底部固定区（一般放分页、批量操作汇总） |

### 示例

```vue
<PageWrapper title="用户管理" subtitle="管理后台所有账户">
  <template #extra>
    <el-button plain @click="onExport">导出</el-button>
  </template>

  <DataTableShell ...>...</DataTableShell>

  <template #footer>
    <el-pagination ... />
  </template>
</PageWrapper>
```

> **约定**：分页可以用 `<div class="app-pagination-bar">` 直接放在默认槽底部，也可以走 `#footer` 槽，二选一项目内保持一致即可。

---

## 二、`SearchForm` — 行内搜索表单

源码：[`src/components/SearchForm/index.vue`](../src/components/SearchForm/index.vue)

封装内联表单 + 「重置 / 查询」两个按钮（plain primary 样式，全站统一）。

### Props

| 名称 | 类型 | 默认 | 说明 |
| ---- | ---- | ---- | ---- |
| `model` | `Recordable` | **必填** | 父级 `reactive(query)` 引用，子组件直接 mutate 同步生效 |
| `loading` | `boolean` | `false` | 查询按钮 loading 态 |
| `labelWidth` | `string \| number` | `'80px'` | `el-form` label-width |

### Emits

| 事件 | 载荷 | 说明 |
| ---- | ---- | ---- |
| `search` | `Recordable` | 用户点了「查询」或回车提交 |
| `reset` | — | 用户点了「重置」（保留 `page`、`pageSize`，其他清空） |

### Slots

| 名称 | 作用域 | 说明 |
| ---- | ------ | ---- |
| `default` | `{ model }` | 行内 `el-form-item` 们 |
| `extra` | — | 在「重置 / 查询」之后追加按钮（如「高级搜索」） |

### 示例

```vue
<SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
  <el-form-item label="账号" prop="username">
    <el-input v-model="query.username" clearable />
  </el-form-item>
  <el-form-item label="状态" prop="status">
    <el-select v-model="query.status" clearable placeholder="全部">
      <el-option label="启用" :value="1" />
      <el-option label="禁用" :value="0" />
    </el-select>
  </el-form-item>
</SearchForm>
```

> **不要** `v-model="query"` —— 父级 `reactive` 是 const 引用，子组件 mutate 即可，传值方式专门做了规避（见组件源码注释）。

---

## 三、`DataTableShell` — 表格壳

源码：[`src/components/DataTable/DataTableShell.vue`](../src/components/DataTable/DataTableShell.vue)

把「搜索区 + 工具栏（密度 / 列设置 / 全屏 / 刷新）+ 表格」三件套包成一个统一壳，**展示状态 / 列显隐持久化到 `localStorage`**。

### Props

| 名称 | 类型 | 默认 | 说明 |
| ---- | ---- | ---- | ---- |
| `storageKey` | `string` | **必填** | localStorage key，每个业务页面唯一（如 `'sys-user'`） |
| `loading` | `boolean` | `false` | 透传给刷新按钮的 loading 态 |
| `columnOptions` | [`DataTableColumnOption[]`](#DataTableColumnOption) | `[]` | 列显隐设置项（key / label / fixed / alwaysVisible） |
| `showRefresh` | `boolean` | `true` | 是否显示刷新按钮 |
| `showDensity` | `boolean` | `true` | 是否显示密度切换 |
| `showFullscreen` | `boolean` | `true` | 是否显示全屏按钮 |
| `showColumnSetting` | `boolean` | `true` | 是否显示列设置 |
| `showDisplaySetting` | `boolean` | `true` | 是否显示展示设置（边框 / 斑马 / 表头底色） |

### Emits

| 事件 | 说明 |
| ---- | ---- |
| `refresh` | 用户点了「刷新」按钮，业务方应重新拉数据 |

### Slots

| 名称 | 作用域 | 说明 |
| ---- | ------ | ---- |
| `search` | — | 放 `<SearchForm>` |
| `toolbar-left` | — | 工具栏左侧业务按钮（新增 / 批量删 / 导出 …） |
| `default` | `{ size, stripe, border, headerCellStyle, columnKeys }` | 放 `<el-table>`，**透传插槽参数** |

#### 默认槽参数（重要）

| 参数 | 类型 | 用法 |
| ---- | ---- | ---- |
| `size` | `'small' \| 'default' \| 'large'` | `<el-table :size="size">`，跟随密度切换 |
| `stripe` | `boolean` | `<el-table :stripe="stripe">` |
| `border` | `boolean` | `<el-table :border="border">` |
| `headerCellStyle` | `CSSProperties \| undefined` | `<el-table :header-cell-style="headerCellStyle">` |
| `columnKeys` | `string[]` | 当前**显示的列 key 数组**，控制 `<el-table-column v-if="columnKeys.includes('xxx')">` |

### `DataTableColumnOption`

```ts
interface DataTableColumnOption {
  key: string;            // 列唯一 key（与模板 v-if 判断一致）
  label: string;          // 列设置弹层显示文案
  fixed?: boolean;        // true：固定排在末尾（一般用于操作列）
  alwaysVisible?: boolean;// true：列设置中不可取消勾选
}
```

> **强制约束**：`action`（操作列）必须 `alwaysVisible: true`，避免用户误操作把行内动作隐藏掉。

### 示例

见 [list-page.md §一、骨架](./list-page.md#一骨架必须照抄)。

---

## 四、`DataTableToolbar` — 表格工具栏

源码：[`src/components/DataTable/DataTableToolbar.vue`](../src/components/DataTable/DataTableToolbar.vue)

一般**不直接用**，由 `DataTableShell` 内部使用。如确实需要在自定义表格外侧复用工具按钮组，可参考其 Props 列表（与 `DataTableShell` 的 `show-*` / `column-*` 同名）。

---

## 五、`Echarts` — ECharts 5 封装

源码：[`src/components/Echarts/index.vue`](../src/components/Echarts/index.vue)

按需注册了 Bar / Line / Pie / Radar / Scatter，自动跟随主题（主色、暗黑模式）；自带 `ResizeObserver` + window resize 防抖；`setOption` 也做了 16ms 节流，避免主色切换时连续重绘抖动。

### Props

| 名称 | 类型 | 默认 | 说明 |
| ---- | ---- | ---- | ---- |
| `option` | `EChartsOption` | **必填** | echarts 配置项，建议是 `computed` 引用主色派生（见下） |
| `height` | `number \| string` | `320` | 数字 = px，字符串 = 直接作为 CSS height |
| `theme` | `string` | — | 自定义 echarts 主题（一般不用，跟随系统暗黑即可） |
| `loading` | `boolean` | `false` | 显示 echarts 自带 loading 蒙版 |

### 跟随主题色的写法

```ts
import { computed } from 'vue';
import { useChartTheme } from '@/composables/useChartTheme';

const { primaryColor } = useChartTheme();

const option = computed(() => ({
  color: [primaryColor.value, '#67c23a', '#e6a23c'],
  xAxis: { type: 'category', data: ['Mon', 'Tue', 'Wed'] },
  yAxis: { type: 'value' },
  series: [{ type: 'bar', data: [10, 20, 15] }]
}));
```

`useChartTheme` 读取 `useAppStore().primaryColor`，主题切换时自动响应。

---

## 六、`v-perm` 指令

源码：[`src/directives/permission.ts`](../src/directives/permission.ts)

按钮 / 元素级权限控制；详见 [permission.md §按钮权限](./permission.md#四按钮权限-v-perm)。

```vue
<el-button type="primary" plain v-perm="'system:user:add'" @click="onAdd">新增</el-button>
<el-button type="danger"  link  v-perm="'system:user:delete'" @click="onDelete">删除</el-button>
```

无权限时元素直接从 DOM 移除，不会渲染占位。

---

## 七、其他常用但无需文档化的组件

| 路径 | 一句话说明 |
| ---- | ---- |
| `components/IconPicker` | Element Plus 图标选择器 |
| `components/Setting` | 右侧抽屉「主题设置面板」（主色 / 预设 / 暗黑 / 紧凑） |
| `components/Breadcrumb` | 顶部面包屑，自动跟随路由 |
| `layout/*` | 布局壳：sider / header / tabs / content |

> 这些组件 API 较稳定 / 一般无需二开调用，老板需要时直接看源码即可，不再单列。
