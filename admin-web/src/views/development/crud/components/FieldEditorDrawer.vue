<template>
  <el-drawer :model-value="modelValue" :title="field ? `配置字段：${field.name}` : '配置字段'" size="min(720px, 96vw)" destroy-on-close @update:model-value="$emit('update:modelValue', $event)">
    <el-tabs v-if="field" v-model="activeTab">
      <el-tab-pane label="基础" name="basic">
        <el-form :model="field" label-width="110px">
          <el-form-item label="字段名"><el-input v-model="field.name" disabled /></el-form-item>
          <el-form-item label="显示名称"><el-input v-model="field.label" /></el-form-item>
          <el-form-item label="数据库类型"><el-input v-model="field.dbType" disabled /></el-form-item>
          <el-form-item label="值类型"><el-input v-model="field.valueType" /></el-form-item>
          <el-form-item label="类型转换"><el-input v-model="field.cast" /></el-form-item>
          <el-form-item label="组件"><el-select v-model="field.component" filterable class="w-full"><el-option v-for="item in CRUD_FIELD_COMPONENTS" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane label="列表" name="list">
        <el-form :model="field" label-width="110px">
          <el-form-item label="列表展示"><el-switch v-model="field.list" /></el-form-item>
          <el-form-item label="允许排序"><el-switch v-model="field.sortable" /></el-form-item>
          <el-form-item label="详情展示"><el-switch v-model="field.detail" /></el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane label="搜索" name="search">
        <el-form :model="field" label-width="110px">
          <el-form-item label="启用搜索"><el-switch v-model="field.search" /></el-form-item>
          <el-form-item label="搜索操作符"><el-select v-model="field.searchOperator" clearable class="w-full"><el-option v-for="item in CRUD_SEARCH_OPERATORS" :key="item" :label="item" :value="item" /></el-select></el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane label="表单" name="form">
        <el-form :model="field" label-width="110px">
          <el-form-item label="表单展示"><el-switch v-model="field.form" :disabled="field.managed" /></el-form-item>
          <el-form-item label="可写"><el-switch v-model="field.writable" :disabled="field.managed" /></el-form-item>
          <el-form-item label="允许空值"><el-switch v-model="field.nullable" disabled /></el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane label="校验" name="validation">
        <el-form :model="field" label-width="110px">
          <el-form-item label="规则"><el-select v-model="field.rules" multiple allow-create filterable default-first-option class="w-full" placeholder="请选择或输入规则"><el-option v-for="item in CRUD_VALIDATION_RULES" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
          <el-form-item label="必填"><el-switch v-model="field.required" /></el-form-item>
          <el-form-item label="唯一"><el-switch v-model="field.unique" /></el-form-item>
          <el-form-item label="最小长度"><el-input-number v-model="field.minLength" :min="0" /></el-form-item>
          <el-form-item label="最大长度"><el-input-number v-model="field.maxLength" :min="0" /></el-form-item>
          <el-form-item label="最小值"><el-input-number v-model="field.min" /></el-form-item>
          <el-form-item label="最大值"><el-input-number v-model="field.max" /></el-form-item>
          <el-form-item label="格式"><el-select v-model="field.format" clearable class="w-full"><el-option v-for="item in formats" :key="item" :label="item" :value="item" /></el-select></el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane label="数据来源" name="source">
        <el-alert v-if="!supportsDataSource" title="当前组件不需要配置数据来源" type="info" show-icon :closable="false" />
        <el-form v-else :model="sourceForm" label-width="130px">
          <el-form-item label="来源类型"><el-select v-model="sourceForm.kind" class="w-full"><el-option label="无" value="none" /><el-option label="静态选项" value="static" /><el-option label="字典" value="dictionary" /><el-option label="接口" value="endpoint" /><el-option label="模型关系" value="model" /></el-select></el-form-item>
          <el-form-item v-if="sourceForm.kind === 'static'" label="静态 options"><el-input v-model="sourceForm.optionsText" type="textarea" :rows="6" placeholder='[{"label":"启用","value":1}]' /></el-form-item>
          <template v-if="sourceForm.kind !== 'none' && sourceForm.kind !== 'static'">
            <el-form-item label="数据源名称"><el-input v-model="sourceForm.sourceName" /></el-form-item>
            <el-form-item label="标签字段"><el-input v-model="sourceForm.labelField" /></el-form-item>
            <el-form-item label="值字段"><el-input v-model="sourceForm.valueField" /></el-form-item>
            <el-form-item v-if="sourceForm.kind === 'dictionary'" label="字典标识"><el-input v-model="sourceForm.dictionary" /></el-form-item>
            <el-form-item v-if="sourceForm.kind === 'endpoint'" label="接口路径"><el-input v-model="sourceForm.endpoint" placeholder="/common/options" /></el-form-item>
          </template>
          <template v-if="sourceForm.kind === 'model'">
            <el-form-item label="关系名称"><el-input v-model="sourceForm.relationName" /></el-form-item>
            <el-form-item label="关系类型"><el-select v-model="sourceForm.relationType" class="w-full"><el-option v-for="type in relationTypes" :key="type" :label="type" :value="type" /></el-select></el-form-item>
            <el-form-item label="目标模型"><el-input v-model="sourceForm.target" placeholder="Admin" /></el-form-item>
            <el-form-item label="目标字段"><el-input v-model="sourceForm.targetField" placeholder="id" /></el-form-item>
            <el-form-item v-if="sourceForm.relationType === 'belongsToMany'" label="中间表"><el-input v-model="sourceForm.pivotTable" /></el-form-item>
            <el-form-item v-if="sourceForm.relationType === 'belongsToMany'" label="本地中间键"><el-input v-model="sourceForm.pivotLocalKey" /></el-form-item>
            <el-form-item v-if="sourceForm.relationType === 'belongsToMany'" label="目标中间键"><el-input v-model="sourceForm.pivotTargetKey" /></el-form-item>
            <el-form-item label="预加载"><el-switch v-model="sourceForm.with" /></el-form-item>
          </template>
          <el-form-item label="枚举 enum"><el-input :model-value="enumText" placeholder='["draft","published"]' @change="setEnum" /></el-form-item>
        </el-form>
      </el-tab-pane>
    </el-tabs>
    <template #footer><el-button @click="$emit('update:modelValue', false)">取消</el-button><el-button type="primary" @click="save">保存</el-button></template>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import type { CrudDefinition, CrudField, CrudOption, CrudOptionsSource, CrudRelation } from '@/types/development/crud';
