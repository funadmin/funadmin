<template>
  <PageWrapper :title="meta?.form.name || '已发布表单'" subtitle="当前构建使用已发布 FormSchema 运行时；生成源码将在下次前端构建后接管独立页面">
    <div class="flex flex-col gap-4 md:flex-row">
    <ListSourceTree v-if="meta?.schema.list?.leftTree?.enabled" :lock="buttonLock" :list="meta.schema.list" :permission-check="hasPermission" :can-read-form="hasPermission('console/form.data:lefttreeform')" :can-mutate="hasPermission('console/form.data:mutatelefttree')" :form-key="formKey" :schema-hash="meta.schemaHash" :config="meta.schema.list.leftTree" :model-value="leftSelection" :filter="{ ...filters, __leftTree: leftSelection }" @change="onLeftTree" @mutated="loadData" />
    <ListCategoryPanel v-if="meta?.schema.list?.category?.enabled && !meta?.schema.list?.leftTree?.enabled" :options="meta.categoryOptions ?? []" :model-value="filters.__category" @change="onCategory" />
    <SchemaTablePage ref="tableRef" class="min-w-0 flex-1" :storage-key="`published-form-${formKey}`" :schema="tableSchema" :query="query" :rows="rows" :total="total" :loading="loading" :context="{ values: {}, permissions: user.permissions, handlers: {} }" :lock="buttonLock" @refresh="loadData" @selection-change="onSelectionChange" @sort-change="onSortChange">
      <template #search>
        <SearchForm :model="filters" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item v-for="field in filterFields" :key="field.field_name" :label="field.label">
            <div v-if="field.list_filter === 'range'" class="flex gap-1"><el-input v-model="filters[field.field_name + '_from']" placeholder="最小值" /><el-input v-model="filters[field.field_name + '_to']" placeholder="最大值" /></div>
            <el-date-picker v-else-if="field.list_filter === 'date'" v-model="dateFilters[field.field_name]" type="daterange" value-format="YYYY-MM-DD" @change="syncDateFilter(field.field_name)" />
            <el-select v-else-if="['is_null', 'not_null'].includes(field.list_filter)" v-model="filters[field.field_name]" clearable><el-option label="启用" value="1" /></el-select>
            <el-input v-else v-model="filters[field.field_name]" clearable placeholder="请输入筛选值" />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar>
        <ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :lock="buttonLock" :refresh="loadData" :context="buttonContext('toolbar')" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" />
      </template>
      <template v-for="field in listFields" :key="field.field_name" #[field.field_name]="{ row }">
            <el-tag v-if="['switch', 'boolean'].includes(field.list_formatter)" :type="Number(row[field.field_name]) === 1 ? 'success' : 'info'">{{ Number(row[field.field_name]) === 1 ? '是' : '否' }}</el-tag>
            <el-tag v-else-if="field.list_formatter === 'tag'">{{ row[field.field_name] }}</el-tag>
            <el-image v-else-if="field.list_formatter === 'image'" :src="String(row[field.field_name] || '')" fit="cover" class="h-10 w-10 rounded" />
            <span v-else-if="['date', 'datetime', 'time'].includes(field.list_formatter)">{{ formatDate(row[field.field_name], field.list_formatter) }}</span>
            <span v-else-if="field.list_formatter === 'money'">￥{{ Number(row[field.field_name] || 0).toFixed(2) }}</span>
            <span v-else-if="field.list_formatter === 'number'">{{ Number(row[field.field_name] || 0).toLocaleString() }}</span>
            <el-link v-else-if="['link', 'email', 'phone'].includes(field.list_formatter)" :href="formatLink(field.list_formatter, row[field.field_name])" target="_blank">{{ row[field.field_name] }}</el-link>
            <code v-else-if="field.list_formatter === 'json'">{{ formatJson(row[field.field_name]) }}</code>
            <span v-else-if="field.list_formatter === 'percent'">{{ Number(row[field.field_name] || 0).toFixed(2) }}%</span>
            <span v-else>{{ row[field.field_name] }}</span>
      </template>
      <template #actions="{ row }">
        <ListButtonBar :buttons="rowButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="row" :fields="listFields.map(field => field.field_name)" :lock="buttonLock" :refresh="loadData" :context="buttonContext('row', row)" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" link />
      </template>
    </SchemaTablePage>
    </div>

    <el-dialog v-model="dialogVisible" :title="editingId !== null ? '编辑' : '新增'" width="720px" destroy-on-close>
      <SchemaRenderer
        v-if="meta"
        ref="schemaRendererRef"
        :form-key="formKey"
        :schema="meta.schema"
        :values="dialogValues"
        :request-keys="requestKeys"
        :action-handlers="actionHandlers"
      />
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="saving" @click="saveRow">保存</el-button></template>
    </el-dialog>
    <el-drawer v-model="detailVisible" title="详情" size="52%"><el-descriptions v-if="detail" :column="1" border><el-descriptions-item v-for="field in listFields" :key="field.field_name" :label="field.label">{{ detail.row[field.field_name] ?? '-' }}</el-descriptions-item></el-descriptions></el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';
