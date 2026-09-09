<template>
  <el-alert v-if="registryError" :title="registryError" type="error" :closable="false" show-icon />
  <el-form
    v-else-if="registryReady"
    ref="formRef"
    :model="values"
    :rules="rules"
    :label-width="labelWidth"
    :label-position="labelPosition"
    :size="formSize"
    :inline="inline"
    :disabled="disabled"
    :data-label-position="labelPosition"
    :data-size="formSize"
    :data-inline="String(inline)"
  >
    <div v-if="errorSummary" class="sr-only" role="alert" aria-live="assertive" aria-atomic="true">{{ errorSummary }}</div>
    <el-row :gutter="gutter">
      <SchemaNodeRenderer
        v-for="node in schema.nodes"
        :key="node.id"
        :node="node"
        :values="values"
        :options="resolvedOptions"
        :data-sources="resolvedDataSources"
        :disabled="disabled"
        :gutter="gutter"
        :state-of="runtime.nodeState"
        :runtime-version="runtimeVersion"
        :id-prefix="idPrefix"
        :read-only="readOnly"
        :errors="fieldErrors"
        @change="(field, value) => updateValue(node.id, field, value)"
        @event="dispatchEvent"
      />
    </el-row>
  </el-form>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import type { FormSchemaDocument, FormSchemaNode } from '../schema/types';
import { flattenSchemaNodes } from '../schema/types';
import SchemaNodeRenderer from './SchemaNodeRenderer.vue';
import { createRuntimeState } from '../runtime/runtimeState';
import type { ActionHandlers } from '../runtime/actionExecutor';
import { useFormDataSource, type FormDataSourceControlState } from '../dataSource/useFormDataSource';
import { createAsyncValidatorRegistry } from '../validation/asyncValidatorRegistry';
import { createElementPlusValidationRules } from '../validation/formSchemaDataValidator';
import { assertRuntimeComponents } from '../schema/runtimeGuard';
import { loadPluginFormComponents } from '../schema/pluginComponentLoader';
import { formDataApi } from '@/api/formData';

const props = withDefaults(defineProps<{
  schema: FormSchemaDocument;
  values: Record<string, unknown>;
  options?: Record<string, Array<{ label: string; value: unknown }>>;
  disabled?: boolean;
  formKey?: string;
  requestKeys?: string[];
  actionHandlers?: Partial<ActionHandlers>;
}>(), { options: () => ({}), disabled: false, formKey: '', requestKeys: () => [], actionHandlers: () => ({}) });

const emit = defineEmits<{ change: [field: string, value: unknown] }>();
const registryReady = ref(false);
const registryError = ref('');
const registryLoading = loadPluginFormComponents()
  .then(() => { registryReady.value = true; })
  .catch((error: unknown) => { registryError.value = error instanceof Error ? error.message : String(error); });
