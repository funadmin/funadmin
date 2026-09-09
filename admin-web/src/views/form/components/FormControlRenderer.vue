<template>
  <div v-if="meta.kind === 'layout'" class="form-layout-control">
    <el-divider v-if="field.type === 'divider'" v-bind="controlAttrs">{{ layoutText }}</el-divider>
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
    :disabled="disabled || readonly"
    :schema-node="schemaNode"
    @update:model-value="updateValue"
    @click="emitEvent('click', $event)"
  />
  <input v-else-if="field.type === 'hidden'" type="hidden" :value="modelValue" @click="emitEvent('click', $event)" />
  <el-text v-else-if="field.type === 'readonly'">{{ displayValue }}</el-text>
  <Upload
    v-else-if="uploadType"
    :model-value="modelValue"
    :type="uploadType"
    :multiple="field.type === 'files'"
    :disabled="disabled || readonly"
    v-bind="controlAttrs"
    @update:model-value="updateValue"
  />
  <el-input
    v-else-if="['input', 'password', 'textarea', 'richtext', 'json'].includes(field.type)"
    :model-value="modelValue"
    :type="inputType"
    :placeholder="placeholder"
    :disabled="disabled || readonly"
    :readonly="readonly"
    v-bind="controlAttrs"
    @update:model-value="updateValue"
    @blur="emitEvent('blur', $event)"
    @focus="emitEvent('focus', $event)"
    @clear="emitEvent('clear', $event)"
    @click="emitEvent('click', $event)"
  />
  <el-mention
    v-else-if="field.type === 'mention'"
    :model-value="stringValue"
    :options="options"
    :placeholder="placeholder"
    :disabled="disabled || readonly"
    v-bind="controlAttrs"
    @update:model-value="updateValue"
  />
  <el-input-number
    v-else-if="field.type === 'number'"
    :model-value="numberValue"
    :disabled="disabled || readonly"
    class="w-full"
    v-bind="controlAttrs"
    @update:model-value="updateValue"
  />
  <div v-else-if="dataSourceSelectionTypes.includes(field.type)" class="data-source-control">
    <el-select
      v-if="selectTypes.includes(field.type)"
      v-bind="controlAttrs"
      :model-value="modelValue"
      :multiple="multiple"
      :placeholder="placeholder"
      :disabled="disabled || readonly"
      :loading="dataSourceState?.loading ?? false"
      :filterable="remoteSearchable || Boolean(controlAttrs.filterable)"
      :remote="remoteSearchable"
      :remote-method="dataSourceState?.search"
      class="w-full"
      @update:model-value="updateValue"
      @change="emitEvent('select', $event)"
      @clear="emitEvent('clear', $event)"
      @blur="emitEvent('blur', $event)"
      @focus="emitEvent('focus', $event)"
    >
      <el-option v-for="option in options" :key="String(option.value)" :label="option.label" :value="option.value" />
    </el-select>
    <el-select-v2
      v-else-if="field.type === 'selectV2'"
      v-bind="controlAttrs"
      :model-value="modelValue"
      :options="options"
      :multiple="multiple"
      :placeholder="placeholder"
      :disabled="disabled || readonly"
      :loading="dataSourceState?.loading ?? false"
      :filterable="remoteSearchable || Boolean(controlAttrs.filterable)"
      :remote="remoteSearchable"
      :remote-method="dataSourceState?.search"
      class="w-full"
      @update:model-value="updateValue"
    />
    <el-tree-select
      v-else-if="['treeSelect', 'department'].includes(field.type)"
      v-bind="controlAttrs"
      :model-value="modelValue"
      :data="options"
      :multiple="multiple"
      :placeholder="placeholder"
      :disabled="disabled || readonly"
      :loading="dataSourceState?.loading ?? false"
      :filterable="remoteSearchable || Boolean(controlAttrs.filterable)"
      :remote="remoteSearchable"
      :remote-method="dataSourceState?.search"
      class="w-full"
      @update:model-value="updateValue"
    />
    <el-cascader
      v-else
      v-bind="controlAttrs"
      :model-value="modelValue"
      :options="options"
      :placeholder="placeholder"
      :disabled="disabled || readonly"
      :loading="dataSourceState?.loading ?? false"
      :filterable="remoteSearchable || Boolean(controlAttrs.filterable)"
      :remote="remoteSearchable"
      :remote-method="dataSourceState?.search"
      :filter-method="filterCascader"
      class="w-full"
      @update:model-value="updateValue"
    />
    <el-alert v-if="dataSourceState?.error" :title="dataSourceError" type="error" :closable="false" show-icon class="data-source-error">
      <template #default>
        <el-button link type="primary" :loading="dataSourceState.loading" @click="dataSourceState.retry">重试</el-button>
      </template>
    </el-alert>
    <el-pagination
      v-if="dataSourceState?.paginated && dataSourceState.total > dataSourceState.pageSize"
      small
      background
      layout="prev, pager, next, total"
      :current-page="dataSourceState.page"
      :page-size="dataSourceState.pageSize"
      :total="dataSourceState.total"
      :disabled="dataSourceState.loading"
      class="data-source-pagination"
      @current-change="dataSourceState.setPage"
    />
  </div>
  <el-radio-group v-else-if="field.type === 'radio'" :model-value="modelValue" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('select', $event)" @click="emitEvent('click', $event)">
    <el-radio v-for="option in options" :key="String(option.value)" :value="option.value">{{ option.label }}</el-radio>
  </el-radio-group>
  <el-checkbox-group v-else-if="field.type === 'checkbox'" :model-value="arrayValue" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('select', $event)" @click="emitEvent('click', $event)">
    <el-checkbox v-for="option in options" :key="String(option.value)" :value="option.value">{{ option.label }}</el-checkbox>
  </el-checkbox-group>
  <el-switch v-else-if="field.type === 'switch'" :model-value="modelValue" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('change', $event)" @click="emitEvent('click', $event)" />
  <el-transfer v-else-if="field.type === 'transfer'" :model-value="arrayValue" :data="transferOptions" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('select', $event)" />
  <el-date-picker
    v-else-if="dateTypes.includes(field.type)"
    :model-value="modelValue"
    :type="datePickerType"
    :placeholder="placeholder"
    :disabled="disabled || readonly"
    class="w-full"
    v-bind="controlAttrs"
    @update:model-value="updateValue"
    @change="emitEvent('select', $event)"
  />
  <el-time-picker v-else-if="field.type === 'time'" :model-value="modelValue" :placeholder="placeholder" :disabled="disabled || readonly" class="w-full" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('select', $event)" />
  <el-time-select v-else-if="field.type === 'timeSelect'" :model-value="stringValue" :placeholder="placeholder" :disabled="disabled || readonly" class="w-full" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('select', $event)" />
  <el-slider v-else-if="field.type === 'slider'" :model-value="numberValue" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('change', $event)" />
  <el-rate v-else-if="field.type === 'rate'" :model-value="numberValue" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('change', $event)" />
  <el-color-picker v-else-if="field.type === 'color'" :model-value="stringValue" :disabled="disabled || readonly" v-bind="controlAttrs" @update:model-value="updateValue" @change="emitEvent('change', $event)" />
  <el-input v-else :model-value="modelValue" :placeholder="placeholder" :disabled="disabled || readonly" @update:model-value="updateValue" @blur="emitEvent('blur', $event)" @focus="emitEvent('focus', $event)" @click="emitEvent('click', $event)" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { FormFieldDef } from '@/api/form';
