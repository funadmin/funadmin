<template>
  <PageWrapper :title="meta?.form.name ? `${meta.form.name} 数据` : '表单数据'" subtitle="元数据驱动通用列表；新增/编辑为弹窗，详情为抽屉">
    <DataTableShell :storage-key="`form-data-${formKey}`" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="filters" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item v-for="field in filterFields" :key="field.field_name" :label="field.label" :prop="field.field_name">
            <template v-if="field.list_filter === 'range' || field.list_filter === 'date'">
              <div class="flex gap-1">
                <el-input v-model="filters[field.field_name + '_from']" placeholder="起" class="w-[110px]" />
                <el-input v-model="filters[field.field_name + '_to']" placeholder="止" class="w-[110px]" />
              </div>
            </template>
            <el-input v-else v-model="filters[field.field_name]" :placeholder="field.label" clearable class="w-[160px]" />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar>
        <el-button type="primary" @click="openDialog()">新增</el-button>
        <el-button @click="onExport">导出</el-button>
      </template>
      <el-table v-loading="loading" :data="rows" border row-key="id" @sort-change="onSortChange">
        <el-table-column prop="id" label="ID" width="70" />
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
            <el-tag v-else-if="field.list_formatter === 'switch'" :type="Number(row[field.field_name]) === 1 ? 'success' : 'info'" size="small">
              {{ Number(row[field.field_name]) === 1 ? '是' : '否' }}
            </el-tag>
            <el-tag v-else-if="field.list_formatter === 'tag'" size="small">{{ row[field.field_name] }}</el-tag>
            <span v-else-if="field.list_formatter === 'money'">￥{{ row[field.field_name] }}</span>
            <span v-else>{{ row[field.field_name] }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170" sortable="custom" />
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDialog(row)">编辑</el-button>
            <el-button link @click="openDetail(row)">详情</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <template #pagination>
        <Pagination v-model:page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" />
      </template>
    </DataTableShell>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑' : '新增'" width="720px" :close-on-click-modal="false" destroy-on-close>
      <SchemaForm ref="schemaFormRef" :form-key="formKey" :fields="formFields" :values="dialogValues" />
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
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
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi, type FormDataMeta } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import SchemaForm from './components/SchemaForm.vue';

const route = useRoute();
const formKey = String(route.params.key ?? '');
const loading = ref(false);
const saving = ref(false);
const meta = ref<FormDataMeta | null>(null);
const rows = ref<Record<string, unknown>[]>([]);
const total = ref(0);
const query = reactive({ page: 1, pageSize: 20 });
const filters = reactive<Record<string, string>>({});
const sort = reactive({ sort: '', order: '' });
const dialogVisible = ref(false);
const detailVisible = ref(false);
const editingId = ref(0);
const dialogValues = reactive<Record<string, any>>({});
const detail = ref<{ row: Record<string, unknown>; children: Record<string, { list: Record<string, unknown>[]; total: number }> } | null>(null);
const schemaFormRef = ref<InstanceType<typeof SchemaForm>>();

const formFields = computed<FormFieldDef[]>(() => meta.value?.fields ?? []);
const listFields = computed(() => formFields.value.filter((f) => f.list_show === 1));
const filterFields = computed(() => formFields.value.filter((f) => f.list_filter !== ''));

async function loadMeta() {
  meta.value = await formDataApi.meta(formKey);
}
async function loadData() {
  loading.value = true;
  try {
    const data = await formDataApi.index(formKey, { ...query, ...sort, filters: { ...filters } });
    rows.value = data.list;
    total.value = data.total;
  } finally {
    loading.value = false;
  }
}
const onSearch = () => {
  query.page = 1;
  loadData();
};
const onReset = () => {
  for (const key of Object.keys(filters)) delete filters[key];
  onSearch();
};
const onSortChange = ({ prop, order }: { prop: string | null; order: 'ascending' | 'descending' | null }) => {
  sort.sort = order && prop ? prop : '';
  sort.order = order === 'descending' ? 'desc' : 'asc';
  loadData();
};

const emptyValues = () => {
  const values: Record<string, unknown> = {};
  for (const field of formFields.value) {
    values[field.field_name] = field.type === 'switch' ? 0 : field.type === 'number' ? undefined : '';
  }
  return values;
};
const openDialog = (row?: Record<string, unknown>) => {
  editingId.value = Number(row?.id ?? 0);
  Object.assign(dialogValues, emptyValues(), row ?? {});
  dialogVisible.value = true;
};
async function onSave() {
  await schemaFormRef.value?.validate();
  saving.value = true;
  try {
    if (editingId.value) {
      await formDataApi.update(formKey, editingId.value, { ...dialogValues });
    } else {
      await formDataApi.create(formKey, { ...dialogValues });
    }
    dialogVisible.value = false;
    ElMessage.success('保存成功');
    loadData();
  } finally {
    saving.value = false;
  }
}
async function openDetail(row: Record<string, unknown>) {
  detail.value = await formDataApi.detail(formKey, Number(row.id));
  detailVisible.value = true;
}
async function onDelete(row: Record<string, unknown>) {
  await ElMessageBox.confirm('确认删除该条数据？', '删除确认', { type: 'warning' });
  await formDataApi.remove(formKey, Number(row.id));
  ElMessage.success('删除成功');
  loadData();
}
async function onExport() {
  const data = await formDataApi.export(formKey, { filters: { ...filters } });
  const columns = ['id', ...listFields.value.map((f) => f.field_name), 'created_at'];
  const lines = [columns.join(',')];
  for (const row of data.list) {
    lines.push(columns.map((column) => `"${String(row[column] ?? '').replace(/"/g, '""')}"`).join(','));
  }
  const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `${formKey}-export.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}
const childColumns = (list: Record<string, unknown>[]) => (list.length ? Object.keys(list[0]) : []);

onMounted(async () => {
  await loadMeta();
  await loadData();
});
</script>
