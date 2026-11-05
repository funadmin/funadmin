<template>
  <PageWrapper
    :title="t('systemMenu.title', '菜单管理')"
    :subtitle="t('systemMenu.subtitle', '维护后台导航目录与页面；按钮权限在角色权限中统一分配')"
  >
    <DataTableShell storage-key="system-menu" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item :label="t('systemMenu.name', '菜单名称')" prop="name">
            <el-input v-model="query.name" :placeholder="t('systemMenu.namePlaceholder', '请输入菜单名称')" clearable />
          </el-form-item>
          <el-form-item :label="t('systemMenu.path', '菜单路由')" prop="path">
            <el-input v-model="query.path" :placeholder="t('systemMenu.pathPlaceholder', '请输入菜单路由')" clearable />
          </el-form-item>
          <el-form-item :label="t('common.status', '状态')" prop="hidden">
            <el-select v-model="query.hidden" :placeholder="t('common.pleaseSelect', '请选择')" clearable class="!w-36">
              <el-option :label="t('systemMenu.visible', '显示')" :value="false" />
              <el-option :label="t('systemMenu.hidden', '隐藏')" :value="true" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'systemmenu:create'" @click="onAdd()">
          <i class="i-ep-plus" /> {{ t('common.add', '新增') }}
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'systemmenu:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> {{ t('common.batchRemove', '批量删除') }}{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
        <el-button type="primary" plain @click="toggleExpand">
          <i :class="expandAll ? 'i-ep-fold' : 'i-ep-expand'" /> {{ expandAll ? t('systemMenu.collapse', '折叠') : t('systemMenu.expand', '展开') }}
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
          <el-table-column type="index" label="" width="52" align="center">
            <template #default="{ row }">
              <span
                v-if="dragEnabled && !row.readOnly"
                class="menu-drag-handle inline-flex cursor-grab items-center justify-center text-[var(--el-text-color-secondary)] active:cursor-grabbing"
                :data-menu-id="row.id"
                :title="t('systemMenu.dragTip', '拖动调整同级顺序')"
              >
                <i class="i-ep-rank text-lg" />
              </span>
              <span v-else class="text-xs text-[var(--el-text-color-secondary)]">—</span>
            </template>
          </el-table-column>
          <el-table-column type="selection" width="48" align="center" :selectable="(row: API.MenuItem) => !row.readOnly" />
          <el-table-column prop="name" :label="t('systemMenu.colName', '名称')" min-width="200" />
          <el-table-column :label="t('systemMenu.icon', '图标')" width="80" align="center">
            <template #default="{ row }">
              <SvgIcon v-if="row.icon" :name="row.icon" :size="18" />
            </template>
          </el-table-column>
          <el-table-column prop="path" :label="t('systemMenu.route', '路由')" min-width="180" />
          <el-table-column prop="permission" :label="t('systemMenu.permission', '权限标识')" min-width="220">
            <template #default="{ row }">
              <span>{{ row.permission || '—' }}</span>
              <el-tag v-if="row.orphaned" class="ml-2" size="small" type="danger">{{ t('systemMenu.orphaned', '孤儿资源') }}</el-tag>
              <el-tag v-else-if="row.readOnly" class="ml-2" size="small" type="info">{{ t('systemMenu.managed', '受管') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('systemMenu.type', '类型')" width="90" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="typeTag(row.type)">{{ typeText(row.type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort" :label="t('systemMenu.sort', '排序')" width="100" align="center">
            <template #default="{ row }">
              <InlineEdit
                v-if="!row.readOnly"
                :model-value="row.sort"
                type="number"
                :min="0"
                :max="999"
                :save="(v: number) => menuApi.update(row.id, { sort: v })"
                @update:model-value="row.sort = $event"
              />
              <span v-else>{{ row.sort }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('common.operation', '操作')" width="320" align="center" fixed="right">
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button v-if="!row.readOnly" size="small" type="primary" link v-perm="'systemmenu:create'" @click="onAdd(row as API.MenuItem)">
                  {{ t('systemMenu.addChild', '新增子项') }}
                </el-button>
                <el-button v-if="!row.readOnly" size="small" type="primary" link v-perm="'systemmenu:update'" @click="onEdit(row as API.MenuItem)">
                  {{ t('common.edit', '编辑') }}
                </el-button>
                <el-button v-if="!row.readOnly" size="small" type="danger" link v-perm="'systemmenu:delete'" @click="onDelete(row as API.MenuItem)">
                  {{ t('common.remove', '删除') }}
                </el-button>
                <el-button v-else-if="row.orphaned && row.removable" size="small" type="danger" link v-perm="'systemmenu:delete'" @click="onDelete(row as API.MenuItem)">
                  {{ t('systemMenu.cleanOrphan', '清理孤儿资源') }}
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
import { useI18n } from 'vue-i18n';
import Sortable from 'sortablejs';
import { menuApi } from '@/api/system/menu';
import { filterTree, listToTree, treeToList } from '@/utils/tree';
import InlineEdit from '@/components/InlineEdit/index.vue';
import MenuFormDialog from './components/MenuFormDialog.vue';

defineOptions({ name: 'SystemMenu' });

const { t } = useI18n();
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
  name: '',
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
    if (query.name && !node.name.includes(query.name)) return false;
    if (query.path && !(node.path || '').includes(query.path)) return false;
    if (query.hidden !== undefined && node.hidden !== query.hidden) return false;
    return true;
  })
);

