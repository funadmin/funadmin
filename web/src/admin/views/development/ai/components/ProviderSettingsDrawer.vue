<template>
  <el-drawer class="provider-drawer" :model-value="modelValue" :title="t('aiDevelopment.profiles.title')" size="min(1120px, 100vw)" @update:model-value="$emit('update:modelValue', $event)">
    <div class="provider-layout">
      <aside class="profile-sidebar">
        <el-button type="primary" class="profile-sidebar__create" data-testid="profile-create" :disabled="busy" @click="select()"><i class="i-ep-plus" />{{ t('aiDevelopment.profiles.create') }}</el-button>
        <div class="profile-list">
          <button v-if="selectedId === null" type="button" class="profile-item active is-draft" aria-current="true">
            <span class="profile-item__top"><strong>{{ form.name || t('aiDevelopment.profiles.newProfile', '新建档案') }}</strong><el-tag size="small" type="warning" effect="light" round>{{ t('aiDevelopment.profiles.unsaved') }}</el-tag></span>
            <span class="profile-item__model">{{ form.model || '—' }}</span>
          </button>
          <button v-for="item in sortedProfiles" :key="item.id" type="button" class="profile-item" :class="{ active: item.id === selectedId, 'is-off': !item.enabled }" :aria-current="item.id === selectedId ? 'true' : undefined" :disabled="busy" @click="select(item)">
            <span class="profile-item__top"><strong>{{ item.name }}</strong><el-tag v-if="item.is_default" size="small" effect="light" round>{{ t('aiDevelopment.profiles.defaultTag') }}</el-tag></span>
            <span class="profile-item__model">{{ item.model }}</span>
            <span class="profile-item__meta"><span class="profile-dot" :class="{ on: item.enabled }" />{{ t(item.enabled ? 'aiDevelopment.profiles.enabled' : 'aiDevelopment.profiles.disabled') }}<span class="profile-item__key" :class="{ on: item.has_api_key }"><i class="i-ep-lock" />{{ t(item.has_api_key ? 'aiDevelopment.profiles.keySaved' : 'aiDevelopment.profiles.keyMissing') }}</span></span>
          </button>
          <p v-if="!profiles.length && selectedId !== null" class="profile-list__empty">{{ t('aiDevelopment.profiles.listEmpty') }}</p>
        </div>
      </aside>
    <el-form class="provider-form" :model="form" :disabled="busy" label-position="top" @submit.prevent="submit">
      <div class="profile-switcher">
        <el-select data-testid="profile-select" :model-value="selectedId" :placeholder="t('aiDevelopment.profiles.newProfile', '新建档案')" :aria-label="t('aiDevelopment.profiles.selectProfile', '配置档案')" @update:model-value="select(profiles.find(p => p.id === $event))">
          <el-option v-for="item in profiles" :key="item.id" :value="item.id" :label="`${item.name}${item.is_default ? t('aiDevelopment.profiles.defaultBadge', '（默认档案）') : ''} · ${item.model}`" />
        </el-select>
        <el-button :aria-label="t('aiDevelopment.profiles.create')" :icon="Plus" @click="select()" />
      </div>
      <div class="profile-header">
        <div class="profile-header__title">
          <strong>{{ form.name || t('aiDevelopment.profiles.newProfile', '新建档案') }}</strong>
          <el-tag v-if="!selectedId" size="small" type="warning" effect="light" round>{{ t('aiDevelopment.profiles.unsaved') }}</el-tag>
          <el-tag v-else-if="currentProfile?.is_default" size="small" effect="light" round>{{ t('aiDevelopment.profiles.defaultTag') }}</el-tag>
          <el-tag v-if="!form.enabled" size="small" type="info" effect="light" round>{{ t('aiDevelopment.profiles.disabled') }}</el-tag>
          <small>{{ form.provider || '—' }} · {{ form.model || '—' }}</small>
        </div>
        <div class="profile-actions">
          <el-tooltip :content="t('aiDevelopment.profiles.copyHint')"><el-button :disabled="!selectedId" @click="$emit('copy', selectedId!)"><i class="i-ep-copy-document" />{{ t('aiDevelopment.profiles.copy') }}</el-button></el-tooltip>
          <el-button :disabled="!selectedId || currentProfile?.is_default" @click="$emit('default', selectedId!)"><i class="i-ep-star" />{{ t('aiDevelopment.profiles.default') }}</el-button>
          <el-button :disabled="!selectedId" type="danger" plain @click="$emit('remove', selectedId!)"><i class="i-ep-delete" />{{ t('aiDevelopment.management.delete') }}</el-button>
        </div>
      </div>
      <div class="profile-content" data-testid="profile-content">
        <section class="profile-section profile-basic">
          <h3>{{ t('aiDevelopment.profiles.basicTitle') }}</h3>
          <el-form-item :label="t('aiDevelopment.profiles.nameLabel', '档案名称')"><el-input v-model="form.name" required maxlength="100" /></el-form-item>
          <el-form-item :label="t('aiDevelopment.profiles.enabled')"><el-switch v-model="form.enabled" :aria-label="t('aiDevelopment.profiles.enabled')" /></el-form-item>
        </section>
        <section class="profile-section profile-fields">
          <h3>{{ t('aiDevelopment.profiles.connectionTitle', '连接与模型') }}</h3>
          <el-form-item class="profile-wide" :label="t('aiDevelopment.profiles.preset', '供应商预设')"><div class="model-control"><el-select v-model="presetId" data-testid="provider-preset"><el-option v-for="preset in presets" :key="preset.id" :value="preset.id" :label="preset.label" /></el-select><el-button data-testid="apply-preset" @click="applyPreset">{{ t('aiDevelopment.profiles.applyPreset', '应用到连接') }}</el-button></div></el-form-item>
          <el-form-item :label="t('aiDevelopment.profiles.protocol', 'API 协议')"><el-select v-model="form.protocol" data-testid="provider-protocol"><el-option value="openai-chat" label="Chat Completions" /><el-option value="openai-responses" label="Responses" /><el-option value="anthropic-messages" label="Anthropic Messages" /></el-select></el-form-item>
          <el-form-item :label="t('aiDevelopment.profiles.providerId', '供应商标识')">
            <el-input v-model="form.provider" required pattern="[a-z0-9][a-z0-9._-]*" maxlength="64" :placeholder="t('aiDevelopment.profiles.providerIdPlaceholder', '例如 openai-compatible')" />
          </el-form-item>
          <p v-if="presetId === 'ollama'" class="profile-wide">{{ t('aiDevelopment.profiles.ollamaHint', 'Ollama 官方本地地址为 http://localhost:11434/v1；本系统禁止本机、私网及 HTTP，请填写经授权的公网 HTTPS 网关地址。') }}</p>
          <el-form-item class="profile-wide" label="Base URL"><el-input v-model="form.base_url" type="url" required placeholder="https://api.example.com/v1" /></el-form-item>
          <el-form-item class="profile-wide" label="API key">
            <div class="key-control">
              <el-input v-model="apiKey" data-testid="provider-api-key" type="password" autocomplete="new-password" :placeholder="t('aiDevelopment.profiles.keyHint')" />
              <span class="key-status">{{ t(hasKey ? 'aiDevelopment.profiles.hasKey' : 'aiDevelopment.profiles.noKey') }}</span>
              <el-switch v-model="clearKey" data-testid="provider-clear-key" :active-text="t('aiDevelopment.profiles.clearKey')" :aria-label="t('aiDevelopment.profiles.clearKey')" />
            </div>
          </el-form-item>
          <el-form-item class="profile-wide" :label="t('aiDevelopment.profiles.defaultModel', '默认 Model')">
            <div class="model-control">
              <el-select v-model="form.model" data-testid="profile-model" filterable allow-create default-first-option :aria-label="t('aiDevelopment.fields.model')"><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select>
              <el-tooltip :content="t('aiDevelopment.profiles.modelsHint')"><el-button :disabled="!selectedId || targetChanged" @click="$emit('models', selectedId!)">{{ t('aiDevelopment.profiles.fetchModelsShort', '获取模型') }}</el-button></el-tooltip>
            </div>
          </el-form-item>
          <el-form-item class="profile-wide" :label="t('aiDevelopment.profiles.favorites')"><el-select v-model="form.favorite_models" multiple filterable allow-create default-first-option><el-option v-for="id in modelOptions" :key="id" :value="id" :label="id" /></el-select></el-form-item>
        </section>
        <section class="profile-section">
          <h3>{{ t('aiDevelopment.profiles.fallbackTitle', '备用模型') }}</h3>
          <div class="fallback-grid">
            <el-form-item :label="t('aiDevelopment.profiles.fallbackEnable', '启用备用')"><el-switch v-model="form.fallback_enabled" data-testid="fallback-enabled" :aria-label="t('aiDevelopment.profiles.fallbackEnable', '启用备用')" /></el-form-item>
            <el-form-item :label="t('aiDevelopment.profiles.fallbackList', '有序备用模型')">
              <el-select :model-value="null" filterable allow-create default-first-option :placeholder="t('aiDevelopment.profiles.fallbackAdd', '添加备用模型（最多 3 个）')" :disabled="(form.fallback_models?.length || 0) >= 3" @update:model-value="addFallback"><el-option v-for="id in fallbackOptions" :key="id" :value="id" :label="id" /></el-select>
            </el-form-item>
          </div>
          <div v-if="form.fallback_models?.length" class="fallback-list">
            <div v-for="(model, index) in form.fallback_models" :key="model" class="fallback-row">
              <span>{{ index + 1 }}. {{ model }}</span>
              <el-button :data-testid="`fallback-up-${index}`" :disabled="index === 0" @click="moveFallback(index, -1)">{{ t('aiDevelopment.profiles.moveUp', '上移') }}</el-button>
              <el-button :disabled="index === (form.fallback_models?.length || 0) - 1" @click="moveFallback(index, 1)">{{ t('aiDevelopment.profiles.moveDown', '下移') }}</el-button>
              <el-button @click="form.fallback_models?.splice(index, 1)">{{ t('aiDevelopment.profiles.removeFallback', '移除') }}</el-button>
            </div>
          </div>
        </section>
        <section class="profile-section">
          <h3>{{ t('aiDevelopment.profiles.tokenLimits', 'Token 限制') }}</h3>
          <div class="token-grid">
            <el-form-item v-for="field in numericFields.slice(0, 3)" :key="field.key" :label="t(`aiDevelopment.profiles.${field.key}`)">
              <el-input-number v-model="form[field.key]" :min="field.min" :max="field.max" :step="1" :precision="0" :value-on-clear="null" controls-position="right" :placeholder="t('aiDevelopment.profiles.unspecified', '不指定')" />
            </el-form-item>
          </div>
          <el-form-item class="profile-reasoning" :label="t('aiDevelopment.reasoning.profile')"><el-select data-testid="reasoning-effort" :model-value="form.reasoning_effort || ''" @update:model-value="form.reasoning_effort = ($event || null) as AiReasoningEffort | null"><el-option value="" :label="t('aiDevelopment.reasoning.defaultOption')" /><el-option v-for="effort in legalEfforts" :key="effort" :value="effort" :label="effort" /></el-select></el-form-item>
          <p v-if="form.reasoning_effort && !legalEfforts.includes(form.reasoning_effort)" role="alert">{{ t('aiDevelopment.profiles.savedEffortIncompatible', { effort: form.reasoning_effort }, { default: '已保存档位 {effort} 不兼容当前选择，请明确选择默认或合法档位。' }) }}</p>
        </section>
        <details class="profile-section profile-advanced" data-testid="profile-advanced">
          <summary>{{ t('aiDevelopment.profiles.advancedTitle', '高级参数与模型能力') }}</summary>
          <div class="profile-fields advanced-fields">
            <el-form-item v-for="field in numericFields.slice(3)" :key="field.key" :label="t(`aiDevelopment.profiles.${field.key}`)"><el-input-number v-model="form[field.key]" :min="field.min" :max="field.max" :step="1" :precision="0" controls-position="right" /></el-form-item>
            <el-form-item :label="t('aiDevelopment.profiles.stream_usage')"><el-switch v-model="form.stream_usage" :aria-label="t('aiDevelopment.profiles.stream_usage')" /></el-form-item>
          </div>
          <h3>{{ t('aiDevelopment.profiles.capabilitiesTitle', '模型能力（管理员声明）') }}</h3>
          <div v-for="(cap, index) in form.model_capabilities" :key="index" class="capability-row">
            <el-form-item :label="t('aiDevelopment.profiles.capModel', '模型标识')"><el-input v-model="cap.model" required maxlength="200" /></el-form-item>
            <el-form-item class="profile-wide" :label="t('aiDevelopment.reasoning.levels')"><el-checkbox-group v-model="cap.reasoning_efforts"><el-checkbox v-for="effort in efforts" :key="effort" :value="effort">{{ effort }}</el-checkbox></el-checkbox-group></el-form-item>
            <el-form-item :label="t('aiDevelopment.profiles.capOutput', '输出参数')"><el-select v-model="cap.output_token_parameter" :data-testid="`cap-output-${index}`"><el-option value="max_tokens" label="max_tokens" /><el-option value="max_completion_tokens" label="max_completion_tokens" /></el-select></el-form-item>
            <el-form-item :label="t('aiDevelopment.profiles.capContext', '模型上下文上限')"><el-input-number v-model="cap.context_window" :min="1" :max="10000000" :precision="0" :value-on-clear="null" :placeholder="t('aiDevelopment.profiles.unknown', '未知')" controls-position="right" /></el-form-item>
            <el-form-item :label="t('aiDevelopment.profiles.capMaxOutput', '模型输出上限')"><el-input-number v-model="cap.max_output_tokens" :min="1" :max="10000000" :precision="0" :value-on-clear="null" :placeholder="t('aiDevelopment.profiles.unknown', '未知')" controls-position="right" /></el-form-item>
            <el-form-item :label="t('aiComposer.imageInput')"><el-switch v-model="cap.image_input" :data-testid="`cap-image-${index}`" :aria-label="t('aiComposer.imageInput')" /></el-form-item>
            <el-form-item :label="t('aiComposer.maxImages')"><el-input-number v-model="cap.max_images" :min="1" :max="4" :precision="0" /></el-form-item>
            <el-form-item class="profile-wide" :label="t('aiDevelopment.profiles.imageFormats', '图片格式')"><el-checkbox-group v-model="cap.image_mime_types"><el-checkbox v-for="mime in ['image/png', 'image/jpeg', 'image/webp']" :key="mime" :value="mime">{{ mime }}</el-checkbox></el-checkbox-group></el-form-item>
            <div class="profile-actions"><small>{{ t('aiComposer.imageTokens') }}: 32768</small><el-button type="danger" plain @click="form.model_capabilities?.splice(index, 1)">{{ t('aiDevelopment.profiles.removeCapability', '删除声明') }}</el-button></div>
          </div>
          <el-button :disabled="(form.model_capabilities?.length || 0) >= 100" @click="form.model_capabilities?.push({ model: '', reasoning_efforts: [], output_token_parameter: 'max_tokens', context_window: null, max_output_tokens: null, image_input: false, image_tokens: 32768, max_images: 4, image_mime_types: ['image/png', 'image/jpeg', 'image/webp'] })">{{ t('aiDevelopment.profiles.addCapability', '添加模型能力声明') }}</el-button>
        </details>
      </div>
      <footer class="profile-footer" data-testid="profile-footer">
        <div class="profile-feedback"><p v-if="error || validationError" role="alert">{{ error || validationError }}</p><p v-if="notice" role="status">{{ notice }}</p></div>
        <div class="profile-actions">
          <el-tooltip :content="t('aiDevelopment.profiles.testHint')"><el-button data-testid="profile-test" :disabled="busy" @click="test"><i class="i-ep-connection" />{{ t('aiDevelopment.providerSettings.testConnection') }}</el-button></el-tooltip>
          <el-button data-testid="profile-save" type="primary" native-type="submit" :loading="busy">{{ t('aiDevelopment.profiles.save') }}</el-button>
        </div>
      </footer>
    </el-form>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from '@element-plus/icons-vue';
