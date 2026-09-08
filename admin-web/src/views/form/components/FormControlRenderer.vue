<template>
  <div v-if="meta.kind === 'layout'" class="form-layout-control">
    <el-divider v-if="field.type === 'divider'" v-bind="controlProps">{{ layoutText }}</el-divider>
    <el-alert v-else-if="field.type === 'group'" :title="layoutText" type="info" :closable="false" show-icon />
    <el-row v-else-if="field.type === 'grid'" :gutter="numberProp('gutter', 16)" class="layout-grid">
      <el-col v-for="column in numberProp('columns', 2)" :key="column" :span="Math.floor(24 / numberProp('columns', 2))">
        <div class="layout-placeholder">栅格 {{ column }}</div>
      </el-col>
    </el-row>
    <el-text v-else-if="field.type === 'text'">{{ layoutText }}</el-text>
    <el-collapse v-else-if="field.type === 'collapse'" :model-value="['preview']">
      <el-collapse-item :title="layoutText" name="preview">折叠区域内容</el-collapse-item>
    </el-collapse>
    <el-tabs v-else-if="field.type === 'tabs'" model-value="0" type="border-card">
      <el-tab-pane v-for="(tab, index) in tabs" :key="index" :label="tab" :name="String(index)">标签页内容</el-tab-pane>
    </el-tabs>
  </div>

  <RepeatableField
    v-else-if="field.relation_type === 'has_many' || ['repeatable', 'subform'].includes(field.type)"
    :field="field"
    :model-value="arrayObjectValue"
    :disabled="disabled"
    @update:model-value="updateValue"
  />
  <input v-else-if="field.type === 'hidden'" type="hidden" :value="modelValue" />
  <el-text v-else-if="field.type === 'readonly'">{{ displayValue }}</el-text>
  <Upload
    v-else-if="uploadType"
    :model-value="modelValue"
    :type="uploadType"
    :multiple="field.type === 'files'"
    :disabled="disabled"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-input
    v-else-if="['input', 'password', 'textarea', 'richtext', 'json'].includes(field.type)"
    :model-value="modelValue"
    :type="inputType"
    :placeholder="placeholder"
    :disabled="disabled"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-mention
    v-else-if="field.type === 'mention'"
    :model-value="stringValue"
    :options="options"
    :placeholder="placeholder"
    :disabled="disabled"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-input-number
    v-else-if="field.type === 'number'"
    :model-value="numberValue"
    :disabled="disabled"
    class="w-full"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-select
    v-else-if="selectTypes.includes(field.type)"
    :model-value="modelValue"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    class="w-full"
    v-bind="controlProps"
    @update:model-value="updateValue"
  >
    <el-option v-for="option in options" :key="String(option.value)" :label="option.label" :value="option.value" />
  </el-select>
  <el-select-v2
    v-else-if="field.type === 'selectV2'"
    :model-value="modelValue"
    :options="options"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    class="w-full"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-tree-select
    v-else-if="['treeSelect', 'department'].includes(field.type)"
    :model-value="modelValue"
    :data="options"
    :multiple="multiple"
    :placeholder="placeholder"
    :disabled="disabled"
    class="w-full"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-cascader
    v-else-if="field.type === 'cascader'"
    :model-value="modelValue"
    :options="options"
    :placeholder="placeholder"
    :disabled="disabled"
    class="w-full"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-radio-group v-else-if="field.type === 'radio'" :model-value="modelValue" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue">
    <el-radio v-for="option in options" :key="String(option.value)" :value="option.value">{{ option.label }}</el-radio>
  </el-radio-group>
  <el-checkbox-group v-else-if="field.type === 'checkbox'" :model-value="arrayValue" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue">
    <el-checkbox v-for="option in options" :key="String(option.value)" :value="option.value">{{ option.label }}</el-checkbox>
  </el-checkbox-group>
  <el-switch v-else-if="field.type === 'switch'" :model-value="modelValue" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue" />
  <el-transfer v-else-if="field.type === 'transfer'" :model-value="arrayValue" :data="transferOptions" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue" />
  <el-date-picker
    v-else-if="dateTypes.includes(field.type)"
    :model-value="modelValue"
    :type="datePickerType"
    :placeholder="placeholder"
    :disabled="disabled"
    class="w-full"
    v-bind="controlProps"
    @update:model-value="updateValue"
  />
  <el-time-picker v-else-if="field.type === 'time'" :model-value="modelValue" :placeholder="placeholder" :disabled="disabled" class="w-full" v-bind="controlProps" @update:model-value="updateValue" />
  <el-time-select v-else-if="field.type === 'timeSelect'" :model-value="stringValue" :placeholder="placeholder" :disabled="disabled" class="w-full" v-bind="controlProps" @update:model-value="updateValue" />
  <el-slider v-else-if="field.type === 'slider'" :model-value="numberValue" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue" />
  <el-rate v-else-if="field.type === 'rate'" :model-value="numberValue" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue" />
  <el-color-picker v-else-if="field.type === 'color'" :model-value="stringValue" :disabled="disabled" v-bind="controlProps" @update:model-value="updateValue" />
  <el-input v-else :model-value="modelValue" :placeholder="placeholder" :disabled="disabled" @update:model-value="updateValue" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { FormFieldDef } from '@/api/form';
