<template>
  <el-card shadow="never" class="mb-4">
    <template #header>列表展示配置</template>
    <el-alert title="独立分类请选择“可管理分类”，绑定已发布分类业务的真实记录；“选项筛选”仅展示静态选项或字典，不提供分类增删改。两种左侧栏互斥，均可配合树形列表。" type="info" :closable="false" />
    <el-form label-width="110px" class="mt-3">
      <el-tabs v-model="activeTab">
      <el-tab-pane label="选项筛选" name="category">
      <el-form-item label="选项筛选"><el-switch :model-value="modelValue.category?.enabled ?? false" @change="enabled => category({ enabled: Boolean(enabled) })" /></el-form-item>
      <el-form-item v-if="modelValue.category?.enabled" label="分类绑定字段">
        <el-select :model-value="modelValue.category.field" filterable placeholder="选择已有静态选项或字典字段" @change="field => category({ field })">
          <el-option v-for="field in categoryFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
        </el-select>
      </el-form-item>
      </el-tab-pane>
      <el-tab-pane label="树形列表" name="tree">
      <el-form-item label="树形列表"><el-switch :model-value="modelValue.tree?.enabled ?? false" @change="enabled => tree({ enabled: Boolean(enabled) })" /></el-form-item>
      <el-form-item v-if="modelValue.tree?.enabled" label="父级字段">
        <el-select :model-value="modelValue.tree.parentField" filterable placeholder="选择存储父记录主键的字段" @change="parentField => tree({ parentField })">
          <el-option v-for="field in scalarFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
        </el-select>
        <div class="text-xs text-[var(--el-text-color-secondary)]">主键自动读取实际表主键；树列表不分页，最多 1000 条授权记录，超限需缩小筛选范围。</div>
      </el-form-item>
      </el-tab-pane>
      <el-tab-pane label="可管理分类" name="leftTree">
      <el-form-item label="可管理分类"><el-switch :model-value="left.enabled" @change="value => updateLeft({ enabled: Boolean(value) })" /></el-form-item>
      <template v-if="left.enabled">
        <el-alert title="独立分类请选择已发布业务，右表关联字段保存分类主键。来源须有读取权限；新增、子级、编辑、删除还需对应权限。父级可留空（平面分类）；插件来源暂不支持快捷管理。" :closable="false" />
        <el-form-item label="来源"><el-select :model-value="left.source.type" @change="changeSource"><el-option label="当前业务（same）" value="current" /><el-option label="已发布业务（cross）" value="module" /></el-select></el-form-item>
        <el-form-item v-if="left.source.type === 'module'" label="业务模块"><el-select :model-value="left.source.module" filterable @visible-change="shown => shown && loadModules()" @change="chooseModule"><el-option v-for="item in modules" :key="item.moduleId" :label="item.moduleCode" :value="item.moduleCode" /></el-select></el-form-item>
        <el-form-item v-for="binding in bindings" :key="binding.key" :label="binding.label">
          <el-select :model-value="left.mapping[binding.key]" clearable filterable @change="value => updateLeft({ mapping: { ...left.mapping, [binding.key]: value } })">
            <el-option v-if="binding.key === 'valueField' && left.source.type === 'current' && !sourceFields.some(field => field.field_name === 'id')" label="系统主键 (id)" value="id" />
            <el-option v-for="field in binding.key === 'targetField' ? targetFields : sourceFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
          </el-select>
        </el-form-item>
        <el-form-item label="选择模式"><el-radio-group :model-value="left.selection?.mode ?? 'single'" @change="mode => updateLeft({ selection: { ...left.selection, mode: mode as 'single' | 'multiple' } })"><el-radio value="single">单选</el-radio><el-radio value="multiple">复选</el-radio></el-radio-group></el-form-item>
        <el-form-item label="包含后代"><el-switch :disabled="!left.mapping.parentField" :model-value="left.selection?.includeDescendants ?? false" @change="value => updateLeft({ selection: { ...left.selection, includeDescendants: Boolean(value) } })" /></el-form-item>
        <el-form-item v-for="action in actions" :key="action.key" :label="action.label"><el-switch :disabled="action.key === 'addChild' && !left.mapping.parentField" :model-value="action.key === 'addChild' && !left.mapping.parentField ? false : left.actions?.[action.key] ?? false" @change="value => updateLeft({ actions: { ...left.actions, [action.key]: Boolean(value) } })" /></el-form-item>
      </template>
      </el-tab-pane>
      <el-tab-pane label="按钮与工具" name="buttons">
        <div class="list-button-section">
          <div class="list-button-actions"><el-button size="small" :loading="catalogLoading" @click="loadButtonCatalog">重新加载动作目录</el-button></div>
        <ListButtonEditor v-for="location in buttonLocations" :key="location.key" :title="location.label" :location="location.key" :fields="location.key.startsWith('category') ? categoryButtonFields : buttonFields" :filter-fields="scalarFields.filter(field => field.list_filter && field.list_filter !== 'none').map(field => field.field_name)" :category-fields="categoryButtonFields" :resources="resources" :actions="actionCatalogs[location.key]" :catalog-error="catalogErrors[location.key]" :builtin-keys="builtinKeys(location.key)" :resource-enabled="!pluginTarget && (!location.key.startsWith('category') || left.enabled)" :model-value="modelValue.buttons?.[location.key]" @update="value => updateButtons(location.key, value)" />
        </div>
        <el-divider>通用工具</el-divider>
        <div class="list-tools-grid"><el-form-item v-for="tool in tools" :key="tool.key" :label="tool.label"><el-switch :model-value="modelValue.tools?.[tool.key] !== false" @change="value => emit('update', { tools: { ...modelValue.tools, [tool.key]: Boolean(value) } })" /></el-form-item></div>
      </el-tab-pane>
      </el-tabs>
    </el-form>
  </el-card>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { formDataApi, type FormSourceMeta, type FormSourceField } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import { businessDevelopmentApi } from '@/api/development/business';
