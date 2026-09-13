<template>
  <el-drawer :model-value="modelValue" :title="t('aiDevelopment.profiles.title')" size="min(900px, 96vw)" @update:model-value="$emit('update:modelValue', $event)">
    <div class="profile-layout">
      <nav :aria-label="t('aiDevelopment.profiles.title')">
        <button type="button" :disabled="busy" @click="select()">{{ t('aiDevelopment.profiles.create') }}</button>
        <button v-for="item in profiles" :key="item.id" type="button" :disabled="busy" :aria-pressed="selectedId === item.id" @click="select(item)">{{ item.name }} {{ item.is_default ? '★' : '' }}</button>
      </nav>
      <form class="provider-form" @submit.prevent="submit">
        <fieldset :disabled="busy">
          <legend>{{ t('aiDevelopment.profiles.general') }}</legend>
          <label>{{ t('aiDevelopment.profiles.name') }}<input v-model="form.name" required maxlength="100" /></label>
          <label><input v-model="form.enabled" type="checkbox" />{{ t('aiDevelopment.profiles.enabled') }}</label>
          <div v-if="selectedId" class="actions">
            <button type="button" @click="$emit('copy', selectedId)">{{ t('aiDevelopment.profiles.copy') }}</button>
            <button type="button" @click="$emit('remove', selectedId)">{{ t('aiDevelopment.management.delete') }}</button>
            <button type="button" @click="$emit('default', selectedId)">{{ t('aiDevelopment.profiles.default') }}</button>
          </div>
          <small>{{ t('aiDevelopment.profiles.copyHint') }}</small>
        </fieldset>
        <fieldset :disabled="busy">
          <legend>{{ t('aiDevelopment.provider') }}</legend>
          <p>{{ t('aiDevelopment.profiles.protocolHint') }}</p>
          <label>Provider<input v-model="form.provider" required pattern="[a-z0-9][a-z0-9._-]*" maxlength="64" /></label>
          <label>Base URL<input v-model="form.base_url" type="url" required /></label>
          <label>API key<input v-model="apiKey" data-testid="provider-api-key" type="password" autocomplete="new-password" :placeholder="t('aiDevelopment.profiles.keyHint')" /></label>
          <small>{{ t(hasKey ? 'aiDevelopment.profiles.hasKey' : 'aiDevelopment.profiles.noKey') }}</small>
          <label><input v-model="clearKey" type="checkbox" />{{ t('aiDevelopment.profiles.clearKey') }}</label>
        </fieldset>
        <fieldset :disabled="busy">
          <legend>{{ t('aiDevelopment.profiles.models') }}</legend>
          <button type="button" :disabled="!selectedId" @click="$emit('models', selectedId!)">{{ t('aiDevelopment.profiles.fetchModels') }}</button>
          <small>{{ t('aiDevelopment.profiles.modelsHint') }}</small>
          <label>Model<el-select v-model="form.model" filterable allow-create default-first-option :aria-label="t('aiDevelopment.fields.model')"><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select></label>
          <label>{{ t('aiDevelopment.profiles.favorites') }}<el-select v-model="form.favorite_models" multiple filterable allow-create default-first-option><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select></label>
        </fieldset>
        <fieldset :disabled="busy">
          <legend>{{ t('aiDevelopment.profiles.limits') }}</legend>
          <label v-for="field in numericFields" :key="field.key">{{ t(`aiDevelopment.profiles.${field.key}`) }}<input v-model.number="form[field.key]" type="number" :min="field.min" :max="field.max" step="1" :required="!field.nullable" /></label>
          <label><input v-model="form.stream_usage" type="checkbox" />{{ t('aiDevelopment.profiles.stream_usage') }}</label>
        </fieldset>
        <fieldset disabled>
          <legend>{{ t('aiDevelopment.profiles.unsupported') }}</legend>
          <label><input data-testid="fallback-disabled" type="checkbox" disabled />Fallback</label>
          <label>Reasoning<input disabled :value="t('aiDevelopment.profiles.disabled')" /></label>
          <p>{{ t('aiDevelopment.profiles.unsupportedHint') }}</p>
        </fieldset>
        <p v-if="error || validationError" role="alert">{{ error || validationError }}</p>
        <p v-if="notice" role="status">{{ notice }}</p>
        <small>{{ t('aiDevelopment.profiles.testHint') }}</small>
        <div class="actions">
          <button data-testid="profile-save" type="submit" :disabled="busy">{{ t('aiDevelopment.profiles.save') }}</button>
          <button data-testid="profile-test" type="button" :disabled="busy" @click="test">{{ t('aiDevelopment.providerSettings.testConnection') }}</button>
        </div>
      </form>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiProfile, AiProfileInput, AiProviderSettings } from '@/api/development/ai';
