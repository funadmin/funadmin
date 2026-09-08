<template>
  <FormControlRenderer
    :field="field"
    :model-value="modelValue"
    :options="options"
    :disabled="disabled"
    :readonly="readonly"
    :input-attrs="inputAttrs"
    :preview="preview"
    :control-props="bindings"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
import type { FormFieldDef } from '@/api/form';
import FormControlRenderer from './FormControlRenderer.vue';

withDefaults(defineProps<{
  field: FormFieldDef;
  modelValue?: unknown;
  options?: Array<{ label: string; value: unknown }>;
  disabled?: boolean;
  readonly?: boolean;
  inputAttrs?: Record<string, unknown>;
  preview?: boolean;
  bindings?: Record<string, unknown>;
}>(), { modelValue: () => '', options: () => [], disabled: false, readonly: false, inputAttrs: () => ({}), preview: false, bindings: () => ({}) });

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>();
</script>