import dayjs from 'dayjs';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi, type FormDataMeta, type FormFieldError, type FormRecordId } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import type { ActionHandlers, FormAction } from './runtime/actionExecutor';
import SchemaRenderer from './components/SchemaRenderer.vue';
import ListCategoryPanel from './components/ListCategoryPanel.vue';
import ListSourceTree from './components/ListSourceTree.vue';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import type { PageSchema } from '@/components/DataTable/pageSchema';
import ListButtonBar from './components/ListButtonBar.vue';
import { resolveListButtons } from './schema/listButtons';
import { provideListButtonAdapter, defaultListButtons, listActionKey, type ListButtonHandlers } from './runtime/listButtonHost';
import type { ListButtonContext } from './runtime/listButtonExecutor';
import type { FormListButton } from './schema/types';
import { useUserStore } from '@/store/modules/user';
import { mapFieldErrors } from './validation/asyncValidatorRegistry';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord
} from './runtime/submissionPolicy';

provideListButtonAdapter({ api: formDataApi, declaration: { catalogPermission: 'console/form.data:listactions', executePermission: 'console/form.data:listaction' } });
const user = useUserStore();
const hasPermission = (code: string) => user.permissions.some(p => p === '*' || p === '*:*:*' || p === code);
const buttonLock = reactive({ busy: false });
const toolbarButtons = computed(() => resolveListButtons(meta.value?.schema.list, 'toolbar', defaultListButtons('toolbar')));
const rowButtons = computed(() => resolveListButtons(meta.value?.schema.list, 'row', defaultListButtons('row')));
const copyAllowed = () => Boolean(meta.value && meta.value.schema.form?.readOnly !== true && hasPermission('console/form.data:create') && hasPermission('console/form.data:detail'));
const buttonAllowed = (button: FormListButton) => {
  const key = listActionKey(button);
  const routes: Record<string, string> = { create: 'create', edit: 'update', detail: 'detail', delete: 'remove', export: 'export', refresh: 'index' };
  return (key !== 'copyCreate' || copyAllowed()) && (!button.permission || hasPermission(button.permission)) && (!routes[key] || hasPermission(`console/form.data:${routes[key]}`)) && (key !== 'edit' || hasPermission('console/form.data:detail')) && (!['create', 'edit', 'delete'].includes(key) || meta.value?.schema.form?.readOnly !== true);
};
const buttonHandlers: ListButtonHandlers = { copyCreate: row => openDialog(row, true), create: () => openDialog(), edit: row => openDialog(row), detail: row => openDetail(row!), delete: row => removeRow(row!), export: () => onExport(), refresh: () => loadData() };
const selectedRows = ref<Record<string, unknown>[]>([]);
const tableRef = ref<{ clearSelection: () => void }>();
const buttonContextVersion = ref(0);
const onSelectionChange = (rows: Record<string, unknown>[]) => { selectedRows.value = rows; buttonContextVersion.value++; };
const clearSelection = () => { selectedRows.value = []; tableRef.value?.clearSelection(); buttonContextVersion.value++; };
const buttonContext = (location: 'row' | 'toolbar', row?: Record<string, unknown>): ListButtonContext => ({ formKey: formKey.value, schemaHash: meta.value?.schemaHash ?? '', location, ids: (row ? [row] : selectedRows.value).map(row => row[primaryKey.value] as FormRecordId), filter: { ...filters, __leftTree: leftSelection.value } });
const closeButtonHost = () => { dialogVisible.value = false; detailVisible.value = false; };
const route = useRoute();
const router = useRouter();
const formKey = computed(() => String(route.params.formKey || route.meta.formKey || route.query.formKey || ''));
const loading = ref(false);
const saving = ref(false);
const meta = ref<FormDataMeta | null>(null);
const rows = ref<Record<string, unknown>[]>([]);
const total = ref(0);
const query = reactive({ page: 1, pageSize: 20, sort: '', order: '' });
const filters = reactive<Record<string, string>>({});
const leftSelection = ref<FormRecordId[]>([]);
const onLeftTree = (values: FormRecordId[]) => { leftSelection.value = values; onSearch(); };
const dateFilters = reactive<Record<string, string[] | null>>({});
let dialogSequence = 0;
let detailSequence = 0;
const dialogVisible = ref(false);
const detailVisible = ref(false);
const detail = ref<{ row: Record<string, unknown> } | null>(null);
const editingId = ref<FormRecordId | null>(null);
const dialogValues = reactive<Record<string, unknown>>({});
const schemaRendererRef = ref<InstanceType<typeof SchemaRenderer>>();
const formFields = computed<FormFieldDef[]>(() => meta.value?.fields ?? []);
const readableFields = computed(() => formFields.value.filter(field => field.type !== 'password' && !field.control_props?.sensitive && !field.control_props?.writeOnly));
const listFields = computed(() => readableFields.value.filter(field => field.list_show === 1));
const filterFields = computed(() => readableFields.value.filter(field => field.list_filter !== ''));
const primaryKey = computed(() => meta.value?.primaryKey.name || 'id');
const treeEnabled = computed(() => meta.value?.schema.list?.tree?.enabled === true);
const tableSchema = computed<PageSchema>(() => ({ pageSchemaVersion: 1, key: 'published_form', primaryKey: primaryKey.value, search: [], toolbar: [], rowActions: [],
  list: { ...(meta.value?.schema.list?.tree ? { tree: meta.value.schema.list.tree } : {}), ...(meta.value?.schema.list?.tools ? { tools: meta.value.schema.list.tools } : {}) },
  columns: [
    ...(toolbarButtons.value.some(button => button.action.type === 'registered') ? [{ key: 'selection', label: '', type: 'selection' as const, width: 48 }] : []),
    { key: 'primary', prop: primaryKey.value, label: 'ID', width: 100, sortable: true },
    ...listFields.value.map(field => ({ key: field.field_name, prop: field.field_name, label: field.label, slot: field.field_name, ...(field.list_width ? { width: field.list_width } : {}), sortable: field.list_sort === 1 })),
    ...(rowButtons.value.some(button => !button.hidden && buttonAllowed(button)) ? [{ key: 'actions', label: '操作', slot: 'actions', width: 180, fixed: 'right' as const }] : [])
  ], pagination: { pageSize: 20, pageSizes: [10, 20, 50, 100], enabled: !treeEnabled.value }
}));
watch(() => JSON.stringify([filters, query, leftSelection.value]), clearSelection, { flush: 'sync' });
const onCategory = (value: string | number | undefined) => { if (value === undefined) delete filters.__category; else filters.__category = String(value); onSearch(); };
const requestKeys = computed(() => (meta.value?.schema.actions ?? []).flatMap((action) => {
  const record = action as { steps?: Array<{ type?: string; key?: string }> };
  return (record.steps ?? []).filter((step) => step.type === 'request' && step.key).map((step) => String(step.key));
}));
const actionHandlers: Partial<ActionHandlers> = {
  request: async (action, context) => {
    const request = action as FormAction & { key?: string; parameters?: Record<string, unknown> };
    if (!request.key) return;
    await formDataApi.action(formKey.value, request.key, { ...dialogValues, ...(request.parameters ?? {}) }, crypto.randomUUID(), context.signal as AbortSignal | undefined);
  },
  notify: async (action) => { ElMessage({ message: String(action.message ?? ''), type: String(action.tone ?? 'success') as 'success' | 'warning' | 'info' | 'error' }); },
  navigate: async (action) => { if (action.route) await router.push({ name: String(action.route), params: action.params as Record<string, string> | undefined, query: action.query as Record<string, string> | undefined }); },
  openDialog: async () => { dialogVisible.value = true; },
  submit: async () => { /* Schema submit 动作由当前保存生命周期统一提交。 */ },
  reset: async () => { Object.assign(dialogValues, emptyRuntimeValues(formFields.value)); }
};

