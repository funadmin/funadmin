<template>
  <PageWrapper :title="meta?.form.name ? `${meta.form.name} 数据` : '表单数据'" subtitle="元数据驱动通用列表；新增/编辑为弹窗，详情为抽屉">
    <div class="flex flex-col gap-4 md:flex-row">
    <ListSourceTree v-if="meta?.schema.list?.leftTree?.enabled" :lock="buttonLock" :form-key="formKey" :schema-hash="meta.schemaHash" :config="meta.schema.list.leftTree" :list="meta.schema.list" :permission-check="hasPermission" :can-read-form="hasPermission('form.data:index')" :can-mutate="hasPermission('form.data:index')" :model-value="leftSelection" @change="onLeftTree" @mutated="loadData" />
    <ListCategoryPanel v-if="meta?.schema.list?.category?.enabled && !meta?.schema.list?.leftTree?.enabled" :options="meta.categoryOptions ?? []" :model-value="filters.__category" @change="onCategory" />
    <SchemaTablePage ref="tableRef" class="min-w-0 flex-1" :storage-key="`form-data-${formKey}`" :schema="tableSchema" :query="query" :rows="rows" :total="total" :loading="loading" :context="{ values: {}, permissions: user.permissions, handlers: {} }" :lock="buttonLock" @refresh="loadData" @selection-change="onSelectionChange" @sort-change="onSortChange">
      <template v-if="meta?.schema.list?.tools?.search !== false" #search>
        <SearchForm :model="filters" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item v-for="field in filterFields" :key="field.field_name" :label="field.label" :prop="field.field_name">
            <template v-if="field.list_filter === 'range'">
              <div class="flex gap-1">
                <el-input v-model="filters[field.field_name + '_from']" placeholder="最小值" class="w-[110px]" />
                <el-input v-model="filters[field.field_name + '_to']" placeholder="最大值" class="w-[110px]" />
              </div>
            </template>
            <el-date-picker
              v-else-if="field.list_filter === 'date'"
              v-model="dateFilters[field.field_name]"
              type="daterange"
              value-format="YYYY-MM-DD"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              @change="syncDateFilter(field.field_name)"
            />
            <el-select
              v-else-if="field.list_filter === 'is_null' || field.list_filter === 'not_null'"
              v-model="filters[field.field_name]"
              placeholder="请选择"
              clearable
              class="w-[160px]"
            >
              <el-option label="启用" value="1" />
            </el-select>
            <el-select v-else-if="isDynamicFieldOptions(optionNode(field)) && ['eq', 'neq', '='].includes(field.list_filter)" v-model="filters[field.field_name]" :loading="filterOptions.pending.value[field.field_name]" :placeholder="filterOptions.placeholder(field.field_name) || '请选择'" clearable>
              <el-option v-for="option in resolveFieldOptions(optionNode(field), filterOptions.supplied.value)" :key="String(option.value)" :label="option.label" :value="option.value as string | number" />
            </el-select>
            <el-input v-else v-model="filters[field.field_name]" :placeholder="filterPlaceholder(field.list_filter)" clearable class="w-[180px]" />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar>
        <el-alert v-if="recycled" title="回收站视图：仅显示已删除记录，可恢复或永久删除" type="warning" :closable="false" class="mb-2" />
        <ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :lock="buttonLock" :refresh="loadData" :context="buttonContext('toolbar')" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" />
      </template>
      <template v-for="field in listFields" :key="field.field_name" #[field.field_name]="{ row }">
            <template v-if="field.relation_type === 'belongs_to'">{{ row['__label_' + field.field_name] ?? row[field.field_name] }}</template>
            <el-tag v-else-if="field.list_formatter === 'switch' || field.list_formatter === 'boolean'" :type="isTruthy(row[field.field_name]) ? 'success' : 'info'" size="small">
              {{ presentField(field, row) }}
            </el-tag>
            <el-tag v-else-if="field.list_formatter === 'tag'" size="small">{{ presentField(field, row) }}</el-tag>
            <el-image
              v-else-if="field.list_formatter === 'image' && imageUrls(row[field.field_name]).length"
              :src="imageUrls(row[field.field_name])[0]"
              :preview-src-list="imageUrls(row[field.field_name])"
              preview-teleported
              fit="cover"
              class="h-10 w-10 rounded"
            />
            <div v-else-if="field.list_formatter === 'images'" class="flex gap-1">
              <el-image
                v-for="url in imageUrls(row[field.field_name]).slice(0, 3)"
                :key="url"
                :src="url"
                :preview-src-list="imageUrls(row[field.field_name])"
                preview-teleported
                fit="cover"
                class="h-10 w-10 rounded"
              />
            </div>
            <span v-else-if="field.list_formatter === 'date'">{{ presentField(field, row) }}</span>
            <span v-else-if="field.list_formatter === 'datetime'">{{ presentField(field, row) }}</span>
            <span v-else-if="field.list_formatter === 'time'">{{ presentField(field, row) }}</span>
            <span v-else-if="field.list_formatter === 'money'">{{ presentField(field, row) }}</span>
            <span v-else-if="field.list_formatter === 'number'">{{ presentField(field, row) }}</span>
            <span v-else-if="field.list_formatter === 'percent'">{{ presentField(field, row) }}</span>
            <el-link v-else-if="field.list_formatter === 'link'" :href="safeUrl(row[field.field_name])" target="_blank" type="primary">{{ presentField(field, row) }}</el-link>
            <el-link v-else-if="field.list_formatter === 'email'" :href="`mailto:${String(row[field.field_name] ?? '')}`" type="primary">{{ presentField(field, row) }}</el-link>
            <el-link v-else-if="field.list_formatter === 'phone'" :href="`tel:${String(row[field.field_name] ?? '')}`" type="primary">{{ presentField(field, row) }}</el-link>
            <code v-else-if="field.list_formatter === 'json'">{{ presentField(field, row) }}</code>
            <span v-else>{{ presentField(field, row) }}</span>
      </template>
      <template #actions="{ row }">
            <ListButtonBar :buttons="rowButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="row" :fields="readableButtonFields" :lock="buttonLock" :refresh="loadData" :context="buttonContext('row', row)" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" link />
      </template>
    </SchemaTablePage>
    <FormDataImportDialog v-model="importVisible" :fields="formFields" @submit="onImportRows" />
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="editingId !== null ? '编辑' : '新增'" width="720px" :close-on-click-modal="false" :before-close="beforeDialogClose" destroy-on-close>
      <SchemaRenderer v-if="meta" ref="schemaRendererRef" :form-key="formKey" :schema="meta.schema" :values="dialogValues" />
      <template #footer>
        <el-button @click="requestDialogClose">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" title="详情" size="52%">
      <el-descriptions :column="1" border>
        <el-descriptions-item v-for="field in readableFields" :key="field.field_name" :label="field.label">
          {{ presentField(field, detail?.row ?? {}, detailOptions) }}
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ detail?.row?.created_at ?? '-' }}</el-descriptions-item>
      </el-descriptions>
      <template v-for="(child, relation) in detail?.children ?? {}" :key="relation">
        <el-divider content-position="left">{{ relation }}</el-divider>
        <el-table :data="child.list" border size="small">
          <el-table-column v-for="column in childColumns(child.list)" :key="column" :prop="column" :label="column" show-overflow-tooltip />
        </el-table>
      </template>
    </el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import type { ListButtonContext } from './runtime/listButtonExecutor';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi, type FormDataMeta, type FormFieldError, type FormRecordId } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import SchemaRenderer from './components/SchemaRenderer.vue';
