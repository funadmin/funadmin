<template>
  <el-drawer :model-value="modelValue" :title="t('aiDevelopment.providerSettings.title')" size="min(520px, 94vw)" @update:model-value="$emit('update:modelValue', $event)">
    <el-alert :title="t('aiDevelopment.providerSettings.securityNotice')" type="info" :closable="false" />
    <el-form class="provider-form" @submit.prevent="submit">
      <el-form-item :label="t('aiDevelopment.provider')"><el-input v-model="form.name" /></el-form-item>
      <el-form-item :label="t('aiDevelopment.fields.baseUrl')"><el-input v-model="form.base_url" /></el-form-item>
      <el-form-item :label="t('aiDevelopment.fields.model')"><el-input v-model="form.model" /></el-form-item>
      <el-form-item :label="t('aiDevelopment.fields.apiKey')">
        <input v-model="apiKey" data-testid="provider-api-key" type="password" autocomplete="new-password" :placeholder="t('aiDevelopment.providerSettings.apiKeyPlaceholder')" />
        <small v-if="settings.provider.configured">{{ t('aiDevelopment.providerSettings.configured', { masked: settings.provider.masked || '••••••••' }) }}</small>
      </el-form-item>
      <el-form-item :label="t('aiDevelopment.providerSettings.connectTimeout')"><el-input v-model.number="form.connect_timeout" type="number" /></el-form-item>
      <el-form-item :label="t('aiDevelopment.providerSettings.requestTimeout')"><el-input v-model.number="form.request_timeout" type="number" /></el-form-item>
      <el-button native-type="submit" type="primary">{{ t('aiDevelopment.providerSettings.testConnection') }}</el-button>
    </el-form>
  </el-drawer>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiProviderSettings } from '@/api/development/ai';
const props = defineProps<{ modelValue: boolean; settings: AiProviderSettings }>();
const { t } = useI18n();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; test: [payload: Record<string, unknown>] }>();
const apiKey = ref('');
const form = reactive({ ...props.settings.provider });
watch(() => props.settings.provider, (provider) => Object.assign(form, provider));
watch(() => props.modelValue, (visible) => { if (!visible) apiKey.value = ''; });
function submit() {
  const payload: Record<string, unknown> = { name: form.name, base_url: form.base_url, model: form.model, connect_timeout: form.connect_timeout, request_timeout: form.request_timeout, max_retries: form.max_retries };
  if (apiKey.value !== '') payload.api_key = apiKey.value;
  emit('test', payload);
  apiKey.value = '';
}
</script>

<style scoped>
.provider-form { display: grid; gap: 4px; margin-top: 16px; }
input[data-testid="provider-api-key"] { box-sizing: border-box; width: 100%; height: 32px; border: 1px solid var(--el-border-color); border-radius: 4px; padding: 0 11px; background: var(--el-fill-color-blank); color: var(--el-text-color-regular); }
small { display: block; margin-top: 4px; color: var(--el-text-color-secondary); }
</style>
