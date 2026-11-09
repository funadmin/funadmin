<template>
  <PageWrapper :title="meta?.form.name ? t('formData.pageTitle', { name: meta.form.name }, '{name} 数据') : t('formData.title', '表单数据')" :subtitle="t('formData.subtitle', '元数据驱动通用列表；新增/编辑为弹窗，详情为抽屉')">
    <div class="flex flex-col gap-4 md:flex-row">
    <ListSourceTree v-if="meta?.schema.list?.leftTree?.enabled" :lock="buttonLock" :form-key="formKey" :schema-hash="meta.schemaHash" :config="meta.schema.list.leftTree" :list="meta.schema.list" :permission-check="hasPermission" :can-read-form="hasPermission('form:data:left-tree-form')" :can-mutate="hasPermission('form:data:mutate-left-tree')" :model-value="leftSelection" @change="onLeftTree" @mutated="loadData" />
    <ListCategoryPanel v-if="meta?.schema.list?.category?.enabled && !meta?.schema.list?.leftTree?.enabled" :options="meta.categoryOptions ?? []" :model-value="filters.__category" @change="onCategory" />
    <SchemaTablePage ref="tableRef" class="min-w-0 flex-1" :storage-key="`form-data-${formKey}`" :schema="tableSchema" :query="query" :rows="rows" :total="total" :loading="loading" :context="{ values: {}, permissions: user.permissions, handlers: {} }" :lock="buttonLock" @refresh="loadData" @selection-change="onSelectionChange" @sort-change="onSortChange">
      <template v-if="meta?.schema.list?.tools?.search !== false" #search>
        <SearchForm :model="filters" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item v-for="node in filterFields" :key="node.id" :label="node.title" :prop="nameOf(node)">
            <template v-if="node.list?.filter === 'range'">
              <div class="flex gap-1">
                <el-input v-model="filters[nameOf(node) + '_from']" :placeholder="t('common.minValue', '最小值')" class="w-[110px]" />
                <el-input v-model="filters[nameOf(node) + '_to']" :placeholder="t('common.maxValue', '最大值')" class="w-[110px]" />
              </div>
            </template>
            <el-date-picker
              v-else-if="node.list?.filter === 'date'"
              v-model="dateFilters[nameOf(node)]"
              type="daterange"
              value-format="YYYY-MM-DD"
              :range-separator="t('common.to', '至')"
              :start-placeholder="t('common.startDate', '开始日期')"
              :end-placeholder="t('common.endDate', '结束日期')"
              @change="syncDateFilter(nameOf(node))"
            />
            <el-select
              v-else-if="node.list?.filter === 'is_null' || node.list?.filter === 'not_null'"
              v-model="filters[nameOf(node)]"
              :placeholder="t('common.pleaseSelect', '请选择')"
              clearable
              class="w-[160px]"
            >
              <el-option :label="t('common.enable', '启用')" value="1" />
            </el-select>
            <el-select v-else-if="isDynamicFieldOptions(node) && ['eq', 'neq', '='].includes(node.list?.filter ?? '')" v-model="filters[nameOf(node)]" :loading="filterOptions.pending.value[node.id]" :placeholder="filterOptions.placeholder(node.id) || t('common.pleaseSelect', '请选择')" clearable>
              <el-option v-for="option in resolveFieldOptions(node, filterOptions.supplied.value)" :key="String(option.value)" :label="option.label" :value="option.value as string | number" />
            </el-select>
            <el-input v-else v-model="filters[nameOf(node)]" :placeholder="filterPlaceholder(node.list?.filter ?? '')" clearable class="w-[180px]" />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar>
        <el-alert v-if="recycled" :title="t('formData.recycleAlert', '回收站视图：仅显示已删除记录，可恢复或永久删除')" type="warning" :closable="false" class="mb-2" />
        <ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :lock="buttonLock" :refresh="loadData" :context="buttonContext('toolbar')" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" />
      </template>
      <template v-for="node in listFields" :key="node.id" #[nameOf(node)]="{ row }">
            <template v-if="relationTypeOf(node) === 'belongs_to'">{{ row['__label_' + nameOf(node)] ?? row[nameOf(node)] }}</template>
            <el-tag v-else-if="node.list?.formatter === 'switch' || node.list?.formatter === 'boolean'" :type="isTruthy(row[nameOf(node)]) ? 'success' : 'info'" size="small">
              {{ presentField(node, row) }}
            </el-tag>
            <el-tag v-else-if="node.list?.formatter === 'tag'" size="small">{{ presentField(node, row) }}</el-tag>
            <el-image
              v-else-if="node.list?.formatter === 'image' && imageUrls(row[nameOf(node)]).length"
              :src="imageUrls(row[nameOf(node)])[0]"
              :preview-src-list="imageUrls(row[nameOf(node)])"
              preview-teleported
              fit="cover"
              class="h-10 w-10 rounded"
            />
            <div v-else-if="node.list?.formatter === 'images'" class="flex gap-1">
              <el-image
                v-for="url in imageUrls(row[nameOf(node)]).slice(0, 3)"
                :key="url"
                :src="url"
                :preview-src-list="imageUrls(row[nameOf(node)])"
                preview-teleported
                fit="cover"
                class="h-10 w-10 rounded"
              />
            </div>
            <span v-else-if="node.list?.formatter === 'date'">{{ presentField(node, row) }}</span>
            <span v-else-if="node.list?.formatter === 'datetime'">{{ presentField(node, row) }}</span>
            <span v-else-if="node.list?.formatter === 'time'">{{ presentField(node, row) }}</span>
            <span v-else-if="node.list?.formatter === 'money'">{{ presentField(node, row) }}</span>
            <span v-else-if="node.list?.formatter === 'number'">{{ presentField(node, row) }}</span>
            <span v-else-if="node.list?.formatter === 'percent'">{{ presentField(node, row) }}</span>
            <el-link v-else-if="node.list?.formatter === 'link'" :href="safeUrl(row[nameOf(node)])" target="_blank" type="primary">{{ presentField(node, row) }}</el-link>
            <el-link v-else-if="node.list?.formatter === 'email'" :href="`mailto:${String(row[nameOf(node)] ?? '')}`" type="primary">{{ presentField(node, row) }}</el-link>
            <el-link v-else-if="node.list?.formatter === 'phone'" :href="`tel:${String(row[nameOf(node)] ?? '')}`" type="primary">{{ presentField(node, row) }}</el-link>
            <code v-else-if="node.list?.formatter === 'json'">{{ presentField(node, row) }}</code>
            <span v-else>{{ presentField(node, row) }}</span>
      </template>
      <template #actions="{ row }">
            <ListButtonBar :buttons="rowButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="row" :fields="readableButtonFields" :lock="buttonLock" :refresh="loadData" :context="buttonContext('row', row)" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" link />
      </template>
    </SchemaTablePage>
    <FormDataImportDialog v-model="importVisible" :fields="formFields" @submit="onImportRows" />
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="editingId !== null ? t('common.edit', '编辑') : t('common.add', '新增')" width="720px" :close-on-click-modal="false" :before-close="beforeDialogClose" destroy-on-close>
      <SchemaRenderer v-if="meta" ref="schemaRendererRef" :form-key="formKey" :schema="meta.schema" :values="dialogValues" />
      <template #footer>
        <el-button @click="requestDialogClose">{{ t('common.cancel', '取消') }}</el-button>
        <el-button v-if="hasPermission(editingId === null ? 'form:data:create' : 'form:data:update')" type="primary" :loading="saving" @click="onSave">{{ t('common.save', '保存') }}</el-button>
      </template>
    </el-dialog>

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" :title="t('common.detail', '详情')" size="52%">
      <el-descriptions :column="1" border>
        <el-descriptions-item v-for="node in readableFields" :key="node.id" :label="node.title">
          {{ presentField(node, detail?.row ?? {}, detailOptions) }}
        </el-descriptions-item>
        <el-descriptions-item :label="t('common.createdAt', '创建时间')">{{ detail?.row?.created_at ?? '-' }}</el-descriptions-item>
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
import { hasPermission } from '@/utils/permission';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import type { ListButtonContext } from './runtime/listButtonExecutor';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi, type FormDataMeta, type FormFieldError, type FormRecordId } from '@/api/formData';
import SchemaRenderer from './components/SchemaRenderer.vue';
import ListButtonBar from './components/ListButtonBar.vue';
import { resolveListButtons } from './schema/listButtons';
import { provideListButtonAdapter, defaultListButtons, listActionKey, declaredCustomActionButton, listButtonState, type ListButtonHandlers } from './runtime/listButtonHost';
import type { FormListButton, FormSchemaNode } from './schema/types';
import { useUserStore } from '@/store/modules/user';
import ListCategoryPanel from './components/ListCategoryPanel.vue';
import ListSourceTree from './components/ListSourceTree.vue';
import FormDataImportDialog from './components/FormDataImportDialog.vue';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import type { PageSchema } from '@/components/DataTable/pageSchema';
import { mapFieldErrors } from './validation/asyncValidatorRegistry';
import { formatFieldValue, resolveFieldOptions, useSuppliedFieldOptions, isDynamicFieldOptions } from './runtime/fieldPresentation';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord,
  stableRuntimeValues
} from './runtime/submissionPolicy';
import { fieldNodes, isSensitiveNode, relationTypeOf, columnTypeOf } from './schema/fieldNodes';

