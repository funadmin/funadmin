<template>
  <PageWrapper title="会员等级" subtitle="维护会员等级、升级金额与折扣；仍被会员引用的等级不能删除">
    <DataTableShell storage-key="system-member-level" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="等级名称" prop="name">
            <el-input v-model="query.name" placeholder="请输入等级名称" clearable />
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
          <el-button type="primary" plain v-perm="'system:member-level:add'" @click="openAdd">
            <i class="i-ep-plus" /> 新增
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:member-level:delete'" @click="recycleSelected">
            <i class="i-ep-delete" /> 移入回收站{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
        </template>
        <template v-else>
          <el-button type="success" plain :disabled="!selection.length" v-perm="'system:member-level:restore'" @click="restoreSelected">
            <i class="i-ep-refresh-left" /> 恢复{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:member-level:destroy'" @click="destroySelected">
            <i class="i-ep-delete-filled" /> 永久删除{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
        </template>
        <el-button type="success" plain v-perm="'system:member-level:import'" @click="fileInput?.click()">
          <i class="i-ep-upload" /> CSV 导入
        </el-button>
        <el-button type="info" plain v-perm="'system:member-level:export'" @click="exportRows">
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
          <el-table-column type="selection" width="48" align="center" />
          <el-table-column prop="id" label="ID" width="72" align="center" />
          <el-table-column prop="name" label="等级名称" min-width="130" />
          <el-table-column label="缩略图" width="92" align="center">
            <template #default="{ row }">
              <el-image v-if="row.thumb" :src="row.thumb" fit="cover" class="h-10 w-10 rounded" :preview-src-list="[row.thumb]" preview-teleported lazy />
              <span v-else class="text-xs text-[var(--el-text-color-secondary)]">无</span>
            </template>
          </el-table-column>
          <el-table-column prop="amount" label="等级金额" width="130" align="right" />
          <el-table-column label="等级折扣" width="110" align="center">
            <template #default="{ row }">{{ row.discount }}%</template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="80" align="center" />
          <el-table-column prop="description" label="描述" min-width="180" show-overflow-tooltip />
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <div v-if="!recycled" class="app-status-switch">
                <el-switch
                  size="small"
                  :model-value="row.status === 1"
                  :disabled="!hasPermission('system:member-level:status')"
                  @change="(value: string | number | boolean) => toggleStatus(row as MemberLevelModel, value === true)"
                />
              </div>
              <el-tag v-else :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="createdAt" label="创建时间" width="170" />
          <el-table-column v-if="recycled" prop="deletedAt" label="删除时间" width="170" />
          <el-table-column v-if="!recycled" label="操作" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link v-perm="'system:member-level:edit'" @click="openEdit(row as MemberLevelModel)">编辑</el-button>
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
    <MemberLevelFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { memberLevelApi, type MemberLevelModel, type MemberLevelPayload, type MemberLevelQuery } from '@/api/system/memberLevel';
import { useUserStore } from '@/store/modules/user';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import MemberLevelFormDialog from './components/MemberLevelFormDialog.vue';

defineOptions({ name: 'SystemMemberLevel' });

const userStore = useUserStore();
const loading = ref(false);
const list = ref<MemberLevelModel[]>([]);
const total = ref(0);
const selection = ref<MemberLevelModel[]>([]);
const recycled = ref(false);
const dialogVisible = ref(false);
const current = ref<MemberLevelModel | null>(null);
const fileInput = ref<HTMLInputElement>();
const query = reactive<MemberLevelQuery>({ page: 1, pageSize: 20, name: '', status: undefined, recycled: 0 });
const csvColumns: CsvColumn<MemberLevelPayload>[] = [
  { key: 'name', label: '等级名称' },
  { key: 'amount', label: '等级金额' },
  { key: 'discount', label: '等级折扣' },
  { key: 'thumb', label: '缩略图' },
  { key: 'status', label: '状态' },
  { key: 'sort', label: '排序' },
  { key: 'description', label: '描述' }
];

function hasPermission(permission: string) {
  return userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === permission);
}

async function loadData() {
  loading.value = true;
  try {
    const result = await memberLevelApi.list(query);
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

function openEdit(row: MemberLevelModel) {
  current.value = row;
  dialogVisible.value = true;
}

async function toggleStatus(row: MemberLevelModel, enabled: boolean) {
  const previous = row.status;
  row.status = enabled ? 1 : 0;
  try {
    await memberLevelApi.updateStatus(row.id, row.status);
  } catch (error) {
    row.status = previous;
    throw error;
  }
}

async function recycleSelected() {
  await ElMessageBox.confirm(`确认将选中的 ${selection.value.length} 个会员等级移入回收站吗？`, '操作确认', { type: 'warning' });
  await memberLevelApi.recycle(selection.value.map((item) => item.id));
  await loadData();
}

async function restoreSelected() {
  await memberLevelApi.restore(selection.value.map((item) => item.id));
  await loadData();
}

async function destroySelected() {
  await ElMessageBox.confirm(`确认永久删除选中的 ${selection.value.length} 个会员等级吗？此操作不可恢复。`, '永久删除确认', {
    type: 'error',
    confirmButtonText: '永久删除'
  });
  await memberLevelApi.destroy(selection.value.map((item) => item.id));
  await loadData();
}

async function importCsv(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  const rows = parseCsv<MemberLevelPayload>(await readFileAsText(file), csvColumns).map((row) => ({
    name: String(row.name ?? ''),
    amount: String(row.amount ?? '0'),
    discount: Number(row.discount ?? 100),
    thumb: String(row.thumb ?? ''),
    status: Number(row.status) === 0 ? 0 : 1,
    sort: Number(row.sort ?? 0),
    description: String(row.description ?? '')
  }));
  if (!rows.length) {
    ElMessage.warning('CSV 中没有可导入的数据');
    return;
  }
  await memberLevelApi.importRows(rows);
  await loadData();
}

async function exportRows() {
  const rows = await memberLevelApi.exportRows({ name: query.name, status: query.status, recycled: query.recycled });
  const columns: CsvColumn<MemberLevelModel>[] = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: '等级名称' },
    { key: 'amount', label: '等级金额' },
    { key: 'discount', label: '等级折扣' },
    { key: 'thumb', label: '缩略图' },
    { key: 'status', label: '状态', formatter: (row) => (row.status === 1 ? '启用' : '停用') },
    { key: 'sort', label: '排序' },
    { key: 'description', label: '描述' },
    { key: 'createdAt', label: '创建时间' },
    { key: 'deletedAt', label: '删除时间' }
  ];
  downloadCsv(`member-levels-${recycled.value ? 'recycle' : 'active'}`, toCsv(rows, columns));
}

onMounted(loadData);
</script>