import ListButtonBar from './components/ListButtonBar.vue';
import { resolveListButtons } from './schema/listButtons';
import { provideListButtonAdapter, defaultListButtons, listActionKey, listButtonState, type ListButtonHandlers } from './runtime/listButtonHost';
import type { FormListButton } from './schema/types';
import { useUserStore } from '@/store/modules/user';
import ListCategoryPanel from './components/ListCategoryPanel.vue';
import ListSourceTree from './components/ListSourceTree.vue';
import FormDataImportDialog from './components/FormDataImportDialog.vue';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import type { PageSchema } from '@/components/DataTable/pageSchema';
import { mapFieldErrors } from './validation/asyncValidatorRegistry';
import { formatFieldValue, resolveFieldOptions, useSuppliedFieldOptions, presentationNodes, isDynamicFieldOptions } from './runtime/fieldPresentation';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  isSensitiveField,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord,
  stableRuntimeValues
} from './runtime/submissionPolicy';

provideListButtonAdapter({ api: formDataApi, declaration: { catalogPermission: 'form.data:listactions', executePermission: 'form.data:listaction' } });
const user = useUserStore();
const hasPermission = (code: string) => user.permissions.some(permission => permission === '*' || permission === '*:*:*' || permission === code);
const buttonLock = reactive({ busy: false });
const recycled = ref(false);
const importVisible = ref(false);
const recycleCapable = computed(() => meta.value?.recycleCapable === true);
const RECYCLE_MODE_ONLY = new Set(['normal', 'restore', 'destroy']);
const NORMAL_MODE_ONLY = new Set(['recycle', 'create', 'import', 'export', 'batchDelete', 'edit', 'detail', 'delete', 'copyCreate']);
const modeVisible = (button: FormListButton) => {
  if (button.action.type !== 'builtin' && button.action.type !== 'refresh') return true;
  const key = listActionKey(button);
  if (['recycle', 'normal', 'restore', 'destroy'].includes(key) && !recycleCapable.value) return false;
  return recycled.value ? !NORMAL_MODE_ONLY.has(key) : !RECYCLE_MODE_ONLY.has(key);
};
const toolbarButtons = computed(() => resolveListButtons(meta.value?.schema.list, 'toolbar', defaultListButtons('toolbar')).filter(modeVisible));
const rowButtons = computed(() => resolveListButtons(meta.value?.schema.list, 'row', defaultListButtons('row')).filter(modeVisible));
const readableButtonFields = computed(() => [primaryKeyName.value, ...formFields.value.filter(field => field.form_show !== 0 && !['password', 'hidden'].includes(field.type) && !field.control_props?.sensitive && !field.control_props?.writeOnly).map(field => field.field_name)]);
const buttonAllowed = (button: FormListButton) => {
  const key = listActionKey(button);
  const route: Record<string, string> = { create: 'create', edit: 'update', detail: 'detail', delete: 'remove', export: 'export', refresh: 'index', batchDelete: 'batchremove', import: 'import', recycle: 'index', normal: 'index', restore: 'restore', destroy: 'destroy' };
  return (!button.permission || hasPermission(button.permission)) && (!route[key] || hasPermission(`form.data:${route[key]}`)) && (key !== 'edit' || hasPermission('form.data:detail')) && (!['create', 'edit', 'delete'].includes(key) || meta.value?.schema.form?.readOnly !== true);
};
const buttonHandlers: ListButtonHandlers = {
  create: () => openDialog(), edit: row => openDialog(row), detail: row => openDetail(row!), delete: row => onDelete(row!), export: () => onExport(), refresh: () => loadData(),
  recycle: async () => { recycled.value = true; await loadData(); },
  normal: async () => { recycled.value = false; await loadData(); },
  batchDelete: async () => {
    const ids = selectedRows.value.map(record => record[primaryKeyName.value] as FormRecordId);
    if (!ids.length) { ElMessage.warning('请先勾选需要删除的记录'); return; }
    await ElMessageBox.confirm(`确认删除选中的 ${ids.length} 条记录吗？`, '批量删除', { type: 'warning' });
    await formDataApi.batchRemove(formKey.value, ids, meta.value?.schemaHash ?? '');
    clearSelection();
    await loadData();
  },
  restore: async (row) => { await formDataApi.restore(formKey.value, [row![primaryKeyName.value] as FormRecordId], meta.value?.schemaHash ?? ''); await loadData(); },
  destroy: async (row) => {
    await ElMessageBox.confirm('永久删除后不可恢复，确认继续吗？', '永久删除', { type: 'warning' });
    await formDataApi.destroy(formKey.value, [row![primaryKeyName.value] as FormRecordId], meta.value?.schemaHash ?? '');
    await loadData();
  },
  import: () => { importVisible.value = true; }
};
const hasRowButtons = computed(() => rowButtons.value.some(button => !button.hidden && buttonAllowed(button)));
const route = useRoute();
const formKey = computed(() => String(route.params.key ?? ''));
const loading = ref(false);
const saving = ref(false);
const meta = ref<FormDataMeta | null>(null);
const rows = ref<Record<string, unknown>[]>([]);
const total = ref(0);
const query = reactive({ page: 1, pageSize: 20 });
const filters = reactive<Record<string, string>>({});
const leftSelection = ref<FormRecordId[]>([]);
const selectedRows = ref<Record<string, unknown>[]>([]);
const tableRef = ref<{ clearSelection: () => void }>();
const buttonContextVersion = ref(0);
const onSelectionChange = (selection: Record<string, unknown>[]) => { selectedRows.value = selection; buttonContextVersion.value++; };
const clearSelection = () => { selectedRows.value = []; tableRef.value?.clearSelection(); buttonContextVersion.value++; };
const closeButtonHost = async () => { if (dialogVisible.value) await requestDialogClose(); detailVisible.value = false; };
const buttonContext = (location: 'row' | 'toolbar', row?: Record<string, unknown>): ListButtonContext => ({
  formKey: formKey.value, schemaHash: meta.value?.schemaHash ?? '', location,
  ids: (row ? [row] : selectedRows.value).map(record => record[primaryKeyName.value] as FormRecordId),
  filter: Object.fromEntries(Object.entries(filters).filter(([name]) => actionFilterFields.value.has(name)))
});
const onLeftTree = (values: FormRecordId[]) => { leftSelection.value = values; onSearch(); };
const dateFilters = reactive<Record<string, [string, string] | undefined>>({});
const sort = reactive({ sort: '', order: '' });
watch(() => JSON.stringify([filters, leftSelection.value, query, sort]), () => clearSelection(), { flush: 'sync' });
const dialogVisible = ref(false);
const detailVisible = ref(false);
const editingId = ref<FormRecordId | null>(null);
const dialogValues = reactive<Record<string, any>>({});
const detail = ref<{ row: Record<string, unknown>; children: Record<string, { list: Record<string, unknown>[]; total: number }> } | null>(null);
const fieldOptions = useSuppliedFieldOptions(rows);
const filterOptions = useSuppliedFieldOptions(filters);
const detailOptions = useSuppliedFieldOptions(() => detailVisible.value && detail.value ? [detail.value.row] : []);
const optionNodes = computed(() => presentationNodes(formFields.value, meta.value?.schema.nodes));
const optionNode = (field: FormFieldDef) => optionNodes.value.find(node => node.field === field.field_name)!;
const loadOptions = () => {
  void fieldOptions.load(formKey.value, optionNodes.value.filter(node => listFields.value.some(field => field.field_name === node.field)));
  void filterOptions.load(formKey.value, optionNodes.value.filter(node => filterFields.value.some(field => field.field_name === node.field)));
  void detailOptions.load(formKey.value, optionNodes.value.filter(node => readableFields.value.some(field => field.field_name === node.field)));
};
const invalidateOptions = () => { fieldOptions.invalidate(); filterOptions.invalidate(); detailOptions.invalidate(); };
const schemaRendererRef = ref<InstanceType<typeof SchemaRenderer>>();
const dialogSnapshot = ref('');
let closeDialogAfterSave = false;
let saveSequence = 0;
let dialogSequence = 0;
let detailSequence = 0;