provideListButtonAdapter({ api: formDataApi, declaration: { catalogPermission: 'form:data:list-actions', executePermission: 'form:data:list-action' } });
const { t } = useI18n();
const user = useUserStore();
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
const readableButtonFields = computed(() => [primaryKeyName.value, ...readableFields.value.map(nameOf)]);
const buttonAllowed = (button: FormListButton) => {
  const key = listActionKey(button);
  const route: Record<string, string> = { create: 'create', edit: 'update', detail: 'detail', delete: 'remove', export: 'export', refresh: 'index', batchDelete: 'batch-remove', import: 'import', recycle: 'index', normal: 'index', restore: 'restore', destroy: 'destroy' };
  return (!button.permission || hasPermission(button.permission)) && (!route[key] || hasPermission(`form:data:${route[key]}`)) && (key !== 'edit' || hasPermission('form:data:detail')) && (!['create', 'edit', 'delete'].includes(key) || meta.value?.schema.form?.readOnly !== true);
};
const buttonHandlers: ListButtonHandlers = {
  create: () => openDialog(), edit: row => openDialog(row), detail: row => openDetail(row!), delete: row => onDelete(row!), export: () => onExport(), refresh: () => loadData(),
  recycle: async () => { recycled.value = true; await loadData(); },
  normal: async () => { recycled.value = false; await loadData(); },
  batchDelete: async () => {
    const ids = selectedRows.value.map(record => record[primaryKeyName.value] as FormRecordId);
    if (!ids.length) { ElMessage.warning(t('formData.selectRecordsToDelete', '请先勾选需要删除的记录')); return; }
    await ElMessageBox.confirm(t('formData.batchDeleteConfirm', { n: ids.length }, '确认删除选中的 {n} 条记录吗？'), t('common.batchRemove', '批量删除'), { type: 'warning' });
    if (!hasPermission('form:data:batch-remove')) return;
    await formDataApi.batchRemove(formKey.value, ids, meta.value?.schemaHash ?? '');
    clearSelection();
    await loadData();
  },
  restore: async (row) => { if (!hasPermission('form:data:restore')) return;
    await formDataApi.restore(formKey.value, [row![primaryKeyName.value] as FormRecordId], meta.value?.schemaHash ?? ''); await loadData(); },
  destroy: async (row) => {
    await ElMessageBox.confirm(t('formData.destroyConfirm', '永久删除后不可恢复，确认继续吗？'), t('formData.destroyTitle', '永久删除'), { type: 'warning' });
    if (!hasPermission('form:data:destroy')) return;
    await formDataApi.destroy(formKey.value, [row![primaryKeyName.value] as FormRecordId], meta.value?.schemaHash ?? '');
    await loadData();
  },
  import: () => { importVisible.value = true; }
};
const hasRowButtons = computed(() => rowButtons.value.some(button => !button.hidden && (buttonAllowed(button) || declaredCustomActionButton(button, meta.value?.schema))));
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
const loadOptions = () => {
  void fieldOptions.load(formKey.value, listFields.value);
  void filterOptions.load(formKey.value, filterFields.value);
  void detailOptions.load(formKey.value, readableFields.value);
};
const invalidateOptions = () => { fieldOptions.invalidate(); filterOptions.invalidate(); detailOptions.invalidate(); };
const schemaRendererRef = ref<InstanceType<typeof SchemaRenderer>>();
const dialogSnapshot = ref('');
let closeDialogAfterSave = false;
let saveSequence = 0;
let dialogSequence = 0;
let detailSequence = 0;

