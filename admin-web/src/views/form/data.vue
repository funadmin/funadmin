<template>
  <PageWrapper :title="meta?.form.name ? `${meta.form.name} 数据` : '表单数据'" subtitle="元数据驱动通用列表；新增/编辑为弹窗，详情为抽屉">
    <div class="flex flex-col gap-4 md:flex-row">
    <ListSourceTree v-if="meta?.schema.list?.leftTree?.enabled" :lock="buttonLock" :form-key="formKey" :schema-hash="meta.schemaHash" :config="meta.schema.list.leftTree" :list="meta.schema.list" :permission-check="hasPermission" :can-read-form="hasPermission('console/form.data:lefttreeform')" :can-mutate="hasPermission('console/form.data:mutatelefttree')" :model-value="leftSelection" @change="onLeftTree" @mutated="loadData" />
    <ListCategoryPanel v-if="meta?.schema.list?.category?.enabled && !meta?.schema.list?.leftTree?.enabled" :options="meta.categoryOptions ?? []" :model-value="filters.__category" @change="onCategory" />
    <DataTableShell class="min-w-0 flex-1" :storage-key="`form-data-${formKey}`" :loading="loading" :show-refresh="meta?.schema.list?.tools?.refresh !== false" :show-density="meta?.schema.list?.tools?.density !== false" :show-fullscreen="meta?.schema.list?.tools?.fullscreen !== false" :show-column-setting="meta?.schema.list?.tools?.columns !== false" @refresh="loadData">
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
            <el-input v-else v-model="filters[field.field_name]" :placeholder="filterPlaceholder(field.list_filter)" clearable class="w-[180px]" />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :lock="buttonLock" :refresh="loadData" :context="buttonContext('toolbar')" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" />
      </template>
      <el-table ref="tableRef" v-loading="loading" :data="displayRows" @selection-change="onSelectionChange" :tree-props="{ children: '__listChildren' }" border :row-key="primaryKeyName" @sort-change="onSortChange">
        <el-table-column v-if="toolbarButtons.some(button => button.action.type === 'registered')" type="selection" width="48" />
        <el-table-column :prop="primaryKeyName" label="ID" width="120" />
        <el-table-column
          v-for="field in listFields"
          :key="field.field_name"
          :prop="field.field_name"
          :label="field.label"
          :width="field.list_width || undefined"
          :sortable="field.list_sort === 1 ? 'custom' : false"
          show-overflow-tooltip
        >
          <template #default="{ row }">
            <template v-if="field.relation_type === 'belongs_to'">{{ row['__label_' + field.field_name] ?? row[field.field_name] }}</template>
            <el-tag v-else-if="field.list_formatter === 'switch' || field.list_formatter === 'boolean'" :type="isTruthy(row[field.field_name]) ? 'success' : 'info'" size="small">
              {{ isTruthy(row[field.field_name]) ? '是' : '否' }}
            </el-tag>
            <el-tag v-else-if="field.list_formatter === 'tag'" size="small">{{ row[field.field_name] }}</el-tag>
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
            <span v-else-if="field.list_formatter === 'date'">{{ formatDate(row[field.field_name], 'YYYY-MM-DD') }}</span>
            <span v-else-if="field.list_formatter === 'datetime'">{{ formatDate(row[field.field_name], 'YYYY-MM-DD HH:mm:ss') }}</span>
            <span v-else-if="field.list_formatter === 'time'">{{ formatDate(row[field.field_name], 'HH:mm:ss') }}</span>
            <span v-else-if="field.list_formatter === 'money'">{{ formatNumber(row[field.field_name], 2, '￥') }}</span>
            <span v-else-if="field.list_formatter === 'number'">{{ formatNumber(row[field.field_name]) }}</span>
            <span v-else-if="field.list_formatter === 'percent'">{{ formatNumber(row[field.field_name], 2, '', '%') }}</span>
            <el-link v-else-if="field.list_formatter === 'link'" :href="safeUrl(row[field.field_name])" target="_blank" type="primary">{{ row[field.field_name] }}</el-link>
            <el-link v-else-if="field.list_formatter === 'email'" :href="`mailto:${String(row[field.field_name] ?? '')}`" type="primary">{{ row[field.field_name] }}</el-link>
            <el-link v-else-if="field.list_formatter === 'phone'" :href="`tel:${String(row[field.field_name] ?? '')}`" type="primary">{{ row[field.field_name] }}</el-link>
            <code v-else-if="field.list_formatter === 'json'">{{ formatJson(row[field.field_name]) }}</code>
            <span v-else>{{ row[field.field_name] }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170" sortable="custom" />
        <el-table-column v-if="hasRowButtons" label="操作" min-width="180" align="center" fixed="right">
          <template #default="{ row }">
            <ListButtonBar :buttons="rowButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="row" :fields="readableButtonFields" :lock="buttonLock" :refresh="loadData" :context="buttonContext('row', row)" :context-version="buttonContextVersion" :permission-check="hasPermission" :clear-selection="clearSelection" :close="closeButtonHost" link />
          </template>
        </el-table-column>
      </el-table>
      <template #pagination>
        <Pagination v-if="!treeEnabled" v-model:page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" />
      </template>
    </DataTableShell>
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
        <el-descriptions-item v-for="field in meta?.fields ?? []" :key="field.field_name" :label="field.label">
          {{ detail?.row?.[field.field_name] ?? '-' }}
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
import dayjs from 'dayjs';
import { formDataApi, type FormDataMeta, type FormFieldError, type FormRecordId } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import SchemaRenderer from './components/SchemaRenderer.vue';
import ListButtonBar from './components/ListButtonBar.vue';
import { resolveListButtons } from './schema/listButtons';
import { defaultListButtons, listActionKey, listButtonState, type ListButtonHandlers } from './runtime/listButtonHost';
import type { FormListButton } from './schema/types';
import { useUserStore } from '@/store/modules/user';
import ListCategoryPanel from './components/ListCategoryPanel.vue';
import ListSourceTree from './components/ListSourceTree.vue';
import { buildListTree } from './runtime/listPresentation';
import { mapFieldErrors } from './validation/asyncValidatorRegistry';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord,
  stableRuntimeValues
} from './runtime/submissionPolicy';

