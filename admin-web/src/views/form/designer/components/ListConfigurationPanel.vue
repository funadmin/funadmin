<template>
  <el-card shadow="never" class="mb-4" :class="{ 'list-panel--buttons-only': buttonsOnly }">
    <el-form label-width="110px" :class="{ 'mt-3': !buttonsOnly }">
      <template v-if="!buttonsOnly">
      <el-form-item :label="t('formDesigner.listPanel.categoryFilter', '选项筛选')"><el-switch :model-value="modelValue.category?.enabled ?? false" @change="enabled => category({ enabled: Boolean(enabled) })" /></el-form-item>
      <el-form-item v-if="modelValue.category?.enabled" :label="t('formDesigner.listPanel.categoryField', '分类绑定字段')">
        <el-select :model-value="modelValue.category.field" filterable :placeholder="t('formDesigner.listPanel.categoryFieldPlaceholder', '选择已有静态选项或字典字段')" @change="field => category({ field })">
          <el-option v-for="field in categoryFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
        </el-select>
      </el-form-item>
      <el-divider />
      <el-form-item :label="t('formDesigner.listPanel.manageableCategory', '可管理分类')"><el-switch :model-value="left.enabled" @change="value => updateLeft({ enabled: Boolean(value) })" /></el-form-item>
      <template v-if="left.enabled">
        <el-form-item :label="t('formDesigner.listPanel.source', '来源')"><el-select :model-value="left.source.type" @change="changeSource"><el-option :label="t('formDesigner.listPanel.sourceCurrent', '当前业务（same）')" value="current" /><el-option :label="t('formDesigner.listPanel.sourceModule', '已发布业务（cross）')" value="module" /></el-select></el-form-item>
        <el-form-item v-if="left.source.type === 'module'" :label="t('formDesigner.listPanel.businessModule', '业务模块')"><el-select :model-value="left.source.module" filterable @visible-change="shown => shown && loadModules()" @change="chooseModule"><el-option v-for="item in modules" :key="item.moduleId" :label="item.moduleCode" :value="item.moduleCode" /></el-select></el-form-item>
        <el-form-item v-for="binding in bindings" :key="binding.key" :label="binding.label">
          <el-select :model-value="left.mapping[binding.key]" clearable filterable @change="value => updateLeft({ mapping: { ...left.mapping, [binding.key]: value } })">
            <el-option v-if="binding.key === 'valueField' && left.source.type === 'current' && !sourceFields.some(field => field.field_name === 'id')" :label="t('formDesigner.listPanel.systemPrimaryKey', '系统主键 (id)')" value="id" />
            <el-option v-for="field in binding.key === 'targetField' ? targetFields : sourceFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('formDesigner.listPanel.selectionMode', '选择模式')"><el-radio-group :model-value="left.selection?.mode ?? 'single'" @change="mode => updateLeft({ selection: { ...left.selection, mode: mode as 'single' | 'multiple' } })"><el-radio value="single">{{ t('formDesigner.listPanel.single', '单选') }}</el-radio><el-radio value="multiple">{{ t('formDesigner.listPanel.multiple', '复选') }}</el-radio></el-radio-group></el-form-item>
        <el-form-item :label="t('formDesigner.listPanel.includeDescendants', '包含后代')"><el-switch :disabled="!left.mapping.parentField" :model-value="left.selection?.includeDescendants ?? false" @change="value => updateLeft({ selection: { ...left.selection, includeDescendants: Boolean(value) } })" /></el-form-item>
        <el-form-item v-for="action in actions" :key="action.key" :label="action.label"><el-switch :disabled="action.key === 'addChild' && !left.mapping.parentField" :model-value="action.key === 'addChild' && !left.mapping.parentField ? false : left.actions?.[action.key] ?? false" @change="value => updateLeft({ actions: { ...left.actions, [action.key]: Boolean(value) } })" /></el-form-item>
      </template>
      </template>
      <template v-else>
        <div class="list-button-section">
          <div class="list-button-actions">
            <span class="list-button-actions__hint">{{ t('formDesigner.listPanel.actionCatalogHint', '各位置可选的“注册动作”来自服务端设计目录（按路由权限过滤）；服务端新增或调整动作后点此刷新。') }}</span>
            <el-button size="small" :loading="catalogLoading" @click="loadButtonCatalog"><i class="i-ep-refresh" />{{ t('formDesigner.listPanel.reloadActionCatalog', '重新加载动作目录') }}</el-button>
          </div>
        <ListButtonEditor v-for="location in buttonLocations" :key="location.key" :title="location.label" :location="location.key" :fields="location.key.startsWith('category') ? categoryButtonFields : buttonFields" :filter-fields="scalarFields.filter(field => field.list_filter && field.list_filter !== 'none').map(field => field.field_name)" :category-fields="categoryButtonFields" :resources="resources" :actions="actionCatalogs[location.key]" :catalog-error="catalogErrors[location.key]" :builtin-keys="builtinKeys(location.key)" :resource-enabled="!pluginTarget && (!location.key.startsWith('category') || left.enabled)" :model-value="modelValue.buttons?.[location.key]" @update="value => updateButtons(location.key, value)" />
        </div>
        <el-divider>{{ t('formDesigner.listPanel.commonTools', '通用工具') }}</el-divider>
        <div class="list-tools-grid"><el-form-item v-for="tool in tools" :key="tool.key" :label="tool.label"><el-switch :model-value="modelValue.tools?.[tool.key] !== false" @change="value => emit('update', { tools: { ...modelValue.tools, [tool.key]: Boolean(value) } })" /></el-form-item></div>
      </template>
    </el-form>
  </el-card>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { formDataApi, type FormSourceMeta, type FormSourceField } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import { businessDevelopmentApi } from '@/api/development/business';
