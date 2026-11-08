# TablePagination

后台列表统一使用此组件。默认放在表格下方 16px、靠右显示，窄屏时在组件内部横向滚动。

```vue
<TablePagination
  v-model:current-page="query.page"
  v-model:page-size="query.pageSize"
  :total="total"
  @change="loadData"
/>
```

在 `<script setup>` 中导入 `@/components/TablePagination/index.vue`。Element Plus 的分页属性、事件和双向绑定均可使用。

默认提供总数、每页条数（10 / 20 / 50 / 100）、前后翻页、页码和跳页。固定条数或窄栏可以传入 `layout="total, prev, pager, next"`，以及 `:page-size="12"`、`small` 等属性。

页面无需再包分页容器或设置对齐样式。请求使用 `@change`，避免同时绑定 `current-change` 和 `size-change` 导致重复查询。生成列表通过 `SchemaTablePage` 自动使用此组件；插件源码也应导入宿主组件。
