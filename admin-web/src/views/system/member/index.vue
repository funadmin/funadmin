<template>
  <PageWrapper title="会员管理" subtitle="维护前台会员资料、分组和等级；后台新建会员默认无登录密码">
    <el-skeleton v-if="definitionLoading" :rows="5" animated />
    <div v-if="definitionError || listError" role="alert" class="mb-3">
      <el-alert :title="definitionError || listError" type="error" :closable="false" />
      <el-button @click="definitionError ? initialize() : loadData()">重试</el-button>
    </div>
    <SchemaTablePage v-if="pageSchema" storage-key="system-member" :schema="pageSchema" :query="query"
      :rows="list" :total="total" :loading="loading" :context="actionContext" :formatters="formatters"
      @refresh="loadData" @search="onSearch" @reset="onReset" @selection-change="selection = $event" @action-error="actionError">
      <template #toolbar-extra>
        <input ref="fileInput" class="hidden" type="file" accept=".csv,text/csv" @change="importCsv" />
      </template>
      <template #member="{ row }">
        <div class="flex items-center gap-2">
          <el-avatar :size="34" :src="row.avatar">{{ row.username.slice(0, 1).toUpperCase() }}</el-avatar>
          <div class="min-w-0">
            <div class="truncate font-medium">{{ row.username }}</div>
            <div class="truncate text-xs text-[var(--el-text-color-secondary)]">{{ row.mobile }}</div>
          </div>
        </div>
      </template>
      <template #groups="{ row }">
        <el-tag v-for="name in row.groupNames" :key="name" size="small" class="mr-1">{{ name }}</el-tag>
      </template>
      <template #status="{ row }">
        <div v-if="!recycled" class="app-status-switch">
          <el-switch size="small" :model-value="row.status === 1" :disabled="!hasPermission('system:member:status')"
            @change="(value: string | number | boolean) => toggleStatus(row as MemberModel, value === true)" />
        </div>
        <el-tag v-else :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
      </template>
    </SchemaTablePage>
    <MemberFormDialog v-model="dialogVisible" :row="current" :options="options" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import { parsePageSchema, type PageSchema, type PageHandler } from '@/components/DataTable/pageSchema';
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
const pageSchema = ref<PageSchema | null>(null);
const definitionLoading = ref(false);
const definitionError = ref('');
const listError = ref('');
let definitionRequest = 0;
let listRequest = 0;
const list = ref<MemberModel[]>([]);
const total = ref(0);
const selection = ref<MemberModel[]>([]);
const recycled = ref(false);
const dialogVisible = ref(false);
const current = ref<MemberModel | null>(null);
const fileInput = ref<HTMLInputElement>();
const options = reactive<MemberOptions>({ groups: [], levels: [], tags: [] });
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
  { key: 'tagIds', label: '会员标签ID', parser: (raw) => raw.split(/[,，]/).map(Number).filter((id) => id > 0) },
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

const formatters = { emptyText: (value: unknown) => value || '-', sexText };
const handlers: Record<string, PageHandler> = {
  normal: { version: '1', run: () => switchMode(false) },
  recycled: { version: '1', run: () => switchMode(true) },
  add: { version: '1', permission: 'system:member:add', available: () => !recycled.value, run: openAdd },
  edit: { version: '1', permission: 'system:member:edit', available: (row) => !recycled.value && !!row, run: openEdit },
  recycle: { version: '1', permission: 'system:member:delete', available: () => !recycled.value && !!selection.value.length, run: recycleSelected },
  restore: { version: '1', permission: 'system:member:restore', available: () => recycled.value && !!selection.value.length, run: restoreSelected },
  destroy: { version: '1', permission: 'system:member:destroy', available: () => recycled.value && !!selection.value.length, run: destroySelected },
  import: { version: '1', permission: 'system:member:import', available: () => !recycled.value, run: () => fileInput.value?.click() },
  export: { version: '1', permission: 'system:member:export', run: exportRows }
};
const actionContext = computed(() => ({ values: { recycled: recycled.value, selectionCount: selection.value.length }, permissions: userStore.permissions, handlers }));
function actionError(error: unknown) {
  if (error !== 'cancel' && error !== 'close') ElMessage.error('操作失败，请重试');
}
async function initialize() {
  const request = ++definitionRequest;
  definitionLoading.value = true;
  definitionError.value = '';
  pageSchema.value = null;
  try {
    const result = await memberApi.options();
    if (request !== definitionRequest) return;
    const schema = parsePageSchema(result.page);
    Object.assign(options, result);
    pageSchema.value = schema;
    query.pageSize = schema.pagination.pageSize;
    await loadData();
  } catch {
    if (request === definitionRequest) definitionError.value = '页面配置加载失败，请重试';
  } finally {
    if (request === definitionRequest) definitionLoading.value = false;
  }
}

async function loadData() {
  if (!pageSchema.value) return;
  const request = ++listRequest;
  loading.value = true;
  listError.value = '';
  selection.value = [];
  try {
    const result = await memberApi.list({ ...query });
    if (request !== listRequest) return;
    list.value = result.list;
    total.value = result.total;
  } catch {
    if (request !== listRequest) return;
    list.value = [];
    total.value = 0;
    listError.value = '列表加载失败，请重试';
  } finally {
    if (request === listRequest) loading.value = false;
  }
}

function onSearch() {
  query.page = 1;
  loadData();
}

function onReset() {
  Object.assign(query, {
    page: 1,
    pageSize: pageSchema.value?.pagination.pageSize ?? 20,
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
    { key: 'tagIds', label: '会员标签ID', formatter: (row) => row.tagIds.join(',') },
    { key: 'tagNames', label: '会员标签', formatter: (row) => row.tagNames.join(',') },
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

onMounted(initialize);
onBeforeUnmount(() => { definitionRequest++; listRequest++; });
</script>