const formFields = computed<FormFieldDef[]>(() => meta.value?.fields ?? []);
const primaryKeyName = computed(() => meta.value?.primaryKey.name ?? 'id');
const treeEnabled = computed(() => meta.value?.schema.list?.tree?.enabled === true);
const tableSchema = computed<PageSchema>(() => ({
  pageSchemaVersion: 1, key: 'dynamic_form', primaryKey: primaryKeyName.value, search: [], toolbar: [], rowActions: [],
  list: { ...(meta.value?.schema.list?.tree ? { tree: meta.value.schema.list.tree } : {}), ...(meta.value?.schema.list?.tools ? { tools: meta.value.schema.list.tools } : {}) },
  columns: [
    ...(toolbarButtons.value.some(button => button.action.type === 'registered' || listActionKey(button) === 'batchDelete') ? [{ key: 'selection', label: '', type: 'selection' as const, width: 48 }] : []),
    { key: 'primary', prop: primaryKeyName.value, label: 'ID', width: 120 },
    ...listFields.value.map(field => ({ key: field.field_name, prop: field.field_name, label: field.label, slot: field.field_name, ...(field.list_width ? { width: field.list_width } : {}), sortable: field.list_sort === 1 })),
    { key: 'created', prop: 'created_at', label: '创建时间', width: 170, sortable: true },
    ...(hasRowButtons.value ? [{ key: 'actions', label: '操作', slot: 'actions', minWidth: 180, fixed: 'right' as const }] : [])
  ], pagination: { pageSize: 20, pageSizes: [10, 20, 50, 100], enabled: !treeEnabled.value }
}));
const onCategory = (value: string | number | undefined) => { if (value === undefined) delete filters.__category; else filters.__category = String(value); onSearch(); };
const listFields = computed(() => formFields.value.filter((f) => f.list_show === 1 && !isSensitiveField(f)));
const filterFields = computed(() => formFields.value.filter((f) => f.list_filter !== '' && f.type !== 'hidden' && !isSensitiveField(f)));

