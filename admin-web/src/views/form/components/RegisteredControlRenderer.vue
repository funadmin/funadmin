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
    :model-value="decodedValue"
    :options="options"
    :data-source-state="dataSourceState"
    :disabled="disabled"
    :readonly="readOnly"
    :input-attrs="inputAttrs"
    :preview="designMode"
    :bindings="bindings"
    :schema-node="node"
    v-bind="pluginBindings"
    @update:model-value="emitEncodedValue"
    @event="(name, payload) => emit('event', name, payload)"
  />
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import type { FormFieldDef } from '@/api/form';
import { componentRegistry, sanitizeComponentBindings } from '../schema/componentRegistry';
import type { FormSchemaNode } from '../schema/types';
import type { FormDataSourceControlState } from '../dataSource/useFormDataSource';

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  field: FormFieldDef;
  modelValue?: unknown;
  options?: Array<{ label: string; value: unknown }>;
  dataSourceState?: FormDataSourceControlState;
  disabled?: boolean;
  readOnly?: boolean;
  inputAttrs?: Record<string, unknown>;
  designMode?: boolean;
}>(), { modelValue: () => '', options: () => [], disabled: false, readOnly: false, inputAttrs: () => ({}), designMode: false });

const emit = defineEmits<{
  'update:modelValue': [value: unknown];
  event: [name: string, payload?: unknown];
}>();
const definition = computed(() => componentRegistry.resolve(props.node.type));
const bindings = computed(() => definition.value
  ? sanitizeComponentBindings(definition.value, { props: props.node.props, attrs: props.node.attrs })
  : {});
const asyncRenderer = computed(() => definition.value ? defineAsyncComponent(definition.value.renderer) : undefined);
const decodedValue = computed(() => definition.value ? definition.value.codec.decode(props.modelValue) : props.modelValue);
const emitEncodedValue = (value: unknown) => emit('update:modelValue', definition.value ? definition.value.codec.encode(value) : value);
const pluginBindings = computed(() => definition.value?.namespace === 'core' ? {} : bindings.value);
</script>
