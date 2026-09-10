<template>
  <PageWrapper title="可视化创建" subtitle="创建业务模块草稿后进入统一 FormSchema v2 设计器">
    <el-card v-loading="loading" shadow="never">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" class="max-w-3xl">
        <el-form-item label="业务名称" prop="name"><el-input v-model="form.name" maxlength="100" /></el-form-item>
        <el-form-item label="业务标识" prop="code"><el-input v-model="form.code" maxlength="61" placeholder="例如 customer_order" @blur="normalize" /></el-form-item>
        <el-form-item label="数据表" prop="table"><el-input v-model="form.table" placeholder="默认 fun_业务标识" /></el-form-item>
        <el-form-item label="数据库连接" prop="connection"><el-input v-model="form.connection" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="4" maxlength="1000" show-word-limit /></el-form-item>
        <el-form-item><el-button type="primary" @click="submit">创建并开始设计</el-button><el-button @click="router.push('/development/business/mine')">取消</el-button></el-form-item>
      </el-form>
    </el-card>
  </PageWrapper>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import type { FormInstance, FormRules } from 'element-plus';
import { businessDevelopmentApi } from '@/api/development/business';

defineOptions({ name: 'BusinessVisual' });
const router = useRouter();
const loading = ref(false);
const formRef = ref<FormInstance>();
const form = reactive({ name: '', code: '', table: '', connection: 'mysql', remark: '' });
const identifier = /^[a-z_][a-z0-9_]*$/;
const rules: FormRules = {
  name: [{ required: true, message: '请输入业务名称', trigger: 'blur' }],
  code: [{ required: true, pattern: /^[a-z][a-z0-9_]{0,60}$/, message: '以小写字母开头，只能包含小写字母、数字和下划线', trigger: 'blur' }],
  table: [{ validator: (_rule, value, callback) => !value || identifier.test(value) ? callback() : callback(new Error('数据表标识不合法')), trigger: 'blur' }],
  connection: [{ required: true, pattern: identifier, message: '连接标识不合法', trigger: 'blur' }]
};
function normalize() { form.code = form.code.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_'); if (!form.table && form.code) form.table = `fun_${form.code}`; }
async function submit() {
  normalize();
  if (!await formRef.value?.validate()) return;
  loading.value = true;
  try {
    const result = await businessDevelopmentApi.createVisual(form);
    await router.push({ path: '/development/business/designer', query: { id: String(result.module.form_id), moduleId: String(result.module.id) } });
  } finally { loading.value = false; }
}
</script>