const user = useUserStore();
const hasPermission = (code: string) => user.permissions.some(permission => permission === '*' || permission === '*:*:*' || permission === code);
const buttonLock = reactive({ busy: false });
const toolbarButtons = computed(() => resolveListButtons(meta.value?.schema.list, 'toolbar', defaultListButtons('toolbar')));
const rowButtons = computed(() => resolveListButtons(meta.value?.schema.list, 'row', defaultListButtons('row')));
const readableButtonFields = computed(() => [primaryKeyName.value, ...formFields.value.filter(field => field.type !== 'password' && !field.control_props?.sensitive && !field.control_props?.writeOnly).map(field => field.field_name)]);
const buttonAllowed = (button: FormListButton) => {
  const key = listActionKey(button);
  const route: Record<string, string> = { create: 'create', edit: 'update', detail: 'detail', delete: 'remove', export: 'export', refresh: 'index' };
  return (!button.permission || hasPermission(button.permission)) && (!route[key] || hasPermission(`console/form.data:${route[key]}`)) && (key !== 'edit' || hasPermission('console/form.data:detail')) && (!['create', 'edit', 'delete'].includes(key) || meta.value?.schema.form?.readOnly !== true);
};
const buttonHandlers: ListButtonHandlers = { create: () => openDialog(), edit: row => openDialog(row), detail: row => openDetail(row!), delete: row => onDelete(row!), export: () => onExport(), refresh: () => loadData() };
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
const schemaRendererRef = ref<InstanceType<typeof SchemaRenderer>>();
const dialogSnapshot = ref('');
let closeDialogAfterSave = false;

const formFields = computed<FormFieldDef[]>(() => meta.value?.fields ?? []);
const primaryKeyName = computed(() => meta.value?.primaryKey.name ?? 'id');
const treeEnabled = computed(() => meta.value?.schema.list?.tree?.enabled === true);
const displayRows = computed(() => treeEnabled.value ? buildListTree(rows.value, primaryKeyName.value, meta.value?.schema.list?.tree?.parentField ?? '') : rows.value);
const onCategory = (value: string | number | undefined) => { if (value === undefined) delete filters.__category; else filters.__category = String(value); onSearch(); };
const listFields = computed(() => formFields.value.filter((f) => f.list_show === 1 && f.type !== 'password' && !f.control_props?.sensitive && !f.control_props?.writeOnly));
const filterFields = computed(() => formFields.value.filter((f) => f.list_filter !== '' && f.type !== 'password' && !f.control_props?.sensitive && !f.control_props?.writeOnly));

