<template>
  <aside class="source-tree" v-loading="loading" aria-label="业务来源树">
    <el-button @click="select([])">全部</el-button>
    <ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :lock="buttonLock" :context="buttonContext('categoryToolbar')" :context-version="contextVersion" :permission-check="permissionCheck" :clear-selection="clearSelection" :close="closeButtonHost" :refresh="refreshButtonHost" />
    <el-tree ref="treeRef" :data="nodes" node-key="value" :props="{ label: 'label', children: '__listChildren' }" :show-checkbox="multiple" check-strictly highlight-current default-expand-all @node-click="clickNode" @check="checkNodes">
      <template #default="{ data }">
        <span>{{ data.label }}</span>
        <ListButtonBar :buttons="nodeButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="data" :lock="buttonLock" :fields="['id', 'value', 'label', 'parent']" :context="buttonContext('categoryNode', data)" :context-version="contextVersion" :permission-check="permissionCheck" :clear-selection="clearSelection" :close="closeButtonHost" :refresh="refreshButtonHost" link />
      </template>
    </el-tree>
    <el-dialog v-model="visible" :title="operation === 'edit' ? '编辑来源节点' : '新增来源节点'" width="720px" destroy-on-close append-to-body>
      <el-alert v-if="unsupportedValidation" title="此来源包含异步校验；左树快捷弹窗暂不支持该校验权限通道，请使用来源业务的正式表单。此处不可保存。" type="warning" :closable="false" />
      <SchemaRenderer v-else-if="sourceMeta" :key="dialogSequence" ref="renderer" :form-key="sourceMeta.form.form_key" :schema="sourceMeta.schema" :values="values" :options-request="optionsRequest" />
      <template #footer><el-button @click="visible = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="unsupportedValidation || !canMutate" @click="save">保存</el-button></template>
    </el-dialog>
  </aside>
