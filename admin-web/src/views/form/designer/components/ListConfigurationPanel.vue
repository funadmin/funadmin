<template>
  <el-card shadow="never" class="mb-4">
    <template #header>列表展示配置</template>
    <el-alert title="分类仅支持当前 Schema 字段的静态选项或字典，不支持任意表、SQL 或远程来源。分类与树形列表可独立启用。" type="info" :closable="false" />
    <el-form label-width="110px" class="mt-3">
      <el-form-item label="左侧分类"><el-switch :model-value="modelValue.category?.enabled ?? false" @change="enabled => category({ enabled: Boolean(enabled) })" /></el-form-item>
      <el-form-item v-if="modelValue.category?.enabled" label="分类绑定字段">
        <el-select :model-value="modelValue.category.field" placeholder="选择已有静态选项或字典字段" @change="field => category({ field })">
          <el-option v-for="field in categoryFields" :key="field.field_name" :label="field.label" :value="field.field_name" />
        </el-select>
      </el-form-item>
      <el-form-item label="树形列表"><el-switch :model-value="modelValue.tree?.enabled ?? false" @change="enabled => tree({ enabled: Boolean(enabled) })" /></el-form-item>
      <el-form-item v-if="modelValue.tree?.enabled" label="父级字段">
        <el-select :model-value="modelValue.tree.parentField" placeholder="选择存储父记录主键的字段" @change="parentField => tree({ parentField })">
          <el-option v-for="field in scalarFields" :key="field.field_name" :label="field.label" :value="field.field_name" />
        </el-select>
        <div class="text-xs text-[var(--el-text-color-secondary)]">主键自动读取实际表主键；树列表不分页，最多 1000 条授权记录，超限需缩小筛选范围。</div>
      </el-form-item>
      <el-form-item label="左侧业务树"><el-switch :model-value="left.enabled" @change="value => updateLeft({ enabled: Boolean(value) })" /></el-form-item>
      <template v-if="left.enabled">
        <el-alert title="来源值仅支持主键；当前业务为 same，已发布业务为 cross。后代展开由服务端按授权节点计算。" :closable="false" />
        <el-form-item label="来源"><el-select :model-value="left.source.type" @change="changeSource"><el-option label="当前业务（same）" value="current" /><el-option label="已发布业务（cross）" value="module" /></el-select></el-form-item>
        <el-form-item v-if="left.source.type === 'module'" label="业务模块"><el-select :model-value="left.source.module" filterable @visible-change="shown => shown && loadModules()" @change="chooseModule"><el-option v-for="item in modules" :key="item.id" :label="item.name" :value="item.code" /></el-select></el-form-item>
        <el-form-item v-for="binding in bindings" :key="binding.key" :label="binding.label">
          <el-select :model-value="left.mapping[binding.key]" clearable @change="value => updateLeft({ mapping: { ...left.mapping, [binding.key]: value } })">
            <el-option v-if="binding.key === 'valueField' && !sourceFields.some(field => field.field_name === 'id')" label="系统主键 (id)" value="id" />
            <el-option v-for="field in binding.key === 'targetField' ? scalarFields : sourceFields" :key="field.field_name" :label="`${field.label} (${field.field_name})`" :value="field.field_name" />
          </el-select>
        </el-form-item>
        <el-form-item label="选择模式"><el-radio-group :model-value="left.selection?.mode ?? 'single'" @change="mode => updateLeft({ selection: { ...left.selection, mode: mode as 'single' | 'multiple' } })"><el-radio value="single">单选</el-radio><el-radio value="multiple">复选</el-radio></el-radio-group></el-form-item>
        <el-form-item label="包含后代"><el-switch :model-value="left.selection?.includeDescendants ?? false" @change="value => updateLeft({ selection: { ...left.selection, includeDescendants: Boolean(value) } })" /></el-form-item>
        <el-form-item v-for="action in actions" :key="action.key" :label="action.label"><el-switch :model-value="left.actions?.[action.key] ?? false" @change="value => updateLeft({ actions: { ...left.actions, [action.key]: Boolean(value) } })" /></el-form-item>
      </template>
    </el-form>
  </el-card>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { businessDevelopmentApi, type BusinessModule } from '@/api/development/business';
