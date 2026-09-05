<template>
  <PageWrapper title="权限资源" subtitle="维护后端路由资源；角色授权与菜单绑定均引用此资源树">
    <DataTableShell storage-key="system-permission" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="资源名称" prop="name">
            <el-input v-model="query.name" placeholder="请输入资源名称" clearable />
          </el-form-item>
          <el-form-item label="资源标识" prop="resource">
            <el-input v-model="query.resource" placeholder="控制器、动作或权限标识" clearable />
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-select v-model="query.status" placeholder="请选择" clearable class="!w-32">
              <el-option label="启用" :value="1" />
              <el-option label="停用" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:permission:add'" @click="onAdd()">
          <i class="i-ep-plus" /> 新增
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:permission:delete'"
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
          @selection-change="selection = $event"
        >
          <el-table-column type="selection" width="48" align="center" />
          <el-table-column prop="name" label="资源名称" min-width="210" />
          <el-table-column label="类型" width="85" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="row.resourceType === 'group' ? 'info' : 'primary'">
                {{ row.resourceType === 'group' ? '目录' : '路由' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="code" label="权限标识" min-width="260">
            <template #default="{ row }">{{ row.code || '—' }}</template>
          </el-table-column>
          <el-table-column prop="sourceType" label="来源" width="110" align="center" />
          <el-table-column label="登录后共享" width="110" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="row.isPublic === 1 ? 'warning' : 'info'">
                {{ row.isPublic === 1 ? '是' : '否' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="85" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'">
                {{ row.status === 1 ? '启用' : '停用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="85" align="center" />
          <el-table-column label="操作" width="290" align="center" fixed="right">
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button type="primary" link v-perm="'system:permission:add'" @click="onAdd(asPermission(row))">
                  <i class="i-ep-plus" /> 新增子项
                </el-button>
                <el-button type="primary" link v-perm="'system:permission:edit'" @click="onEdit(asPermission(row))">
                  <i class="i-ep-edit" /> 编辑
                </el-button>
                <el-button type="danger" link v-perm="'system:permission:delete'" @click="onDelete(asPermission(row))">
                  <i class="i-ep-delete" /> 删除
                </el-button>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <PermissionFormDialog
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
import { ElMessage, ElMessageBox } from 'element-plus';
import { permissionApi, type PermissionModel } from '@/api/system/permission';
import { filterTree, listToTree, treeToList } from '@/utils/tree';
import PermissionFormDialog from './components/PermissionFormDialog.vue';

defineOptions({ name: 'SystemPermission' });

const loading = ref(false);
const tree = ref<PermissionModel[]>([]);
const selection = ref<PermissionModel[]>([]);
const dialogVisible = ref(false);
const current = ref<PermissionModel | null>(null);
const defaultParentId = ref(0);
const expandAll = ref(true);
const tableRenderKey = ref(0);

const query = reactive({
  name: '',
  resource: '',
  status: undefined as 0 | 1 | undefined
});

const normalizedTree = computed(() => {
  const flat = treeToList(tree.value).map((item) => ({ ...item, children: undefined }));
  flat.sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));
  return listToTree(flat, { idKey: 'id', parentKey: 'parentId' }) as PermissionModel[];
});

const displayTree = computed(() =>
  filterTree(normalizedTree.value, (node: PermissionModel) => {
    if (query.name && !node.name.includes(query.name)) return false;
    if (query.resource) {
      const value = `${node.code} ${node.object} ${node.action}`.toLowerCase();
      if (!value.includes(query.resource.toLowerCase())) return false;
    }
    if (query.status !== undefined && node.status !== query.status) return false;
    return true;
  })
);

async function loadData() {
  loading.value = true;
  try {
    tree.value = await permissionApi.tree();
    selection.value = [];
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  // 筛选在树结构上本地执行，以保留命中节点的父级路径。
}

function onReset() {
  query.name = '';
  query.resource = '';
  query.status = undefined;
}

function asPermission(row: unknown): PermissionModel {
  return row as PermissionModel;
}

function onAdd(parent?: PermissionModel) {
  current.value = null;
  defaultParentId.value = parent?.id ?? 0;
  dialogVisible.value = true;
}

function onEdit(row: PermissionModel) {
  current.value = row;
  defaultParentId.value = row.parentId;
  dialogVisible.value = true;
}

async function onDelete(row: PermissionModel) {
  await ElMessageBox.confirm(`确认删除权限资源“${row.name}”吗？`, '删除确认', { type: 'warning' });
  await permissionApi.remove(row.id);
  await loadData();
}

async function onBatchDelete() {
  await ElMessageBox.confirm(`确认删除已选中的 ${selection.value.length} 个权限资源吗？`, '批量删除', {
    type: 'warning'
  });
  await permissionApi.removeMany(selection.value.map((item) => item.id));
  ElMessage.success('批量删除成功');
  await loadData();
}

function toggleExpand() {
  expandAll.value = !expandAll.value;
  tableRenderKey.value += 1;
}

onMounted(loadData);
</script>