</template>
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import ListButtonBar from './ListButtonBar.vue';
import type { ListButtonContext } from '../runtime/listButtonExecutor';
import { defaultListButtons, listActionKey, listButtonState, type ListButtonHandlers } from '../runtime/listButtonHost';
import { resolveListButtons } from '../schema/listButtons';
import { flattenSchemaNodes } from '../schema/types';
import type { FormDataSourceRequest } from '../dataSource/useFormDataSource';
import { ElMessageBox, type ElTree } from 'element-plus';
import { formDataApi, type FormDataMeta, type FormLeftTreeResult, type FormRecordId } from '@/api/formData';
import type { FormLeftTreeConfiguration, FormListConfiguration, FormListButton } from '../schema/types';
import { buildListTree } from '../runtime/listPresentation';
import { buildSubmissionPayload, emptyRuntimeValues } from '../runtime/submissionPolicy';
import SchemaRenderer from './SchemaRenderer.vue';
const props = withDefaults(defineProps<{ formKey: string; schemaHash: string; config: FormLeftTreeConfiguration; list?: FormListConfiguration; lock?: { busy: boolean }; permissionCheck?: (code: string) => boolean; modelValue?: FormRecordId[]; canReadForm?: boolean; canMutate?: boolean; api?: Pick<typeof formDataApi, 'leftTree' | 'leftTreeForm' | 'mutateLeftTree'> }>(), { canReadForm: true, canMutate: true });
const emit = defineEmits<{ change: [values: FormRecordId[]]; mutated: [] }>();
const localButtonLock = reactive({ busy: false });
const buttonLock = computed(() => props.lock ?? localButtonLock);
const toolbarButtons = computed(() => resolveListButtons(props.list, 'categoryToolbar', defaultListButtons('categoryToolbar')));
const nodeButtons = computed(() => resolveListButtons(props.list, 'categoryNode', defaultListButtons('categoryNode')));
const buttonAllowed = (button: FormListButton) => {
  const key = listActionKey(button);
  if (button.permission && !props.permissionCheck?.(button.permission)) return false;
  if (button.action.type === 'registered') return Boolean(props.permissionCheck?.('console/form.data:listactions') && props.permissionCheck?.('console/form.data:listaction'));
  if (key === 'refresh') return true;
  return props.canMutate && (key === 'delete' || props.canReadForm) && Boolean(props.config.actions?.[key as 'create'] ?? true) && Boolean(result.value?.actions[key as 'create']) && (key !== 'addChild' || Boolean(props.config.mapping.parentField));
};
const buttonHandlers: ListButtonHandlers = { create: () => open('create'), addChild: row => open('addChild', row?.id as FormRecordId), edit: row => open('edit', row?.id as FormRecordId), delete: row => remove(row?.id as FormRecordId), refresh: () => load() };
const actionAllowed = (key: string) => [...toolbarButtons.value, ...nodeButtons.value].some(button => {
  if (listActionKey(button) !== key) return false;
  const state = listButtonState(button, { handlers: buttonHandlers, allowed: buttonAllowed });
  return state.visible && !state.disabled;
});
const result = ref<FormLeftTreeResult>();
const contextVersion = ref(0);
const clearSelection = () => select([]);
const closeButtonHost = () => { visible.value = false; dialogSequence.value++; };
const refreshButtonHost = async () => { await load(); emit('mutated'); };
const buttonContext = (location: 'categoryToolbar' | 'categoryNode', row?: Record<string, unknown>): ListButtonContext => ({
  formKey: props.formKey, schemaHash: props.schemaHash, location, ids: [],
  sourceSchemaHash: result.value?.schemaHash, sourceKey: result.value?.sourceKey,
  ...(row ? { category: { id: row.id as FormRecordId } } : {})
});
const sourceMeta = ref<FormDataMeta>();
const loading = ref(false);
const saving = ref(false);
const visible = ref(false);
const operation = ref<'create' | 'addChild' | 'edit'>('create');
const editingId = ref<FormRecordId>('');
const values = ref<Record<string, unknown>>({});
const renderer = ref<InstanceType<typeof SchemaRenderer>>();
const treeRef = ref<InstanceType<typeof ElTree>>();
let loadSequence = 0;
const dialogSequence = ref(0);
onBeforeUnmount(() => { loadSequence++; dialogSequence.value++; });
const unsupportedValidation = computed(() => sourceMeta.value ? flattenSchemaNodes(sourceMeta.value.schema.nodes).some(({ node }) => node.validation?.some(rule => Boolean(rule.validator))) : false);
const optionsRequest = computed<FormDataSourceRequest>(() => {
  const sequence = dialogSequence.value;
  const action = operation.value;
  const id = editingId.value;
  return async (_key, field, context) => {
    if (!props.canReadForm || !actionAllowed(action) || sequence !== dialogSequence.value) throw new Error('弹窗已失效');
    const form = await (props.api ?? formDataApi).leftTreeForm(props.formKey, action, id, props.schemaHash, field, context);
    if (sequence !== dialogSequence.value || form.meta.schemaHash !== sourceMeta.value?.schemaHash) throw new Error('来源发布版本已变化，请重新打开弹窗');
    return { options: form.options ?? [], total: form.total };
  };
});
const multiple = computed(() => props.config.selection?.mode === 'multiple');
const nodes = computed(() => buildListTree(result.value?.nodes ?? [], 'value', 'parent'));
const load = async () => {
  const sequence = ++loadSequence;
  contextVersion.value++;
  dialogSequence.value++;
  visible.value = false;
  sourceMeta.value = undefined;
  result.value = undefined;
  loading.value = true;
  try {
    const loaded = await (props.api ?? formDataApi).leftTree(props.formKey);
    if (sequence !== loadSequence) return;
    result.value = loaded;
    await nextTick();
    treeRef.value?.setCheckedKeys(props.modelValue ?? []);
    treeRef.value?.setCurrentKey(props.modelValue?.[0] ?? null);
  }
  finally { if (sequence === loadSequence) loading.value = false; }
};
const select = (ids: FormRecordId[]) => { contextVersion.value++; treeRef.value?.setCheckedKeys(ids); treeRef.value?.setCurrentKey(ids[0] ?? null); emit('change', ids); };
const clickNode = (node: { value: FormRecordId }) => { if (!multiple.value) select([node.value]); };
const checkNodes = (_node: unknown, state: { checkedKeys: FormRecordId[] }) => select(state.checkedKeys);
const open = async (action: 'create' | 'addChild' | 'edit', id: FormRecordId = '') => {
  if (!actionAllowed(action) || !props.canReadForm || !props.canMutate || !result.value?.actions[action] || (action === 'addChild' && !props.config.mapping.parentField)) return;
  const sequence = ++dialogSequence.value;
  visible.value = false;
  sourceMeta.value = undefined;
  const form = await (props.api ?? formDataApi).leftTreeForm(props.formKey, action, id, props.schemaHash);
  if (sequence !== dialogSequence.value || !actionAllowed(action)) return;
  sourceMeta.value = form.meta;
  operation.value = action;
  editingId.value = id;
  values.value = action === 'edit' ? form.row : emptyRuntimeValues(sourceMeta.value.fields);
  if (action === 'addChild' && props.config.mapping.parentField) values.value[props.config.mapping.parentField] = id;
  visible.value = true;
};
const save = async () => {
  if (!actionAllowed(operation.value) || !props.canReadForm || !props.canMutate || !sourceMeta.value || !result.value?.actions[operation.value] || saving.value || unsupportedValidation.value || !renderer.value) return;
  saving.value = true;
  const sequence = dialogSequence.value;
  try {
    await renderer.value.submit();
    if (sequence !== dialogSequence.value || !actionAllowed(operation.value) || !sourceMeta.value) return;
    const data = buildSubmissionPayload(sourceMeta.value.fields, values.value, []);
    const api = props.api ?? formDataApi;
    await api.mutateLeftTree(props.formKey, operation.value, editingId.value, data, props.schemaHash, sourceMeta.value.schemaHash);
    visible.value = false;
    await load(); emit('mutated');
  } finally { saving.value = false; }
};
const remove = async (id: FormRecordId) => {
  if (!actionAllowed('delete') || !props.canMutate || !result.value?.actions.delete) return;
  const sequence = dialogSequence.value;
  await ElMessageBox.confirm('确认删除来源节点？有子节点或业务引用时禁止删除。', '删除确认', { type: 'warning' });
  if (sequence !== dialogSequence.value || !actionAllowed('delete') || !result.value) return;
  const api = props.api ?? formDataApi;
  await api.mutateLeftTree(props.formKey, 'delete', id, {}, props.schemaHash, result.value.schemaHash);
  select((props.modelValue ?? []).filter(value => String(value) !== String(id)));
  await load(); emit('mutated');
};
watch(() => [props.formKey, props.schemaHash], load, { immediate: true });
watch(() => props.modelValue, ids => { contextVersion.value++; treeRef.value?.setCheckedKeys(ids ?? []); treeRef.value?.setCurrentKey(ids?.[0] ?? null); });
</script>
<style scoped>
.source-tree { width: 280px; flex-shrink: 0; padding: 12px; border: 1px solid var(--el-border-color); }
@media (max-width: 768px) { .source-tree { width: 100%; } }
</style>