import type { ListResource } from '../../runtime/listResourceHost';
import { listButtonKeys } from '../../runtime/listButtonHost';
import type { FormListActionMetadata, FormListBuiltinAction } from '../../schema/types';
import type { FormListConfiguration, FormLeftTreeConfiguration, FormListButton, FormListButtonLocation } from '../../schema/types';
import ListButtonEditor from './ListButtonEditor.vue';
const props = defineProps<{ modelValue: FormListConfiguration; fields: FormFieldDef[]; moduleId?: number; formKey?: string; permissions?: string[]; pluginTarget?: boolean }>();
const emit = defineEmits<{ update: [value: FormListConfiguration] }>();
// Tab 仅保存面板显示状态，不进入 Schema 更新通道。
const activeTab = ref('category');
const buttonLocations = [{ key: 'toolbar', label: '顶部操作' }, { key: 'row', label: '行操作' }, { key: 'categoryToolbar', label: '分类顶部操作' }, { key: 'categoryNode', label: '分类节点操作' }] as const;
const tools = [{ key: 'refresh', label: '刷新' }, { key: 'search', label: '搜索' }, { key: 'columns', label: '列设置' }, { key: 'density', label: '密度' }, { key: 'fullscreen', label: '全屏' }] as const;
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
const bindings = [{ key: 'valueField', label: '来源主键' }, { key: 'labelField', label: '显示字段' }, { key: 'parentField', label: '父级字段' }, { key: 'sortField', label: '排序字段' }, { key: 'targetField', label: '右表关联字段' }] as const;
const actions = [{ key: 'create', label: '新增根节点' }, { key: 'addChild', label: '新增子节点' }, { key: 'edit', label: '编辑节点' }, { key: 'delete', label: '删除节点' }] as const;
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
  if (!externalFields.value.some(field => field.field_name === meta.primaryKey.name)) externalFields.value.unshift({ field_name: meta.primaryKey.name, label: '来源主键' });
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
    if (result.moduleId !== props.moduleId || result.designOnly !== true || result.executable !== false) throw new Error('设计目录响应无效');
    // 服务端按路由权限过滤；前端权限别名不能替代或再次误过滤该目录。
    resources.value = result.resources;
    actionCatalogs.value = result.actions;
  } catch {
    for (const location of buttonLocations) errors[location.key] = '设计动作目录加载失败、无权限或目标无适配器；已保存配置仍保留，请重试。';
  } finally { if (!disposed && current === catalogSequence) { catalogErrors.value = errors; catalogLoading.value = false; } }
}
watch(() => [props.moduleId, props.permissions, props.pluginTarget], loadButtonCatalog, { immediate: true, deep: true });
const categoryFields = computed(() => scalarFields.value.filter(field => ['static', 'dictionary'].includes(String(field.options_source?.kind ?? field.options_source?.mode ?? ''))));
const category = (patch: Partial<NonNullable<FormListConfiguration['category']>>) => emit('update', { category: { enabled: false, ...props.modelValue.category, ...patch }, ...(patch.enabled ? { leftTree: { ...left.value, enabled: false } } : {}) });
const tree = (patch: Partial<NonNullable<FormListConfiguration['tree']>>) => emit('update', { tree: { enabled: false, ...props.modelValue.tree, ...patch } });
</script>
<style scoped>
.list-button-actions { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.list-tools-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0 16px; }
@media (max-width: 600px) { .list-tools-grid { grid-template-columns: 1fr; } }
.list-tools-grid :deep(.el-form-item) { margin-bottom: 8px; }
.list-tools-grid :deep(.el-form-item__label) { width: auto !important; }
.list-tools-grid :deep(.el-form-item__content) { flex: 0 0 auto; }
</style>
