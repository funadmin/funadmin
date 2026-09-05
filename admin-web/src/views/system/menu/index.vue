<template>
  <PageWrapper
    title="菜单管理"
    subtitle="维护后台导航目录与页面；按钮权限在角色权限中统一分配"
  >
    <DataTableShell storage-key="system-menu" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="菜单名称" prop="title">
            <el-input v-model="query.title" placeholder="请输入菜单名称" clearable />
          </el-form-item>
          <el-form-item label="菜单路由" prop="path">
            <el-input v-model="query.path" placeholder="请输入菜单路由" clearable />
          </el-form-item>
          <el-form-item label="状态" prop="hidden">
            <el-select v-model="query.hidden" placeholder="请选择" clearable class="!w-36">
              <el-option label="显示" :value="false" />
              <el-option label="隐藏" :value="true" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:menu:add'" @click="onAdd()">
          <i class="i-ep-plus" /> 新增
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:menu:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> 批量删除{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
        <el-button type="primary" plain @click="toggleExpand">
          <i :class="expandAll ? 'i-ep-fold' : 'i-ep-expand'" /> {{ expandAll ? '折叠' : '展开' }}
        </el-button>
      </template>

      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table
          ref="menuTableRef"
          :key="tableRenderKey"
          :data="displayTree"
          v-loading="loading"
          :size="size"
          :stripe="stripe"
          :border="border"
          :header-cell-style="headerCellStyle"
          row-key="id"
          :tree-props="{ children: 'children' }"
          :default-expand-all="expandAll"
          @selection-change="onSelectionChange"
        >
          <el-table-column type="selection" width="48" align="center" />
          <el-table-column label="" width="52" align="center" fixed="left">
            <template #default="{ row }">
              <span
                v-if="dragEnabled"
                class="menu-drag-handle inline-flex cursor-grab items-center justify-center text-[var(--el-text-color-secondary)] active:cursor-grabbing"
                :data-menu-id="row.id"
                title="拖动调整同级顺序"
              >
                <i class="i-ep-rank text-lg" />
              </span>
              <span v-else class="text-xs text-[var(--el-text-color-secondary)]">—</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="名称" min-width="200" />
          <el-table-column label="图标" width="80" align="center">
            <template #default="{ row }">
              <SvgIcon v-if="row.icon" :name="row.icon" :size="18" />
            </template>
          </el-table-column>
          <el-table-column prop="path" label="路由" min-width="180" />
          <el-table-column prop="permission" label="权限标识" min-width="180" />
          <el-table-column label="类型" width="90" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="typeTag(row.type)">{{ typeText(row.type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="100" align="center">
            <template #default="{ row }">
              <InlineEdit
                :model-value="row.sort"
                type="number"
                :min="0"
                :max="999"
                :save="(v: number) => menuApi.update(row.id, { sort: v })"
                @update:model-value="row.sort = $event"
              />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="320" align="center" fixed="right">
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button size="small" type="primary" link v-perm="'system:menu:add'" @click="onAdd(row as API.MenuItem)">
                  <i class="i-ep-plus" /> 新增子项
                </el-button>
                <el-button size="small" type="primary" link v-perm="'system:menu:edit'" @click="onEdit(row as API.MenuItem)">
                  <i class="i-ep-edit" /> 编辑
                </el-button>
                <el-button size="small" type="danger" link v-perm="'system:menu:delete'" @click="onDelete(row as API.MenuItem)">
                  <i class="i-ep-delete" /> 删除
                </el-button>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <MenuFormDialog
      v-model="dialogVisible"
      :row="current"
      :tree="tree"
      :default-parent-id="defaultParentId"
      @success="loadData"
    />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, nextTick, onActivated, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import Sortable from 'sortablejs';
import { menuApi } from '@/api/system/menu';
import { filterTree, listToTree, treeToList } from '@/utils/tree';
import InlineEdit from '@/components/InlineEdit/index.vue';
import MenuFormDialog from './components/MenuFormDialog.vue';

defineOptions({ name: 'SystemMenu' });

const loading = ref(false);
const tree = ref<API.MenuItem[]>([]);
const selection = ref<API.MenuItem[]>([]);
const dialogVisible = ref(false);
const current = ref<API.MenuItem | null>(null);
const defaultParentId = ref(0);

const expandAll = ref(true);
const tableRenderKey = ref(0);
const menuTableRef = ref<{ $el?: HTMLElement } | null>(null);
let menuRowSortable: Sortable | null = null;

const query = reactive({
  title: '',
  path: '',
  hidden: undefined as boolean | undefined
});

const treeData = computed(() => {
  const flat = treeToList(tree.value).map((it) => ({ ...it, children: undefined }));
  flat.sort((a: any, b: any) => (a.sort ?? 0) - (b.sort ?? 0));
  return listToTree(flat, { idKey: 'id', parentKey: 'parentId' });
});

const displayTree = computed(() =>
  filterTree(treeData.value, (node) => {
    if (query.title && !node.title.includes(query.title)) return false;
    if (query.path && !(node.path || '').includes(query.path)) return false;
    if (query.hidden !== undefined && node.hidden !== query.hidden) return false;
    return true;
  })
);

/** 筛选或折叠时 DOM 与整树不一致，禁用拖拽避免错乱 */
const dragEnabled = computed(
  () =>
    !query.title?.trim() &&
    !query.path?.trim() &&
    query.hidden === undefined &&
    expandAll.value,
);

function getMenuIdFromTr(tr: Element): number | null {
  const el = tr.querySelector('[data-menu-id]') as HTMLElement | undefined;
  const v = el?.dataset?.menuId;
  if (v == null || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function menuNodeMap() {
  return new Map(treeToList(displayTree.value).map((n) => [n.id, n]));
}

function getMenuTbody(): HTMLElement | null {
  const root = menuTableRef.value?.$el;
  if (!root) return null;
  return root.querySelector('.el-table__body-wrapper tbody') as HTMLElement | null;
}

function destroyMenuRowSortable() {
  menuRowSortable?.destroy();
  menuRowSortable = null;
}

async function onMenuRowSortEnd() {
  if (!dragEnabled.value) return;
  const map = menuNodeMap();
  const tbody = getMenuTbody();
  if (!tbody) return;

  const parentToOrderedIds = new Map<number, number[]>();
  tbody.querySelectorAll('tr').forEach((tr) => {
    const id = getMenuIdFromTr(tr);
    if (id == null) return;
    const node = map.get(id);
    if (!node) return;
    const pid = node.parentId ?? 0;
    if (!parentToOrderedIds.has(pid)) parentToOrderedIds.set(pid, []);
    parentToOrderedIds.get(pid)!.push(id);
  });

  const toUpdate: { id: number; sort: number }[] = [];
  parentToOrderedIds.forEach((ids) => {
    ids.forEach((id, idx) => {
      const node = map.get(id);
      if (!node) return;
      if ((node.sort ?? 0) !== idx) toUpdate.push({ id, sort: idx });
    });
  });

  if (!toUpdate.length) return;

  try {
    await Promise.all(
      toUpdate.map((u) =>
        menuApi.update(u.id, { sort: u.sort }, { requestOptions: { showSuccessMsg: false } }),
      ),
    );
    ElMessage.success('排序已保存');
  } catch {
    ElMessage.error('保存排序失败');
  } finally {
    await loadData();
  }
}

function initMenuRowSortable() {
  destroyMenuRowSortable();
  if (!dragEnabled.value || loading.value) return;
  const tbody = getMenuTbody();
  if (!tbody) return;

  menuRowSortable = Sortable.create(tbody, {
    handle: '.menu-drag-handle',
    animation: 180,
    ghostClass: 'menu-sortable-ghost',
    onMove(evt: Sortable.MoveEvent) {
      const map = menuNodeMap();
      const dragId = getMenuIdFromTr(evt.dragged);
      const relatedId = getMenuIdFromTr(evt.related);
      if (dragId == null || relatedId == null) return false;
      const a = map.get(dragId);
      const b = map.get(relatedId);
      if (!a || !b) return false;
      return a.parentId === b.parentId;
    },
    onEnd: () => {
      void onMenuRowSortEnd();
    },
  });
}

watch(
  [dragEnabled, displayTree, loading, tableRenderKey],
  () => {
    nextTick(() => initMenuRowSortable());
  },
  { flush: 'post' },
);

function toggleExpand() {
  expandAll.value = !expandAll.value;
  tableRenderKey.value++;
}

function onSearch() {
  // 前端筛选，无需请求
}

function onReset() {
  query.title = '';
  query.path = '';
  query.hidden = undefined;
  loadData();
}

async function loadData() {
  loading.value = true;
  try {
    tree.value = await menuApi.tree();
    selection.value = [];
  } finally {
    loading.value = false;
  }
}

function typeTag(type: API.MenuItem['type']) {
  return ({ M: 'primary', C: 'success' } as const)[type as 'M' | 'C'];
}
function typeText(type: API.MenuItem['type']) {
  return ({ M: '目录', C: '页面' } as const)[type as 'M' | 'C'] || '未知';
}

function onAdd(parent?: API.MenuItem) {
  current.value = null;
  defaultParentId.value = parent?.id || 0;
  dialogVisible.value = true;
}

function onEdit(row: API.MenuItem) {
  current.value = row;
  defaultParentId.value = row.parentId;
  dialogVisible.value = true;
}

async function onDelete(row: API.MenuItem) {
  await ElMessageBox.confirm(`确认删除 ${row.title} ?`, '提示', { type: 'warning' });
  await menuApi.remove(row.id);
  loadData();
}

function onSelectionChange(rows: API.MenuItem[]) {
  selection.value = rows;
}

/**
 * 树形批量删除：去除「已被选中父节点的子节点」，避免后端级联+前端重复删。
 * 算法：把所有选中节点的 ID 放进 set，遍历选中节点向上找 parentId，
 *      如果祖先链上任一节点已在 set 中，说明此节点是「冗余子节点」，跳过。
 */
async function onBatchDelete() {
  if (!selection.value.length) {
    ElMessage.warning('请至少选择一项');
    return;
  }
  const selectedIds = new Set(selection.value.map((r) => r.id));
  const idMap = new Map<number, number>();
  treeToList(tree.value).forEach((n: any) => idMap.set(n.id, n.parentId));

  function hasSelectedAncestor(id: number): boolean {
    let pid = idMap.get(id);
    while (pid && pid !== 0) {
      if (selectedIds.has(pid)) return true;
      pid = idMap.get(pid);
    }
    return false;
  }

  const topIds = selection.value.map((r) => r.id).filter((id) => !hasSelectedAncestor(id));

  await ElMessageBox.confirm(
    `已选中 ${selection.value.length} 项，去重后将删除 ${topIds.length} 个顶层节点（含其子节点）。是否继续？`,
    '提示',
    { type: 'warning' }
  );

  await menuApi.removeMany(topIds);
  loadData();
}

onMounted(loadData);
onActivated(loadData);

onUnmounted(() => {
  destroyMenuRowSortable();
});
</script>

<style scoped>
:deep(tr.menu-sortable-ghost) {
  opacity: 0.45;
}
</style>