const resolvedOptions = ref<Record<string, Array<{ label: string; value: unknown }>>>({ ...props.options });
const resolvedDataSources = ref<Record<string, FormDataSourceControlState>>({});
const runtime = createRuntimeState(props.schema.nodes, props.values, {
  actions: props.schema.actions as Array<{ id?: string; event?: string; steps?: import('../runtime/actionExecutor').FormAction[] }> | undefined,
  submitAction: String(props.schema.submit?.action ?? ''),
  requestKeys: props.requestKeys,
  request: props.actionHandlers.request,
  notify: props.actionHandlers.notify,
  navigate: props.actionHandlers.navigate,
  openDialog: props.actionHandlers.openDialog,
  validate: props.actionHandlers.validate,
  submit: props.actionHandlers.submit,
  reset: props.actionHandlers.reset
});
const runtimeVersion = ref(0);
const formRef = ref<FormInstance>();
const fieldErrors = ref<Record<string, string>>({});
const errorSummary = computed(() => {
  const count = Object.keys(fieldErrors.value).length;
  return count ? `表单存在 ${count} 个错误，请检查并修正。` : '';
});
const asyncDataSources = flattenSchemaNodes(props.schema.nodes).map(({ node }) => node).filter((node) => {
  const kind = String(node.dataSource?.kind ?? node.dataSource?.mode ?? '');
  return Boolean(props.formKey && node.field && kind && kind !== 'static');
}).map((node) => ({
  node,
  source: useFormDataSource({
    formKey: props.formKey,
    field: node.field!,
    definition: node.dataSource ?? {},
    values: props.values
  })
}));
for (const { node, source } of asyncDataSources) {
  resolvedDataSources.value[node.id] = {
    get loading() { return source.loading.value; },
    get error() { return source.error.value; },
    get page() { return source.page.value; },
    pageSize: source.pageSize,
    get total() { return source.total.value; },
    searchable: source.searchable,
    paginated: source.paginated,
    search: source.search,
    setPage: source.setPage,
    retry: source.refresh
  };
  watch(source.options, (options) => {
    resolvedOptions.value[node.id] = options.map((option) => ({ ...option, label: String(option.label ?? '') }));
  }, { immediate: true });
}
const asyncValidatorRegistry = createAsyncValidatorRegistry({
  remote: async ({ value, values, options, signal }) => {
    const field = String(options.field ?? '');
    const validator = String(options.validator ?? '');
    const result = await formDataApi.validate(props.formKey, field, validator, value, values, options.params as Record<string, unknown> ?? {}, signal);
    return { valid: result.valid, message: result.fieldErrors[0]?.message };
  }
});
const idPrefix = computed(() => `form-${String(props.schema.key).replace(/[^A-Za-z0-9_-]/g, '-')}`);
const labelWidth = computed(() => String(props.schema.form?.labelWidth ?? '110px'));
const labelPosition = computed(() => props.schema.form?.labelPosition ?? 'right');
const formSize = computed(() => props.schema.form?.size ?? 'default');
const inline = computed(() => Boolean(props.schema.form?.inline));
const readOnly = computed(() => Boolean(props.schema.form?.readOnly));
const gutter = computed(() => Number(props.schema.form?.gutter ?? 16));
const rules = computed<FormRules>(() => {
  const result: FormRules = {};
  for (const { node } of flattenSchemaNodes(props.schema.nodes)) {
    const state = runtime.nodeState(node.id);
    if (!node.field || state.hidden) continue;
    const items = createElementPlusValidationRules(node.field, node.validation ?? [], props.values, (key, rule) => (
      asyncValidatorRegistry.rule('remote', {
        debounce: rule.validator?.debounce,
        timeout: rule.validator?.timeout,
        cacheTtl: rule.validator?.cacheTtl,
        values: props.values,
        params: { field: node.field, validator: key, params: rule.validator?.params ?? {} },
        message: rule.message
      })
    ));
    if (state.required && !items.some((rule) => rule.required === true)) items.unshift({ required: true, message: `${node.title}不能为空`, trigger: ['blur', 'change'] });
    if (items.length) result[node.field] = items;
  }
  return result;
});
const dispatchEvent = async (nodeId: string, event: string, payload?: unknown) => {
  await runtime.dispatch(nodeId, event, payload);
  runtimeVersion.value += 1;
};
const updateValue = async (nodeId: string, field: string, value: unknown) => {
  props.values[field] = value;
  runtime.refresh();
  await dispatchEvent(nodeId, 'change', value);
  emit('change', field, value);
};
watch(() => props.values, () => {
  runtime.refresh();
  runtimeVersion.value += 1;
}, { deep: true });
const focusFirstError = async () => {
  await nextTick();
  const first = Object.keys(fieldErrors.value)[0];
  if (!first) return;
  const node = flattenSchemaNodes(props.schema.nodes).find(({ node }) => node.field === first)?.node;
  const control = node ? document.getElementById(`${idPrefix.value}-field-${node.id}`) : null;
  control?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
  control?.focus?.();
};
const setFieldErrors = async (errors: Record<string, string>) => {
  fieldErrors.value = { ...errors };
  await focusFirstError();
};
const validate = async () => {
  await registryLoading;
  if (registryError.value) throw new Error(registryError.value);
  assertRuntimeComponents(props.schema);
  fieldErrors.value = {};
  try {
    return await formRef.value?.validate();
  } catch (reason) {
    const invalid = reason && typeof reason === 'object' ? reason as Record<string, Array<{ message?: string }>> : {};
    fieldErrors.value = Object.fromEntries(Object.entries(invalid).map(([field, items]) => [field, items[0]?.message ?? '字段校验失败']));
    await focusFirstError();
    throw reason;
  }
};
const dispatchFormEvent = (event: 'submit' | 'reset' | 'mounted', payload?: unknown) => runtime.dispatch('', event, payload);
const submit = async (handler?: () => unknown | Promise<unknown>) => {
  await validate();
  await dispatchFormEvent('submit');
  return handler?.();
};
void dispatchFormEvent('mounted');
defineExpose({ validate, submit, setFieldErrors, dispatchFormEvent });
</script>