const schemaNodes = computed(() => meta.value?.schema.nodes ?? []);
const formFields = computed(() => fieldNodes(meta.value?.schema.nodes ?? []));
const nameOf = (node: FormSchemaNode) => node.field ?? '';
const primaryKeyName = computed(() => meta.value?.primaryKey.name ?? 'id');
const treeEnabled = computed(() => meta.value?.schema.list?.tree?.enabled === true);
const tableSchema = computed<PageSchema>(() => ({
  pageSchemaVersion: 1, key: 'dynamic_form', primaryKey: primaryKeyName.value, search: [], toolbar: [], rowActions: [],
  list: { ...(meta.value?.schema.list?.tree ? { tree: meta.value.schema.list.tree } : {}), ...(meta.value?.schema.list?.tools ? { tools: meta.value.schema.list.tools } : {}) },
  columns: [
    ...(toolbarButtons.value.some(button => button.action.type === 'registered' || listActionKey(button) === 'batchDelete') ? [{ key: 'selection', label: '', type: 'selection' as const, width: 48 }] : []),
    { key: 'primary', prop: primaryKeyName.value, label: 'ID', width: 120 },
    ...listFields.value.map(node => ({ key: nameOf(node), prop: nameOf(node), label: node.title, slot: nameOf(node), ...(node.list?.width ? { width: node.list.width } : {}), sortable: node.list?.sort === true })),
    { key: 'created', prop: 'created_at', label: t('common.createdAt', '创建时间'), width: 170, sortable: true },
    ...(hasRowButtons.value ? [{ key: 'actions', label: t('common.operation', '操作'), slot: 'actions', minWidth: 180, fixed: 'right' as const }] : [])
  ], pagination: { pageSize: 20, pageSizes: [10, 20, 50, 100], enabled: !treeEnabled.value }
}));
const onCategory = (value: string | number | undefined) => { if (value === undefined) delete filters.__category; else filters.__category = String(value); onSearch(); };
const listFields = computed(() => formFields.value.filter(node => node.list?.show === true && !isSensitiveNode(node)));
const filterFields = computed(() => formFields.value.filter(node => !!node.list?.filter && node.type !== 'hidden' && !isSensitiveNode(node)));