const actionFilterFields = computed(() => new Set(filterFields.value.filter(field => field.column_type && (!field.relation_type || field.relation_type === 'none')).flatMap(field => ['range', 'date'].includes(field.list_filter) ? [field.field_name + '_from', field.field_name + '_to'] : [field.field_name])));
const filterPlaceholder = (type: string) => ['in', 'not_in'].includes(type) ? '多个值用英文逗号分隔' : '请输入筛选值';
const syncDateFilter = (name: string) => {
  const range = dateFilters[name];
  filters[name + '_from'] = range?.[0] ?? '';
  filters[name + '_to'] = range?.[1] ?? '';
};
const readableFields = computed(() => formFields.value.filter(field => field.form_show !== 0 && !['password', 'hidden'].includes(field.type) && !field.control_props?.sensitive && !field.control_props?.writeOnly));
const presentField = (field: FormFieldDef, row: Record<string, unknown>, state = fieldOptions) => state.placeholder(field.field_name, row) || formatFieldValue(row[field.field_name], resolveFieldOptions(optionNode(field), state.forContext(row)), field.list_formatter);
const isTruthy = (value: unknown) => ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
const formatDate = (value: unknown, format: string) => formatFieldValue(value, [], format === 'YYYY-MM-DD' ? 'date' : format === 'HH:mm:ss' ? 'time' : 'datetime', 'data');
const formatNumber = (value: unknown, digits?: number, prefix = '', suffix = '') => formatFieldValue(value, [], prefix ? 'money' : suffix ? 'percent' : 'number', 'data');
const formatJson = (value: unknown) => formatFieldValue(value, [], 'json', 'data');
const parseArray = (value: unknown): unknown[] => {
  if (Array.isArray(value)) return value;
  if (typeof value !== 'string' || value === '') return [];
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [parsed];
  } catch {
    return value.split(',').map((item) => item.trim()).filter(Boolean);
  }
};
const imageUrls = (value: unknown) => parseArray(value)
  .map((item) => typeof item === 'string' ? item : String((item as Record<string, unknown>)?.url ?? ''))
  .filter(Boolean);
