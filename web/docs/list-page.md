# 列表页开发指南

> 目标：让你 5 分钟复制一个**风格统一**的业务列表页（搜索 + 工具栏 + 表格 + 分页 + CRUD 弹窗）。
> 参考实现：[`src/views/system/user/index.vue`](../src/views/system/user/index.vue)、[`src/views/system/role/index.vue`](../src/views/system/role/index.vue)。

---

## 一、骨架（必须照抄）

一个标准列表页固定 4 段：

```vue
<template>
  <PageWrapper title="模块名" subtitle="一句话副标题">
    <!-- 1) 表格壳：搜索槽 + 工具栏槽 + 默认槽（外面套了密度/列设置/全屏） -->
    <DataTableShell
      storage-key="biz-foo"
      :loading="loading"
      :column-options="columnOptions"
      @refresh="loadData"
    >
      <!-- 1.1 搜索表单（行内表单 + 重置/查询按钮） -->
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="名称" prop="name">
            <el-input v-model="query.name" clearable />
          </el-form-item>
        </SearchForm>
      </template>

      <!-- 1.2 工具栏左侧：业务按钮（新增 / 批量删 / 导出 …） -->
      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'biz:foo:add'" @click="onAdd">
          <i class="i-ep-plus" /> 新增
        </el-button>
      </template>

      <!-- 1.3 表格主体：通过插槽参数拿到当前 size/stripe/border/headerCellStyle/columnKeys -->
      <template #default="{ size, stripe, border, headerCellStyle, columnKeys }">
        <el-table
          :data="list"
          v-loading="loading"
          :size="size"
          :stripe="stripe"
          :border="border"
          :header-cell-style="headerCellStyle"
        >
          <el-table-column v-if="columnKeys.includes('id')" prop="id" label="ID" width="80" />
          <!-- ...其它列 -->
          <el-table-column v-if="columnKeys.includes('action')" label="操作" width="280" fixed="right">
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button size="small" type="primary" link v-perm="'biz:foo:edit'" @click="onEdit(row)">
                  <i class="i-ep-edit" /> 编辑
                </el-button>
                <el-button size="small" type="danger" link v-perm="'biz:foo:delete'" @click="onDelete(row)">
                  <i class="i-ep-delete" /> 删除
                </el-button>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <!-- 2) 分页（注意：分页不在 DataTableShell 内，统一放 PageWrapper 底部） -->
    <div class="app-pagination-bar">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @current-change="loadData"
        @size-change="loadData"
      />
    </div>

    <!-- 3) 弹窗 / 抽屉 -->
    <FooFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>
```

> 4 段对应：`PageWrapper` → 页面壳；`DataTableShell` → 表格壳；`SearchForm` → 搜索；表单弹窗 → CRUD。
> 缺哪段都不要省，**风格统一** 的代价就是「都用同一个壳」。

---

## 二、脚本部分（最小可用）

```ts
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { fooApi, type FooModel } from '@/api/system/foo';
import type { DataTableColumnOption } from '@/components/DataTable';
import FooFormDialog from './components/FooFormDialog.vue';

defineOptions({ name: 'BizFoo' });

/** 列定义：操作列 alwaysVisible，避免被取消勾选导致行内动作不可达 */
const columnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'name', label: '名称' },
  { key: 'status', label: '状态' },
  { key: 'createdAt', label: '创建时间' },
  { key: 'action', label: '操作', alwaysVisible: true }
];

const loading = ref(false);
const list = ref<FooModel[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const current = ref<FooModel | null>(null);

const query = reactive({
  page: 1,
  pageSize: 10,
  name: '',
  status: undefined as number | undefined
});

async function loadData() {
  loading.value = true;
  try {
    const res = await fooApi.list(query);
    list.value = res.list;
    total.value = res.total;
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  query.page = 1;
  loadData();
}

function onReset() {
  query.name = '';
  query.status = undefined;
  query.page = 1;
  loadData();
}

function onAdd() {
  current.value = null;
  dialogVisible.value = true;
}

function onEdit(row: FooModel) {
  current.value = row;
  dialogVisible.value = true;
}

async function onDelete(row: FooModel) {
  await ElMessageBox.confirm(`确认删除 ${row.name} ?`, '提示', { type: 'warning' });
  await fooApi.remove(row.id);
  ElMessage.success('删除成功');
  loadData();
}

onMounted(loadData);
</script>
```