import Upload from '@/components/Upload/index.vue';
import { controlMeta } from '../registry';
import RepeatableField from './RepeatableField.vue';

interface ControlOption {
  [key: string]: any;
  label: string;
  value: any;
  children?: ControlOption[];
}

const props = withDefaults(defineProps<{
  field: FormFieldDef;
  modelValue?: any;
  options?: ControlOption[];
  disabled?: boolean;
  preview?: boolean;
}>(), {
  modelValue: '',
  options: () => [],
  disabled: false,
  preview: false
});

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>();
const meta = computed(() => controlMeta(props.field.type));
const controlProps = computed(() => props.field.control_props ?? {});
const placeholder = computed(() => props.field.placeholder || props.field.label);
const multiple = computed(() => props.field.relation_multiple === 1 || Boolean(controlProps.value.multiple));
const selectTypes = ['select', 'dictionary', 'relation', 'user'];
const dateTypes = ['date', 'datetime', 'daterange', 'datetimerange'];
const datePickerType = computed(() => props.field.type as 'date' | 'datetime' | 'daterange' | 'datetimerange');
const inputType = computed(() => {
  if (props.field.type === 'password') return 'password';
  return ['textarea', 'richtext', 'json'].includes(props.field.type) ? 'textarea' : 'text';
});
const uploadType = computed<'image' | 'images' | 'file' | null>(() => {
  if (props.field.type === 'image' || props.field.type === 'images') return props.field.type;
  return props.field.type === 'file' || props.field.type === 'files' ? 'file' : null;
});
const stringValue = computed(() => typeof props.modelValue === 'string' ? props.modelValue : '');
const numberValue = computed(() => typeof props.modelValue === 'number' ? props.modelValue : 0);
const arrayValue = computed(() => Array.isArray(props.modelValue) ? props.modelValue : []);
const arrayObjectValue = computed(() => arrayValue.value.filter((item): item is Record<string, unknown> => Boolean(item) && typeof item === 'object' && !Array.isArray(item)));
const displayValue = computed(() => String(props.modelValue ?? props.field.default_value ?? ''));
const transferOptions = computed(() => props.options.map((option) => ({ key: option.value, label: option.label })));
const tabs = computed(() => {
  const value = controlProps.value.tabs;
  return Array.isArray(value) ? value.map(String) : ['标签一', '标签二'];
});
const layoutText = computed(() => String(controlProps.value.title ?? controlProps.value.content ?? props.field.label));
const numberProp = (key: string, fallback: number) => {
  const value = Number(controlProps.value[key]);
  return Number.isFinite(value) && value > 0 ? value : fallback;
};
const updateValue = (value: any) => {
  if (!props.preview) emit('update:modelValue', value);
};
</script>

<style scoped>
.form-layout-control { width: 100%; }
.layout-grid { width: 100%; }
.layout-placeholder { padding: 12px; border: 1px dashed var(--el-border-color); border-radius: 4px; text-align: center; color: var(--el-text-color-secondary); }
</style>
