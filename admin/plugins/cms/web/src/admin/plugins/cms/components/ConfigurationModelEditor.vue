<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import type { ConfigurationField } from '../types';
import { configurationChild, configurationDefault, configurationPresets, scalarConfigurationTypes } from './configurationModel';
const { t } = useCmsI18n();
const props = defineProps<{ modelValue: ConfigurationField[] }>();
const emit = defineEmits<{ 'update:modelValue': [fields: ConfigurationField[]] }>();
function update(index: number, changes: Partial<ConfigurationField>) {
  emit('update:modelValue', props.modelValue.map((field, i) => i === index ? { ...field, ...changes } : field));
}
</script>
<template>
  <section class="configuration-model-editor">
    <div class="model-presets"><span>{{ t('cms.labels.useFieldPreset') }}</span><el-button v-for="preset in configurationPresets" :key="preset.label" size="small" @click="emit('update:modelValue', preset.fields())">{{ preset.label }}</el-button></div>
    <p class="model-hint">{{ t('cms.labels.defineTheFieldsForEachRecordEnablementAndOrder') }}</p>
    <div v-for="(field, index) in modelValue" :key="index" class="model-field">
      <el-input :model-value="field.label" :placeholder="t('cms.labels.fieldNameEGLogo')" :aria-label="t('cms.labels.fieldValueName', { value1: index + 1 })" @update:model-value="update(index, { label: String($event) })" />
      <el-input :model-value="field.key" :placeholder="t('cms.labels.fieldCodeEGLogo')" :aria-label="t('cms.labels.fieldValueCode', { value1: index + 1 })" @update:model-value="update(index, { key: String($event) })" />
      <el-select :model-value="field.type" @update:model-value="update(index, { type: $event, default: configurationDefault($event), options: [] })"><el-option v-for="type in scalarConfigurationTypes" :key="type.value" :label="type.label" :value="type.value" /></el-select>
      <el-button link type="danger" @click="emit('update:modelValue', modelValue.filter((_, i) => i !== index))">{{ t('cms.labels.removeField') }}</el-button>
      <el-input v-if="field.type === 'select'" class="model-options" :model-value="(field.options || []).join('\n')" type="textarea" :placeholder="t('cms.labels.oneOptionPerLine')" @update:model-value="update(index, { options: String($event).split('\n').map(value => value.trim()).filter(Boolean) })" />
    </div>
    <el-button :disabled="modelValue.length >= 20" @click="emit('update:modelValue', [...modelValue, configurationChild()])">{{ t('cms.labels.addField') }}</el-button>
  </section>
</template>
<style scoped>
.configuration-model-editor{width:100%}.model-presets{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.model-presets span,.model-hint{font-size:12px;color:var(--el-text-color-secondary)}.model-presets .el-button+.el-button{margin-left:0}.model-field{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) 130px auto;gap:10px;margin-bottom:12px}.model-options{grid-column:1/-1}@media(max-width:640px){.model-field{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
