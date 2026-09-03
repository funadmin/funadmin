<template>
  <PageWrapper :title="t('systemDept.title')" :subtitle="t('systemDept.subtitle')">
    <DataTableShell storage-key="system-dept" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item :label="t('systemDept.name')" prop="name">
            <el-input v-model="query.name" :placeholder="t('systemDept.namePlaceholder')" clearable />
          </el-form-item>
          <el-form-item :label="t('systemDept.status')" prop="status">
            <el-select v-model="query.status" :placeholder="t('systemDept.pleaseSelect')" clearable class="!w-32">
              <el-option :label="t('systemDept.enabled')" :value="1" />
              <el-option :label="t('systemDept.disabled')" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:dept:add'" @click="onAdd()">
          <i class="i-ep-plus" /> {{ t('systemDept.add') }}
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:dept:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete mr-1" />
          {{ t('systemDept.batchDelete') + (selection.length ? `(${selection.length})` : '') }}
        </el-button>
        <el-button type="primary" plain @click="toggleExpand">
          <i :class="expandAll ? 'i-ep-fold' : 'i-ep-expand'" />
          {{ expandAll ? t('systemDept.collapse') : t('systemDept.expand') }}
        </el-button>
      </template>

      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table
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
          <el-table-column prop="name" :label="t('systemDept.name')" min-width="200" />
          <el-table-column prop="leader" :label="t('systemDept.colLeader')" min-width="120" />
          <el-table-column prop="phone" :label="t('systemDept.colPhone')" min-width="140" />
          <el-table-column prop="email" :label="t('systemDept.colEmail')" min-width="180" />
          <el-table-column prop="sort" :label="t('systemDept.colSort')" width="100" align="center">
            <template #default="{ row }">
              <InlineEdit
                :model-value="row.sort"
                type="number"
                :min="0"
                :max="999"
                :save="(v: number) => deptApi.update(row.id, { sort: v })"
                @update:model-value="row.sort = $event"
              />
            </template>
          </el-table-column>
          <el-table-column :label="t('systemDept.status')" width="90" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'">
                {{ row.status === 1 ? t('systemDept.enabled') : t('systemDept.disabled') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('systemDept.colActions')" width="280" align="center" fixed="right">
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button size="small" type="primary" link v-perm="'system:dept:add'" @click="onAdd(row as DeptModel)">
                  <i class="i-ep-plus" /> {{ t('systemDept.addChild') }}
                </el-button>
                <el-button size="small" type="primary" link v-perm="'system:dept:edit'" @click="onEdit(row as DeptModel)">
                  <i class="i-ep-edit" /> {{ t('systemDept.edit') }}
                </el-button>
                <el-button size="small" type="danger" link v-perm="'system:dept:delete'" @click="onDelete(row as DeptModel)">
                  <i class="i-ep-delete" /> {{ t('systemDept.delete') }}
                </el-button>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <DeptFormDialog
      v-model="dialogVisible"
      :row="current"
      :tree="tree"
      :default-parent-id="defaultParentId"
      @success="loadData"
    />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';

const { t } = useI18n();
import { deptApi, type DeptModel } from '@/api/system/dept';
import { filterTree, treeToList } from '@/utils/tree';
import InlineEdit from '@/components/InlineEdit/index.vue';
import DeptFormDialog from './components/DeptFormDialog.vue';

defineOptions({ name: 'SystemDept' });

const loading = ref(false);
const tree = ref<DeptModel[]>([]);
const selection = ref<DeptModel[]>([]);
const dialogVisible = ref(false);
const current = ref<DeptModel | null>(null);
const defaultParentId = ref(0);

const expandAll = ref(true);
const tableRenderKey = ref(0);

const query = reactive({
  name: '',
  status: undefined as number | undefined
});

const displayTree = computed(() =>
  filterTree(tree.value, (node) => {
    if (query.name && !node.name.includes(query.name)) return false;
    if (query.status !== undefined && node.status !== query.status) return false;
    return true;
  })
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
  query.status = undefined;
  loadData();
}

async function loadData() {
  loading.value = true;
  try {
    tree.value = await deptApi.tree();
    selection.value = [];
  } finally {
    loading.value = false;
  }
}

function onAdd(parent?: DeptModel) {
  current.value = null;
  defaultParentId.value = parent?.id || 0;
  dialogVisible.value = true;
}

function onEdit(row: DeptModel) {
  current.value = row;
  defaultParentId.value = row.parentId;
  dialogVisible.value = true;
}

async function onDelete(row: DeptModel) {
  if (row.children?.length) {
    ElMessage.warning(t('systemDept.hasChildrenWarn'));
    return;
  }
  await ElMessageBox.confirm(t('systemDept.deleteConfirm', { name: row.name }), t('layout.tip'), {
    type: 'warning'
  });
  await deptApi.remove(row.id);
  loadData();
}

function onSelectionChange(rows: DeptModel[]) {
  selection.value = rows;
}

/**
 * 树形批量删除：去除「已被选中父节点的子节点」，避免后端级联+前端重复删
 */
async function onBatchDelete() {
  if (!selection.value.length) {
    ElMessage.warning(t('systemDept.selectAtLeastOne'));
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
    t('systemDept.batchDeleteConfirm', { n: selection.value.length, m: topIds.length }),
    t('layout.tip'),
    { type: 'warning' }
  );
  await deptApi.removeMany(topIds);
  loadData();
}

onMounted(loadData);
</script>
