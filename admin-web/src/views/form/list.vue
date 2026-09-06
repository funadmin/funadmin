<template>
  <PageWrapper title="表单管理" subtitle="可视化设计表单并绑定真实表；created 表走守卫式迁移，adopted 表仅元数据">
    <DataTableShell storage-key="form-list" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="关键词" prop="keyword">
            <el-input v-model="query.keyword" placeholder="名称/标识" clearable />
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-select v-model="query.status" placeholder="全部" clearable class="w-[120px]">
              <el-option label="启用" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar>
        <el-button type="primary" @click="openCreate">新建表单</el-button>
      </template>
      <el-table v-loading="loading" :data="rows" border row-key="id">
        <el-table-column prop="name" label="表单名称" min-width="140" show-overflow-tooltip />
        <el-table-column prop="form_key" label="标识" min-width="120" show-overflow-tooltip />
        <el-table-column prop="table_name" label="绑定表" min-width="120" show-overflow-tooltip />
        <el-table-column prop="source_type" label="来源" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.source_type === 'created' ? 'primary' : 'info'" size="small">{{ row.source_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="fields_count" label="字段数" width="80" align="center" />
        <el-table-column prop="status" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" width="220" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="goDesigner(row)">设计</el-button>
            <el-button link type="warning" @click="toggleStatus(row)">{{ row.status === 1 ? '禁用' : '启用' }}</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <template #pagination>
        <Pagination v-model:page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" />
      </template>
    </DataTableShell>

    <el-dialog v-model="createVisible" title="新建表单" width="520px" :close-on-click-modal="false">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item label="表单名称" prop="name">
          <el-input v-model="createForm.name" placeholder="如：活动报名" />
        </el-form-item>
        <el-form-item label="表单标识" prop="form_key">
          <el-input v-model="createForm.form_key" placeholder="小写字母开头，如 activity" />
        </el-form-item>
        <el-form-item label="来源" prop="source_type">
          <el-radio-group v-model="createForm.source_type">
            <el-radio-button value="created">创建新表</el-radio-button>
            <el-radio-button value="adopted">采纳已有表</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="绑定表" prop="table_name">
          <el-select
            v-if="createForm.source_type === 'adopted'"
            v-model="createForm.table_name"
            filterable
            placeholder="选择已有表"
            class="w-full"
            @visible-change="loadTables"
          >
            <el-option v-for="table in tables" :key="table.name" :label="`${table.name}${table.comment ? '（' + table.comment + '）' : ''}`" :value="table.name" />
          </el-select>
          <el-input v-else v-model="createForm.table_name" placeholder="新表名，如 fun_activity" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="createForm.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onCreate">创建并设计</el-button>
      </template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus';
import { formDesignerApi, type FormDefinition } from '@/api/form';
import { crudDevelopmentApi } from '@/api/development/crud';

const router = useRouter();
const loading = ref(false);
const saving = ref(false);
const rows = ref<FormDefinition[]>([]);
const total = ref(0);
const query = reactive({ page: 1, pageSize: 20, keyword: '', status: '' as number | string });
const tables = ref<Array<{ name: string; comment?: string }>>([]);
const createVisible = ref(false);
const createFormRef = ref<FormInstance>();
const createForm = reactive({ name: '', form_key: '', source_type: 'created' as 'created' | 'adopted', table_name: '', remark: '' });
const createRules: FormRules = {
  name: [{ required: true, message: '请输入表单名称', trigger: 'blur' }],
  form_key: [{ required: true, pattern: /^[a-z][a-z0-9_]{1,60}$/, message: '小写字母开头的字母数字下划线', trigger: 'blur' }],
  table_name: [{ required: true, pattern: /^[a-z][a-z0-9_]*$/, message: '表名不合法', trigger: 'blur' }]
};

async function loadData() {
  loading.value = true;
  try {
    const data = await formDesignerApi.list({ ...query });
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
  Object.assign(query, { page: 1, pageSize: 20, keyword: '', status: '' });
  loadData();
};
const openCreate = () => {
  Object.assign(createForm, { name: '', form_key: '', source_type: 'created', table_name: '', remark: '' });
  createVisible.value = true;
};
async function loadTables(visible: boolean) {
  if (!visible || tables.value.length) return;
  tables.value = await crudDevelopmentApi.tables('mysql');
}
async function onCreate() {
  await createFormRef.value?.validate();
  saving.value = true;
  try {
    const saved = await formDesignerApi.save({ ...createForm, fields: [] });
    createVisible.value = false;
    ElMessage.success('表单已创建');
    router.push({ path: '/form/designer', query: { id: String(saved.form.id) } });
  } finally {
    saving.value = false;
  }
}
const goDesigner = (row: FormDefinition) => router.push({ path: '/form/designer', query: { id: String(row.id) } });
async function toggleStatus(row: FormDefinition) {
  await formDesignerApi.status(row.id as number, row.status === 1 ? 0 : 1);
  ElMessage.success('状态已更新');
  loadData();
}
async function onDelete(row: FormDefinition) {
  await ElMessageBox.confirm(`确认删除表单 ${row.name} ?（仅删元数据，不删业务表）`, '删除确认', { type: 'warning' });
  await formDesignerApi.remove(row.id as number);
  ElMessage.success('删除成功');
  loadData();
}
onMounted(loadData);
</script>
