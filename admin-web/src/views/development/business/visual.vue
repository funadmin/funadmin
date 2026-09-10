<template>
  <PageWrapper title="可视化创建" subtitle="创建业务模块草稿后进入统一 FormSchema v2 设计器">
    <el-card shadow="never">
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top" class="business-form max-w-3xl">
        <el-form-item label="业务名称" prop="name">
          <el-input v-model="form.name" maxlength="100" aria-describedby="business-name-help" />
          <span id="business-name-help" class="field-help">用于展示业务模块，最多 100 个字符。</span>
        </el-form-item>
        <el-form-item label="业务标识" prop="code">
          <el-input v-model="form.code" maxlength="61" placeholder="例如 customer_order" aria-describedby="business-code-help" @blur="normalize" />
          <span id="business-code-help" class="field-help">以小写字母开头，只能包含小写字母、数字和下划线。</span>
        </el-form-item>
        <el-form-item label="数据表" prop="table">
          <el-input v-model="form.table" placeholder="默认 fun_业务标识" aria-describedby="business-table-help" />
          <span id="business-table-help" class="field-help">留空时根据业务标识自动生成。</span>
        </el-form-item>
        <el-form-item label="数据库连接" prop="connection">
          <el-input v-model="form.connection" aria-describedby="business-connection-help" />
          <span id="business-connection-help" class="field-help">填写已配置的数据库连接标识。</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="4" maxlength="1000" show-word-limit aria-describedby="business-remark-help" />
          <span id="business-remark-help" class="field-help">可选，最多 1000 个字符。</span>
        </el-form-item>
        <el-form-item>
          <div class="form-actions">
            <el-button type="primary" :loading="submitting" :disabled="submitting" @click="submit">创建并开始设计</el-button>
            <el-button :disabled="submitting" @click="cancel">取消</el-button>
          </div>
        </el-form-item>
      </el-form>
    </el-card>
  </PageWrapper>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import type { FormInstance, FormRules } from 'element-plus';
import { businessDevelopmentApi } from '@/api/development/business';
import { useDirtyGuard } from './composables/useDirtyGuard';

defineOptions({ name: 'BusinessVisual' });
const router = useRouter();
const submitting = ref(false);
const dirty = ref(false);
const formRef = ref<FormInstance>();
const form = reactive({ name: '', code: '', table: '', connection: 'mysql', remark: '' });
const identifier = /^[a-z_][a-z0-9_]*$/;
const rules: FormRules = {
  name: [{ required: true, message: '请输入业务名称', trigger: 'blur' }],
  code: [{ required: true, pattern: /^[a-z][a-z0-9_]{0,60}$/, message: '以小写字母开头，只能包含小写字母、数字和下划线', trigger: 'blur' }],
  table: [{ validator: (_rule, value, callback) => !value || identifier.test(value) ? callback() : callback(new Error('数据表标识不合法')), trigger: 'blur' }],
  connection: [{ required: true, pattern: identifier, message: '连接标识不合法', trigger: 'blur' }]
};

useDirtyGuard(dirty);
watch(form, () => { dirty.value = true; }, { deep: true, flush: 'sync' });

function normalize() {
  form.code = form.code.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
  if (!form.table && form.code) form.table = `fun_${form.code}`;
}

function fieldErrorMessage(value: unknown): string | undefined {
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return value.find((item): item is string => typeof item === 'string');
  if (value && typeof value === 'object' && 'message' in value && typeof value.message === 'string') return value.message;
  return undefined;
}

function errorRecord(reason: unknown): Record<string, unknown> {
  return reason && typeof reason === 'object' ? reason as Record<string, unknown> : {};
}

function responseDetails(reason: unknown): Record<string, unknown> {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  const error = errorRecord(data.error);
  return errorRecord(response.details ?? data.details ?? error.details);
}

function responseFieldErrors(reason: unknown): Record<string, string> {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  const details = responseDetails(reason);
  const source = details.fieldErrors ?? data.fieldErrors ?? response.fieldErrors;
  if (Array.isArray(source)) {
    return Object.fromEntries(source.flatMap((item) => {
      const error = errorRecord(item);
      const field = typeof error.path === 'string' ? error.path : typeof error.field === 'string' ? error.field : '';
      const message = fieldErrorMessage(error.message);
      return field && message ? [[field, message]] : [];
    }));
  }
  return Object.fromEntries(
    Object.entries(errorRecord(source)).flatMap(([field, value]) => {
      const message = fieldErrorMessage(value);
      return message ? [[field, message]] : [];
    })
  );
}

function responseCode(reason: unknown): number | undefined {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  return [response.status, response.code, data.status, data.code].find((value): value is number => typeof value === 'number');
}

function setFieldErrors(errors: Record<string, string>) {
  formRef.value?.clearValidate();
  for (const [field, message] of Object.entries(errors)) {
    const context = formRef.value?.fields.find((item) => item.prop === field);
    if (!context) continue;
    context.validateState = 'error';
    context.validateMessage = message;
  }
  const first = Object.keys(errors)[0];
  if (!first) return;
  formRef.value?.scrollToField(first);
  formRef.value?.fields.find((item) => item.prop === first)?.$el?.querySelector<HTMLElement>('input, textarea, select, [tabindex]')?.focus();
}

function cancel() {
  if (submitting.value) return;
  router.push('/development/business/mine');
}

async function submit() {
  if (submitting.value) return;
  submitting.value = true;
  try {
    normalize();
    if (!await formRef.value?.validate()) return;
    const result = await businessDevelopmentApi.createVisual(form);
    dirty.value = false;
    await router.push({ path: '/development/business/designer', query: { id: String(result.module.form_id), moduleId: String(result.module.id) } });
  } catch (reason) {
    const fieldErrors = responseFieldErrors(reason);
    if (Object.keys(fieldErrors).length) setFieldErrors(fieldErrors);
    else if (responseCode(reason) === 409) {
      const response = errorRecord(reason);
      setFieldErrors({ code: typeof response.msg === 'string' ? response.msg : '业务标识已存在' });
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.field-help {
  display: block;
  width: 100%;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.form-actions :deep(.el-button) {
  min-height: 44px;
  margin-left: 0;
}

@media (max-width: 640px) {
  .business-form :deep(.el-form-item__label) {
    width: auto !important;
  }

  .form-actions {
    width: 100%;
  }
}
</style>
