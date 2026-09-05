<template>
  <el-drawer v-model="visible" :title="`${name} 配置`" size="520px">
    <el-form v-loading="loading" label-position="top">
      <el-form-item v-for="(definition, key) in schema" :key="key" :label="definition.title || key">
        <el-switch v-if="definition.type === 'switch'" :model-value="booleanValue(key)" @update:model-value="values[key] = $event" />
        <el-input v-else-if="definition.type === 'textarea'" :model-value="inputValue(key)" type="textarea" :rows="4" @update:model-value="values[key] = $event" />
        <el-input v-else :model-value="inputValue(key)" :type="definition.type === 'password' ? 'password' : 'text'" :show-password="definition.type === 'password'" @update:model-value="values[key] = $event" />
        <div v-if="definition.tip" class="text-xs text-gray-400">{{ definition.tip }}</div>
      </el-form-item>
      <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
    </el-form>
  </el-drawer>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { pluginApi, type PluginConfigDefinition } from '@/api/plugin';
const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ name: string }>();
const emit = defineEmits<{ saved: [] }>();
type ConfigValue = string | number | boolean | null;

const schema = ref<Record<string, PluginConfigDefinition>>({});
const values = reactive<Record<string, ConfigValue>>({});
const loading = ref(false);
const saving = ref(false);
async function load() { loading.value = true; try { schema.value = await pluginApi.config(props.name); Object.keys(values).forEach((key) => delete values[key]); Object.entries(schema.value).forEach(([key, definition]) => { values[key] = normalizeValue(definition); }); } finally { loading.value = false; } }
function normalizeValue(definition: PluginConfigDefinition): ConfigValue { if (definition.type === 'switch') return Boolean(definition.value); return typeof definition.value === 'string' || typeof definition.value === 'number' || definition.value === null ? definition.value : String(definition.value ?? ''); }
function booleanValue(key: string): boolean { return Boolean(values[key]); }
function inputValue(key: string): string | number | null { const value = values[key]; return typeof value === 'boolean' ? String(value) : value ?? null; }
async function save() { saving.value = true; try { await pluginApi.saveConfig(props.name, { ...values }); visible.value = false; emit('saved'); } finally { saving.value = false; } }
watch(visible, (open) => { if (open && props.name) load(); });
</script>