import { AI_REASONING_EFFORTS, profileCapabilityError, profileModelCapability, type AiCatalogModel, type AiReasoningEffort, type AiProfile, type AiProfileInput, type AiProviderSettings } from '@/api/development/ai';
const props = withDefaults(defineProps<{ modelValue: boolean; settings?: AiProviderSettings; profiles?: AiProfile[]; busy?: boolean; models?: AiCatalogModel[]; error?: string; notice?: string; savedProfile?: AiProfile | null }>(), { profiles: () => [], models: () => [] });
const { t } = useI18n();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; save: [payload: AiProfileInput, id: number | null]; test: [payload: Record<string, unknown>]; copy: [id: number]; remove: [id: number]; default: [id: number]; models: [id: number]; select: [] }>();
const selectedId = ref<number | null>(null);
const currentProfile = computed(() => props.profiles.find((item) => item.id === selectedId.value));
const sortedProfiles = computed(() => [...props.profiles].sort((left, right) => Number(right.is_default) - Number(left.is_default) || Number(right.enabled) - Number(left.enabled) || left.name.localeCompare(right.name)));
const apiKey = ref('');
const clearKey = ref(false);
const hasKey = ref(false);
const validationError = ref('');
const defaults = (): AiProfileInput => ({ name: '', provider: 'openai-compatible', protocol: 'openai-chat', base_url: '', model: '', enabled: true, favorite_models: [], model_capabilities: [], fallback_enabled: false, fallback_models: [], reasoning_effort: null, context_window: null, max_input_tokens: null, max_output_tokens: null, max_iterations: 10, stream_usage: false, connect_timeout: 5, request_timeout: 60, max_retries: 2 });
const form = reactive(defaults());
const presetId = ref('custom');
const presets = computed<Array<{ id: string; label: string; url: string; protocol?: AiProfileInput['protocol'] }>>(() => [
  { id: 'openai', label: 'OpenAI', url: 'https://api.openai.com/v1' },
  { id: 'anthropic', label: 'Anthropic (Claude)', url: 'https://api.anthropic.com/v1', protocol: 'anthropic-messages' },
  { id: 'deepseek', label: 'DeepSeek', url: 'https://api.deepseek.com/v1' },
  { id: 'kimi', label: 'Kimi', url: 'https://api.moonshot.cn/v1' },
  { id: 'zhipu', label: t('aiDevelopment.profiles.presetZhipu', '智谱 GLM'), url: 'https://open.bigmodel.cn/api/paas/v4' },
  { id: 'qwen', label: t('aiDevelopment.profiles.presetQwen', '通义千问（北京）'), url: 'https://dashscope.aliyuncs.com/compatible-mode/v1' },
  { id: 'doubao', label: t('aiDevelopment.profiles.presetDoubao', '豆包（北京）'), url: 'https://ark.cn-beijing.volces.com/api/v3' },
  { id: 'siliconflow', label: t('aiDevelopment.profiles.presetSiliconFlow', '硅基流动'), url: 'https://api.siliconflow.cn/v1' },
  { id: 'openrouter', label: 'OpenRouter', url: 'https://openrouter.ai/api/v1' },
  { id: 'groq', label: 'Groq', url: 'https://api.groq.com/openai/v1' },
  { id: 'google-gemini', label: t('aiDevelopment.profiles.presetGemini', 'Google Gemini（OpenAI 兼容）'), url: 'https://generativelanguage.googleapis.com/v1beta/openai' },
  { id: 'ollama', label: t('aiDevelopment.profiles.presetOllama', 'Ollama（自备安全网关）'), url: '' },
  { id: 'custom', label: t('aiDevelopment.profiles.presetCustom', '自定义'), url: '' }
]);
const originalTarget = ref('');
const target = () => JSON.stringify([form.provider, form.protocol, form.base_url]);
const targetChanged = computed(() => selectedId.value !== null && originalTarget.value !== target());
const presetConfirmation = ref('');
function applyPreset() {
  const preset = presets.value.find(p => p.id === presetId.value);
  if (!preset || preset.id === 'custom') return;
  if (selectedId.value && presetConfirmation.value !== preset.id) {
    presetConfirmation.value = preset.id;
    validationError.value = t('aiDevelopment.profiles.presetConfirm', '再次点击应用将替换当前档案连接目标，并清空临时密钥；保存时必须提供新密钥或明确清空旧密钥。');
    return;
  }
  Object.assign(form, { provider: preset.id, protocol: preset.protocol || 'openai-chat', base_url: preset.url });
  apiKey.value = '';
  clearKey.value = false;
  validationError.value = '';
  presetConfirmation.value = '';
}
watch(() => [form.provider, form.protocol, form.base_url], () => { apiKey.value = ''; presetConfirmation.value = ''; });
watch(presetId, () => { presetConfirmation.value = ''; });
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
const legalEfforts = computed(() => form.protocol !== 'openai-chat' ? [] : efforts.filter(e => [form.model, ...(form.fallback_enabled ? form.fallback_models || [] : [])].every(m => profileModelCapability(form, m).reasoning_efforts.includes(e))));
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
watch(() => props.modelValue, (visible) => {
  if (!visible) { apiKey.value = ''; clearKey.value = false; return; }
  select(props.profiles.find((p) => p.id === selectedId.value) ?? props.profiles.find((p) => p.is_default) ?? sortedProfiles.value[0]);
});
function submit() {
  if (targetChanged.value && hasKey.value && !apiKey.value && !clearKey.value) { validationError.value = t('aiDevelopment.profiles.targetChangedNeedKey', '连接目标已变更，请提供新密钥或明确清空旧密钥。'); return; }
  if (form.protocol !== 'openai-chat' && form.reasoning_effort) { validationError.value = t('aiDevelopment.profiles.reasoningProtocolUnsupported', 'Responses / Messages 暂不支持显式推理模式，请选择默认并使用非思考模型。'); return; }
  if (form.protocol === 'anthropic-messages' && !form.max_output_tokens) { validationError.value = t('aiDevelopment.profiles.messagesOutputRequired', 'Messages 必须设置输出 Token 预算。'); return; }
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
  if (form.protocol !== 'openai-chat' && form.reasoning_effort) { validationError.value = t('aiDevelopment.profiles.protocolReasoningUnsupported', '当前协议不支持显式推理模式。'); return; }
  emit('test', { protocol: form.protocol, name: form.provider, max_output_tokens: form.max_output_tokens, base_url: form.base_url, model: form.model, connect_timeout: form.connect_timeout, request_timeout: form.request_timeout, max_retries: form.max_retries, api_key: clearKey.value ? '' : apiKey.value });
  apiKey.value = '';
}
</script>