const safeUrl = (value: unknown) => {
  const url = String(value ?? '').trim();
  return /^(https?:\/\/|\/)/i.test(url) ? url : '#';
};

async function loadMeta() {
  const key = formKey.value;
  const sequence = ++metaSequence;
  invalidateOptions();
  const loaded = await formDataApi.meta(key);
  if (sequence !== metaSequence || key !== formKey.value) return false;
  meta.value = loaded;
  loadOptions();
  return true;
}
let dataSequence = 0;
let metaSequence = 0;
onBeforeUnmount(() => { metaSequence++; dataSequence++; saveSequence++; dialogSequence++; detailSequence++; });
async function loadData() {
  const sequence = ++dataSequence;
  clearSelection();
  loading.value = true;
  try {
    const data = await formDataApi.index(formKey.value, { ...query, ...sort, scope: recycled.value ? 'recycled' : 'normal', filters: { ...filters, __leftTree: leftSelection.value } });
    if (sequence !== dataSequence) return;
    rows.value = data.list;
    total.value = data.total;
  } finally {
    if (sequence === dataSequence) loading.value = false;
  }
}
const onSearch = () => {
  query.page = 1;
  loadData();
};
const onImportRows = async (importRows: Array<Record<string, unknown>>) => {
  await formDataApi.importRows(formKey.value, importRows, meta.value?.schemaHash ?? '');
  importVisible.value = false;
  await loadData();
};
const onReset = () => {
  leftSelection.value = [];
  for (const key of Object.keys(filters)) delete filters[key];
  for (const key of Object.keys(dateFilters)) delete dateFilters[key];
  onSearch();
};
const onSortChange = ({ prop, order }: { prop: string | null; order: 'ascending' | 'descending' | null }) => {
  sort.sort = order && prop ? prop : '';
  sort.order = order === 'descending' ? 'desc' : 'asc';
  loadData();
};

