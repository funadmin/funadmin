<template>
  <el-form ref="formRef" :model="values" :rules="rules" :label-width="labelWidth" :disabled="disabled">
    <el-row :gutter="gutter">
      <SchemaNodeRenderer
        v-for="node in schema.nodes"
        :key="node.id"
        :node="node"
        :values="values"
        :options="options"
        :disabled="disabled"
        :gutter="gutter"
        @change="updateValue"
      />
    </el-row>
  </el-form>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import type { FormSchemaDocument, FormSchemaNode } from '../schema/types';
import { flattenSchemaNodes } from '../schema/types';
import SchemaNodeRenderer from './SchemaNodeRenderer.vue';

const props = withDefaults(defineProps<{
  schema: FormSchemaDocument;
  values: Record<string, unknown>;
  options?: Record<string, Array<{ label: string; value: unknown }>>;
  disabled?: boolean;
}>(), { options: () => ({}), disabled: false });

const emit = defineEmits<{ change: [field: string, value: unknown] }>();
const formRef = ref<FormInstance>();
const labelWidth = computed(() => String(props.schema.form?.labelWidth ?? '110px'));
const gutter = computed(() => Number(props.schema.form?.gutter ?? 16));
const rules = computed<FormRules>(() => {
  const result: FormRules = {};
  for (const { node } of flattenSchemaNodes(props.schema.nodes)) {
    if (!node.field || node.hidden) continue;
    const items = (node.validation ?? []).map((rule) => validationRule(node, rule));
    if (items.length) result[node.field] = items;
  }
  return result;
});
const validationRule = (node: FormSchemaNode, rule: NonNullable<FormSchemaNode['validation']>[number]) => {
  if (rule.type === 'required') return { required: true, message: rule.message ?? `${node.title}不能为空`, trigger: rule.trigger ?? ['blur', 'change'] };
  if (rule.type === 'pattern') return { pattern: new RegExp(String(rule.value ?? '')), message: rule.message ?? `${node.title}格式不正确`, trigger: rule.trigger ?? 'blur' };
  if (rule.type === 'minlen') return { min: Number(rule.value), message: rule.message ?? `${node.title}长度不足`, trigger: rule.trigger ?? 'blur' };
  if (rule.type === 'maxlen') return { max: Number(rule.value), message: rule.message ?? `${node.title}长度过长`, trigger: rule.trigger ?? 'blur' };
  return { type: rule.type, message: rule.message, trigger: rule.trigger };
};
const updateValue = (field: string, value: unknown) => {
  props.values[field] = value;
  emit('change', field, value);
};
const validate = async () => formRef.value?.validate();
defineExpose({ validate });
</script>
