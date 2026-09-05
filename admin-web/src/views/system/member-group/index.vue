<template>
  <PageWrapper title="会员组" subtitle="维护会员分组；默认组和仍被会员引用的分组不能删除">
    <DataTableShell storage-key="system-member-group" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="组名" prop="name">
            <el-input v-model="query.name" placeholder="请输入会员组名称" clearable />
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-select v-model="query.status" placeholder="全部" clearable class="!w-28">
              <el-option label="启用" :value="1" />
              <el-option label="停用" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button :type="recycled ? 'info' : 'primary'" plain @click="switchMode(false)">正常列表</el-button>
        <el-button :type="recycled ? 'warning' : 'info'" plain @click="switchMode(true)">回收站</el-button>
        <template v-if="!recycled">
          <el-button type="primary" plain v-perm="'system:member-group:add'" @click="openAdd">
            <i class="i-ep-plus" /> 新增
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:member-group:delete'" @click="recycleSelected">
            <i class="i-ep-delete" /> 移入回收站{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
        </template>
        <template v-else>
          <el-button type="success" plain :disabled="!selection.length" v-perm="'system:member-group:restore'" @click="restoreSelected">
            <i class="i-ep-refresh-left" /> 恢复{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:member-group:destroy'" @click="destroySelected">
            <i class="i-ep-delete-filled" /> 永久删除{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
        </template>
        <el-button type="success" plain v-perm="'system:member-group:import'" @click="fileInput?.click()">
          <i class="i-ep-upload" /> CSV 导入
        </el-button>
        <el-button type="info" plain v-perm="'system:member-group:export'" @click="exportRows">
          <i class="i-ep-download" /> CSV 导出
        </el-button>
        <input ref="fileInput" class="hidden" type="file" accept=".csv,text/csv" @change="importCsv" />
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table
          :data="list"
          v-loading="loading"
          :size="size"
          :stripe="stripe"
          :border="border"
          :header-cell-style="headerCellStyle"
          @selection-change="selection = $event"
        >
          <el-table-column type="selection" width="48" align="center" :selectable="(row: MemberGroupModel) => row.id !== 1" />
          <el-table-column prop="id" label="ID" width="80" align="center" />
          <el-table-column prop="name" label="会员组名称" min-width="180" />
          <el-table-column label="图标" width="70" align="center">
            <template #default="{ row }">
              <i v-if="row.icon" :class="row.icon" class="text-base" />
            </template>
          </el-table-column>
          <el-table-column label="默认组" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="row.id === 1 ? 'success' : 'info'" size="small">{{ row.id === 1 ? '是' : '否' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <div v-if="!recycled" class="app-status-switch">
                <el-switch
                  size="small"
                  :model-value="row.status === 1"
                  :disabled="!hasPermission('system:member-group:status')"
                  @change="(value: string | number | boolean) => toggleStatus(row as MemberGroupModel, value === true)"
                />
              </div>
              <el-tag v-else :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="createdAt" label="创建时间" width="170" />
          <el-table-column v-if="recycled" prop="deletedAt" label="删除时间" width="170" />
          <el-table-column v-if="!recycled" label="操作" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link v-perm="'system:member-group:edit'" @click="openEdit(row as MemberGroupModel)">编辑</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-4 flex justify-end">
          <el-pagination
            v-model:current-page="query.page"
            v-model:page-size="query.pageSize"
            :total="total"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            @change="loadData"
          />
        </div>
      </template>
    </DataTableShell>
    <MemberGroupFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { memberGroupApi, type MemberGroupImportRow, type MemberGroupModel, type MemberGroupQuery } from '@/api/system/memberGroup';
import { useUserStore } from '@/store/modules/user';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import MemberGroupFormDialog from './components/MemberGroupFormDialog.vue';

defineOptions({ name: 'SystemMemberGroup' });

const userStore = useUserStore();
const loading = ref(false);
const list = ref<MemberGroupModel[]>([]);
const total = ref(0);
const selection = ref<MemberGroupModel[]>([]);
const recycled = ref(false);
const dialogVisible = ref(false);
const current = ref<MemberGroupModel | null>(null);
const fileInput = ref<HTMLInputElement>();
const query = reactive<MemberGroupQuery>({ page: 1, pageSize: 20, name: '', status: undefined, recycled: 0 });
const csvColumns: CsvColumn<MemberGroupImportRow>[] = [
  { key: 'name', label: '会员组名称' },
  { key: 'icon', label: '图标' },
  { key: 'status', label: '状态' }
];

function hasPermission(permission: string) {
  return userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === permission);
}

async function loadData() {
  loading.value = true;
  try {
    const result = await memberGroupApi.list(query);
    list.value = result.list;
    total.value = result.total;
    selection.value = [];
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  query.page = 1;
  loadData();
}

function onReset() {
  Object.assign(query, { page: 1, pageSize: 20, name: '', status: undefined, recycled: recycled.value ? 1 : 0 });
  loadData();
}

function switchMode(value: boolean) {
  recycled.value = value;
  query.recycled = value ? 1 : 0;
  query.page = 1;
  loadData();
}

function openAdd() {
  current.value = null;
  dialogVisible.value = true;
}

function openEdit(row: MemberGroupModel) {
  current.value = row;
  dialogVisible.value = true;
}

async function toggleStatus(row: MemberGroupModel, enabled: boolean) {
  const previous = row.status;
  row.status = enabled ? 1 : 0;
  try {
    await memberGroupApi.updateStatus(row.id, row.status);
  } catch (error) {
    row.status = previous;
    throw error;
  }
}

async function recycleSelected() {
  await ElMessageBox.confirm(`确认将选中的 ${selection.value.length} 个会员组移入回收站吗？`, '操作确认', { type: 'warning' });
  await memberGroupApi.recycle(selection.value.map((item) => item.id));
  await loadData();
}

async function restoreSelected() {
  await memberGroupApi.restore(selection.value.map((item) => item.id));
  await loadData();
}

async function destroySelected() {
  await ElMessageBox.confirm(`确认永久删除选中的 ${selection.value.length} 个会员组吗？此操作不可恢复。`, '永久删除确认', {
    type: 'error',
    confirmButtonText: '永久删除'
  });
  await memberGroupApi.destroy(selection.value.map((item) => item.id));
  await loadData();
}

async function importCsv(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  const rows: MemberGroupImportRow[] = parseCsv<MemberGroupImportRow>(await readFileAsText(file), csvColumns).map((row) => ({
    name: String(row.name ?? ''),
    icon: String(row.icon ?? ''),
    status: Number(row.status) === 0 ? (0 as const) : (1 as const)
  }));
  if (!rows.length) {
    ElMessage.warning('CSV 中没有可导入的数据');
    return;
  }
  await memberGroupApi.importRows(rows);
  await loadData();
}

async function exportRows() {
  const rows = await memberGroupApi.exportRows({ name: query.name, status: query.status, recycled: query.recycled });
  const columns: CsvColumn<MemberGroupModel>[] = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: '会员组名称' },
    { key: 'status', label: '状态', formatter: (row) => (row.status === 1 ? '启用' : '停用') },
    { key: 'createdAt', label: '创建时间' },
    { key: 'deletedAt', label: '删除时间' }
  ];
  downloadCsv(`member-groups-${recycled.value ? 'recycle' : 'active'}`, toCsv(rows, columns));
}

onMounted(loadData);
</script>
