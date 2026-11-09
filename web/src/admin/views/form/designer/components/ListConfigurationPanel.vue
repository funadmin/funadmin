<template>
  <el-card shadow="never" class="mb-4" :class="{ 'list-panel--buttons-only': buttonsOnly, 'list-panel--categories': mode === 'categories' }">
    <CategoryWorkspace v-if="!buttonsOnly" :model-value="modelValue" :left="left" :category-fields="categoryFields" :source-fields="sourceFields" :target-fields="targetSources" :modules="modules" :columns="previewColumns" :locations="workspaceLocations" :tree-pending="treePending" @category="category" @update-left="updateLeft" @change-source="changeSource" @choose-module="code => chooseModule(code)" @load-modules="loadModules" @clear="clearCategories" />
    <ButtonsWorkspace v-else :model-value="modelValue" :locations="workspaceLocations" :tools="tools" :filter-fields="filterFields" :category-fields="categoryButtonFields" :resources="resources" :columns="previewColumns" :catalog-loading="catalogLoading" :catalog-count="catalogCount" :catalog-failed="Object.keys(catalogErrors).length > 0" :catalog-teleport="catalogTeleport" :custom-action-entity="customActionEntity" :custom-actions-blocked="pluginTarget" @update-buttons="updateButtons" @update-tools="value => emit('update', { tools: value })" @reload-catalog="loadButtonCatalog" @update-custom-actions="updateCustomActions" />
  </el-card>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { formDataApi, type FormSourceMeta, type FormSourceField } from '@/api/formData';