const actionFilterFields = computed(() => new Set(filterFields.value.filter(node => columnTypeOf(node) && relationTypeOf(node) === 'none').flatMap(node => ['range', 'date'].includes(node.list?.filter ?? '') ? [nameOf(node) + '_from', nameOf(node) + '_to'] : [nameOf(node)])));
const filterPlaceholder = (type: string) => ['in', 'not_in'].includes(type) ? t('formData.filterInPlaceholder', '多个值用英文逗号分隔') : t('formData.filterPlaceholder', '请输入筛选值');
const syncDateFilter = (name: string) => {
  const range = dateFilters[name];
  filters[name + '_from'] = range?.[0] ?? '';
  filters[name + '_to'] = range?.[1] ?? '';
};
const readableFields = computed(() => formFields.value.filter(node => !node.hidden && node.type !== 'hidden' && !isSensitiveNode(node)));
const presentField = (node: FormSchemaNode, row: Record<string, unknown>, state = fieldOptions) => state.placeholder(node.id, row) || formatFieldValue(row[nameOf(node)], resolveFieldOptions(node, state.forContext(row)), node.list?.formatter);
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
  if (!hasPermission('form:data:import')) return;
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
  await ElMessageBox.confirm(t('formData.discardChangesConfirm', '确认放弃未保存的修改？'), t('formData.leaveConfirmTitle', '离开确认'), { type: 'warning' });
  done();
};
const requestDialogClose = () => beforeDialogClose(() => { dialogVisible.value = false; });
const decodeValue = (node: FormSchemaNode, value: unknown) => {
  if (columnTypeOf(node) !== 'json' || typeof value !== 'string' || value === '') return value;
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
  const record = sanitizeRuntimeRecord(schemaNodes.value, source?.row ?? row ?? {});
  const values = { ...emptyRuntimeValues(schemaNodes.value), ...record };
  for (const [relation, child] of Object.entries(source?.children ?? {})) values[relation] = child.list;
  for (const node of formFields.value) values[nameOf(node)] = decodeValue(node, values[nameOf(node)]);
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
    if (identity === formKey.value && sequence === dataSequence) ElMessage.warning(t('formData.refreshFailed', '操作已成功，但列表刷新失败，请手动刷新，不要重复提交'));
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
    if (saveSequenceValue !== saveSequence || identity !== JSON.stringify([formKey.value, editingId.value, meta.value?.schemaHash]) || !dialogVisible.value || !hasPermission(editingId.value === null ? 'form:data:create' : 'form:data:update')) return;
    const include = resolveSubmissionInclude(meta.value ? { schema_document: meta.value.schema } : null);
    const payload = buildSubmissionPayload(schemaNodes.value, dialogValues, include, hasPermission);
    const schemaHash = meta.value?.schemaHash ?? '';
    if (editingId.value !== null) await formDataApi.update(formKey.value, editingId.value, payload, include, schemaHash);
    else await formDataApi.create(formKey.value, payload, include, schemaHash);
    if (saveSequenceValue !== saveSequence) return;
    closeDialogAfterSave = true;
    dialogVisible.value = false;
    ElMessage.success(t('common.saveSuccess', '保存成功'));
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
  detail.value = { ...result, row: sanitizeRuntimeRecord(schemaNodes.value, result.row) };
  detailVisible.value = true;
}
async function onDelete(row: Record<string, unknown>) {
  const context = JSON.stringify(buttonContext('row', row));
  const version = buttonContextVersion.value;
  await ElMessageBox.confirm(t('formData.deleteConfirm', '确认删除该条数据？'), t('formData.deleteConfirmTitle', '删除确认'), { type: 'warning' });
  if (context !== JSON.stringify(buttonContext('row', row)) || version !== buttonContextVersion.value || !hasPermission('form:data:remove') || !rows.value.some(record => record[primaryKeyName.value] === row[primaryKeyName.value])) return;
  await formDataApi.remove(formKey.value, row[primaryKeyName.value] as FormRecordId, meta.value?.schemaHash ?? '');
  ElMessage.success(t('common.deleteSuccess', '删除成功'));
  await refreshAfterWrite();
}
async function onExport() {
  if (!hasPermission('form:data:export')) return;
  const data = await formDataApi.export(formKey.value, { filters: { ...filters, __leftTree: leftSelection.value } });
  const columns = [primaryKeyName.value, ...listFields.value.map(nameOf), 'created_at'];
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
