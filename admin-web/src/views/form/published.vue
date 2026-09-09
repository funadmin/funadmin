<template>
  <PageWrapper :title="meta?.form.name || '已发布表单'" subtitle="当前构建使用已发布 FormSchema 运行时；生成源码将在下次前端构建后接管独立页面">
    <DataTableShell :storage-key="`published-form-${formKey}`" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="filters" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item v-for="field in filterFields" :key="field.field_name" :label="field.label">
            <el-input v-model="filters[field.field_name]" clearable placeholder="请输入筛选值" />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button type="primary" @click="openDialog()">新增</el-button>
      </template>
      <el-table :data="rows" border :row-key="primaryKey">
        <el-table-column :prop="primaryKey" label="ID" width="100" />
        <el-table-column v-for="field in listFields" :key="field.field_name" :prop="field.field_name" :label="field.label" :width="field.list_width || undefined" show-overflow-tooltip>
          <template #default="{ row }">
            <el-tag v-if="['switch', 'boolean'].includes(field.list_formatter)" :type="Number(row[field.field_name]) === 1 ? 'success' : 'info'">{{ Number(row[field.field_name]) === 1 ? '是' : '否' }}</el-tag>
            <el-tag v-else-if="field.list_formatter === 'tag'">{{ row[field.field_name] }}</el-tag>
            <el-image v-else-if="field.list_formatter === 'image'" :src="String(row[field.field_name] || '')" fit="cover" class="h-10 w-10 rounded" />
            <span v-else-if="field.list_formatter === 'money'">￥{{ Number(row[field.field_name] || 0).toFixed(2) }}</span>
            <span v-else-if="field.list_formatter === 'percent'">{{ Number(row[field.field_name] || 0).toFixed(2) }}%</span>
            <span v-else>{{ row[field.field_name] }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDialog(row)">编辑</el-button>
            <el-button link type="danger" @click="removeRow(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <template #pagination><Pagination v-model:page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" /></template>
    </DataTableShell>

    <el-dialog v-model="dialogVisible" :title="editingId !== null ? '编辑' : '新增'" width="720px" destroy-on-close>
      <SchemaRenderer
        v-if="meta"
        ref="schemaRendererRef"
        :form-key="formKey"
        :schema="meta.schema"
        :values="dialogValues"
      />
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="saving" @click="saveRow">保存</el-button></template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi, type FormDataMeta, type FormFieldError, type FormRecordId } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import SchemaRenderer from './components/SchemaRenderer.vue';
import { mapFieldErrors } from './validation/asyncValidatorRegistry';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord
} from './runtime/submissionPolicy';

const route = useRoute();
const formKey = computed(() => String(route.meta.formKey || route.query.formKey || ''));
const loading = ref(false);
const saving = ref(false);
const meta = ref<FormDataMeta | null>(null);
const rows = ref<Record<string, unknown>[]>([]);
const total = ref(0);
const query = reactive({ page: 1, pageSize: 20 });
const filters = reactive<Record<string, string>>({});
const dialogVisible = ref(false);
const editingId = ref<FormRecordId | null>(null);
const dialogValues = reactive<Record<string, unknown>>({});
const schemaRendererRef = ref<InstanceType<typeof SchemaRenderer>>();
const formFields = computed<FormFieldDef[]>(() => meta.value?.fields ?? []);
const listFields = computed(() => formFields.value.filter((field) => field.list_show === 1));
const filterFields = computed(() => formFields.value.filter((field) => field.list_filter !== ''));
const primaryKey = computed(() => meta.value?.primaryKey.name || 'id');

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
  await schemaRendererRef.value?.validate();
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
  await formDataApi.remove(formKey.value, row[primaryKey.value] as FormRecordId);
  ElMessage.success('删除成功');
  await loadData();
};

onMounted(loadData);
</script>