const actionFilterFields = computed(() => new Set(filterFields.value.filter(field => field.column_type && (!field.relation_type || field.relation_type === 'none')).flatMap(field => ['range', 'date'].includes(field.list_filter) ? [field.field_name + '_from', field.field_name + '_to'] : [field.field_name])));
const filterPlaceholder = (type: string) => ['in', 'not_in'].includes(type) ? '多个值用英文逗号分隔' : '请输入筛选值';
const syncDateFilter = (name: string) => {
  const range = dateFilters[name];
  filters[name + '_from'] = range?.[0] ?? '';
  filters[name + '_to'] = range?.[1] ?? '';
};
const isTruthy = (value: unknown) => ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
const formatDate = (value: unknown, format: string) => {
  if (!value) return '-';
  if (format === 'HH:mm:ss' && /^\d{2}:\d{2}(?::\d{2})?$/.test(String(value))) return String(value);
  return dayjs(value as string).isValid() ? dayjs(value as string).format(format) : '-';
};
const formatNumber = (value: unknown, digits?: number, prefix = '', suffix = '') => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return `${prefix}${number.toLocaleString('zh-CN', digits === undefined ? undefined : { minimumFractionDigits: digits, maximumFractionDigits: digits })}${suffix}`;
};
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
const formatJson = (value: unknown) => {
  if (typeof value !== 'string') return JSON.stringify(value);
  try {
    return JSON.stringify(JSON.parse(value));
  } catch {
    return value;
  }
};

async function loadMeta() {
  const key = formKey.value;
  const loaded = await formDataApi.meta(key);
  if (key === formKey.value) meta.value = loaded;
}
let dataSequence = 0;
onBeforeUnmount(() => { dataSequence++; });
async function loadData() {
  const sequence = ++dataSequence;
  clearSelection();
  loading.value = true;
  try {
    const data = await formDataApi.index(formKey.value, { ...query, ...sort, filters: { ...filters, __leftTree: leftSelection.value } });
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
  editingId.value = row ? row[primaryKeyName.value] as FormRecordId : null;
  const source = editingId.value !== null ? await formDataApi.detail(formKey.value, editingId.value) : null;
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
async function onSave() {
  if (saving.value) return;
  await schemaRendererRef.value?.submit();
  saving.value = true;
  try {
    const include = resolveSubmissionInclude(meta.value ? { schema_document: meta.value.schema } : null);
    const payload = buildSubmissionPayload(formFields.value, dialogValues, include);
    const schemaHash = meta.value?.schemaHash ?? '';
    if (editingId.value !== null) await formDataApi.update(formKey.value, editingId.value, payload, include, schemaHash);
    else await formDataApi.create(formKey.value, payload, include, schemaHash);
    closeDialogAfterSave = true;
    dialogVisible.value = false;
    ElMessage.success('保存成功');
    loadData();
  } catch (reason) {
    const errors = responseFieldErrors(reason);
    if (errors.length) await schemaRendererRef.value?.setFieldErrors(mapFieldErrors(errors));
    else throw reason;
  } finally {
    saving.value = false;
  }
}
async function openDetail(row: Record<string, unknown>) {
  const result = await formDataApi.detail(formKey.value, row[primaryKeyName.value] as FormRecordId);
  detail.value = { ...result, row: sanitizeRuntimeRecord(formFields.value, result.row) };
  detailVisible.value = true;
}
async function onDelete(row: Record<string, unknown>) {
  const context = JSON.stringify(buttonContext('row', row));
  const version = buttonContextVersion.value;
  await ElMessageBox.confirm('确认删除该条数据？', '删除确认', { type: 'warning' });
  if (context !== JSON.stringify(buttonContext('row', row)) || version !== buttonContextVersion.value || !hasPermission('console/form.data:remove') || !rows.value.some(record => record[primaryKeyName.value] === row[primaryKeyName.value])) return;
  await formDataApi.remove(formKey.value, row[primaryKeyName.value] as FormRecordId, meta.value?.schemaHash ?? '');
  ElMessage.success('删除成功');
  loadData();
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

watch(formKey, async () => { dataSequence++; clearSelection(); meta.value = null; rows.value = []; dialogVisible.value = false; detailVisible.value = false; await loadMeta(); await loadData(); });
onMounted(async () => {
  await loadMeta();
  await loadData();
});
</script>