---

## 三、`DataTableShell` 关键插槽 / 属性速查

| 插槽 | 作用 | 常用法 |
| ---- | ---- | ------ |
| `#search` | 搜索区 | 套 `SearchForm` |
| `#toolbar-left` | 工具栏左侧 | 业务按钮（新增、批量、导出） |
| `#default="{ size, stripe, border, headerCellStyle, columnKeys }"` | 表格主体 | 把这 5 个值传给 `el-table` |

| Prop | 类型 | 默认 | 说明 |
| ---- | ---- | ---- | ---- |
| `storage-key` | `string` | — | **必填**，本表格的偏好（密度 / 边框 / 列勾选）以此为 key 写入 `localStorage`，每个业务页用不同 key |
| `column-options` | `DataTableColumnOption[]` | `[]` | 列设置弹层用，`alwaysVisible` 用于操作列等不可隐藏列 |
| `loading` | `boolean` | `false` | 控制刷新按钮的转动状态 |
| `show-refresh` / `show-density` / `show-fullscreen` / `show-column-setting` / `show-display-setting` | `boolean` | `true` | 工具栏右侧各按钮显隐 |

| 事件 | 触发 |
| ---- | ---- |
| `@refresh` | 用户点了「刷新」按钮，自己调 `loadData` |

> 工具栏右侧顺序：刷新 → 密度 → 全屏 → 列设置 → 表格设置（斑马纹 / 边框 / 表头背景）。

---

## 四、`SearchForm` 用法

```vue
<SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
  <el-form-item label="账号" prop="username">
    <el-input v-model="query.username" placeholder="账号 / 昵称" clearable />
  </el-form-item>
  <el-form-item label="状态" prop="status">
    <el-select v-model="query.status" placeholder="请选择" clearable class="!w-32">
      <el-option label="启用" :value="1" />
      <el-option label="禁用" :value="0" />
    </el-select>
  </el-form-item>
</SearchForm>
```

要点：

1. **`:model` 单向**，不要写 `v-model`。父级传入的 `reactive` 通过引用共享，组件内直接 mutate 字段即生效。
2. 「重置」会自动 `formRef.resetFields()` 并清空非 `page/pageSize` 字段，再 `emit('reset')`，老板自己在 `onReset` 里重新拉一次数据即可。
3. 「重置」「查询」按钮都用 `plain` 风格，跟整套 UI 统一。

---

## 五、表格列写法

### 5.1 跟「列设置」联动

每一列前都写 `v-if="columnKeys.includes('xxx')"`，`xxx` 必须与 `columnOptions[*].key` 一致：

```vue
<el-table-column v-if="columnKeys.includes('mobile')" prop="mobile" label="手机" width="140" />
```

### 5.2 状态开关（仅开关，不带文字）

```vue
<el-table-column v-if="columnKeys.includes('status')" label="状态" width="90" align="center">
  <template #default="{ row }">
    <div class="app-status-switch">
      <el-switch
        size="small"
        :model-value="row.status === 1"
        @change="(v: string | number | boolean) => onToggleStatus(row, v === true)"
      />
    </div>
  </template>
</el-table-column>
```