import { businessDevelopmentApi } from '@/api/development/business';
import type { ListResource } from '../../runtime/listResourceHost';
import { listButtonKeys } from '../../runtime/listButtonHost';
import type { FormListActionMetadata, FormListBuiltinAction, FormSchemaNode } from '../../schema/types';
import { columnTypeOf, isSensitiveNode, relationTypeOf } from '../../schema/fieldNodes';
import type { FormListConfiguration, FormLeftTreeConfiguration, FormListButton, FormListButtonLocation, FormListCustomAction } from '../../schema/types';
import { customActionEntity as customActionEntityOf } from './customActions';
import ButtonsWorkspace, { type ButtonsWorkspaceLocation } from './ButtonsWorkspace.vue';
import CategoryWorkspace from './CategoryWorkspace.vue';
import type { ListPreviewColumn } from './listPreviewSample';
const props = defineProps<{ modelValue: FormListConfiguration; fields: FormSchemaNode[]; moduleId?: number; formKey?: string; permissions?: string[]; pluginTarget?: boolean; mode?: 'all' | 'buttons' | 'categories'; catalogTeleport?: string }>();
const emit = defineEmits<{ update: [value: FormListConfiguration] }>();
const { t } = useI18n();
// 按钮与工具模式下外层只保留编辑区，去掉卡片头、说明与冗余的二级 Tab 条。
const buttonsOnly = computed(() => props.mode === 'buttons');
const buttonLocations = computed(() => [{ key: 'toolbar', label: t('formDesigner.listPanel.toolbarActions', '顶部操作') }, { key: 'row', label: t('formDesigner.listPanel.rowActions', '行操作') }, { key: 'categoryToolbar', label: t('formDesigner.listPanel.categoryToolbarActions', '分类顶部操作') }, { key: 'categoryNode', label: t('formDesigner.listPanel.categoryNodeActions', '分类节点操作') }] as const);
const tools = computed(() => [{ key: 'refresh', label: t('common.refresh', '刷新') }, { key: 'search', label: t('formDesigner.listPanel.toolSearch', '搜索') }, { key: 'columns', label: t('formDesigner.listPanel.toolColumns', '列设置') }, { key: 'density', label: t('formDesigner.listPanel.toolDensity', '密度') }, { key: 'fullscreen', label: t('formDesigner.listPanel.toolFullscreen', '全屏') }] as const);
const updateButtons = (location: FormListButtonLocation, value: FormListButton[] | undefined) => {
  const buttons = { ...props.modelValue.buttons };
  if (value === undefined) delete buttons[location]; else buttons[location] = value;
  emit('update', { buttons });
};
// 自定义动作写回 list.customActions（为空时写 undefined 以删除该键，不写空数组），删除 / 去掉位置时同时移除绑定的按钮。
const customActionEntity = computed(() => props.formKey ? customActionEntityOf(props.formKey) : undefined);
const updateCustomActions = (actions: FormListCustomAction[] | undefined, buttons: Partial<Record<FormListButtonLocation, FormListButton[]>> | undefined) => {
  emit('update', { customActions: actions, ...(buttons ? { buttons: { ...props.modelValue.buttons, ...buttons } } : {}) });
};
// fields 为设计器字段节点（调用方经 fieldNodes() 展开，布局内字段已包含）。
const targetFields = computed(() => props.fields.filter(node => Boolean(node.field) && Boolean(columnTypeOf(node)) && ['none', 'belongs_to'].includes(relationTypeOf(node)) && !['repeatable', 'subform', 'json', 'checkbox', 'transfer'].includes(node.type) && !node.props?.multiple && !isSensitiveNode(node)));
const scalarFields = computed(() => targetFields.value.filter(node => relationTypeOf(node) === 'none'));
const fieldName = (node: FormSchemaNode): string => String(node.field);
const sourceField = (node: FormSchemaNode): FormSourceField => ({ field: fieldName(node), title: node.title });
const targetSources = computed(() => targetFields.value.map(sourceField));
const modules = ref<FormSourceMeta[]>([]);
const externalFields = ref<FormSourceField[]>([]);
const left = computed<FormLeftTreeConfiguration>(() => props.modelValue.leftTree ?? { enabled: false, source: { type: 'current' }, mapping: { valueField: '', labelField: '', targetField: '' } });
const sourceFields = computed<FormSourceField[]>(() => left.value.source.type === 'current' ? scalarFields.value.map(sourceField) : externalFields.value);
// 没有父级字段时不能新增子节点、也不能包含后代：每次写回分类树都按此规则清理。
const normalizeLeft = (patch: Partial<FormLeftTreeConfiguration>): FormLeftTreeConfiguration => {
  const next = { ...left.value, ...patch };
  if (!next.mapping.parentField) {
    next.actions = { ...next.actions, addChild: false };
    next.selection = { ...next.selection, includeDescendants: false };
  }
  return next;
};
// 后端拒绝必填映射为空的已开启分类树：选中分类树但映射未补全时只保留界面选中态（待完成），
// 写回的配置保持关闭，映射补全后与 enabled: true 一并写回，自动保存因此不会失败。
const REQUIRED_MAPPINGS = ['valueField', 'labelField', 'targetField'] as const;
const mappingComplete = (config: FormLeftTreeConfiguration) => REQUIRED_MAPPINGS.every(key => Boolean(config.mapping[key]));
const treePending = ref(false);
// 本面板最近一次写回的分类树：据此区分外部改动（保存前兜底关闭、撤销等）与本面板的关闭操作。
let emittedLeft = '';
const emitUpdate = (value: FormListConfiguration) => {
  if (value.leftTree) emittedLeft = JSON.stringify(value.leftTree);
  emit('update', value);
};
// 外部把映射未补全的已开启分类树改为关闭时（保存前兜底），保留分类树选中与待完成提示。
watch(() => [left.value.enabled, props.modelValue.category?.enabled], ([tree, tabs], [wasTree]) => {
  if (tree || tabs) treePending.value = false;
  else if (wasTree && !mappingComplete(left.value) && JSON.stringify(left.value) !== emittedLeft) treePending.value = true;
});
// 选择分类树（当前业务）时预填：来源主键用系统主键，显示字段用首个文本字段；右表关联字段必须由用户选择。
const prefillMapping = (patch: Partial<FormLeftTreeConfiguration>): Partial<FormLeftTreeConfiguration> => {
  const source = patch.source ?? left.value.source;
  if (source.type !== 'current') return patch;
  const mapping = patch.mapping ?? left.value.mapping;
  const text = scalarFields.value.find(node => ['input', 'textarea'].includes(node.type));
  return { ...patch, mapping: { ...mapping, valueField: mapping.valueField || 'id', labelField: mapping.labelField || (text ? fieldName(text) : '') } };
};
const updateLeft = (patch: Partial<FormLeftTreeConfiguration>) => {
  const choosing = patch.enabled === true && !left.value.enabled && !treePending.value;
  const next = normalizeLeft(choosing ? prefillMapping(patch) : patch);
  const wantTree = next.enabled || (treePending.value && patch.enabled !== false);
  if (wantTree) next.enabled = mappingComplete(next);
  treePending.value = wantTree && !next.enabled;
  emitUpdate({ leftTree: next, ...(next.enabled || (wantTree && props.modelValue.category?.enabled) ? { category: { ...props.modelValue.category, enabled: false } } : {}) });
};
let sourceSequence = 0;
let disposed = false;
let moduleRequest: Promise<void> | undefined;
onBeforeUnmount(() => { disposed = true; sourceSequence++; });
const changeSource = (type: 'current' | 'module') => { sourceSequence++; externalFields.value = []; updateLeft({ source: { type }, mapping: { valueField: '', labelField: '', targetField: left.value.mapping.targetField } }); };
const loadModules = (): Promise<void> => {
  if (moduleRequest) return moduleRequest;
  moduleRequest = (async () => {
    const result = await formDataApi.sourceCandidates();
    if (!disposed) modules.value = result.list;
  })().catch(() => { if (!disposed) modules.value = []; }).finally(() => { moduleRequest = undefined; });
  return moduleRequest;
};
const chooseModule = async (code: string, restore = false) => {
  try {
  const sequence = ++sourceSequence;
  externalFields.value = [];
  const item = modules.value.find(item => item.moduleCode === code);
  if (!item) return;
  const meta = await formDataApi.sourceMeta(item.formKey);
  if (disposed || sequence !== sourceSequence || left.value.source.type !== 'module') return;
  externalFields.value = [...meta.fields];
  if (!externalFields.value.some(field => field.field === meta.primaryKey.name)) externalFields.value.unshift({ field: meta.primaryKey.name, title: t('formDesigner.listPanel.sourcePrimaryKey', '来源主键') });
  if (!restore) updateLeft({ source: { type: 'module', module: code }, mapping: { valueField: meta.primaryKey.name, labelField: '', targetField: left.value.mapping.targetField } });
  } catch { /* 来源授权已撤销时保留原配置，不覆盖草稿。 */ }
};
watch(() => [left.value.source.type, left.value.source.module], async ([type, code]) => {
  const sequence = ++sourceSequence;
  externalFields.value = [];
  if (type !== 'module' || !code) return;
  await loadModules();
  if (disposed || sequence !== sourceSequence) return;
  await chooseModule(code, true);
}, { immediate: true });
// 带访问控制（node.access 非空）的字段不能被按钮参数引用，与后端 ListButtonSchemaValidator::readableField 一致。
const hasAccess = (node: FormSchemaNode): boolean => Object.keys(node.access ?? {}).length > 0;
const buttonFields = computed(() => ['id', ...scalarFields.value.filter(node => !hasAccess(node)).map(fieldName)].filter((field, index, all) => all.indexOf(field) === index));
const categoryButtonFields = computed(() => left.value.enabled ? sourceFields.value.map(field => field.field).filter(name => buttonFields.value.includes(name)) : []);
const resources = ref<Record<string, ListResource>>({});
const actionCatalogs = ref<Partial<Record<FormListButtonLocation, Record<string, FormListActionMetadata>>>>({});
const catalogErrors = ref<Partial<Record<FormListButtonLocation, string>>>({});
const catalogLoading = ref(false);
let catalogSequence = 0;
function builtinKeys(location: FormListButtonLocation): FormListBuiltinAction[] {
  if (!location.startsWith('category')) return listButtonKeys[location];
  if (!left.value.enabled || props.pluginTarget) return [];
  return listButtonKeys[location].filter(key => left.value.actions?.[key as 'create'] === true && (key !== 'addChild' || Boolean(left.value.mapping.parentField)));
}
async function loadButtonCatalog() {
  const current = ++catalogSequence;
  resources.value = {}; actionCatalogs.value = {}; catalogErrors.value = {};
  catalogLoading.value = false;
  if (!props.moduleId) return;
  catalogLoading.value = true;
  const errors: Partial<Record<FormListButtonLocation, string>> = {};
  try {
    const result = await businessDevelopmentApi.designActionCatalog(props.moduleId);
    if (disposed || current !== catalogSequence) return;
    if (result.moduleId !== props.moduleId || result.designOnly !== true || result.executable !== false) throw new Error(t('formDesigner.listPanel.designCatalogInvalid', '设计目录响应无效'));
    // 服务端按路由权限过滤；前端权限别名不能替代或再次误过滤该目录。
    resources.value = result.resources;
    actionCatalogs.value = result.actions;
  } catch {
    for (const location of buttonLocations.value) errors[location.key] = t('formDesigner.listPanel.actionCatalogLoadFailed', '设计动作目录加载失败、无权限或目标无适配器；已保存配置仍保留，请重试。');
  } finally { if (!disposed && current === catalogSequence) { catalogErrors.value = errors; catalogLoading.value = false; } }
}
watch(() => [props.moduleId, props.permissions, props.pluginTarget], loadButtonCatalog, { immediate: true, deep: true });
const filterFields = computed(() => scalarFields.value.filter(node => node.list?.filter && node.list.filter !== 'none').map(fieldName));
// 按钮与工具工作区：每个位置的字段、可用内置动作、目录与错误；数据语义与原四个 ListButtonEditor 完全一致。
const workspaceLocations = computed<ButtonsWorkspaceLocation[]>(() => buttonLocations.value.map(location => ({ key: location.key, label: location.label, fields: location.key.startsWith('category') ? categoryButtonFields.value : buttonFields.value, builtinKeys: builtinKeys(location.key), resourceEnabled: !props.pluginTarget && (!location.key.startsWith('category') || left.value.enabled), actions: actionCatalogs.value[location.key], catalogError: catalogErrors.value[location.key] })));
const catalogCount = computed(() => catalogLoading.value || !props.moduleId || Object.keys(catalogErrors.value).length ? undefined : Object.keys(resources.value).length + Object.values(actionCatalogs.value).reduce((total, items) => total + Object.keys(items ?? {}).length, 0));
const staticOptions = (node: FormSchemaNode) => { const source = node.dataSource as { kind?: string; options?: Array<{ label?: unknown; value?: unknown }> } | null | undefined; return source?.kind === 'static' && Array.isArray(source?.options) ? source.options.filter(option => option && option.value !== undefined).map(option => ({ label: String(option.label ?? option.value), value: option.value })) : undefined; };
const previewColumns = computed<ListPreviewColumn[]>(() => { const readable = scalarFields.value.filter(node => buttonFields.value.includes(fieldName(node))); const shown = readable.filter(node => node.list?.show); return [...shown, ...readable.filter(node => !node.list?.show)].map(node => ({ field: fieldName(node), label: node.title?.trim() || fieldName(node), options: staticOptions(node), numeric: /int|decimal|float|double|numeric/i.test(columnTypeOf(node)) })); });
const categoryFields = computed(() => scalarFields.value.filter(node => ['static', 'dictionary'].includes(String(node.dataSource?.kind ?? ''))).map(sourceField));
const category = (patch: Partial<NonNullable<FormListConfiguration['category']>>) => {
  if (patch.enabled) treePending.value = false;
  emitUpdate({ category: { enabled: false, ...props.modelValue.category, ...patch }, ...(patch.enabled ? { leftTree: { ...left.value, enabled: false } } : {}) });
};
// 「不分类」：只关闭当前开启的一方，写回内容与原先分别关闭两个开关一致（分类树经同一父级规则清理），其余配置保留以便再次开启。
// 分类树仅处于待完成（未写入开启）时只清除界面状态。
const clearCategories = () => {
  treePending.value = false;
  if (!props.modelValue.category?.enabled && !left.value.enabled) return;
  emitUpdate({ ...(props.modelValue.category?.enabled ? { category: { ...props.modelValue.category, enabled: false } } : {}), ...(left.value.enabled ? { leftTree: normalizeLeft({ enabled: false }) } : {}) });
};
</script>
<style scoped>
.list-panel--buttons-only, .list-panel--categories { border: 0; background: transparent; }
.list-panel--buttons-only :deep(.el-card__body), .list-panel--categories :deep(.el-card__body) { padding: 0; }
</style>
