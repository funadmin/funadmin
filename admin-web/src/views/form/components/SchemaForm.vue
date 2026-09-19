<template>
  <SchemaRenderer :key="definitionKey" ref="renderer" :schema="schema" :form-key="formKey" :values="values" :options-request="optionsRequest" />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormFieldDef } from '@/api/form';
import type { FormSchemaDocument, FormSchemaNode, FormSchemaValidationRule } from '../schema/types';
import { formDataApi } from '@/api/formData';
import { evaluateLinkRules } from '../linkRules';
import { controlMeta } from '../registry';
import SchemaRenderer from './SchemaRenderer.vue';

const { t } = useI18n();

const props = defineProps<{ formKey: string; fields: FormFieldDef[]; values: Record<string, any> }>();
const renderer = ref<InstanceType<typeof SchemaRenderer>>();
const linkState = computed(() => evaluateLinkRules(props.fields, props.values));
const definitionKey = computed(() => JSON.stringify([props.formKey, props.fields, linkState.value.effects]));

/** 旧投影只转换协议；渲染、校验及错误定位均由统一渲染器负责。 */
function validation(field: FormFieldDef): FormSchemaValidationRule[] {
  const extra = field.validate_rules ?? {};
  const rules: FormSchemaValidationRule[] = [];
  if (field.form_required === 1) rules.push({ type: 'required', message: t('formData.fieldRequiredWithLabel', { label: field.label }, '{label}不能为空') });
  for (const [key, value] of Object.entries(extra)) {
    let type = key;
    let argument = value;
    if (key === 'email') { if (value !== true) continue; type = 'format'; argument = 'email'; }
    if (key === 'type' && ['email', 'url'].includes(String(value))) type = 'format';
    if (key === 'minlen') type = 'minLength';
    if (key === 'maxlen') type = 'maxLength';
    if (extra.type === 'array' && ['min', 'max'].includes(key)) type = key === 'min' ? 'minLength' : 'maxLength';
    if (type === 'minLength' && Number(argument) > 0 && extra.type === 'array' && field.form_required !== 1) rules.push({ type: 'required' });
    rules.push({ type, value: argument, message: t('formData.fieldInvalid', { label: field.label }, '{label}格式或长度不正确') });
  }
  return rules;
}
const schema = computed<FormSchemaDocument>(() => ({
  schemaVersion: 2, key: props.formKey, title: '',
  nodes: props.fields.map((field): FormSchemaNode => ({
    id: field.field_name,
    kind: controlMeta(field.type).kind === 'layout' ? 'layout' : 'field',
    type: field.type,
    field: controlMeta(field.type).kind === 'layout' ? null : field.field_name,
    title: field.label,
    defaultValue: field.default_value,
    props: field.control_props ?? {},
    children: [],
    layout: { span: field.form_span || 24 },
    hidden: linkState.value.effects[field.field_name]?.hidden,
    disabled: linkState.value.effects[field.field_name]?.disabled,
    validation: validation(field),
    dataSource: field.options_source ?? null
  }))
}));
const optionsRequest = (key: string, field: string, params: Record<string, unknown>, signal?: AbortSignal) => formDataApi.options(key, field, params, signal);
watch(() => linkState.value.writes, writes => {
  for (const [key, value] of Object.entries(writes)) if (props.values[key] !== value) props.values[key] = value;
}, { deep: true });
const validate = async () => renderer.value?.validate();
const setFieldErrors = (errors: Record<string, string>) => renderer.value?.setFieldErrors(errors);
defineExpose({ validate, setFieldErrors });
</script>