import type { FormDataSourceControlState } from '../dataSource/useFormDataSource';
import type { FormSchemaNode } from '../schema/types';
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
  dataSourceState?: FormDataSourceControlState;
  disabled?: boolean;
  readonly?: boolean;
  inputAttrs?: Record<string, unknown>;
  preview?: boolean;
  controlProps?: Record<string, unknown>;
  schemaNode?: FormSchemaNode;
}>(), {
  modelValue: '',
  options: () => [],
  disabled: false,
  readonly: false,
  inputAttrs: () => ({}),
  preview: false
});

const emit = defineEmits<{
  'update:modelValue': [value: unknown];
  event: [name: string, payload?: unknown];
}>();
const meta = computed(() => controlMeta(props.field.type));
const controlProps = computed(() => props.controlProps ?? props.field.control_props ?? {});
const controlAttrs = computed(() => ({ ...controlProps.value, ...props.inputAttrs }));
const placeholder = computed(() => props.field.placeholder || props.field.label);
const multiple = computed(() => props.field.relation_multiple === 1 || Boolean(controlProps.value.multiple));
const selectTypes = ['select', 'dictionary', 'relation', 'user'];
const dataSourceSelectionTypes = [...selectTypes, 'selectV2', 'treeSelect', 'cascader', 'department'];
const remoteSearchable = computed(() => Boolean(props.dataSourceState?.searchable));
const dataSourceError = computed(() => {
  const reason = props.dataSourceState?.error;
  if (reason instanceof Error) return reason.message;
  return typeof reason === 'string' ? reason : '选项加载失败';
});
const filterCascader = (_node: unknown, keyword: string): boolean => {
  props.dataSourceState?.search(keyword);
  return true;
};
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
const emitEvent = (name: string, payload?: unknown) => {
  if (!props.preview) emit('event', name, payload);
};
</script>

<style scoped>
.form-layout-control { width: 100%; }
.layout-grid { width: 100%; }
.layout-placeholder { padding: 12px; border: 1px dashed var(--el-border-color); border-radius: 4px; text-align: center; color: var(--el-text-color-secondary); }
.data-source-control { width: 100%; }
.data-source-error { margin-top: 8px; }
.data-source-pagination { justify-content: flex-end; margin-top: 8px; }
</style>