let metaSequence = 0;
const loadMeta = async (isCurrent: () => boolean = () => true) => {
  if (!formKey.value) return false;
  const key = formKey.value;
  const sequence = ++metaSequence;
  const result = await formDataApi.meta(key);
  if (sequence !== metaSequence || key !== formKey.value || !isCurrent()) return false;
  meta.value = result;
  return true;
};
let dataSequence = 0;
onBeforeUnmount(() => { metaSequence++; dataSequence++; dialogSequence++; detailSequence++; });
const loadData = async () => {
  if (!formKey.value) return;
  const sequence = ++dataSequence;
  clearSelection();
  loading.value = true;
  try {
    if (!meta.value && !await loadMeta(() => sequence === dataSequence)) return;
    if (sequence !== dataSequence) return;
    const result = await formDataApi.index(formKey.value, { ...query, filters: { ...filters, __leftTree: leftSelection.value } });
    if (sequence !== dataSequence) return;
    rows.value = result.list;
    total.value = result.total;
  } finally {
    if (sequence === dataSequence) loading.value = false;
  }
};
const onSearch = () => { query.page = 1; void loadData(); };
const syncDateFilter = (field: string) => {
  const range = dateFilters[field];
  filters[`${field}_from`] = range?.[0] ?? '';
  filters[`${field}_to`] = range?.[1] ?? '';
};
const onSortChange = ({ prop, order }: { prop: string | null; order: string | null }) => {
  query.sort = prop ?? '';
  query.order = order === 'descending' ? 'desc' : 'asc';
  void loadData();
};
const onExport = async () => {
  const result = await formDataApi.export(formKey.value, { filters: { ...filters, __leftTree: leftSelection.value } });
  const blob = new Blob([JSON.stringify(result.list, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a'); anchor.href = url; anchor.download = `${formKey.value}.json`; anchor.click(); URL.revokeObjectURL(url);
};
const formatDate = (value: unknown, type: string) => value ? dayjs(String(value)).format(type === 'date' ? 'YYYY-MM-DD' : type === 'time' ? 'HH:mm:ss' : 'YYYY-MM-DD HH:mm:ss') : '';
const formatLink = (type: string, value: unknown) => type === 'email' ? `mailto:${String(value ?? '')}` : type === 'phone' ? `tel:${String(value ?? '')}` : /^https?:\/\//.test(String(value ?? '')) ? String(value) : '#';
const formatJson = (value: unknown) => { try { return JSON.stringify(typeof value === 'string' ? JSON.parse(value) : value); } catch { return String(value ?? ''); } };
const openDetail = async (row: Record<string, unknown>) => {
  const sequence = ++detailSequence;
  const identity = { formKey: formKey.value, id: row[primaryKey.value] as FormRecordId, schemaHash: meta.value?.schemaHash ?? '' };
  const result = await formDataApi.detail(identity.formKey, identity.id);
  if (sequence !== detailSequence || identity.formKey !== formKey.value || identity.schemaHash !== (meta.value?.schemaHash ?? '')) return;
  detail.value = result;
  detailVisible.value = true;
};
const onReset = () => { leftSelection.value = []; Object.keys(filters).forEach((key) => delete filters[key]); Object.keys(dateFilters).forEach((key) => delete dateFilters[key]); onSearch(); };
const refreshAfterWrite = async () => {
  try {
    await loadData();
  } catch {
    ElMessage.warning('操作已成功，但列表刷新失败，请手动刷新，不要重复提交');
  }
};
const decodeValue = (field: FormFieldDef, value: unknown) => {
  if (field.column_type !== 'json' || typeof value !== 'string' || value === '') return value;
  try {
    return JSON.parse(value);
  } catch {
    return value;
  }
};
const openDialog = async (row?: Record<string, unknown>, copyCreate = false) => {
  if (copyCreate && (!copyAllowed() || !row || !rows.value.some(item => item[primaryKey.value] === row[primaryKey.value]))) return;
  const sequence = ++dialogSequence;
  const key = formKey.value;
  if (!meta.value) await loadMeta(() => sequence === dialogSequence && key === formKey.value);
  if (sequence !== dialogSequence || key !== formKey.value || !meta.value) return;
  const identity = { formKey: key, id: row ? row[primaryKey.value] as FormRecordId : null, schemaHash: meta.value.schemaHash };
  const source = identity.id !== null ? await (copyCreate ? formDataApi.detail(identity.formKey, identity.id, true, identity.schemaHash) : formDataApi.detail(identity.formKey, identity.id)) : null;
  if (sequence !== dialogSequence || identity.formKey !== formKey.value || identity.schemaHash !== meta.value?.schemaHash || (copyCreate && !copyAllowed())) return;
  editingId.value = copyCreate ? null : identity.id;
  const record = sanitizeRuntimeRecord(formFields.value, source?.row ?? row ?? {});
  const values = { ...emptyRuntimeValues(formFields.value), ...record };
  if (!copyCreate) for (const [relation, child] of Object.entries(source?.children ?? {})) values[relation] = child.list;
  for (const field of formFields.value) values[field.field_name] = decodeValue(field, values[field.field_name]);
  Object.keys(dialogValues).forEach((key) => delete dialogValues[key]);
  Object.assign(dialogValues, values);
  dialogVisible.value = true;
};
const responseFieldErrors = (reason: unknown): FormFieldError[] => {
  if (!reason || typeof reason !== 'object') return [];
  const response = reason as { data?: { fieldErrors?: FormFieldError[] }; fieldErrors?: FormFieldError[] };
  return response.data?.fieldErrors ?? response.fieldErrors ?? [];
};
const isSchemaConflict = (reason: unknown) => {
  if (!reason || typeof reason !== 'object') return false;
  const response = reason as { code?: string | number; data?: { code?: string } };
  return response.code === 409 || response.data?.code === 'FORM_SCHEMA_CONFLICT';
};
const saveRow = async () => {
  if (saving.value || buttonLock.busy || !meta.value) return;
  saving.value = true;
  buttonLock.busy = true;
  const sequence = dialogSequence;
  const identity = JSON.stringify([formKey.value, editingId.value, meta.value.schemaHash]);
  try {
    await schemaRendererRef.value?.submit();
    if (sequence !== dialogSequence || identity !== JSON.stringify([formKey.value, editingId.value, meta.value?.schemaHash]) || !dialogVisible.value || !hasPermission(editingId.value === null ? 'console/form.data:create' : 'console/form.data:update')) return;
    const include = resolveSubmissionInclude({ schema_document: meta.value.schema });
    const payload = buildSubmissionPayload(formFields.value, dialogValues, include);
    const schemaHash = meta.value.schemaHash;
    if (editingId.value !== null) await formDataApi.update(formKey.value, editingId.value, payload, include, schemaHash);
    else await formDataApi.create(formKey.value, payload, include, schemaHash);
    if (sequence !== dialogSequence) return;
    dialogVisible.value = false;
    ElMessage.success('保存成功');
    await refreshAfterWrite();
  } catch (reason) {
    if (sequence !== dialogSequence) return;
    const errors = responseFieldErrors(reason);
    if (errors.length) await schemaRendererRef.value?.setFieldErrors(mapFieldErrors(errors));
    else if (isSchemaConflict(reason)) {
      dialogVisible.value = false;
      meta.value = null;
      await loadData();
      ElMessage.warning('表单已发布新版本，请重新填写');
    } else throw reason;
  } finally {
    saving.value = false;
    buttonLock.busy = false;
  }
};
const removeRow = async (row: Record<string, unknown>) => {
  const snapshot = JSON.stringify(buttonContext('row', row)); const version = buttonContextVersion.value;
  await ElMessageBox.confirm('确认删除该条数据？', '删除确认', { type: 'warning' });
  if (snapshot !== JSON.stringify(buttonContext('row', row)) || version !== buttonContextVersion.value || !hasPermission('console/form.data:remove')) return;
  await formDataApi.remove(formKey.value, row[primaryKey.value] as FormRecordId, meta.value?.schemaHash ?? '');
  ElMessage.success('删除成功');
  await refreshAfterWrite();
};

watch(formKey, async () => { metaSequence++; dataSequence++; clearSelection(); meta.value = null; rows.value = []; dialogVisible.value = false; detailVisible.value = false; await loadData(); });
// 初次元数据就绪允许继续打开，其余身份变化同步使旧请求失效。
watch(formKey, () => { metaSequence++; dataSequence++; dialogSequence++; detailSequence++; }, { flush: 'sync' });
watch(() => meta.value?.schemaHash, (_, previous) => { if (previous !== undefined) { dialogSequence++; detailSequence++; } }, { flush: 'sync' });
watch(dialogVisible, () => { dialogSequence++; }, { flush: 'sync' });
watch(detailVisible, () => { detailSequence++; }, { flush: 'sync' });
onMounted(loadData);
</script>
