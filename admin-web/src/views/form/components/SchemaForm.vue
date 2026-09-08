<template>
  <el-form ref="formRef" :model="values" :rules="rules" label-width="110px">
    <el-row :gutter="16">
      <el-col v-for="field in visibleFields" :key="field.field_name" :span="field.form_span || 24">
        <RegisteredControlRenderer
          v-if="controlMeta(field.type).kind === 'layout'"
          :node="fieldNode(field)"
          :field="field"
          design-mode
        />
        <el-form-item v-else :label="field.type === 'hidden' ? undefined : field.label" :prop="validationProp(field)">
          <RegisteredControlRenderer
            v-model="values[field.field_name]"
            :node="fieldNode(field)"
            :field="field"
            :options="optionsOf(field)"
            :disabled="disabled(field)"
          />
        </el-form-item>
      </el-col>
    </el-row>
  </el-form>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import type { FormFieldDef } from '@/api/form';
import type { FormSchemaNode } from '../schema/types';
import { formDataApi } from '@/api/formData';
import { evaluateLinkRules } from '../linkRules';
import { controlMeta } from '../registry';
import { assertFieldComponents } from '../schema/runtimeGuard';
import { loadPluginFormComponents } from '../schema/pluginComponentLoader';
import RegisteredControlRenderer from './RegisteredControlRenderer.vue';

const props = defineProps<{ formKey: string; fields: FormFieldDef[]; values: Record<string, any> }>();

const formRef = ref<FormInstance>();
const remoteOptions = ref<Record<string, Array<{ label: string; value: string | number }>>>({});

const linkState = computed(() => evaluateLinkRules(props.fields, props.values));
const visibleFields = computed(() => props.fields.filter((field) => !linkState.value.effects[field.field_name]?.hidden));
const disabled = (field: FormFieldDef) => Boolean(linkState.value.effects[field.field_name]?.disabled) || field.form_readonly === 1;
const validationProp = (field: FormFieldDef) => controlMeta(field.type).kind === 'layout' ? undefined : field.field_name;
const fieldNode = (field: FormFieldDef): FormSchemaNode => ({
  id: field.field_name,
  kind: controlMeta(field.type).kind === 'layout' ? 'layout' : 'field',
  type: field.type,
  field: field.field_name,
  title: field.label,
  defaultValue: field.default_value,
  props: field.control_props ?? {},
  children: []
});

const needsRemote = (field: FormFieldDef) =>
  ['select', 'selectV2', 'treeSelect', 'cascader', 'dictionary', 'relation', 'department', 'user'].includes(field.type) &&
  ((field.options_source?.mode !== 'static') || (field.options_source == null && field.relation_type === 'belongs_to'));

const optionsOf = (field: FormFieldDef) => {
  if (needsRemote(field)) return remoteOptions.value[field.field_name] ?? [];
  const options = field.options_source?.options;
  return Array.isArray(options) ? (options as Array<{ label: string; value: string | number }>) : [];
};

const rules = computed<FormRules>(() => {
  const result: FormRules = {};
  for (const field of props.fields) {
    if (controlMeta(field.type).kind === 'layout') continue;
    const items: Array<Record<string, unknown>> = [];
    if (field.form_required === 1) {
      items.push({ required: true, message: `${field.label}不能为空`, trigger: ['blur', 'change'] });
    }
    const extra = (field.validate_rules ?? {}) as Record<string, unknown>;
    if (typeof extra.pattern === 'string' && extra.pattern) {
      items.push({ pattern: new RegExp(extra.pattern), message: `${field.label}格式不正确`, trigger: 'blur' });
    }
    if (items.length) result[field.field_name] = items;
  }
  return result;
});

// 联动回写值
watch(
  () => linkState.value.writes,
  (writes) => {
    for (const [key, value] of Object.entries(writes)) {
      if (props.values[key] !== value) props.values[key] = value;
    }
  },
  { deep: true }
);

onMounted(async () => {
  const targets = props.fields.filter(needsRemote);
  await Promise.all(
    targets.map(async (field) => {
      const data = await formDataApi.options(props.formKey, field.field_name);
      remoteOptions.value[field.field_name] = data.options;
    })
  );
});

const validate = async () => {
  await loadPluginFormComponents();
  assertFieldComponents(props.fields);
  return formRef.value?.validate();
};
const setFieldErrors = (errors: Record<string, string>) => {
  formRef.value?.clearValidate();
  for (const [field, message] of Object.entries(errors)) {
    const context = formRef.value?.fields.find((item) => item.prop === field);
    if (context) {
      context.validateState = 'error';
      context.validateMessage = message;
    }
  }
  const first = Object.keys(errors)[0];
  if (first) formRef.value?.scrollToField(first);
  formRef.value?.fields.find((item) => item.prop === first)?.$el?.querySelector<HTMLElement>('input, textarea, select, [tabindex]')?.focus();
};
defineExpose({ validate, setFieldErrors });
</script>