const isDialogDirty = () => dialogSnapshot.value !== stableRuntimeValues(dialogValues);
const beforeDialogClose = async (done: () => void) => {
  if (closeDialogAfterSave || !isDialogDirty()) {
    closeDialogAfterSave = false;
    done();
    return;
  }
  await ElMessageBox.confirm('确认放弃未保存的修改？', '离开确认', { type: 'warning' });
  done();
};
const requestDialogClose = () => beforeDialogClose(() => { dialogVisible.value = false; });
const decodeValue = (field: FormFieldDef, value: unknown) => {
  if (field.column_type !== 'json' || typeof value !== 'string' || value === '') return value;
  try {
    return JSON.parse(value);
  } catch {
    return value;
  }
};
const openDialog = async (row?: Record<string, unknown>) => {
  const sequence = ++dialogSequence;
  const identity = { formKey: formKey.value, id: row ? row[primaryKeyName.value] as FormRecordId : null, schemaHash: meta.value?.schemaHash ?? '' };
  const source = identity.id !== null ? await formDataApi.detail(identity.formKey, identity.id) : null;
  if (sequence !== dialogSequence || identity.formKey !== formKey.value || identity.schemaHash !== (meta.value?.schemaHash ?? '')) return;
  editingId.value = identity.id;
  const record = sanitizeRuntimeRecord(formFields.value, source?.row ?? row ?? {});
  const values = { ...emptyRuntimeValues(formFields.value), ...record };
  for (const [relation, child] of Object.entries(source?.children ?? {})) values[relation] = child.list;
  for (const field of formFields.value) values[field.field_name] = decodeValue(field, values[field.field_name]);
  for (const key of Object.keys(dialogValues)) delete dialogValues[key];
  Object.assign(dialogValues, values);
  dialogSnapshot.value = stableRuntimeValues(dialogValues);
  closeDialogAfterSave = false;
  dialogVisible.value = true;
};
const responseFieldErrors = (reason: unknown): FormFieldError[] => {
  if (!reason || typeof reason !== 'object') return [];
  const response = reason as { data?: { fieldErrors?: FormFieldError[] }; fieldErrors?: FormFieldError[] };
  return response.data?.fieldErrors ?? response.fieldErrors ?? [];
};
async function refreshAfterWrite() {
  const identity = formKey.value;
  const sequence = dataSequence + 1;
  try {
    await loadData();
  } catch {
    if (identity === formKey.value && sequence === dataSequence) ElMessage.warning('操作已成功，但列表刷新失败，请手动刷新，不要重复提交');
  }
}
async function onSave() {
  if (saving.value || buttonLock.busy) return;
  saving.value = true;
  buttonLock.busy = true;
  const saveSequenceValue = ++saveSequence;
  const identity = JSON.stringify([formKey.value, editingId.value, meta.value?.schemaHash]);
  try {
    await schemaRendererRef.value?.submit();
    if (saveSequenceValue !== saveSequence || identity !== JSON.stringify([formKey.value, editingId.value, meta.value?.schemaHash]) || !dialogVisible.value || !hasPermission(editingId.value === null ? 'form.data:create' : 'form.data:update')) return;
    const include = resolveSubmissionInclude(meta.value ? { schema_document: meta.value.schema } : null);
    const payload = buildSubmissionPayload(formFields.value, dialogValues, include, hasPermission);
    const schemaHash = meta.value?.schemaHash ?? '';
    if (editingId.value !== null) await formDataApi.update(formKey.value, editingId.value, payload, include, schemaHash);
    else await formDataApi.create(formKey.value, payload, include, schemaHash);
    if (saveSequenceValue !== saveSequence) return;
    closeDialogAfterSave = true;
    dialogVisible.value = false;
    ElMessage.success('保存成功');
    await refreshAfterWrite();
  } catch (reason) {
    if (saveSequenceValue !== saveSequence) return;
    const errors = responseFieldErrors(reason);
    if (errors.length) await schemaRendererRef.value?.setFieldErrors(mapFieldErrors(errors));
    else throw reason;
  } finally {
    saving.value = false;
    buttonLock.busy = false;
  }
}
async function openDetail(row: Record<string, unknown>) {
  const sequence = ++detailSequence;
  const identity = { formKey: formKey.value, id: row[primaryKeyName.value] as FormRecordId, schemaHash: meta.value?.schemaHash ?? '' };
  const result = await formDataApi.detail(identity.formKey, identity.id);
  if (sequence !== detailSequence || identity.formKey !== formKey.value || identity.schemaHash !== (meta.value?.schemaHash ?? '')) return;
  detail.value = { ...result, row: sanitizeRuntimeRecord(formFields.value, result.row) };
  detailVisible.value = true;
}
async function onDelete(row: Record<string, unknown>) {
  const context = JSON.stringify(buttonContext('row', row));
  const version = buttonContextVersion.value;
  await ElMessageBox.confirm('确认删除该条数据？', '删除确认', { type: 'warning' });
  if (context !== JSON.stringify(buttonContext('row', row)) || version !== buttonContextVersion.value || !hasPermission('form.data:remove') || !rows.value.some(record => record[primaryKeyName.value] === row[primaryKeyName.value])) return;
  await formDataApi.remove(formKey.value, row[primaryKeyName.value] as FormRecordId, meta.value?.schemaHash ?? '');
  ElMessage.success('删除成功');
  await refreshAfterWrite();
}
async function onExport() {
  const data = await formDataApi.export(formKey.value, { filters: { ...filters, __leftTree: leftSelection.value } });
  const columns = [primaryKeyName.value, ...listFields.value.map((f) => f.field_name), 'created_at'];
  const lines = [columns.join(',')];
  for (const row of data.list) {
    lines.push(columns.map((column) => `"${String(row[column] ?? '').replace(/"/g, '""')}"`).join(','));
  }
  const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `${formKey.value}-export.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}
const childColumns = (list: Record<string, unknown>[]) => (list.length ? Object.keys(list[0]) : []);

watch(formKey, async () => { dataSequence++; dialogSequence++; detailSequence++; clearSelection(); meta.value = null; rows.value = []; dialogVisible.value = false; detailVisible.value = false; if (await loadMeta()) await loadData(); });
// 同步失效可覆盖同一轮关闭再打开、身份切走再切回。
watch(dialogVisible, () => { dialogSequence++; saveSequence++; }, { flush: 'sync' });
watch(detailVisible, () => { detailSequence++; }, { flush: 'sync' });
watch(formKey, () => { invalidateOptions(); metaSequence++; dataSequence++; }, { flush: 'sync' });
watch([formKey, () => meta.value?.schemaHash], () => { saveSequence++; dialogSequence++; detailSequence++; }, { flush: 'sync' });
onMounted(async () => {
  if (await loadMeta()) await loadData();
});
</script>
