<template>
  <component :is="interaction?.presentation === 'drawer' ? ElDrawer : ElDialog" v-if="visible" :model-value="visible" :title="interaction?.title || title" width="min(520px, 94vw)" size="min(520px, 94vw)" append-to-body :close-on-click-modal="false" @close="cancel">
    <el-alert v-if="failure" :title="failure" type="error" :closable="false" />
    <p v-if="interaction?.message">{{ interaction.message }}</p>
    <el-form label-position="top" @submit.prevent="submit">
      <el-form-item v-for="field in interaction?.fields ?? []" :key="field.name" :label="field.label" :required="field.required" :error="errors[field.name]">
        <el-input-number v-if="field.type === 'number'" v-model="values[field.name]" :min="field.min" :max="field.max" />
        <el-switch v-else-if="field.type === 'switch'" v-model="values[field.name]" />
        <el-select v-else-if="field.type === 'select'" v-model="values[field.name]"><el-option v-for="option in field.options ?? []" :key="option.value" :label="option.label" :value="option.value" /></el-select>
        <el-date-picker v-else-if="field.type === 'date'" v-model="values[field.name]" value-format="YYYY-MM-DD" />
        <el-input v-else v-model="values[field.name]" :type="field.type === 'textarea' ? 'textarea' : 'text'" :maxlength="field.maxLength ?? 10000" />
      </el-form-item>
    </el-form>
    <template #footer><el-button @click="cancel">{{ t('common.cancel', '取消') }}</el-button><el-button type="primary" @click="submit">{{ t('common.ok', '确定') }}</el-button></template>
  </component>
</template>
<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { ElDialog, ElDrawer } from 'element-plus';
import { useI18n } from 'vue-i18n';
import type { FormListButton, FormListButtonInteraction } from '../schema/types';
const { t } = useI18n();
const visible = ref(false);
const failure = ref('');
const interaction = ref<FormListButtonInteraction>();
const title = ref('');
const values = ref<Record<string, any>>({});
const errors = ref<Record<string, string>>({});
let resolve: ((value: Record<string, unknown> | null) => void) | undefined;
function cancel() { visible.value = false; resolve?.(null); resolve = undefined; }
function open(button: FormListButton, previous: Record<string, unknown>): Promise<Record<string, unknown> | null> {
  if (!button.interaction || button.interaction.type === 'none') return Promise.resolve(previous);
  cancel(); interaction.value = button.interaction; title.value = button.label;
  values.value = { ...previous }; errors.value = {}; failure.value = ''; visible.value = true;
  return new Promise(done => { resolve = done; });
}
function showError(button: FormListButton, previous: Record<string, unknown>, message: string, retry: (input: Record<string, unknown>) => void) {
  void open(button, previous).then(input => { if (input !== null) retry(input); });
  failure.value = message;
}
function submit() {
  errors.value = {};
  for (const field of interaction.value?.fields ?? []) {
    const value = values.value[field.name];
    if (field.required && (value === undefined || value === null || String(value).trim() === '')) errors.value[field.name] = t('formData.fieldRequired', '此项必填');
    else if (value !== undefined && value !== null && value !== '') {
      if (field.type === 'number' && (typeof value !== 'number' || !Number.isFinite(value) || (field.min !== undefined && value < field.min) || (field.max !== undefined && value > field.max))) errors.value[field.name] = t('formData.numberOutOfRange', '数值超出允许范围');
      if (typeof value === 'string' && value.length > (field.maxLength ?? 10000)) errors.value[field.name] = t('formData.inputTooLong', '输入过长');
      if (field.type === 'select' && !field.options?.some(option => option.value === value)) errors.value[field.name] = t('formData.invalidOption', '请选择有效选项');
    }
  }
  if (Object.keys(errors.value).length) return;
  resolve?.({ ...values.value }); resolve = undefined; visible.value = false;
}
onBeforeUnmount(cancel);
defineExpose({ open, cancel, showError });
</script>