import { formDataApi } from '@/api/formData';
import type { FormFieldDef } from '@/api/form';
import type { FormListConfiguration, FormLeftTreeConfiguration } from '../../schema/types';
const props = defineProps<{ modelValue: FormListConfiguration; fields: FormFieldDef[] }>();
const emit = defineEmits<{ update: [value: FormListConfiguration] }>();
const scalarFields = computed(() => props.fields.filter(field => field.column_type && field.relation_type === 'none' && !['password', 'repeatable', 'subform', 'json', 'checkbox', 'transfer'].includes(field.type) && !field.control_props?.multiple && !field.control_props?.sensitive && !field.control_props?.writeOnly));
const modules = ref<BusinessModule[]>([]);
const externalFields = ref<FormFieldDef[]>([]);
const left = computed<FormLeftTreeConfiguration>(() => props.modelValue.leftTree ?? { enabled: false, source: { type: 'current' }, mapping: { valueField: '', labelField: '', targetField: '' } });
const sourceFields = computed(() => left.value.source.type === 'current' ? scalarFields.value : externalFields.value);
const bindings = [{ key: 'valueField', label: '来源主键' }, { key: 'labelField', label: '显示字段' }, { key: 'parentField', label: '父级字段' }, { key: 'sortField', label: '排序字段' }, { key: 'targetField', label: '右表关联字段' }] as const;
const actions = [{ key: 'create', label: '新增根节点' }, { key: 'addChild', label: '新增子节点' }, { key: 'edit', label: '编辑节点' }, { key: 'delete', label: '删除节点' }] as const;
const updateLeft = (patch: Partial<FormLeftTreeConfiguration>) => emit('update', { leftTree: { ...left.value, ...patch } });
let sourceSequence = 0;
let disposed = false;
let moduleRequest: Promise<void> | undefined;
onBeforeUnmount(() => { disposed = true; sourceSequence++; });
const changeSource = (type: 'current' | 'module') => { sourceSequence++; externalFields.value = []; updateLeft({ source: { type }, mapping: { valueField: '', labelField: '', targetField: left.value.mapping.targetField } }); };
const loadModules = (): Promise<void> => {
  if (moduleRequest) return moduleRequest;
  moduleRequest = (async () => {
    const collected: BusinessModule[] = [];
    let count = 0;
    for (let page = 1; !disposed; page++) {
      const result = await businessDevelopmentApi.modules({ page, pageSize: 100 });
      collected.push(...result.list.filter(item => ['published', 'dynamic_published'].includes(item.lifecycle_status)));
      count += result.list.length;
      if (!result.list.length || count >= result.total) break;
    }
    if (!disposed) modules.value = collected;
  })().finally(() => { moduleRequest = undefined; });
  return moduleRequest;
};
const chooseModule = async (code: string, restore = false) => {
  const sequence = ++sourceSequence;
  externalFields.value = [];
  const item = modules.value.find(item => item.code === code);
  if (!item) return;
  const detail = await businessDevelopmentApi.module(item.id);
  if (disposed || sequence !== sourceSequence || !detail.form) return;
  const meta = await formDataApi.meta(detail.form.form_key);
  if (disposed || sequence !== sourceSequence || left.value.source.type !== 'module') return;
  externalFields.value = meta.fields.filter(field => field.column_type && !['password', 'repeatable', 'json', 'checkbox', 'transfer', 'subform'].includes(field.type) && !field.control_props?.multiple && !field.control_props?.sensitive && !field.control_props?.writeOnly);
  if (!restore) updateLeft({ source: { type: 'module', module: code }, mapping: { valueField: meta.primaryKey.name, labelField: '', targetField: left.value.mapping.targetField } });
};
watch(() => [left.value.source.type, left.value.source.module], async ([type, code]) => {
  const sequence = ++sourceSequence;
  externalFields.value = [];
  if (type !== 'module' || !code) return;
  await loadModules();
  if (disposed || sequence !== sourceSequence) return;
  await chooseModule(code, true);
}, { immediate: true });
const categoryFields = computed(() => scalarFields.value.filter(field => ['static', 'dictionary'].includes(String(field.options_source?.kind ?? field.options_source?.mode ?? ''))));
const category = (patch: Partial<NonNullable<FormListConfiguration['category']>>) => emit('update', { category: { enabled: false, ...props.modelValue.category, ...patch } });
const tree = (patch: Partial<NonNullable<FormListConfiguration['tree']>>) => emit('update', { tree: { enabled: false, ...props.modelValue.tree, ...patch } });
</script>
