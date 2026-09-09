<template>
  <el-col v-if="!state.hidden" v-bind="columnProps">
    <template v-if="node.kind === 'layout' && definition">
      <el-divider v-if="node.type === 'divider'">{{ node.title }}</el-divider>
      <el-text v-else-if="node.type === 'text'">{{ node.title }}</el-text>
      <el-collapse v-else-if="node.type === 'collapse'" :model-value="[node.id]">
        <el-collapse-item :name="node.id" :title="node.title">
          <el-row :gutter="gutter"><SchemaNodeRenderer v-for="child in node.children" :key="child.id" v-bind="$props" :node="child" /></el-row>
        </el-collapse-item>
      </el-collapse>
      <el-tabs v-else-if="node.type === 'tabs'" type="border-card">
        <el-tab-pane v-for="child in node.children" :key="child.id" :label="child.title" :name="child.id">
          <el-row :gutter="gutter"><SchemaNodeRenderer v-bind="$props" :node="child" /></el-row>
        </el-tab-pane>
      </el-tabs>
      <el-card v-else-if="node.type === 'group'" :header="node.title" shadow="never">
        <el-row :gutter="gutter"><SchemaNodeRenderer v-for="child in node.children" :key="child.id" v-bind="$props" :node="child" /></el-row>
      </el-card>
      <el-row v-else :gutter="gutter">
        <SchemaNodeRenderer v-for="child in node.children" :key="child.id" v-bind="$props" :node="child" />
      </el-row>
    </template>
    <el-form-item
      v-else
      :label="node.type === 'hidden' ? undefined : node.title"
      :for="controlId"
      :prop="node.field ?? undefined"
      :required="required"
      :error="fieldError"
    >
      <RegisteredControlRenderer
        :node="node"
        :field="legacyField"
        :model-value="node.field ? values[node.field] : undefined"
        :options="nodeOptions"
        :data-source-state="dataSourceState"
        :disabled="state.disabled || disabled"
        :read-only="readOnly"
        :input-attrs="controlAttrs"
        :design-mode="designMode"
        @update:model-value="updateValue"
      />
      <el-text v-if="node.info" :id="helpId" type="info" size="small">{{ node.info }}</el-text>
      <span v-if="fieldError" :id="errorId" class="sr-only" role="alert">{{ fieldError }}</span>
    </el-form-item>
  </el-col>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { FormFieldDef } from '@/api/form';
import { componentRegistry } from '../schema/componentRegistry';
import type { FormSchemaNode } from '../schema/types';
import type { FormDataSourceControlState } from '../dataSource/useFormDataSource';
import RegisteredControlRenderer from './RegisteredControlRenderer.vue';

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  values: Record<string, unknown>;
  options?: Record<string, Array<{ label: string; value: unknown }>>;
  dataSources?: Record<string, FormDataSourceControlState>;
  disabled?: boolean;
  gutter?: number;
  stateOf?: (nodeId: string) => { hidden: boolean; disabled: boolean; required: boolean };
  runtimeVersion?: number;
  idPrefix?: string;
  readOnly?: boolean;
  errors?: Record<string, string>;
  designMode?: boolean;
}>(), { options: () => ({}), dataSources: () => ({}), disabled: false, gutter: 16, stateOf: undefined, runtimeVersion: 0, idPrefix: 'form', readOnly: false, errors: () => ({}), designMode: false });

const emit = defineEmits<{ change: [field: string, value: unknown] }>();
const definition = computed(() => componentRegistry.resolve(props.node.type));
const controlId = computed(() => `${props.idPrefix}-field-${props.node.id}`);
const helpId = computed(() => `${controlId.value}-help`);
const errorId = computed(() => `${controlId.value}-error`);
const fieldError = computed(() => props.node.field ? props.errors[props.node.field] ?? '' : '');
const required = computed(() => state.value.required || (props.node.validation ?? []).some((rule) => rule.type === 'required'));
const describedBy = computed(() => [props.node.info ? helpId.value : '', fieldError.value ? errorId.value : ''].filter(Boolean).join(' ') || undefined);
const controlAttrs = computed(() => ({
  id: controlId.value,
  'aria-required': required.value ? 'true' : 'false',
  'aria-invalid': fieldError.value ? 'true' : 'false',
  'aria-describedby': describedBy.value
}));
const columnProps = computed(() => {
  const span = props.node.layout?.span ?? 24;
  return typeof span === 'number' ? { span } : span;
});
const state = computed(() => {
  void props.runtimeVersion;
  return props.stateOf?.(props.node.id) ?? { hidden: Boolean(props.node.hidden), disabled: Boolean(props.node.disabled), required: false };
});
const dataSourceState = computed(() => props.dataSources[props.node.id]);
const nodeOptions = computed(() => {
  const supplied = props.options[props.node.id];
  if (supplied) return supplied;
  const staticOptions = props.node.dataSource?.options;
  return Array.isArray(staticOptions) ? staticOptions as Array<{ label: string; value: unknown }> : [];
});
const legacyField = computed<FormFieldDef>(() => ({
  field_name: props.node.field ?? props.node.id,
  label: props.node.title,
  type: props.node.type,
  column_type: '', nullable: 1, default_value: String(props.node.defaultValue ?? ''), comment: '', unsigned: 0,
  index_type: 'none', placeholder: String(props.node.props?.placeholder ?? ''), options_source: props.node.dataSource,
  control_props: props.node.props, validate_rules: null, link_rules: null, relation_type: 'none', relation_table: '',
  relation_label_field: '', relation_value_field: '', relation_multiple: 0, relation_on_delete: 'restrict', list_show: 0,
  list_sort: 0, list_filter: '', list_formatter: '', list_width: 0, form_show: props.node.hidden ? 0 : 1,
  form_required: 0, form_group: '', form_span: typeof props.node.layout?.span === 'number' ? props.node.layout.span : 24, form_readonly: props.node.disabled ? 1 : 0, sort_order: 0
}));
const updateValue = (value: unknown) => {
  if (props.node.field) emit('change', props.node.field, value);
};
</script>
