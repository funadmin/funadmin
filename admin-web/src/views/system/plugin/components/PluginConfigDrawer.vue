<template>
  <el-drawer v-model="visible" :title="`${code} 配置`" size="520px">
    <el-form v-loading="loading" label-position="top">
      <el-form-item v-for="(definition, key) in schema" :key="key" :label="definition.title || key">
        <el-switch v-if="definition.type === 'switch'" :model-value="booleanValue(key)" @update:model-value="values[key] = $event" />
        <el-input-number v-else-if="definition.type === 'number'" :model-value="numberValue(key)" class="w-full" @update:model-value="values[key] = $event ?? 0" />
        <el-checkbox-group v-else-if="definition.type === 'checkbox'" :model-value="arrayValue(key)" @update:model-value="values[key] = $event">
          <el-checkbox v-for="option in options(definition)" :key="option.value" :value="option.value">{{ option.label }}</el-checkbox>
        </el-checkbox-group>
        <el-select v-else-if="definition.type === 'select'" :model-value="scalarValue(key)" class="w-full" @update:model-value="values[key] = $event">
          <el-option v-for="option in options(definition)" :key="option.value" :label="option.label" :value="option.value" />
        </el-select>
        <el-select v-else-if="['selects', 'xmselect'].includes(definition.type || '')" :model-value="arrayValue(key)" class="w-full" multiple @update:model-value="values[key] = $event">
          <el-option v-for="option in options(definition)" :key="option.value" :label="option.label" :value="option.value" />
        </el-select>
        <el-input v-else-if="definition.type === 'textarea'" :model-value="inputValue(key)" type="textarea" :rows="4" @update:model-value="values[key] = $event" />
        <el-input v-else :model-value="inputValue(key)" :type="definition.type === 'password' ? 'password' : 'text'" :show-password="definition.type === 'password'" @update:model-value="values[key] = $event" />
        <div v-if="definition.tip" class="text-xs text-gray-400">{{ definition.tip }}</div>
      </el-form-item>
      <el-button type="primary" :loading="saving" v-perm="'system:plugin:config'" @click="save">保存配置</el-button>
    </el-form>
  </el-drawer>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { pluginApi, type PluginConfigDefinition } from '@/api/plugin';

const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ code: string }>();
const emit = defineEmits<{ saved: [] }>();
type ConfigScalar = string | number | boolean | null;
type ConfigValue = ConfigScalar | Array<string | number>;
type ConfigOption = { label: string; value: string };

const schema = ref<Record<string, PluginConfigDefinition>>({});
const values = reactive<Record<string, ConfigValue>>({});
const loading = ref(false);
const saving = ref(false);

async function load() {
  loading.value = true;
  try {
    schema.value = await pluginApi.config(props.code);
    Object.keys(values).forEach((key) => delete values[key]);
    Object.entries(schema.value).forEach(([key, definition]) => { values[key] = normalizeValue(definition); });
  } finally {
    loading.value = false;
  }
}

function normalizeValue(definition: PluginConfigDefinition): ConfigValue {
  if (definition.type === 'switch') return Boolean(definition.value);
  if (['checkbox', 'selects', 'xmselect'].includes(definition.type || '')) {
    return Array.isArray(definition.value) ? definition.value.map(String) : definition.value == null || definition.value === '' ? [] : [String(definition.value)];
  }
  if (definition.type === 'number') return Number(definition.value || 0);
  return typeof definition.value === 'string' || typeof definition.value === 'number' || definition.value === null ? definition.value : String(definition.value ?? '');
}

function options(definition: PluginConfigDefinition): ConfigOption[] {
  if (Array.isArray(definition.options)) return definition.options;
  return Object.entries(definition.options || {}).map(([value, label]) => ({ value, label }));
}

function booleanValue(key: string): boolean { return Boolean(values[key]); }
function numberValue(key: string): number { return Number(values[key] || 0); }
function arrayValue(key: string): Array<string | number> { const value = values[key]; return Array.isArray(value) ? value : []; }
function scalarValue(key: string): string | number | boolean | null { const value = values[key]; return Array.isArray(value) ? value[0] || '' : value ?? null; }
function inputValue(key: string): string | number | null { const value = values[key]; return Array.isArray(value) ? value.join(',') : typeof value === 'boolean' ? String(value) : value ?? null; }

async function save() {
  saving.value = true;
  try {
    await pluginApi.saveConfig(props.code, { ...values });
    visible.value = false;
    emit('saved');
  } finally {
    saving.value = false;
  }
}

watch(visible, (open) => { if (open && props.code) load(); });
</script>
