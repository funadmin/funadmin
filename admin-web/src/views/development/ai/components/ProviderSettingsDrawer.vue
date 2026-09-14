<template>
  <el-drawer class="provider-drawer" :model-value="modelValue" :title="t('aiDevelopment.profiles.title')" size="min(1080px, 100vw)" @update:model-value="$emit('update:modelValue', $event)">
    <el-form class="provider-form" :model="form" :disabled="busy" label-width="136px" @submit.prevent="submit">
      <div class="profile-toolbar">
        <el-form-item label="配置档案">
          <el-select data-testid="profile-select" :model-value="selectedId" placeholder="新建档案" @update:model-value="select(profiles.find(p => p.id === $event))">
            <el-option v-for="item in profiles" :key="item.id" :value="item.id" :label="`${item.name}${item.is_default ? '（默认档案）' : ''} · ${item.model}`" />
          </el-select>
        </el-form-item>
        <el-form-item label="档案名称"><el-input v-model="form.name" required maxlength="100" /></el-form-item>
        <div class="profile-actions">
          <el-button data-testid="profile-create" @click="select()">{{ t('aiDevelopment.profiles.create') }}</el-button>
          <el-tooltip :content="t('aiDevelopment.profiles.copyHint')"><el-button :disabled="!selectedId" @click="$emit('copy', selectedId!)">{{ t('aiDevelopment.profiles.copy') }}</el-button></el-tooltip>
          <el-button :disabled="!selectedId" type="danger" plain @click="$emit('remove', selectedId!)">{{ t('aiDevelopment.management.delete') }}</el-button>
          <el-button :disabled="!selectedId" @click="$emit('default', selectedId!)">{{ t('aiDevelopment.profiles.default') }}</el-button>
        </div>
      </div>
      <div class="profile-content" data-testid="profile-content">
        <section class="profile-section profile-fields">
          <h3>连接与模型</h3>
          <el-form-item class="profile-wide" label="供应商预设"><div class="model-control"><el-select v-model="presetId" data-testid="provider-preset"><el-option v-for="preset in presets" :key="preset.id" :value="preset.id" :label="preset.label" /></el-select><el-button data-testid="apply-preset" @click="applyPreset">应用到连接</el-button></div></el-form-item>
          <el-form-item class="profile-wide" label="API 协议"><el-select v-model="form.protocol" data-testid="provider-protocol"><el-option value="openai-chat" label="Chat Completions" /><el-option value="openai-responses" label="Responses" /><el-option value="anthropic-messages" label="Anthropic Messages" /></el-select></el-form-item>
          <p class="profile-wide">预设仅在点击应用后替换供应商、协议和地址，不预置密钥；应用到已有档案需再次确认。协议与地址可独立修改。</p>
          <p v-if="presetId === 'ollama'" class="profile-wide">Ollama 官方本地地址为 http://localhost:11434/v1；本系统禁止本机、私网及 HTTP，请填写经授权的公网 HTTPS 网关地址。</p>
          <p v-if="form.protocol === 'anthropic-messages'" class="profile-wide">Messages 必须设置明确的输出 Token 预算，推理档位请选择默认。</p>
          <el-form-item :label="t('aiDevelopment.profiles.enabled')"><el-switch v-model="form.enabled" :aria-label="t('aiDevelopment.profiles.enabled')" /></el-form-item>
          <el-form-item label="供应商标识">
            <el-input v-model="form.provider" required pattern="[a-z0-9][a-z0-9._-]*" maxlength="64" placeholder="例如 openai-compatible" />
          </el-form-item>
          <el-form-item class="profile-wide" label="Base URL"><el-input v-model="form.base_url" type="url" required placeholder="https://api.example.com/v1" /></el-form-item>
          <el-form-item label="API key"><el-input v-model="apiKey" data-testid="provider-api-key" type="password" autocomplete="new-password" :placeholder="t('aiDevelopment.profiles.keyHint')" /></el-form-item>
          <el-form-item label="密钥状态">
            <div class="control-stack">
              <small>{{ t(hasKey ? 'aiDevelopment.profiles.hasKey' : 'aiDevelopment.profiles.noKey') }}</small>
              <el-switch v-model="clearKey" data-testid="provider-clear-key" :active-text="t('aiDevelopment.profiles.clearKey')" :aria-label="t('aiDevelopment.profiles.clearKey')" />
            </div>
          </el-form-item>
          <el-form-item class="profile-wide" label="默认 Model">
            <div class="model-control">
              <el-select v-model="form.model" data-testid="profile-model" filterable allow-create default-first-option :aria-label="t('aiDevelopment.fields.model')"><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select>
              <el-tooltip :content="t('aiDevelopment.profiles.modelsHint')"><el-button :disabled="!selectedId || targetChanged" @click="$emit('models', selectedId!)">获取模型</el-button></el-tooltip>
            </div>
          </el-form-item>
          <el-form-item class="profile-wide" :label="t('aiDevelopment.profiles.favorites')"><el-select v-model="form.favorite_models" multiple filterable allow-create default-first-option><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select></el-form-item>
        </section>
        <section class="profile-section">
          <h3>备用模型</h3>
          <el-form-item label="启用备用"><el-switch v-model="form.fallback_enabled" data-testid="fallback-enabled" aria-label="启用 Fallback" /></el-form-item>
          <el-form-item label="有序备用模型">
            <div class="control-stack">
              <el-select :model-value="null" filterable allow-create default-first-option placeholder="添加备用模型（最多 3 个）" :disabled="(form.fallback_models?.length || 0) >= 3" @update:model-value="addFallback"><el-option v-for="id in fallbackOptions" :key="id" :value="id" :label="id" /></el-select>
              <div v-for="(model, index) in form.fallback_models" :key="model" class="fallback-row">
                <span>{{ index + 1 }}. {{ model }}</span>
                <el-button :data-testid="`fallback-up-${index}`" :disabled="index === 0" @click="moveFallback(index, -1)">上移</el-button>
                <el-button :disabled="index === (form.fallback_models?.length || 0) - 1" @click="moveFallback(index, 1)">下移</el-button>
                <el-button @click="form.fallback_models?.splice(index, 1)">移除</el-button>
              </div>
            </div>
          </el-form-item>
        </section>
        <section class="profile-section">
          <h3>Token 限制</h3>
          <div class="token-grid">
            <el-form-item v-for="field in numericFields.slice(0, 3)" :key="field.key" :label="t(`aiDevelopment.profiles.${field.key}`)" label-position="top">
              <el-input-number v-model="form[field.key]" :min="field.min" :max="field.max" :step="1" :precision="0" :value-on-clear="null" controls-position="right" placeholder="不指定" />
            </el-form-item>
          </div>
          <el-form-item :label="t('aiDevelopment.reasoning.profile')"><el-select data-testid="reasoning-effort" :model-value="form.reasoning_effort || ''" @update:model-value="form.reasoning_effort = ($event || null) as AiReasoningEffort | null"><el-option value="" :label="t('aiDevelopment.reasoning.defaultOption')" /><el-option v-for="effort in legalEfforts" :key="effort" :value="effort" :label="effort" /></el-select></el-form-item>
          <p v-if="form.reasoning_effort && !legalEfforts.includes(form.reasoning_effort)" role="alert">已保存档位 {{ form.reasoning_effort }} 不兼容当前选择，请明确选择默认或合法档位。</p>
        </section>
        <details class="profile-section profile-advanced" data-testid="profile-advanced">
          <summary>高级参数与模型能力</summary>
          <div class="profile-fields">
            <el-form-item v-for="field in numericFields.slice(3)" :key="field.key" :label="t(`aiDevelopment.profiles.${field.key}`)"><el-input-number v-model="form[field.key]" :min="field.min" :max="field.max" :step="1" :precision="0" controls-position="right" /></el-form-item>
            <el-form-item :label="t('aiDevelopment.profiles.stream_usage')"><el-switch v-model="form.stream_usage" :aria-label="t('aiDevelopment.profiles.stream_usage')" /></el-form-item>
          </div>
          <h3>模型能力（管理员声明）</h3>
          <div v-for="(cap, index) in form.model_capabilities" :key="index" class="capability-row">
            <el-form-item label="模型标识"><el-input v-model="cap.model" required maxlength="200" /></el-form-item>
            <el-form-item class="profile-wide" :label="t('aiDevelopment.reasoning.levels')"><el-checkbox-group v-model="cap.reasoning_efforts"><el-checkbox v-for="effort in efforts" :key="effort" :value="effort">{{ effort }}</el-checkbox></el-checkbox-group></el-form-item>
            <el-form-item label="输出参数"><el-select v-model="cap.output_token_parameter" :data-testid="`cap-output-${index}`"><el-option value="max_tokens" label="max_tokens" /><el-option value="max_completion_tokens" label="max_completion_tokens" /></el-select></el-form-item>
            <el-form-item label="模型上下文上限"><el-input-number v-model="cap.context_window" :min="1" :max="10000000" :precision="0" :value-on-clear="null" placeholder="未知" controls-position="right" /></el-form-item>
            <el-form-item label="模型输出上限"><el-input-number v-model="cap.max_output_tokens" :min="1" :max="10000000" :precision="0" :value-on-clear="null" placeholder="未知" controls-position="right" /></el-form-item>
            <el-form-item :label="t('aiComposer.imageInput')"><el-switch v-model="cap.image_input" :data-testid="`cap-image-${index}`" :aria-label="t('aiComposer.imageInput')" /></el-form-item>
            <el-form-item :label="t('aiComposer.maxImages')"><el-input-number v-model="cap.max_images" :min="1" :max="4" :precision="0" /></el-form-item>
            <el-form-item class="profile-wide" label="图片格式"><el-checkbox-group v-model="cap.image_mime_types"><el-checkbox v-for="mime in ['image/png', 'image/jpeg', 'image/webp']" :key="mime" :value="mime">{{ mime }}</el-checkbox></el-checkbox-group></el-form-item>
            <div class="profile-actions"><small>{{ t('aiComposer.imageTokens') }}: 32768</small><el-button type="danger" plain @click="form.model_capabilities?.splice(index, 1)">删除声明</el-button></div>
          </div>
          <el-button :disabled="(form.model_capabilities?.length || 0) >= 100" @click="form.model_capabilities?.push({ model: '', reasoning_efforts: [], output_token_parameter: 'max_tokens', context_window: null, max_output_tokens: null, image_input: false, image_tokens: 32768, max_images: 4, image_mime_types: ['image/png', 'image/jpeg', 'image/webp'] })">添加模型能力声明</el-button>
        </details>
      </div>
      <footer class="profile-footer" data-testid="profile-footer">
        <div class="profile-feedback"><p v-if="error || validationError" role="alert">{{ error || validationError }}</p><p v-if="notice" role="status">{{ notice }}</p></div>
        <div class="profile-actions">
          <el-tooltip :content="t('aiDevelopment.profiles.testHint')"><el-button data-testid="profile-test" :disabled="busy" @click="test">{{ t('aiDevelopment.providerSettings.testConnection') }}</el-button></el-tooltip>
          <el-button data-testid="profile-save" type="primary" native-type="submit" :loading="busy">{{ t('aiDevelopment.profiles.save') }}</el-button>
        </div>
      </footer>
    </el-form>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { AI_REASONING_EFFORTS, profileCapabilityError, profileModelCapability, type AiCatalogModel, type AiReasoningEffort, type AiProfile, type AiProfileInput, type AiProviderSettings } from '@/api/development/ai';
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
const presetId = ref('custom');
const presets: Array<{ id: string; label: string; url: string; protocol?: AiProfileInput['protocol'] }> = [
  { id: 'openai', label: 'OpenAI', url: 'https://api.openai.com/v1' },
  { id: 'anthropic', label: 'Anthropic (Claude)', url: 'https://api.anthropic.com/v1', protocol: 'anthropic-messages' },
  { id: 'deepseek', label: 'DeepSeek', url: 'https://api.deepseek.com/v1' },
  { id: 'kimi', label: 'Kimi', url: 'https://api.moonshot.cn/v1' },
  { id: 'zhipu', label: '智谱 GLM', url: 'https://open.bigmodel.cn/api/paas/v4' },
  { id: 'qwen', label: '通义千问（北京）', url: 'https://dashscope.aliyuncs.com/compatible-mode/v1' },
  { id: 'doubao', label: '豆包（北京）', url: 'https://ark.cn-beijing.volces.com/api/v3' },
  { id: 'siliconflow', label: '硅基流动', url: 'https://api.siliconflow.cn/v1' },
  { id: 'openrouter', label: 'OpenRouter', url: 'https://openrouter.ai/api/v1' },
  { id: 'groq', label: 'Groq', url: 'https://api.groq.com/openai/v1' },
  { id: 'google-gemini', label: 'Google Gemini（OpenAI 兼容）', url: 'https://generativelanguage.googleapis.com/v1beta/openai' },
  { id: 'ollama', label: 'Ollama（自备安全网关）', url: '' },
  { id: 'custom', label: '自定义', url: '' }
];
const originalTarget = ref('');
const target = () => JSON.stringify([form.provider, form.protocol, form.base_url]);
const targetChanged = computed(() => selectedId.value !== null && originalTarget.value !== target());
const presetConfirmation = ref('');
function applyPreset() {
  const preset = presets.find(p => p.id === presetId.value);
  if (!preset || preset.id === 'custom') return;
  if (selectedId.value && presetConfirmation.value !== preset.id) {
    presetConfirmation.value = preset.id;
    validationError.value = '再次点击应用将替换当前档案连接目标，并清空临时密钥；保存时必须提供新密钥或明确清空旧密钥。';
    return;
  }
  Object.assign(form, { provider: preset.id, protocol: preset.protocol || 'openai-chat', base_url: preset.url });
  apiKey.value = '';
  clearKey.value = false;
  validationError.value = '';
  presetConfirmation.value = '';
}
watch(() => [form.provider, form.protocol, form.base_url], () => { apiKey.value = ''; });
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
const efforts = AI_REASONING_EFFORTS;
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
  Object.assign(form, clean);
  originalTarget.value = target();
  presetId.value = 'custom';
  presetConfirmation.value = '';
  form.favorite_models = [...(clean.favorite_models || [])];
  form.fallback_models = [...(clean.fallback_models || [])];
  form.model_capabilities = (clean.model_capabilities || []).map(c => ({ model: c.model, reasoning_efforts: [...c.reasoning_efforts], output_token_parameter: c.output_token_parameter, context_window: c.context_window, max_output_tokens: c.max_output_tokens, image_input: c.image_input ?? false, image_tokens: c.image_tokens ?? 32768, max_images: c.max_images ?? 4, image_mime_types: [...(c.image_mime_types ?? ['image/png', 'image/jpeg', 'image/webp'])] }));
  hasKey.value = profile?.has_api_key ?? false;
  apiKey.value = '';
  clearKey.value = false;
  emit('select');
}
watch(() => props.savedProfile, (profile) => { if (profile) select(profile); });
watch(() => props.modelValue, (visible) => { if (!visible) { apiKey.value = ''; clearKey.value = false; } else select(props.profiles.find((p) => p.id === selectedId.value)); });
function submit() {
  if (targetChanged.value && hasKey.value && !apiKey.value && !clearKey.value) { validationError.value = '连接目标已变更，请提供新密钥或明确清空旧密钥。'; return; }
  if (form.protocol === 'anthropic-messages' && (!form.max_output_tokens || form.reasoning_effort)) { validationError.value = 'Messages 必须设置输出 Token 预算且推理档位为默认。'; return; }
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
  emit('test', { protocol: form.protocol, name: form.provider, max_output_tokens: form.max_output_tokens, base_url: form.base_url, model: form.model, connect_timeout: form.connect_timeout, request_timeout: form.request_timeout, max_retries: form.max_retries, api_key: clearKey.value ? '' : apiKey.value });
  apiKey.value = '';
}
</script>

