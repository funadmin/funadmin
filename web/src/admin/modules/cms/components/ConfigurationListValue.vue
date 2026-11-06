<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed } from 'vue';
import type { ConfigurationField, ConfigurationRow, ConfigurationScalar } from '../types';
import ConfigurationValue from './ConfigurationValue.vue';

const { t } = useCmsI18n();
const props = defineProps<{ field: ConfigurationField; modelValue: unknown; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: ConfigurationRow[]] }>();
const rows = computed<ConfigurationRow[]>(() => Array.isArray(props.modelValue) ? props.modelValue : []);
function update(index: number, key: string, value: ConfigurationScalar) {
  if (props.disabled) return;
  emit('update:modelValue', rows.value.map((row, i) => i === index ? { ...row, [key]: value } : row));
}
function add() {
  if (props.disabled || rows.value.length >= 100) return;
  const row = Object.fromEntries((props.field.fields || []).map(field => [field.key, field.default]));
  emit('update:modelValue', [...rows.value, { ...row, enabled: true } as ConfigurationRow]);
}
function move(index: number, direction: number) {
  const target = index + direction;
  if (props.disabled || target < 0 || target >= rows.value.length) return;
  const next = [...rows.value];
  [next[index], next[target]] = [next[target]!, next[index]!];
  emit('update:modelValue', next);
}
function remove(index: number) {
  if (!props.disabled) emit('update:modelValue', rows.value.filter((_, i) => i !== index));
}
</script>
<template>
  <div class="configuration-list">
    <div v-for="(row, index) in rows" :key="index" class="configuration-row">
      <header class="row-toolbar">
        <strong>{{ t('cms.labels.listItemNumber', { count: index + 1 }) }}</strong>
        <el-switch :model-value="row.enabled !== false" :disabled="disabled" :active-text="t('cms.labels.enabled')" @update:model-value="update(index, 'enabled', !!$event)" />
        <div class="row-actions">
          <el-button link :disabled="disabled || index === 0" @click="move(index, -1)">{{ t('cms.labels.moveUp') }}</el-button>
          <el-button link :disabled="disabled || index === rows.length - 1" @click="move(index, 1)">{{ t('cms.labels.moveDown') }}</el-button>
          <el-button link type="danger" :disabled="disabled" @click="remove(index)">{{ t('cms.labels.remove') }}</el-button>
        </div>
      </header>
      <div class="row-fields">
        <div v-for="child in field.fields" :key="child.key" class="row-field">
          <label>{{ child.label }}</label>
          <ConfigurationValue :field="child" :model-value="row[child.key] ?? child.default" :disabled="disabled" @update:model-value="update(index, child.key, $event)" />
        </div>
      </div>
    </div>
    <el-empty v-if="!rows.length" :description="t('cms.labels.noItemsYetAddAndSaveAnItemTo')" :image-size="55" />
    <div class="list-footer"><el-button :disabled="disabled || rows.length >= 100" @click="add">{{ t('cms.labels.addItem') }}</el-button><span>{{ t('cms.labels.listItemLimit', { count: rows.length }) }}</span></div>
  </div>
</template>
<style scoped>
.configuration-list{width:100%;min-width:0}.configuration-row{border:1px solid var(--el-border-color-lighter);border-radius:8px;margin-bottom:14px;padding:18px;background:var(--el-bg-color)}.row-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px}.row-toolbar strong{font-size:13px}.row-actions{margin-left:auto;display:flex;gap:8px}.row-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 24px}.row-field{min-width:0}.row-field>label{display:block;margin-bottom:8px;font-size:13px;color:var(--el-text-color-regular)}.list-footer{display:flex;align-items:center;flex-wrap:wrap;gap:12px}.list-footer span{font-size:12px;color:var(--el-text-color-secondary)}@media(max-width:640px){.row-fields{grid-template-columns:1fr}.configuration-row{padding:14px}.row-actions{width:100%;margin-left:0}}
</style>
