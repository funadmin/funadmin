<template>
  <PageWrapper title="数据库采纳" subtitle="先只读检查表结构，再创建绑定已有数据表的业务模块">
    <el-card v-loading="loading" shadow="never">
      <el-form :model="form" label-width="110px" class="max-w-3xl">
        <el-form-item label="数据库连接"><el-input v-model="form.connection" /></el-form-item>
        <el-form-item label="数据表"><el-input v-model="form.table"><template #append><el-button :loading="inspecting" @click="inspect">检查结构</el-button></template></el-input></el-form-item>
        <el-form-item label="业务名称"><el-input v-model="form.name" maxlength="100" /></el-form-item>
        <el-form-item label="业务标识"><el-input v-model="form.code" maxlength="61" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="3" maxlength="1000" /></el-form-item>
      </el-form>
      <el-alert v-if="inspection" :title="`已识别 ${inspection.fields.length} 个字段，确认后将保存不可变 Schema 基线。`" type="success" :closable="false" class="mb-3" />
      <el-table v-if="inspection" :data="inspection.fields" border max-height="360">
        <el-table-column prop="field_name" label="字段" min-width="140" /><el-table-column prop="label" label="名称" min-width="140" /><el-table-column prop="column_type" label="列类型" min-width="140" /><el-table-column prop="type" label="控件" min-width="120" />
      </el-table>
      <div class="mt-4"><el-button type="primary" :disabled="!inspection" @click="create">采纳并进入设计器</el-button><el-button @click="router.push('/development/business/mine')">取消</el-button></div>
    </el-card>
  </PageWrapper>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { useRouter } from 'vue-router';
import { businessDevelopmentApi, type BusinessFieldInspection } from '@/api/development/business';

defineOptions({ name: 'BusinessDatabase' });
const router = useRouter();
const loading = ref(false);
const inspecting = ref(false);
const inspection = ref<BusinessFieldInspection | null>(null);
const form = reactive({ connection: 'mysql', table: '', name: '', code: '', remark: '' });
async function inspect() {
  if (!form.connection || !form.table) { ElMessage.warning('请输入连接和数据表'); return; }
  inspecting.value = true; inspection.value = null;
  try { inspection.value = await businessDevelopmentApi.inspectDatabase(form.connection, form.table); if (!form.code) form.code = form.table.replace(/^fun_/, '').toLowerCase(); if (!form.name) form.name = form.code; }
  finally { inspecting.value = false; }
}
async function create() {
  if (!inspection.value || !form.name || !form.code) { ElMessage.warning('请完成结构检查并填写业务名称、标识'); return; }
  loading.value = true;
  try { const result = await businessDevelopmentApi.createFromDatabase(form); await router.push({ path: '/development/business/designer', query: { id: String(result.module.form_id), moduleId: String(result.module.id) } }); }
  finally { loading.value = false; }
}
</script>