<style scoped>
:global(.provider-drawer) { --profile-space: calc(var(--app-gap, 16px) * .75); }
:global(.provider-drawer .el-drawer__body) { display: flex; min-height: 0; overflow: hidden; padding: 0 var(--app-gap); }
:global(.provider-drawer .el-drawer__header) { margin-bottom: 0; padding: var(--profile-space) var(--app-gap); border-bottom: 1px solid var(--el-border-color-lighter); }
.provider-form { display: flex; flex-direction: column; width: 100%; max-width: 1000px; height: 100%; min-height: 0; min-width: 0; margin: 0 auto; }
.profile-toolbar { flex: none; display: grid; grid-template-columns: 1fr 1fr; gap: 0 var(--profile-space); padding: var(--profile-space) 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.profile-toolbar :deep(.el-form-item) { margin-bottom: 10px; }
.profile-toolbar .profile-actions { grid-column: 1 / -1; justify-content: flex-end; }
.profile-content { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; padding: var(--profile-space) calc(var(--profile-space) / 2) var(--profile-space) 0; }
.profile-section { margin-bottom: var(--profile-space); padding: var(--profile-space); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }
.profile-section h3 { margin: 0 0 var(--profile-space); font-size: 15px; font-weight: 600; }
.profile-fields, .capability-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: var(--profile-space); }
.profile-fields > h3, .profile-wide, .capability-row > .profile-actions { grid-column: 1 / -1; }
.profile-section :deep(.el-form-item) { min-width: 0; margin-bottom: var(--profile-space); }
.provider-form :deep(.el-form-item__content) { min-width: 0; }
.provider-form :deep(.el-select), .provider-form :deep(.el-input-number) { width: 100%; min-width: 0; }
.provider-form :deep(.el-checkbox-group) { display: flex; flex-wrap: wrap; gap: 4px 16px; }
.provider-form :deep(.el-checkbox) { margin-right: 0; }
.control-stack { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; width: 100%; min-width: 0; }
.model-control { display: flex; gap: 8px; width: 100%; min-width: 0; }
.model-control .el-select { flex: 1; }
.token-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--profile-space); }
.profile-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.profile-actions :deep(.el-button + .el-button), .fallback-row :deep(.el-button + .el-button) { margin-left: 0; }
.fallback-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; }
.fallback-row > span { flex: 1; min-width: 100px; overflow-wrap: anywhere; }
.profile-advanced > summary { cursor: pointer; }
.profile-advanced > summary { font-weight: 600; }
.profile-advanced[open] > summary { margin-bottom: var(--profile-space); }
.profile-section p, .profile-footer p { margin: 4px 0; overflow-wrap: anywhere; }
.capability-row { padding: var(--profile-space); margin: var(--profile-space) 0; border: 1px solid var(--el-border-color-lighter); border-radius: 6px; }
.profile-footer { flex: none; display: flex; align-items: center; justify-content: space-between; gap: var(--profile-space); padding: var(--profile-space) 0; border-top: 1px solid var(--el-border-color-lighter); background: var(--el-bg-color); }
.profile-feedback { flex: 1; min-width: 0; font-size: 12px; }
.provider-form [role="alert"] { color: var(--el-color-danger); }
@media (max-width: 640px) {
  :global(.provider-drawer .el-drawer__body) { padding: 0 12px; }
  .profile-toolbar { grid-template-columns: 1fr; padding: 10px 0; }
  .profile-toolbar .profile-actions { justify-content: flex-start; }
  .profile-fields, .capability-row, .token-grid { grid-template-columns: 1fr; gap: 0; }
  .provider-form :deep(.el-form-item) { flex-direction: column; }
  .provider-form :deep(.el-form-item__label) { width: auto !important; height: auto; justify-content: flex-start; margin-bottom: 6px; line-height: 1.5; }
  .provider-form :deep(.el-form-item__content) { margin-left: 0 !important; }
  .profile-footer { flex-wrap: wrap; padding: 10px 0; }
  .profile-feedback:empty { display: none; }
  .profile-footer .profile-actions { margin-left: auto; }
}
/* 矮窗口取消固定栏夹层，让全部字段和保存操作可通过正文滚动访问。 */
@media (max-height: 600px) {
  :global(.provider-drawer .el-drawer__body) { display: block; overflow-y: auto; }
  .provider-form { height: auto; }
  .profile-content { flex: none; overflow-y: visible; }
}
</style>