import type { ListResource } from '../../runtime/listResourceHost';
import { listButtonKeys } from '../../runtime/listButtonHost';
import type { FormListActionMetadata, FormListBuiltinAction } from '../../schema/types';
import type { FormListConfiguration, FormLeftTreeConfiguration, FormListButton, FormListButtonLocation } from '../../schema/types';
import ListButtonEditor from './ListButtonEditor.vue';
const props = defineProps<{ modelValue: FormListConfiguration; fields: FormFieldDef[]; moduleId?: number; formKey?: string; permissions?: string[]; pluginTarget?: boolean; mode?: 'all' | 'buttons' | 'categories' }>();
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
const fieldLabel = (field: FormSourceField): string => {
  const title = field.label?.trim();
  return title && title !== field.field_name ? `${title} (${field.field_name})` : field.field_name;
};
const targetFields = computed(() => props.fields.filter(field => field.column_type && ['none', 'belongs_to'].includes(field.relation_type) && !['password', 'repeatable', 'subform', 'json', 'checkbox', 'transfer'].includes(field.type) && !field.control_props?.multiple && !field.control_props?.sensitive && !field.control_props?.writeOnly));
const scalarFields = computed(() => targetFields.value.filter(field => field.relation_type === 'none'));
const modules = ref<FormSourceMeta[]>([]);
const externalFields = ref<FormSourceField[]>([]);
const left = computed<FormLeftTreeConfiguration>(() => props.modelValue.leftTree ?? { enabled: false, source: { type: 'current' }, mapping: { valueField: '', labelField: '', targetField: '' } });
const sourceFields = computed(() => left.value.source.type === 'current' ? scalarFields.value : externalFields.value);
const bindings = computed(() => [{ key: 'valueField', label: t('formDesigner.listPanel.sourcePrimaryKey', '来源主键') }, { key: 'labelField', label: t('formDesigner.listPanel.labelField', '显示字段') }, { key: 'parentField', label: t('formDesigner.listPanel.parentField', '父级字段') }, { key: 'sortField', label: t('formDesigner.listPanel.sortField', '排序字段') }, { key: 'targetField', label: t('formDesigner.listPanel.targetField', '右表关联字段') }] as const);
const actions = computed(() => [{ key: 'create', label: t('formDesigner.listPanel.addRootNode', '新增根节点') }, { key: 'addChild', label: t('formDesigner.listPanel.addChildNode', '新增子节点') }, { key: 'edit', label: t('formDesigner.listPanel.editNode', '编辑节点') }, { key: 'delete', label: t('formDesigner.listPanel.deleteNode', '删除节点') }] as const);
const updateLeft = (patch: Partial<FormLeftTreeConfiguration>) => {
  const next = { ...left.value, ...patch };
  if (!next.mapping.parentField) {
    next.actions = { ...next.actions, addChild: false };
    next.selection = { ...next.selection, includeDescendants: false };
  }
  emit('update', { leftTree: next, ...(next.enabled ? { category: { ...props.modelValue.category, enabled: false } } : {}) });
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
  if (!externalFields.value.some(field => field.field_name === meta.primaryKey.name)) externalFields.value.unshift({ field_name: meta.primaryKey.name, label: t('formDesigner.listPanel.sourcePrimaryKey', '来源主键') });
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
const buttonFields = computed(() => ['id', ...scalarFields.value.filter(field => !field.control_props?.schemaAccess).map(field => field.field_name)].filter((field, index, all) => all.indexOf(field) === index));
const categoryButtonFields = computed(() => left.value.enabled ? sourceFields.value.map(field => field.field_name).filter(name => buttonFields.value.includes(name)) : []);
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
const categoryFields = computed(() => scalarFields.value.filter(field => ['static', 'dictionary'].includes(String(field.options_source?.kind ?? field.options_source?.mode ?? ''))));
const category = (patch: Partial<NonNullable<FormListConfiguration['category']>>) => emit('update', { category: { enabled: false, ...props.modelValue.category, ...patch }, ...(patch.enabled ? { leftTree: { ...left.value, enabled: false } } : {}) });
</script>
<style scoped>
.list-button-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.list-button-actions__hint { font-size: 12px; color: var(--el-text-color-secondary); }
.list-tools-grid { display: flex; flex-wrap: wrap; gap: 0 32px; }
.list-tools-grid :deep(.el-form-item) { margin-bottom: 8px; }
.list-tools-grid :deep(.el-form-item__label) { width: auto !important; }
.list-tools-grid :deep(.el-form-item__content) { flex: 0 0 auto; }
</style>
