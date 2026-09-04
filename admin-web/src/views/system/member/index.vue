<template>
  <PageWrapper title="会员管理" subtitle="维护前台会员资料、分组和等级；后台新建会员默认无登录密码">
    <DataTableShell storage-key="system-member" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="关键词" prop="keyword">
            <el-input v-model="query.keyword" placeholder="用户名/手机号/邮箱" clearable />
          </el-form-item>
          <el-form-item label="会员组" prop="groupId">
            <el-select v-model="query.groupId" placeholder="全部" clearable class="!w-36">
              <el-option v-for="item in options.groups" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="会员等级" prop="levelId">
            <el-select v-model="query.levelId" placeholder="全部" clearable class="!w-36">
              <el-option v-for="item in options.levels" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
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
        <el-button :type="recycled ? 'default' : 'primary'" plain @click="switchMode(false)">正常列表</el-button>
        <el-button :type="recycled ? 'primary' : 'default'" plain @click="switchMode(true)">回收站</el-button>
        <template v-if="!recycled">
          <el-button type="primary" plain v-perm="'system:member:add'" @click="openAdd">
            <i class="i-ep-plus" /> 新增
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:member:delete'" @click="recycleSelected">
            <i class="i-ep-delete" /> 移入回收站{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button plain v-perm="'system:member:import'" @click="fileInput?.click()">
            <i class="i-ep-upload" /> CSV 导入
          </el-button>
        </template>
        <template v-else>
          <el-button type="success" plain :disabled="!selection.length" v-perm="'system:member:restore'" @click="restoreSelected">
            <i class="i-ep-refresh-left" /> 恢复{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:member:destroy'" @click="destroySelected">
            <i class="i-ep-delete-filled" /> 永久删除{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
        </template>
        <el-button plain v-perm="'system:member:export'" @click="exportRows">
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
          <el-table-column label="会员" min-width="180">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <el-avatar :size="34" :src="row.avatar">{{ row.username.slice(0, 1).toUpperCase() }}</el-avatar>
                <div class="min-w-0">
                  <div class="truncate font-medium">{{ row.username }}</div>
                  <div class="truncate text-xs text-[var(--el-text-color-secondary)]">{{ row.mobile }}</div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="email" label="邮箱" min-width="180">
            <template #default="{ row }">{{ row.email || '-' }}</template>
          </el-table-column>
          <el-table-column label="性别" width="80" align="center">
            <template #default="{ row }">{{ sexText(row.sex) }}</template>
          </el-table-column>
          <el-table-column label="会员组" min-width="170">
            <template #default="{ row }">
              <el-tag v-for="name in row.groupNames" :key="name" size="small" class="mr-1">{{ name }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="levelName" label="会员等级" min-width="120" />
          <el-table-column label="状态" width="130" align="center">
            <template #default="{ row }">
              <div v-if="!recycled" class="app-status-switch">
                <el-switch
                  size="small"
                  :model-value="row.status === 1"
                  :disabled="!hasPermission('system:member:status')"
                  @change="(value: string | number | boolean) => toggleStatus(row as MemberModel, value === true)"
                />
                <span class="app-status-switch__text" :class="row.status === 1 ? 'is-on' : 'is-off'">{{ row.status === 1 ? '启用' : '停用' }}</span>
              </div>
              <el-tag v-else :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="loginCount" label="登录次数" width="95" align="center" />
          <el-table-column prop="createdAt" label="注册时间" width="170" />
          <el-table-column v-if="recycled" prop="deletedAt" label="删除时间" width="170" />
          <el-table-column v-if="!recycled" label="操作" width="120" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link v-perm="'system:member:edit'" @click="openEdit(row as MemberModel)">编辑</el-button>
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
    <MemberFormDialog v-model="dialogVisible" :row="current" :options="options" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  memberApi,
  type MemberImportResult,
  type MemberModel,
  type MemberOptions,
  type MemberPayload,
  type MemberQuery
} from '@/api/system/member';
import { useUserStore } from '@/store/modules/user';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import MemberFormDialog from './components/MemberFormDialog.vue';

defineOptions({ name: 'SystemMember' });

const userStore = useUserStore();
const loading = ref(false);
const list = ref<MemberModel[]>([]);
const total = ref(0);
const selection = ref<MemberModel[]>([]);
const recycled = ref(false);
const dialogVisible = ref(false);
const current = ref<MemberModel | null>(null);
const fileInput = ref<HTMLInputElement>();
const options = reactive<MemberOptions>({ groups: [], levels: [] });
const query = reactive<MemberQuery>({
  page: 1,
  pageSize: 20,
  keyword: '',
  status: undefined,
  groupId: undefined,
  levelId: undefined,
  recycled: 0
});

const csvColumns: CsvColumn<any>[] = [
  { key: 'username', label: '用户名' },
  { key: 'mobile', label: '手机号' },
  { key: 'email', label: '邮箱' },
  { key: 'sex', label: '性别' },
  { key: 'groupIds', label: '会员组ID', parser: (raw) => raw.split(/[,，]/).map(Number).filter((id) => id > 0) },
  { key: 'levelId', label: '会员等级ID', parser: (raw) => Number(raw) },
  { key: 'avatar', label: '头像' },
  { key: 'status', label: '状态', parser: (raw) => (raw === '0' || raw === '停用' ? 0 : 1) }
];

function hasPermission(permission: string) {
  return userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === permission);
}

function sexText(sex: MemberModel['sex']) {
  return sex === '1' ? '男' : sex === '2' ? '女' : '保密';
}

async function loadOptions() {
  const result = await memberApi.options();
  options.groups = result.groups;
  options.levels = result.levels;
}

async function loadData() {
  loading.value = true;
  try {
    const result = await memberApi.list(query);
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
  Object.assign(query, {
    page: 1,
    pageSize: 20,
    keyword: '',
    status: undefined,
    groupId: undefined,
    levelId: undefined,
    recycled: recycled.value ? 1 : 0
  });
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

function openEdit(row: MemberModel) {
  current.value = row;
  dialogVisible.value = true;
}

async function toggleStatus(row: MemberModel, enabled: boolean) {
  const previous = row.status;
  row.status = enabled ? 1 : 0;
  try {
    await memberApi.updateStatus(row.id, row.status);
  } catch (error) {
    row.status = previous;
    throw error;
  }
}

async function recycleSelected() {
  await ElMessageBox.confirm(`确认将选中的 ${selection.value.length} 个会员移入回收站吗？`, '操作确认', { type: 'warning' });
  await memberApi.recycle(selection.value.map((item) => item.id));
  await loadData();
}

async function restoreSelected() {
  await memberApi.restore(selection.value.map((item) => item.id));
  await loadData();
}

async function destroySelected() {
  await ElMessageBox.confirm(`确认永久删除选中的 ${selection.value.length} 个会员吗？此操作不可恢复。`, '永久删除确认', {
    type: 'error',
    confirmButtonText: '永久删除'
  });
  await memberApi.destroy(selection.value.map((item) => item.id));
  await loadData();
}

async function importCsv(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  const parsed = parseCsv<Partial<MemberPayload>>(await readFileAsText(file), csvColumns);
  if (!parsed.length) {
    ElMessage.warning('CSV 中没有可导入的数据');
    return;
  }
  const result: MemberImportResult = await memberApi.importRows(parsed);
  if (result.errors.length) {
    ElMessage.warning(`成功 ${result.created} 条，跳过 ${result.skipped} 条：${result.errors.slice(0, 3).join('；')}`);
  }
  await loadData();
}

async function exportRows() {
  const rows = await memberApi.exportRows({
    keyword: query.keyword,
    status: query.status,
    groupId: query.groupId,
    levelId: query.levelId,
    recycled: query.recycled
  });
  const columns: CsvColumn<MemberModel>[] = [
    { key: 'id', label: 'ID' },
    { key: 'username', label: '用户名' },
    { key: 'mobile', label: '手机号' },
    { key: 'email', label: '邮箱' },
    { key: 'sex', label: '性别' },
    { key: 'groupIds', label: '会员组ID', formatter: (row) => row.groupIds.join(',') },
    { key: 'groupNames', label: '会员组', formatter: (row) => row.groupNames.join(',') },
    { key: 'levelId', label: '会员等级ID' },
    { key: 'levelName', label: '会员等级' },
    { key: 'avatar', label: '头像' },
    { key: 'status', label: '状态', formatter: (row) => (row.status === 1 ? '启用' : '停用') },
    { key: 'loginCount', label: '登录次数' },
    { key: 'lastLoginAt', label: '最后登录时间' },
    { key: 'lastLoginIp', label: '最后登录IP' },
    { key: 'createdAt', label: '注册时间' },
    { key: 'deletedAt', label: '删除时间' }
  ];
  downloadCsv(`members-${recycled.value ? 'recycle' : 'active'}`, toCsv(rows, columns));
}

onMounted(async () => {
  await loadOptions();
  await loadData();
});
</script>