<style scoped>
:global(.provider-drawer) { --profile-space: calc(var(--app-gap, 16px) * .75); }
:global(.provider-drawer .el-drawer__header) { margin-bottom: 0; padding: 14px 20px; border-bottom: 1px solid var(--el-border-color-lighter); color: var(--el-text-color-primary); font-weight: 600; }
:global(.provider-drawer .el-drawer__body) { display: flex; min-height: 0; overflow: hidden; padding: 0; }
.provider-layout { display: grid; flex: 1; grid-template-columns: 256px minmax(0, 1fr); min-width: 0; min-height: 0; }

.profile-sidebar { display: flex; flex-direction: column; gap: 12px; min-height: 0; padding: 16px 12px; overflow-y: auto; border-right: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-lighter); }
.profile-sidebar__create { width: 100%; }
.profile-sidebar__create i, .profile-actions :deep(.el-button i) { margin-right: 4px; }
.profile-list { display: grid; align-content: start; gap: 6px; }
.profile-item { position: relative; display: grid; gap: 4px; width: 100%; padding: 10px 12px; border: 1px solid transparent; border-radius: 10px; background: transparent; color: var(--el-text-color-regular); font: inherit; text-align: left; cursor: pointer; transition: background-color .15s ease, border-color .15s ease; }
.profile-item:hover:not(:disabled) { background: var(--el-bg-color); border-color: var(--el-border-color-lighter); }
.profile-item.active { border-color: color-mix(in srgb, var(--el-color-primary) 45%, transparent); background: color-mix(in srgb, var(--el-color-primary) 9%, var(--el-bg-color)); }
.profile-item.active::before { position: absolute; top: 12px; bottom: 12px; left: -1px; width: 3px; border-radius: 0 3px 3px 0; background: var(--el-color-primary); content: ''; }
.profile-item.is-draft { border-style: dashed; cursor: default; }
.profile-item:disabled { cursor: not-allowed; opacity: .7; }
.profile-item:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 1px; }
.profile-item__top { display: flex; align-items: center; justify-content: space-between; gap: 6px; min-width: 0; }
.profile-item__top strong { min-width: 0; overflow: hidden; color: var(--el-text-color-primary); font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.profile-item.active .profile-item__top strong { color: var(--el-color-primary); }
.profile-item__model { overflow: hidden; color: var(--el-text-color-secondary); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.profile-item__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; color: var(--el-text-color-secondary); font-size: 11px; }
.profile-item.is-off .profile-item__top strong, .profile-item.is-off .profile-item__model { color: var(--el-text-color-placeholder); }
.profile-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--el-text-color-placeholder); }
.profile-dot.on { background: var(--el-color-success); }
.profile-item__key { display: inline-flex; align-items: center; gap: 3px; margin-left: 4px; color: var(--el-color-warning); }
.profile-item__key.on { color: var(--el-text-color-secondary); }
.profile-list__empty { margin: 0; padding: 16px 8px; color: var(--el-text-color-secondary); font-size: 12px; text-align: center; }