import { applyFieldDataConfiguration } from '../workbench';
import { CRUD_FIELD_COMPONENTS, CRUD_SEARCH_OPERATORS, CRUD_VALIDATION_RULES } from '../fieldOptions';

const props = defineProps<{ modelValue: boolean; definition: CrudDefinition; fieldName: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const activeTab = ref('basic');
const formats = ['email', 'url', 'date', 'datetime', 'uuid', 'ip'] as const;
const relationTypes: CrudRelation['type'][] = ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany'];
const sourceComponents = ['select', 'radio', 'checkbox', 'switch', 'dictionary'];
const field = computed<CrudField | null>(() => props.definition.fields.find((item) => item.name === props.fieldName) || null);
const supportsDataSource = computed(() => sourceComponents.includes(field.value?.component || ''));
const enumText = computed(() => JSON.stringify(field.value?.enum || []));
const sourceForm = reactive({ kind: 'none' as 'none' | 'static' | 'dictionary' | 'endpoint' | 'model', optionsText: '[]', sourceName: '', labelField: '', valueField: '', dictionary: '', endpoint: '', relationName: '', relationType: 'belongsTo' as CrudRelation['type'], target: '', targetField: '', pivotTable: '', pivotLocalKey: '', pivotTargetKey: '', with: false });

const loadSource = () => {
  const current = field.value;
  if (!current) return;
  Object.assign(sourceForm, { kind: 'none', optionsText: JSON.stringify(current.options || [], null, 2), sourceName: '', labelField: '', valueField: '', dictionary: '', endpoint: '', relationName: '', relationType: 'belongsTo', target: '', targetField: '', pivotTable: '', pivotLocalKey: '', pivotTargetKey: '', with: false });
  if (current.options?.length) sourceForm.kind = 'static';
  const source = props.definition.optionsSource.find((item) => item.name === current.optionsSource);
  const relation = props.definition.relations.find((item) => item.name === current.relation && item.field === current.name);
  if (source) Object.assign(sourceForm, { kind: source.type === 'relation' ? 'model' : source.type, sourceName: source.name, labelField: source.labelField, valueField: source.valueField, dictionary: source.dictionary || '', endpoint: source.endpoint || '' });
  if (relation) Object.assign(sourceForm, { relationName: relation.name, relationType: relation.type, target: relation.target, targetField: relation.targetField, pivotTable: relation.pivotTable || '', pivotLocalKey: relation.pivotLocalKey || '', pivotTargetKey: relation.pivotTargetKey || '', with: relation.with || false });
};
watch(() => [props.modelValue, props.fieldName], loadSource, { immediate: true });

const parseArray = <T>(value: string, label: string): T[] | null => {
  try {
    const parsed = JSON.parse(value) as unknown;
    if (!Array.isArray(parsed)) throw new Error();
    return parsed as T[];
  } catch {
    ElMessage.error(`${label}必须是 JSON 数组`);
    return null;
  }
};
const setEnum = (value: string) => {
  const parsed = parseArray<string | number>(value, 'enum');
  if (field.value && parsed) field.value.enum = parsed;
};
const required = (values: string[]) => values.every((value) => value.trim() !== '');
const save = () => {
  if (!field.value) return;
  try {
    if (!saveFieldDataConfiguration()) return;
  } catch (error) {
    const message = error instanceof Error ? error.message : '字段数据来源保存失败';
    ElMessage.error(message);
    return;
  }
  emit('update:modelValue', false);
};
const saveFieldDataConfiguration = (): boolean => {
  if (!field.value) return false;
  if (sourceForm.kind === 'none') applyFieldDataConfiguration(props.definition, field.value.name, { kind: 'none' });
  if (sourceForm.kind === 'static') {
    const options = parseArray<CrudOption>(sourceForm.optionsText, '静态 options');
    if (!options) return false;
    applyFieldDataConfiguration(props.definition, field.value.name, { kind: 'static', options });
  }
  if (sourceForm.kind === 'dictionary' || sourceForm.kind === 'endpoint') {
    if (!required([sourceForm.sourceName, sourceForm.labelField, sourceForm.valueField, sourceForm.kind === 'dictionary' ? sourceForm.dictionary : sourceForm.endpoint])) {
      ElMessage.error('请填写当前数据源的全部必填项');
      return false;
    }
    const source: CrudOptionsSource = { name: sourceForm.sourceName, type: sourceForm.kind, labelField: sourceForm.labelField, valueField: sourceForm.valueField };
    if (sourceForm.kind === 'dictionary') source.dictionary = sourceForm.dictionary;
    else source.endpoint = sourceForm.endpoint;
    applyFieldDataConfiguration(props.definition, field.value.name, { kind: sourceForm.kind, source });
  }
  if (sourceForm.kind === 'model') {
    const pivot = sourceForm.relationType !== 'belongsToMany' || required([sourceForm.pivotTable, sourceForm.pivotLocalKey, sourceForm.pivotTargetKey]);
    if (!required([sourceForm.sourceName, sourceForm.labelField, sourceForm.valueField, sourceForm.relationName, sourceForm.target, sourceForm.targetField]) || !pivot) {
      ElMessage.error('请填写模型关系的全部必填项');
      return false;
    }
    const source: CrudOptionsSource = { name: sourceForm.sourceName, type: 'relation', labelField: sourceForm.labelField, valueField: sourceForm.valueField };
    const relation: Omit<CrudRelation, 'field' | 'optionsSource'> = { name: sourceForm.relationName, type: sourceForm.relationType, target: sourceForm.target, targetField: sourceForm.targetField, with: sourceForm.with };
    if (sourceForm.relationType === 'belongsToMany') Object.assign(relation, { pivotTable: sourceForm.pivotTable, pivotLocalKey: sourceForm.pivotLocalKey, pivotTargetKey: sourceForm.pivotTargetKey });
    applyFieldDataConfiguration(props.definition, field.value.name, { kind: 'model', source, relation });
  }
  return true;
};
</script>