/** 筛选或折叠时 DOM 与整树不一致，禁用拖拽避免错乱 */
const dragEnabled = computed(
  () =>
    !query.name?.trim() &&
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
      if (!node || node.readOnly) return;
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
    ElMessage.success(t('systemMenu.sortSaved', '排序已保存'));
  } catch {
    ElMessage.error(t('systemMenu.sortSaveFailed', '保存排序失败'));
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
      if (!a || !b || a.readOnly || b.readOnly) return false;
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
  query.name = '';
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
  return type === 'M' ? t('systemMenu.typeDir', '目录') : type === 'C' ? t('systemMenu.typePage', '页面') : t('systemMenu.typeUnknown', '未知');
}

function onAdd(parent?: API.MenuItem) {
  if (parent?.readOnly) return;
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
  const orphaned = row.orphaned && row.sourceType === 'generated';
  const message = orphaned
    ? t('systemMenu.deleteOrphanConfirm', { name: row.name }, { default: '确认清理孤儿资源 {name}？同一生成来源的菜单、权限与授权规则将一并删除。' })
    : t('systemMenu.deleteConfirm', { name: row.name }, { default: '确认删除 {name} ?' });
  await ElMessageBox.confirm(message, orphaned ? t('systemMenu.cleanOrphan', '清理孤儿资源') : t('common.tip', '提示'), { type: 'warning' });
  await menuApi.remove(row.id);
  await loadData();
}

function onSelectionChange(rows: API.MenuItem[]) {
  selection.value = rows.filter((row) => !row.readOnly);
}

/**
 * 树形批量删除：去除「已被选中父节点的子节点」，避免后端级联+前端重复删。
 * 算法：把所有选中节点的 ID 放进 set，遍历选中节点向上找 parentId，
 *      如果祖先链上任一节点已在 set 中，说明此节点是「冗余子节点」，跳过。
 */
async function onBatchDelete() {
  if (!selection.value.length) {
    ElMessage.warning(t('common.selectAtLeastOne', '请至少选择一项'));
    return;
  }
  const rows = selection.value.filter((row) => !row.readOnly);
  const selectedIds = new Set(rows.map((r) => r.id));
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

  const topIds = rows.map((r) => r.id).filter((id) => !hasSelectedAncestor(id));

  await ElMessageBox.confirm(
    t('systemMenu.batchDeleteConfirm', { selected: selection.value.length, n: topIds.length }, { default: '已选中 {selected} 项，去重后将删除 {n} 个顶层节点（含其子节点）。是否继续？' }),
    t('common.tip', '提示'),
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
