<template>
  <PageWrapper title="黑名单" subtitle="管理登录 IP/规则；启用且未删除的记录会参与后台登录拦截">
    <DataTableShell storage-key="system-blacklist" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="IP/规则" prop="ip">
            <el-input v-model="query.ip" placeholder="请输入 IP 或规则" clearable />
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
        <el-button :type="recycled ? 'default' : 'primary'" :plain="!recycled" @click="switchMode(false)">正常列表</el-button>
        <el-button :type="recycled ? 'primary' : 'default'" :plain="recycled" @click="switchMode(true)">回收站</el-button>
        <template v-if="!recycled">
          <el-button type="primary" plain v-perm="'system:blacklist:add'" @click="openAdd">
            <i class="i-ep-plus" /> 新增
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:blacklist:delete'" @click="moveToRecycle">
            <i class="i-ep-delete" /> 移入回收站{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button v-perm="'system:blacklist:import'" @click="fileInput?.click()">
            <i class="i-ep-upload" /> CSV 导入
          </el-button>
        </template>
        <template v-else>
          <el-button type="success" plain :disabled="!selection.length" v-perm="'system:blacklist:restore'" @click="restoreSelected">
            <i class="i-ep-refresh-left" /> 恢复{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:blacklist:destroy'" @click="destroySelected">
            <i class="i-ep-delete-filled" /> 永久删除{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
        </template>
        <el-button v-perm="'system:blacklist:export'" @click="exportRows">
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
          <el-table-column prop="id" label="ID" width="80" align="center" />
          <el-table-column prop="ip" label="IP/规则" min-width="180" />
          <el-table-column prop="remark" label="备注" min-width="220" show-overflow-tooltip />
          <el-table-column label="状态" width="130" align="center">
            <template #default="{ row }">
              <div v-if="!recycled" class="app-status-switch">
                <el-switch
                  size="small"
                  :model-value="row.status === 1"
                  :disabled="!hasPermission('system:blacklist:status')"
                  @change="(value: string | number | boolean) => toggleStatus(row as BlacklistModel, value === true)"
                />
                <span class="app-status-switch__text" :class="row.status === 1 ? 'is-on' : 'is-off'">
                  {{ row.status === 1 ? '启用' : '停用' }}
                </span>
              </div>
              <el-tag v-else :type="row.status === 1 ? 'success' : 'info'" size="small">
                {{ row.status === 1 ? '启用' : '停用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="createdAt" label="创建时间" width="170" />
          <el-table-column v-if="recycled" prop="deletedAt" label="删除时间" width="170" />
          <el-table-column v-if="!recycled" label="操作" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link v-perm="'system:blacklist:edit'" @click="openEdit(row as BlacklistModel)">编辑</el-button>
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

    <BlacklistFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { blacklistApi, type BlacklistModel, type BlacklistQuery } from '@/api/system/blacklist';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import { useUserStore } from '@/store/modules/user';
import BlacklistFormDialog from './components/BlacklistFormDialog.vue';

defineOptions({ name: 'SystemBlacklist' });

const userStore = useUserStore();
const loading = ref(false);
const list = ref<BlacklistModel[]>([]);
const total = ref(0);
const selection = ref<BlacklistModel[]>([]);
const recycled = ref(false);
const dialogVisible = ref(false);
const current = ref<BlacklistModel | null>(null);
const fileInput = ref<HTMLInputElement>();
const query = reactive<BlacklistQuery>({ page: 1, pageSize: 20, ip: '', status: undefined, recycled: 0 });

const csvColumns: CsvColumn<Record<string, any>>[] = [
  { key: 'ip', label: 'IP/规则' },
  { key: 'remark', label: '备注' },
  { key: 'status', label: '状态', parser: (value) => (['1', '启用', '开启'].includes(value.trim()) ? 1 : 0) }
];

function hasPermission(permission: string) {
  return userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === permission);
}

async function loadData() {
  loading.value = true;
  try {
    const result = await blacklistApi.list(query);
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
  Object.assign(query, { page: 1, pageSize: 20, ip: '', status: undefined, recycled: recycled.value ? 1 : 0 });
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

function openEdit(row: BlacklistModel) {
  current.value = row;
  dialogVisible.value = true;
}

async function toggleStatus(row: BlacklistModel, enabled: boolean) {
  const previous = row.status;
  row.status = enabled ? 1 : 0;
  try {
    await blacklistApi.updateStatus(row.id, row.status);
  } catch (error) {
    row.status = previous;
    throw error;
  }
}

async function moveToRecycle() {
  await ElMessageBox.confirm(`确认将选中的 ${selection.value.length} 条记录移入回收站吗？`, '操作确认', { type: 'warning' });
  await blacklistApi.removeMany(selection.value.map((item) => item.id));
  await loadData();
}

async function restoreSelected() {
  await blacklistApi.restore(selection.value.map((item) => item.id));
  await loadData();
}

async function destroySelected() {
  await ElMessageBox.confirm(
    `确认永久删除选中的 ${selection.value.length} 条记录吗？此操作不可恢复。`,
    '永久删除确认',
    { type: 'error', confirmButtonText: '永久删除' }
  );
  await blacklistApi.destroy(selection.value.map((item) => item.id));
  await loadData();
}

async function importCsv(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  const rows = parseCsv<Record<string, any>>(await readFileAsText(file), csvColumns).map((row) => ({
    ip: String(row.ip ?? ''),
    remark: String(row.remark ?? ''),
    status: Number(row.status) === 1 ? (1 as const) : (0 as const)
  }));
  const result = await blacklistApi.importRows(rows);
  if (result.skipped) {
    await ElMessageBox.alert(result.errors.slice(0, 20).join('\n'), `导入完成：成功 ${result.created}，跳过 ${result.skipped}`, {
      type: 'warning'
    });
  } else {
    ElMessage.success(`成功导入 ${result.created} 条`);
  }
  await loadData();
}

async function exportRows() {
  const rows = await blacklistApi.exportRows({ ip: query.ip, status: query.status, recycled: query.recycled });
  const columns: CsvColumn<BlacklistModel>[] = [
    { key: 'ip', label: 'IP/规则' },
    { key: 'remark', label: '备注' },
    { key: 'status', label: '状态', formatter: (row) => (row.status === 1 ? '启用' : '停用') },
    { key: 'createdAt', label: '创建时间' },
    { key: 'deletedAt', label: '删除时间' }
  ];
  downloadCsv(`blacklist-${recycled.value ? 'recycle' : 'active'}`, toCsv(rows, columns));
}

onMounted(loadData);
</script>
