<template>
  <el-drawer :model-value="modelValue" :title="t('aiDevelopment.profiles.title')" size="min(900px, 96vw)" @update:model-value="$emit('update:modelValue', $event)">
    <div class="profile-layout">
      <nav :aria-label="t('aiDevelopment.profiles.title')">
        <button type="button" :disabled="busy" @click="select()">{{ t('aiDevelopment.profiles.create') }}</button>
        <button v-for="item in profiles" :key="item.id" type="button" :disabled="busy" :aria-pressed="selectedId === item.id" @click="select(item)">{{ item.name }}{{ item.is_default ? '（默认档案）' : '' }} · {{ item.model }}</button>
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
          <label>默认 Model<el-select v-model="form.model" filterable allow-create default-first-option :aria-label="t('aiDevelopment.fields.model')"><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select></label>
          <label>{{ t('aiDevelopment.profiles.favorites') }}<el-select v-model="form.favorite_models" multiple filterable allow-create default-first-option><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select></label>
        </fieldset>
        <details>
          <summary>模型能力（管理员声明）</summary>
          <fieldset :disabled="busy">
            <p>以下能力由管理员明确声明，不代表官方验证。目录只提供模型标识，不自动推断或采纳能力。</p>
            <p v-for="item in models" :key="item.id">{{ item.id }} · source: {{ item.capabilities?.source || 'unknown' }}（{{ item.capabilities?.source === 'administrator' ? '已保存的管理员声明' : '未知能力' }}）</p>
            <div v-for="(cap, index) in form.model_capabilities" :key="index" class="capability-row">
              <label>模型标识<input v-model="cap.model" required maxlength="200" /></label>
              <label v-for="effort in efforts" :key="effort"><input v-model="cap.reasoning_efforts" type="checkbox" :value="effort" />{{ effort }}</label>
              <label>输出参数<select v-model="cap.output_token_parameter" :data-testid="`cap-output-${index}`"><option value="max_tokens">max_tokens</option><option value="max_completion_tokens">max_completion_tokens</option></select></label>
              <label>模型上下文上限<input v-model.number="cap.context_window" type="number" min="1" max="10000000" placeholder="未知" /></label>
              <label>模型输出上限<input v-model.number="cap.max_output_tokens" type="number" min="1" max="10000000" placeholder="未知" /></label>
              <button type="button" @click="form.model_capabilities?.splice(index, 1)">删除声明</button>
            </div>
            <button type="button" :disabled="(form.model_capabilities?.length || 0) >= 100" @click="form.model_capabilities?.push({ model: '', reasoning_efforts: [], output_token_parameter: 'max_tokens', context_window: null, max_output_tokens: null })">添加模型能力声明</button>
          </fieldset>
        </details>
        <fieldset :disabled="busy">
          <legend>Token 限制</legend>
          <p>当前模型：{{ currentCapability.source === 'administrator' ? '管理员声明' : '未知能力' }}；上下文 {{ currentCapability.context_window ?? '未知' }}；输出 {{ currentCapability.max_output_tokens ?? '未知' }}；输出参数 {{ currentCapability.output_token_parameter }}</p>
          <label v-for="field in numericFields.slice(0, 3)" :key="field.key">{{ t(`aiDevelopment.profiles.${field.key}`) }}<input v-model.number="form[field.key]" type="number" :min="field.min" :max="field.max" step="1" :required="!field.nullable" /></label>
          <label>思考模式<select data-testid="reasoning-effort" :value="form.reasoning_effort || ''" @change="form.reasoning_effort = (($event.target as HTMLSelectElement).value || null) as AiReasoningEffort | null"><option value="">默认（不发送 reasoning_effort）</option><option v-for="effort in legalEfforts" :key="effort" :value="effort">{{ effort }}</option></select></label>
          <p v-if="form.reasoning_effort && !legalEfforts.includes(form.reasoning_effort)" role="alert">已保存档位 {{ form.reasoning_effort }} 不兼容当前选择，请明确选择默认或合法档位。</p>
        </fieldset>
        <details>
          <summary>高级参数与有序备用模型</summary>
          <fieldset :disabled="busy">
            <label><input v-model="form.fallback_enabled" data-testid="fallback-enabled" type="checkbox" />启用 Fallback</label>
            <p>同档案有序备用，最多 3 个，不得重复或包含主模型；所有候选须兼容当前思考档位和预算。流式请求不支持备用切换。</p>
            <el-select :model-value="null" filterable allow-create placeholder="添加备用模型" :disabled="(form.fallback_models?.length || 0) >= 3" @update:model-value="addFallback"><el-option v-for="id in fallbackOptions" :key="id" :value="id" :label="id" /></el-select>
            <div v-for="(model, index) in form.fallback_models" :key="model" class="actions">
              <span>{{ index + 1 }}. {{ model }}</span>
              <button type="button" :data-testid="`fallback-up-${index}`" :disabled="index === 0" @click="moveFallback(index, -1)">上移</button>
              <button type="button" :disabled="index === (form.fallback_models?.length || 0) - 1" @click="moveFallback(index, 1)">下移</button>
              <button type="button" @click="form.fallback_models?.splice(index, 1)">移除</button>
            </div>
            <label v-for="field in numericFields.slice(3)" :key="field.key">{{ t(`aiDevelopment.profiles.${field.key}`) }}<input v-model.number="form[field.key]" type="number" :min="field.min" :max="field.max" step="1" required /></label>
            <label><input v-model="form.stream_usage" type="checkbox" />{{ t('aiDevelopment.profiles.stream_usage') }}</label>
            <p>运行累计上限：{{ selectedRuntime?.max_requests ?? 12 }} 次请求、{{ selectedRuntime?.max_reserved_seconds ?? 300 }} 秒预留超时；达到上限由后端终止，不保证尝试完所有备用。</p>
          </fieldset>
        </details>
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
import { profileCapabilityError, profileModelCapability, type AiCatalogModel, type AiReasoningEffort, type AiProfile, type AiProfileInput, type AiProviderSettings } from '@/api/development/ai';
const props = withDefaults(defineProps<{ modelValue: boolean; settings?: AiProviderSettings; profiles?: AiProfile[]; busy?: boolean; models?: AiCatalogModel[]; error?: string; notice?: string; savedProfile?: AiProfile | null }>(), { profiles: () => [], models: () => [] });
const { t } = useI18n();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; save: [payload: AiProfileInput, id: number | null]; test: [payload: Record<string, unknown>]; copy: [id: number]; remove: [id: number]; default: [id: number]; models: [id: number]; select: [] }>();
const selectedId = ref<number | null>(null);
const apiKey = ref('');
const clearKey = ref(false);
const hasKey = ref(false);
const validationError = ref('');
const defaults = (): AiProfileInput => ({ name: '', provider: 'openai-compatible', protocol: 'openai-chat', base_url: '', model: '', enabled: true, favorite_models: [], model_capabilities: [], fallback_enabled: false, fallback_models: [], reasoning_effort: null, context_window: null, max_input_tokens: null, max_output_tokens: null, max_iterations: 10, stream_usage: false, connect_timeout: 5, request_timeout: 60, max_retries: 2 });
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
const modelOptions = computed(() => [...new Set([...props.models.map((item) => item.id), ...(form.favorite_models || []), ...(form.model_capabilities || []).map(c => c.model), ...(form.model ? [form.model] : [])])].filter(Boolean));
const efforts: AiReasoningEffort[] = ['low', 'medium', 'high'];
const currentCapability = computed(() => profileModelCapability(form, form.model));
const selectedRuntime = computed(() => props.profiles.find(p => p.id === selectedId.value)?.runtime_capabilities);
const legalEfforts = computed(() => efforts.filter(e => [form.model, ...(form.fallback_enabled ? form.fallback_models || [] : [])].every(m => profileModelCapability(form, m).reasoning_efforts.includes(e))));
const fallbackOptions = computed(() => modelOptions.value.filter(m => m !== form.model && !form.fallback_models?.includes(m)));
function addFallback(value: string) {
  const model = value?.trim();
  if (model && model !== form.model && !form.fallback_models?.includes(model) && (form.fallback_models?.length || 0) < 3) form.fallback_models?.push(model);
}
function moveFallback(index: number, direction: number) {
  const list = form.fallback_models!;
  const target = index + direction;
  if (target >= 0 && target < list.length) [list[index], list[target]] = [list[target], list[index]];
}
function select(profile?: AiProfile) {
  validationError.value = '';
  selectedId.value = profile?.id ?? null;
  const clean = defaults();
  if (profile) for (const key of Object.keys(clean) as Array<keyof AiProfileInput>) { if (profile[key as keyof AiProfile] !== undefined) Object.assign(clean, { [key]: profile[key as keyof AiProfile] }); }
  Object.assign(form, clean, { protocol: 'openai-chat' });
  form.favorite_models = [...(clean.favorite_models || [])];
  form.fallback_models = [...(clean.fallback_models || [])];
  form.model_capabilities = (clean.model_capabilities || []).map(c => ({ model: c.model, reasoning_efforts: [...c.reasoning_efforts], output_token_parameter: c.output_token_parameter, context_window: c.context_window, max_output_tokens: c.max_output_tokens }));
  hasKey.value = profile?.has_api_key ?? false;
  apiKey.value = '';
  clearKey.value = false;
  emit('select');
}
watch(() => props.savedProfile, (profile) => { if (profile) select(profile); });
watch(() => props.modelValue, (visible) => { if (!visible) { apiKey.value = ''; clearKey.value = false; } else select(props.profiles.find((p) => p.id === selectedId.value)); });
function submit() {
  const payload = { ...form, favorite_models: [...(form.favorite_models || [])], fallback_models: [...(form.fallback_models || [])], model_capabilities: (form.model_capabilities || []).map(c => ({ ...c, reasoning_efforts: [...c.reasoning_efforts], context_window: String(c.context_window) === '' ? null : c.context_window, max_output_tokens: String(c.max_output_tokens) === '' ? null : c.max_output_tokens })) };
  for (const field of numericFields) if (field.nullable && (payload[field.key] === undefined || String(payload[field.key]) === '')) Object.assign(payload, { [field.key]: null });
  const capabilityError = profileCapabilityError(payload);
  if (capabilityError) { validationError.value = capabilityError; return; }
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
summary { cursor: pointer; padding: 10px 0; font-weight: 600; }
.capability-row { display: grid; gap: 8px; padding: 12px; border: 1px solid var(--el-border-color); }
select { max-width: 100%; padding: 8px; color: var(--el-text-color-primary); background: var(--el-bg-color); }
@media (max-width: 640px) { .profile-layout { grid-template-columns: 1fr; } nav { flex-direction: row; flex-wrap: wrap; } }
</style>