const props = withDefaults(defineProps<{ modelValue: boolean; settings?: AiProviderSettings; profiles?: AiProfile[]; busy?: boolean; models?: Array<{ id: string }>; error?: string; notice?: string; savedProfile?: AiProfile | null }>(), { profiles: () => [], models: () => [] });
const { t } = useI18n();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; save: [payload: AiProfileInput, id: number | null]; test: [payload: Record<string, unknown>]; copy: [id: number]; remove: [id: number]; default: [id: number]; models: [id: number]; select: [] }>();
const selectedId = ref<number | null>(null);
const apiKey = ref('');
const clearKey = ref(false);
const hasKey = ref(false);
const validationError = ref('');
const defaults = (): AiProfileInput => ({ name: '', provider: 'openai-compatible', protocol: 'openai-chat', base_url: '', model: '', enabled: true, favorite_models: [], fallback_enabled: false, fallback_models: [], reasoning_effort: null, context_window: null, max_input_tokens: null, max_output_tokens: null, max_iterations: 10, stream_usage: false, connect_timeout: 5, request_timeout: 60, max_retries: 2 });
const form = reactive(defaults());
const numericFields = [
  { key: 'context_window', min: 1, max: 10000000, nullable: true },
  { key: 'max_input_tokens', min: 1, max: 10000000, nullable: true },
  { key: 'max_output_tokens', min: 1, max: 10000000, nullable: true },
  { key: 'max_iterations', min: 1, max: 100, nullable: false },
  { key: 'connect_timeout', min: 1, max: 30, nullable: false },
  { key: 'request_timeout', min: 1, max: 300, nullable: false },
  { key: 'max_retries', min: 0, max: 3, nullable: false }
] as const;
const modelOptions = computed(() => [...new Set([...props.models.map((item) => item.id), ...(form.favorite_models || []), ...(form.model ? [form.model] : [])])]);
function select(profile?: AiProfile) {
  validationError.value = '';
  selectedId.value = profile?.id ?? null;
  const clean = defaults();
  if (profile) for (const key of Object.keys(clean) as Array<keyof AiProfileInput>) { if (profile[key as keyof AiProfile] !== undefined) Object.assign(clean, { [key]: profile[key as keyof AiProfile] }); }
  Object.assign(form, clean, { protocol: 'openai-chat', fallback_enabled: false, fallback_models: [], reasoning_effort: null });
  form.favorite_models = [...(clean.favorite_models || [])];
  hasKey.value = profile?.has_api_key ?? false;
  apiKey.value = '';
  clearKey.value = false;
  emit('select');
}
watch(() => props.savedProfile, (profile) => { if (profile) select(profile); });
watch(() => props.modelValue, (visible) => { if (!visible) { apiKey.value = ''; clearKey.value = false; } else select(props.profiles.find((p) => p.id === selectedId.value)); });
function submit() {
  const payload = { ...form, favorite_models: [...(form.favorite_models || [])] };
  for (const field of numericFields) if (field.nullable && (payload[field.key] === undefined || String(payload[field.key]) === '')) Object.assign(payload, { [field.key]: null });
  if (clearKey.value || apiKey.value) payload.api_key = clearKey.value ? '' : apiKey.value;
  let validUrl = false;
  try { const url = new URL(payload.base_url); validUrl = ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password && !url.search && !url.hash; } catch { /* 无效地址由统一校验提示处理。 */ }
  const invalidNumber = numericFields.some((field) => { const value = payload[field.key]; return !(field.nullable && value === null) && (typeof value !== 'number' || !Number.isInteger(value) || value < field.min || value > field.max); });
  if (!payload.name.trim() || !payload.model.trim() || !validUrl || invalidNumber || (payload.context_window !== null && (payload.max_input_tokens || 0) + (payload.max_output_tokens || 0) > (payload.context_window || 0)) || payload.connect_timeout! > payload.request_timeout!) {
    validationError.value = t('aiDevelopment.profiles.invalid'); return;
  }
  validationError.value = '';
  emit('save', payload, selectedId.value);
  apiKey.value = '';
}
function test() {
  emit('test', { protocol: 'openai-chat', name: form.provider, base_url: form.base_url, model: form.model, connect_timeout: form.connect_timeout, request_timeout: form.request_timeout, max_retries: form.max_retries, api_key: clearKey.value ? '' : apiKey.value });
  apiKey.value = '';
}
</script>

<style scoped>
.profile-layout { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 20px; }
nav, .provider-form { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
nav button { text-align: left; overflow-wrap: anywhere; }
button { cursor: pointer; padding: 8px 12px; border: 1px solid var(--el-border-color); border-radius: 6px; color: var(--el-text-color-primary); background: var(--el-bg-color); }
button[aria-pressed="true"] { border-color: var(--el-color-primary); color: var(--el-color-primary); }
button:disabled { cursor: not-allowed; opacity: .55; }
fieldset { margin: 0; padding: 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; display: grid; gap: 12px; min-width: 0; }
legend { font-weight: 600; } label { display: grid; gap: 6px; }
input:not([type="checkbox"]) { box-sizing: border-box; width: 100%; padding: 8px; background: var(--el-fill-color-blank); color: var(--el-text-color-primary); border: 1px solid var(--el-border-color); border-radius: 4px; }
small, p { margin: 0; color: var(--el-text-color-secondary); line-height: 1.6; } [role="alert"] { color: var(--el-color-danger); }
.actions { display: flex; flex-wrap: wrap; gap: 8px; }
@media (max-width: 640px) { .profile-layout { grid-template-columns: 1fr; } nav { flex-direction: row; flex-wrap: wrap; } }
</style>
