/**
 * 通用列表页 CRUD 五件套：list / loading / query / 选中 / 弹窗
 *
 * - 兼容「分页接口」(返回 API.PageResult<T>) 与「全量接口」(返回 T[])
 * - 删除统一为「单删 + 批量删」，由 api 层声明能力：
 *   · 仅传 `remove(id)`：批量删用 Promise.all 串接（适合 RESTful 风格）
 *   · 同时传 `removeMany(ids)`：批量走专用接口（user/role 风格）
 *
 * 调用方负责：
 *   1) 模板里给 `<el-table>` 绑 `@selection-change="onSelectionChange"`
 *   2) 模板里给批量按钮绑 `:disabled="!selection.length"` + `@click="onBatchDelete"`
 *   3) 弹窗用 `<XxxFormDialog v-model="dialogVisible" :row="current" @success="loadData" />`
 */
import { reactive, ref, type Ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

export interface UseCrudApi<T, ID = number> {
  list: (params: any) => Promise<API.PageResult<T> | T[]>;
  /** 单删（必填） */
  remove?: (id: ID) => Promise<unknown>;
  /** 批量删（可选；不传则用 remove 串接） */
  removeMany?: (ids: ID[]) => Promise<unknown>;
}

export interface UseCrudOptions<T, Q extends Record<string, any>, ID = number> {
  api: UseCrudApi<T, ID>;
  /** 查询条件初始值工厂（每次 reset 重新调用） */
  initialQuery: () => Q;
  /** 主键字段名，默认 `id` */
  rowKey?: string;
  /** 是否服务端分页：true 时 onSearch 会把 page 重置为 1 */
  pagination?: boolean;
  /** 自定义删除确认文案 */
  deleteConfirm?: (target: T | T[]) => string;
  /** 是否在 mounted 时自动加载（默认 true） */
  immediate?: boolean;
}

export function useCrud<T extends Record<string, any>, Q extends Record<string, any>, ID = number>(
  options: UseCrudOptions<T, Q, ID>
) {
  const { api, initialQuery, rowKey = 'id', pagination = false, deleteConfirm } = options;

  const loading = ref(false);
  const list = ref([]) as Ref<T[]>;
  const total = ref(0);
  const query = reactive(initialQuery()) as Q;
  const selection = ref([]) as Ref<T[]>;
  const dialogVisible = ref(false);
  const drawerVisible = ref(false);
  const current = ref<T | null>(null);

  async function loadData() {
    loading.value = true;
    try {
      const res = await api.list(query);
      if (Array.isArray(res)) {
        list.value = res;
        total.value = res.length;
      } else {
        list.value = res.list;
        total.value = res.total;
      }
    } finally {
      loading.value = false;
    }
  }

  function onSearch() {
    if (pagination && (query as any).page !== undefined) {
      (query as any).page = 1;
    }
    loadData();
  }

  function onReset() {
    Object.assign(query, initialQuery());
    loadData();
  }

  function onAdd() {
    current.value = null;
    dialogVisible.value = true;
  }

  function onEdit(row: T) {
    current.value = row;
    dialogVisible.value = true;
  }

  /** 抽屉场景：如「分配权限」「查看详情」 */
  function onOpenDrawer(row: T) {
    current.value = row;
    drawerVisible.value = true;
  }

  async function onDelete(row: T) {
    if (!api.remove) return;
    const text = deleteConfirm?.(row) ?? '确认删除该记录？此操作不可恢复';
    await ElMessageBox.confirm(text, '提示', { type: 'warning' });
    await api.remove(row[rowKey] as ID);
    loadData();
  }

  async function onBatchDelete() {
    if (!api.remove && !api.removeMany) return;
    if (!selection.value.length) {
      ElMessage.warning('请至少选择一项');
      return;
    }
    const text =
      deleteConfirm?.(selection.value) ??
      `确认删除选中的 ${selection.value.length} 条？此操作不可恢复`;
    await ElMessageBox.confirm(text, '提示', { type: 'warning' });
    const ids = selection.value.map((r) => r[rowKey] as ID);
    if (api.removeMany) {
      await api.removeMany(ids);
    } else if (api.remove) {
      // 串接调用：保证 mock/真实 API 都能接住
      await Promise.all(ids.map((id) => api.remove!(id)));
    }
    selection.value = [];
    loadData();
  }

  function onSelectionChange(rows: T[]) {
    selection.value = rows;
  }

  return {
    // 状态
    loading,
    list,
    total,
    query,
    selection,
    dialogVisible,
    drawerVisible,
    current,
    // 行为
    loadData,
    onSearch,
    onReset,
    onAdd,
    onEdit,
    onOpenDrawer,
    onDelete,
    onBatchDelete,
    onSelectionChange
  };
}
