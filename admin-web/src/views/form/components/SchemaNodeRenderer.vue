<template>
  <el-col v-if="!node.hidden" :span="node.layout?.span ?? 24">
    <el-alert
      v-if="!definition"
      :title="`未注册的表单组件：${node.type}`"
      type="error"
      :closable="false"
      show-icon
    />
    <template v-else-if="node.kind === 'layout'">
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
    <el-form-item v-else :label="node.type === 'hidden' ? undefined : node.title" :prop="node.field ?? undefined">
      <FormControlRenderer
        :field="legacyField"
        :model-value="node.field ? values[node.field] : undefined"
        :options="options[node.id] ?? []"
        :disabled="node.disabled || disabled"
        @update:model-value="updateValue"
      />
      <el-text v-if="node.info" type="info" size="small">{{ node.info }}</el-text>
    </el-form-item>
  </el-col>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { FormFieldDef } from '@/api/form';
import { componentRegistry } from '../schema/componentRegistry';
import type { FormSchemaNode } from '../schema/types';
import FormControlRenderer from './FormControlRenderer.vue';

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  values: Record<string, unknown>;
  options?: Record<string, Array<{ label: string; value: unknown }>>;
  disabled?: boolean;
  gutter?: number;
}>(), { options: () => ({}), disabled: false, gutter: 16 });

const emit = defineEmits<{ change: [field: string, value: unknown] }>();
const definition = computed(() => componentRegistry.resolve(props.node.type));
const legacyField = computed<FormFieldDef>(() => ({
  field_name: props.node.field ?? props.node.id,
  label: props.node.title,
  type: props.node.type,
  column_type: '', nullable: 1, default_value: String(props.node.defaultValue ?? ''), comment: '', unsigned: 0,
  index_type: 'none', placeholder: String(props.node.props?.placeholder ?? ''), options_source: props.node.dataSource,
  control_props: props.node.props, validate_rules: null, link_rules: null, relation_type: 'none', relation_table: '',
  relation_label_field: '', relation_value_field: '', relation_multiple: 0, relation_on_delete: 'restrict', list_show: 0,
  list_sort: 0, list_filter: '', list_formatter: '', list_width: 0, form_show: props.node.hidden ? 0 : 1,
  form_required: 0, form_group: '', form_span: props.node.layout?.span ?? 24, form_readonly: props.node.disabled ? 1 : 0, sort_order: 0
}));
const updateValue = (value: unknown) => {
  if (props.node.field) emit('change', props.node.field, value);
};
</script>