`app-status-switch` 样式在每个列表页 scoped CSS 内复用（[user/index.vue](../src/views/system/user/index.vue#L227)）。

### 5.3 状态 Tag

```vue
<template #default="{ row }">
  <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
    {{ row.status === 1 ? '启用' : '禁用' }}
  </el-tag>
</template>
```

### 5.4 操作列（必须 fixed='right' + link 按钮）

```vue
<el-table-column v-if="columnKeys.includes('action')" label="操作" width="300" align="center" fixed="right">
  <template #default="{ row }">
    <div class="app-table-actions app-table-actions--link">
      <el-button size="small" type="primary" link v-perm="'biz:foo:edit'" @click="onEdit(row)">
        <i class="i-ep-edit" /> 编辑
      </el-button>
      <el-button size="small" type="danger" link v-perm="'biz:foo:delete'" @click="onDelete(row)">
        <i class="i-ep-delete" /> 删除
      </el-button>
    </div>
  </template>
</el-table-column>
```

> 要点：
> - 用 `link` 形式（**禁止**在表格内放实色 primary 按钮，会破坏密度）。
> - 外面套 `app-table-actions app-table-actions--link`，可控制按钮间距与对齐。
> - 每个动作都加 `v-perm`。

---

## 六、分页

分页**不放在 `DataTableShell` 内**，统一放在 `PageWrapper` 底部，靠右对齐 + 顶部分隔线：

```vue
<div class="app-pagination-bar">
  <el-pagination
    v-model:current-page="query.page"
    v-model:page-size="query.pageSize"
    :total="total"
    :page-sizes="[10, 20, 50, 100]"
    layout="total, sizes, prev, pager, next, jumper"
    background
    @current-change="loadData"
    @size-change="loadData"
  />
</div>
```

```css
.app-pagination-bar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding-top: 12px;
  margin-top: 12px;
  border-top: 1px solid var(--app-border, #ebeef5);
}
```

---

## 七、API 模块写法

约定：**一张表一个文件**，路径 `src/api/<module>/<resource>.ts`。

```ts
// src/api/system/user.ts
import http from '@/utils/http';

const PREFIX = '/system/user';

export interface UserModel {
  id: number;
  username: string;
  nickname: string;
  status: 0 | 1;
  // ...
}

export const userApi = {
  list:   (params: API.PageQuery)               => http.get<API.PageResult<UserModel>>(PREFIX, params),
  detail: (id: number)                          => http.get<UserModel>(`${PREFIX}/${id}`),
  create: (data: Partial<UserModel> & { password: string }) =>
            http.post<UserModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<UserModel>) =>
            http.put<UserModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number | number[]) =>
            http.delete<void>(PREFIX, { ids: Array.isArray(ids) ? ids : [ids] }, {
              requestOptions: { showSuccessMsg: true }
            }),
  toggleStatus: (id: number, status: 0 | 1) =>
            http.post<void>(`${PREFIX}/${id}/status`, { status })
};
```

要点：

- **`http.get/post/put/delete/upload/download`**：`@/utils/http` 已封装；返回值已 **解包 `data`**，老板直接 `await` 拿业务数据。
- **`requestOptions`**：
  - `showSuccessMsg: true` → 接口成功后自动 `ElMessage.success(msg)`，写表单一定加。
  - `showErrorMsg: false` → 部分静默接口（轮询、心跳）可以关掉错误提示。
  - `withToken: false` → 登录、刷新等 **不带 token** 的接口。
  - `errorMessageMode: 'modal'` → 致命错误用模态框，避免一晃而过。
- **批量删除走 query string**：`http.delete(PREFIX, { ids: [1,2,3] })`，会被序列化为 `?ids[]=1&ids[]=2&ids[]=3`，与后端契约一致（见 [api.md §1.5](./api.md#15-批量删除)）。
- **Token 4001 自动刷新**：拦截器内置队列，老板不用关心。

---

## 八、CRUD 完整闭环

> **首选**：用 [`useCrud`](../src/composables/useCrud.ts) 把下面这张表的状态机一次性抽掉，业务文件只剩「列定义 + 模板」。

```ts
import { useCrud } from '@/composables/useCrud';
import { userApi } from '@/api/system/user';

const {
  loading, list, total, query, selection, dialogVisible, current,
  loadData, onSearch, onReset, onAdd, onEdit, onDelete, onBatchDelete, onSelectionChange
} = useCrud<UserModel, UserQuery>({
  api: userApi,
  initialQuery: () => ({ page: 1, pageSize: 20, keyword: '' }),
  pagination: true
});
onMounted(loadData);
```

约定：

- **api** 至少要有 `list`；单条删除需要 `remove`；`removeMany` 可选（不传则用 `Promise.all` 串接 `remove`）。
- `pagination: true` 时 `onSearch` 自动把 `query.page` 重置为 1。
- 模板里给 `<el-table @selection-change="onSelectionChange">`、批量按钮 `:disabled="!selection.length" @click="onBatchDelete"`。
- 抽屉类交互（如分配权限）用返回值里的 `drawerVisible`、`onOpenDrawer`。
- 首次加载：在 `setup` 里保留 `onMounted(loadData)`（与 composable 内逻辑一致）。

下表是底层语义，方便不用 `useCrud` 时照着自己写：

| 动作 | 触发 | 实现要点 |
| ---- | ---- | -------- |
| 列表加载 | `onMounted` / `loadData` | `loading=true` → `await api.list(query)` → 写回 `list/total` → `finally loading=false` |
| 搜索 | `SearchForm @search` | `query.page = 1; loadData()` |
| 重置 | `SearchForm @reset` | 把搜索字段置空 → `query.page = 1; loadData()` |
| 新增 | 工具栏「新增」 | `current = null; dialogVisible = true` |
| 编辑 | 操作列「编辑」 | `current = row; dialogVisible = true` |
| 弹窗成功 | `@success` | 关闭弹窗（弹窗内做） + `loadData()` |
| 删除 | 操作列「删除」 | `await ElMessageBox.confirm(...)` → `await api.remove(id)` → `loadData()` |
| 状态切换 | 行内 `el-switch` | 调 `api.toggleStatus(id, target)` → `row.status = target`（避免重新拉表） |
| 重置密码 | 操作列「重置密码」 | `await ElMessageBox.prompt(..., { inputPattern, inputErrorMessage })` → `await api.resetPassword` |

---

## 九、按钮权限（`v-perm`）

每个**业务按钮**和**行内动作**都要打 `v-perm`，权限点跟后端菜单 `permission` 字段一致：

```vue
<el-button v-perm="'system:user:add'" ...>新增</el-button>
<el-button v-perm="'system:user:edit'" ...>编辑</el-button>

<!-- 任意一个匹配即可 -->
<el-button v-perm="['system:user:edit', 'system:user:editSelf']" ...>编辑</el-button>

<!-- 必须全部匹配 -->
<el-button v-perm:all="['a', 'b']" ...>需要 a + b</el-button>
```

详见 [permission.md](./permission.md)。

---

## 十、风格 / 体感约定（很重要）

1. **按钮风格**：整套系统是「克制商务风」，按钮统一 `plain` 或 `link`：
   - 工具栏 / 表单顶部按钮：`type="primary" plain`。
   - 表格行内动作：`type="primary|warning|danger" link`。
   - **禁止** 在工具栏 / 表格内放实色 primary 按钮，影响密度与一致性。
2. **图标**：使用本地生成的 Element Plus 图标 CSS，写法 `<i class="i-ep-plus" />`。
3. **国际化**：固定按钮文案（重置、查询、新增、编辑、删除）走系统词库；模块自有文案在模块内 `useI18n()`。
4. **响应式 / 暗黑**：所有内置组件已经处理过，**不要**自己往里加 `dark:` 或 `@media`，靠 CSS 变量自动跟随。
5. **持久化偏好**：`DataTableShell` 已经把「密度 / 边框 / 表头底色 / 列勾选」写进 `localStorage`，**禁止** 在业务页另起一套。

---

## 十一、CSV 导入 / 导出（轻量方案）

> 适用：表头/字段稳定、不需要多 sheet、不需要二进制 xlsx 的常规列表。
> 实现见 [`src/utils/csv.ts`](../src/utils/csv.ts)，参考接入：[`src/views/system/user/index.vue`](../src/views/system/user/index.vue)。

### 11.1 设计要点

- **零依赖**：不引入 `xlsx`，控制包体积；如真要导出多 sheet 的二进制再升级。
- **统一列定义**：导出表头 / 解析字段 / 模板下载共用一份 `CsvColumn[]`，`formatter` 管出参（如 `1 → 启用`），`parser` 管入参（`启用 → 1`）。
- **UTF-8 BOM**：写入时自动追加 `\uFEFF`，避免 Excel 打开中文乱码。
- **RFC 4180 子集**：解析支持双引号转义、字段内逗号 / 换行。

### 11.2 工具栏 + 隐藏 input

```vue
<template #toolbar-left>
  <el-button type="primary" plain v-perm="'biz:foo:add'" @click="onAdd">新增</el-button>
  <el-button type="danger" plain :disabled="!selection.length" @click="onBatchDelete">
    批量删除{{ selection.length ? `(${selection.length})` : '' }}
  </el-button>
  <el-button plain :loading="exporting" v-perm="'biz:foo:export'" @click="onExport">导出</el-button>
  <el-button plain v-perm="'biz:foo:import'" @click="triggerImport">导入</el-button>
  <el-button link type="info" @click="onDownloadTemplate">模板</el-button>
  <input ref="fileInputRef" type="file" accept=".csv,text/csv" class="hidden" @change="onFileSelected" />
</template>
```

### 11.3 列定义 + 三个动作

```ts
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';

const csvColumns: CsvColumn<UserModel>[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '账号' },
  { key: 'status', label: '状态',
    formatter: (r) => (r.status === 1 ? '启用' : '禁用'),
    parser: (v) => (String(v).trim() === '禁用' || v === '0' ? 0 : 1)
  }
];

// 导出：勾选优先，否则拉全量
async function onExport() {
  const rows = selection.value.length
    ? [...selection.value]
    : (await api.list({ ...query, page: 1, pageSize: 9999 })).list;
  downloadCsv(`列表_${new Date().toISOString().slice(0,10)}.csv`, toCsv(rows, csvColumns));
}

// 模板：表头 + 一行示例
function onDownloadTemplate() {
  downloadCsv('导入模板.csv', toCsv([{ username: 'demo', status: 1 } as any], csvColumns));
}

// 导入：读 → 解析 → 二次确认 → batchImport → 用 ElNotification 展示部分错误
async function onFileSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  const rows = parseCsv(await readFileAsText(file), csvColumns);
  await ElMessageBox.confirm(`检测到 ${rows.length} 条数据，确认导入？`, '导入确认', { type: 'info' });
  const result = await api.batchImport(rows as any);
  if (result.errors?.length) {
    ElNotification.warning({
      title: `导入完成（成功 ${result.created}，跳过 ${result.skipped}）`,
      message: result.errors.slice(0, 5).join('\n')
    });
  }
  loadData();
}
```

### 11.4 后端契约（mock 已实现）

`POST /system/user/import`，body `{ rows: [...] }`，响应：

```json
{
  "code": 400,
  "data": { "created": 3, "skipped": 1, "errors": ["第 2 行：账号 admin 已存在"] }
}
```

---

## 十二、行内编辑（`InlineEdit`）

> 适用：单字段轻量编辑，例如排序值、备注短文本。
> 组件源码：[`src/components/InlineEdit/index.vue`](../src/components/InlineEdit/index.vue)
> 演示：[`src/views/system/menu/index.vue`](../src/views/system/menu/index.vue) `sort` 列。

### 12.1 体感约定

- 默认显示纯文本，hover 时浮现一支「铅笔」图标。
- 点击进入编辑态，**Enter / blur 保存**，**Esc 取消**。
- 保存中显示加载图标；失败自动回滚草稿，弹 `ElMessage.error`。
- 不弹「成功」消息（依赖 axios 拦截器的 `showSuccessMsg`），避免行内编辑刷屏。

### 12.2 用法

```vue
<el-table-column prop="sort" label="排序" width="100" align="center">
  <template #default="{ row }">
    <InlineEdit
      :model-value="row.sort"
      type="number"
      :min="0" :max="999"
      :save="(v: number) => menuApi.update(row.id, { sort: v })"
      @update:model-value="row.sort = $event"
    />
  </template>
</el-table-column>
```

### 12.3 Props 速查

| 字段 | 类型 | 说明 |
| ---- | ---- | ---- |
| `modelValue` | `string \| number` | 当前值；保存成功后通过 `update:modelValue` 回写 |
| `type` | `'text' \| 'number'` | 编辑组件类型，默认 `text` |
| `save` | `(v) => Promise<unknown>` | **必填**，保存回调；抛错则回滚 |
| `disabled` | `boolean` | 禁用编辑（仅展示） |
| `min` / `max` / `step` | `number` | `type=number` 时透传给 `el-input-number` |
| `maxlength` | `number` | `type=text` 时透传 |
| `emptyText` | `string` | 空值占位，默认 `—` |

---

## 十三、对比参考实现

- 标准列表（**分页 / 服务端筛选**）：[`src/views/system/user/index.vue`](../src/views/system/user/index.vue)
- 全量列表（**前端筛选 / Tag 状态**）：[`src/views/system/role/index.vue`](../src/views/system/role/index.vue)
- 树表 / 抽屉权限分配：[`src/views/system/role/components/RolePermDrawer.vue`](../src/views/system/role/components/RolePermDrawer.vue)
- 表单弹窗：详见 [form-dialog.md](./form-dialog.md)
