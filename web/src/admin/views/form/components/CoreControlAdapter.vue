<template>
  <FormControlRenderer
    :node="schemaNode"
    :model-value="modelValue"
    :options="options"
    :data-source-state="dataSourceState"
    :disabled="disabled"
    :readonly="readonly"
    :input-attrs="inputAttrs"
    :preview="preview"
    :control-props="bindings"
    @update:model-value="emit('update:modelValue', $event)"
    @event="(name, payload) => emit('event', name, payload)"
  />
</template>

<script setup lang="ts">
import FormControlRenderer from './FormControlRenderer.vue';
import type { FormDataSourceControlState } from '../dataSource/useFormDataSource';
import type { FormSchemaNode } from '../schema/types';

withDefaults(defineProps<{
  /** 注册渲染器统一契约：所有渲染器（含插件）都以 schema-node 接收节点。 */
  schemaNode: FormSchemaNode;
  modelValue?: unknown;
  options?: Array<{ label: string; value: unknown }>;
  dataSourceState?: FormDataSourceControlState;
  disabled?: boolean;
  readonly?: boolean;
  inputAttrs?: Record<string, unknown>;
  preview?: boolean;
  bindings?: Record<string, unknown>;
}>(), { modelValue: () => '', options: () => [], disabled: false, readonly: false, inputAttrs: () => ({}), preview: false, bindings: () => ({}) });

const emit = defineEmits<{
  'update:modelValue': [value: unknown];
  event: [name: string, payload?: unknown];
}>();
</script>