.provider-form { display: flex; flex-direction: column; width: 100%; max-width: 1000px; height: 100%; min-height: 0; min-width: 0; }
.profile-switcher { display: none; }
.profile-header { flex: none; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding: 14px 24px; border-bottom: 1px solid var(--el-border-color-lighter); }
.profile-header__title { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; min-width: 0; }
.profile-header__title strong { min-width: 0; overflow: hidden; color: var(--el-text-color-primary); font-size: 16px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.profile-header__title small { flex-basis: 100%; color: var(--el-text-color-secondary); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; }
.profile-content { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; padding: 20px 24px 8px; }
.profile-section { margin-bottom: var(--profile-space); padding: var(--profile-space); border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.profile-section h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 var(--profile-space); color: var(--el-text-color-primary); font-size: 14px; font-weight: 600; }
.profile-section h3::before { width: 3px; height: 14px; border-radius: 2px; background: var(--el-color-primary); content: ''; }
.profile-basic, .profile-fields, .capability-row, .fallback-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: calc(var(--profile-space) * 1.5); }
.profile-basic > h3, .profile-fields > h3, .profile-wide, .capability-row > .profile-actions, .profile-reasoning { grid-column: 1 / -1; }
.profile-section :deep(.el-form-item) { min-width: 0; margin-bottom: var(--profile-space); }
.provider-form :deep(.el-form-item__label) { margin-bottom: 4px; color: var(--el-text-color-regular); font-size: 13px; line-height: 1.5; }
.provider-form :deep(.el-form-item__content) { min-width: 0; }
.provider-form :deep(.el-select), .provider-form :deep(.el-input-number) { width: 100%; min-width: 0; }
.provider-form :deep(.el-checkbox-group) { display: flex; flex-wrap: wrap; gap: 4px 16px; }
.provider-form :deep(.el-checkbox) { margin-right: 0; }
.key-control { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px; width: 100%; min-width: 0; }
.key-control .el-input { flex: 1; min-width: 220px; }
.key-status { flex: none; padding: 0 8px; border-radius: 999px; background: var(--el-fill-color); color: var(--el-text-color-secondary); font-size: 12px; line-height: 22px; white-space: nowrap; }
.model-control { display: flex; gap: 8px; width: 100%; min-width: 0; }
.model-control .el-select { flex: 1; }
.fallback-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: var(--profile-space); }
.fallback-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; box-sizing: border-box; padding: 6px 8px 6px 12px; border-radius: 8px; background: var(--el-fill-color-light); }
.fallback-row > span { flex: 1; min-width: 100px; font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 13px; overflow-wrap: anywhere; }
.token-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: calc(var(--profile-space) * 1.5); }
.profile-fields.advanced-fields { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.profile-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.profile-actions :deep(.el-button + .el-button), .fallback-row :deep(.el-button + .el-button) { margin-left: 0; }
.profile-advanced > summary { display: flex; align-items: center; gap: 8px; color: var(--el-text-color-primary); font-size: 14px; font-weight: 600; list-style: none; cursor: pointer; }
.profile-advanced > summary::-webkit-details-marker { display: none; }
.profile-advanced > summary::before { width: 3px; height: 14px; border-radius: 2px; background: var(--el-color-primary); content: ''; }
.profile-advanced > summary::after { margin-left: auto; color: var(--el-text-color-secondary); font-size: 14px; transition: transform .2s ease; content: '▾'; }
.profile-advanced[open] > summary::after { transform: rotate(180deg); }
.profile-advanced[open] > summary { margin-bottom: var(--profile-space); }
.profile-advanced h3 { margin-top: var(--profile-space); }
.profile-section p, .profile-footer p { margin: 4px 0; overflow-wrap: anywhere; }
.capability-row { padding: var(--profile-space); margin: var(--profile-space) 0; border: 1px dashed var(--el-border-color); border-radius: 10px; background: var(--el-fill-color-lighter); }
.capability-row > .profile-actions { justify-content: space-between; }
.capability-row small { color: var(--el-text-color-secondary); }
.profile-footer { flex: none; display: flex; align-items: center; justify-content: space-between; gap: var(--profile-space); padding: 12px 24px; border-top: 1px solid var(--el-border-color-lighter); background: var(--el-bg-color); }
.profile-feedback { flex: 1; min-width: 0; font-size: 12px; }
.profile-feedback [role="status"] { color: var(--el-color-success); }
.provider-form [role="alert"] { color: var(--el-color-danger); }

@media (max-width: 900px) {
  .profile-fields.advanced-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
  .provider-layout { grid-template-columns: minmax(0, 1fr); }
  .profile-sidebar { display: none; }
  .profile-switcher { flex: none; display: flex; gap: 8px; padding: 12px 16px 0; }
  .profile-header { padding: 12px 16px; }
  .profile-content { padding: 16px 16px 8px; }
  .profile-footer { padding: 10px 16px; }
}
@media (max-width: 640px) {
  .profile-basic, .profile-fields, .capability-row, .token-grid, .fallback-grid { grid-template-columns: 1fr; gap: 0; }
  .profile-fields.advanced-fields { grid-template-columns: 1fr; }
  .profile-header .profile-actions { width: 100%; }
  .profile-footer { flex-wrap: wrap; }
  .profile-feedback:empty { display: none; }
  .profile-footer .profile-actions { margin-left: auto; }
}
/* 矮窗口取消固定栏夹层，让全部字段和保存操作可通过正文滚动访问。 */
@media (max-height: 600px) {
  :global(.provider-drawer .el-drawer__body) { display: block; overflow-y: auto; }
  .provider-layout { height: auto; }
  .profile-sidebar { overflow-y: visible; }
  .provider-form { height: auto; }
  .profile-content { flex: none; overflow-y: visible; }
}
</style>
