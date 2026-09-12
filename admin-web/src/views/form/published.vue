<template>
  <PageWrapper :title="meta?.form.name || '已发布表单'" subtitle="当前构建使用已发布 FormSchema 运行时；生成源码将在下次前端构建后接管独立页面">
    <div class="flex flex-col gap-4 md:flex-row">
    <ListCategoryPanel v-if="meta?.schema.list?.category?.enabled" :options="meta.categoryOptions ?? []" :model-value="filters.__category" @change="onCategory" />
    <DataTableShell class="min-w-0 flex-1" :storage-key="`published-form-${formKey}`" :loading="loading" @refresh="loadData">
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
      <template #toolbar-left>
        <el-button type="primary" @click="openDialog()">新增</el-button>
        <el-button @click="onExport">导出</el-button>
      </template>
      <el-table :data="displayRows" :tree-props="{ children: '__listChildren' }" border :row-key="primaryKey" @sort-change="onSortChange">
        <el-table-column :prop="primaryKey" label="ID" width="100" sortable="custom" />
        <el-table-column v-for="field in listFields" :key="field.field_name" :prop="field.field_name" :label="field.label" :width="field.list_width || undefined" :sortable="field.list_sort === 1 ? 'custom' : false" show-overflow-tooltip>
          <template #default="{ row }">
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
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDialog(row)">编辑</el-button>
            <el-button link @click="openDetail(row)">详情</el-button>
            <el-button link type="danger" @click="removeRow(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <template #pagination><Pagination v-if="!treeEnabled" v-model:page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" /></template>
    </DataTableShell>
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
import { computed, onMounted, reactive, ref } from 'vue';
import dayjs from 'dayjs';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi, type FormDataMeta, type FormFieldError, type FormRecordId } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import type { ActionHandlers, FormAction } from './runtime/actionExecutor';
import SchemaRenderer from './components/SchemaRenderer.vue';
import ListCategoryPanel from './components/ListCategoryPanel.vue';
import { buildListTree } from './runtime/listPresentation';
import { mapFieldErrors } from './validation/asyncValidatorRegistry';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord
} from './runtime/submissionPolicy';

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
const dateFilters = reactive<Record<string, string[] | null>>({});
const dialogVisible = ref(false);
const detailVisible = ref(false);
const detail = ref<{ row: Record<string, unknown> } | null>(null);
const editingId = ref<FormRecordId | null>(null);
const dialogValues = reactive<Record<string, unknown>>({});
const schemaRendererRef = ref<InstanceType<typeof SchemaRenderer>>();
const formFields = computed<FormFieldDef[]>(() => meta.value?.fields ?? []);
const listFields = computed(() => formFields.value.filter((field) => field.list_show === 1));
const filterFields = computed(() => formFields.value.filter((field) => field.list_filter !== ''));
const primaryKey = computed(() => meta.value?.primaryKey.name || 'id');
const treeEnabled = computed(() => meta.value?.schema.list?.tree?.enabled === true);
const displayRows = computed(() => treeEnabled.value ? buildListTree(rows.value, primaryKey.value, meta.value?.schema.list?.tree?.parentField ?? '') : rows.value);
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

const loadMeta = async () => {
  if (!formKey.value) return;
  meta.value = await formDataApi.meta(formKey.value);
};
const loadData = async () => {
  if (!formKey.value) return;
  loading.value = true;
  try {
    if (!meta.value) await loadMeta();
    const result = await formDataApi.index(formKey.value, { ...query, filters: { ...filters } });
    rows.value = result.list;
    total.value = result.total;
  } finally {
    loading.value = false;
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
  const result = await formDataApi.export(formKey.value, { filters: { ...filters } });
  const blob = new Blob([JSON.stringify(result.list, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a'); anchor.href = url; anchor.download = `${formKey.value}.json`; anchor.click(); URL.revokeObjectURL(url);
};
const formatDate = (value: unknown, type: string) => value ? dayjs(String(value)).format(type === 'date' ? 'YYYY-MM-DD' : type === 'time' ? 'HH:mm:ss' : 'YYYY-MM-DD HH:mm:ss') : '';
const formatLink = (type: string, value: unknown) => type === 'email' ? `mailto:${String(value ?? '')}` : type === 'phone' ? `tel:${String(value ?? '')}` : /^https?:\/\//.test(String(value ?? '')) ? String(value) : '#';
const formatJson = (value: unknown) => { try { return JSON.stringify(typeof value === 'string' ? JSON.parse(value) : value); } catch { return String(value ?? ''); } };
const openDetail = async (row: Record<string, unknown>) => { detail.value = await formDataApi.detail(formKey.value, row[primaryKey.value] as FormRecordId); detailVisible.value = true; };
const onReset = () => { Object.keys(filters).forEach((key) => delete filters[key]); onSearch(); };
const decodeValue = (field: FormFieldDef, value: unknown) => {
  if (field.column_type !== 'json' || typeof value !== 'string' || value === '') return value;
  try {
    return JSON.parse(value);
  } catch {
    return value;
  }
};
const openDialog = async (row?: Record<string, unknown>) => {
  if (!meta.value) await loadMeta();
  editingId.value = row ? row[primaryKey.value] as FormRecordId : null;
  const source = editingId.value !== null ? await formDataApi.detail(formKey.value, editingId.value) : null;
  const record = sanitizeRuntimeRecord(formFields.value, source?.row ?? row ?? {});
  const values = { ...emptyRuntimeValues(formFields.value), ...record };
  for (const [relation, child] of Object.entries(source?.children ?? {})) values[relation] = child.list;
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
  if (saving.value || !meta.value) return;
  await schemaRendererRef.value?.submit();
  saving.value = true;
  try {
    const include = resolveSubmissionInclude({ schema_document: meta.value.schema });
    const payload = buildSubmissionPayload(formFields.value, dialogValues, include);
    const schemaHash = meta.value.schemaHash;
    if (editingId.value !== null) await formDataApi.update(formKey.value, editingId.value, payload, include, schemaHash);
    else await formDataApi.create(formKey.value, payload, include, schemaHash);
    dialogVisible.value = false;
    ElMessage.success('保存成功');
    await loadData();
  } catch (reason) {
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
  }
};
const removeRow = async (row: Record<string, unknown>) => {
  await ElMessageBox.confirm('确认删除该条数据？', '删除确认', { type: 'warning' });
  await formDataApi.remove(formKey.value, row[primaryKey.value] as FormRecordId, meta.value?.schemaHash ?? '');
  ElMessage.success('删除成功');
  await loadData();
};

onMounted(loadData);
</script>
