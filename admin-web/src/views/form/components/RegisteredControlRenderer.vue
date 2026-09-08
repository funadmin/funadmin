<template>
  <el-alert
    v-if="!definition"
    :title="`未注册的表单组件：${node.type}`"
    type="error"
    :closable="false"
    show-icon
  />
  <component
    :is="asyncRenderer"
    v-else
    :field="field"
    :model-value="modelValue"
    :options="options"
    :disabled="disabled"
    :readonly="readOnly"
    :input-attrs="inputAttrs"
    :preview="designMode"
    :bindings="bindings"
    v-bind="pluginBindings"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import type { FormFieldDef } from '@/api/form';
import { componentRegistry, sanitizeComponentBindings } from '../schema/componentRegistry';
import type { FormSchemaNode } from '../schema/types';

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  field: FormFieldDef;
  modelValue?: unknown;
  options?: Array<{ label: string; value: unknown }>;
  disabled?: boolean;
  readOnly?: boolean;
  inputAttrs?: Record<string, unknown>;
  designMode?: boolean;
}>(), { modelValue: () => '', options: () => [], disabled: false, readOnly: false, inputAttrs: () => ({}), designMode: false });

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>();
const definition = computed(() => componentRegistry.resolve(props.node.type));
const bindings = computed(() => definition.value
  ? sanitizeComponentBindings(definition.value, { props: props.node.props, attrs: props.node.attrs })
  : {});
const asyncRenderer = computed(() => definition.value ? defineAsyncComponent(definition.value.renderer) : undefined);
const pluginBindings = computed(() => definition.value?.namespace === 'core' ? {} : bindings.value);
</script>
