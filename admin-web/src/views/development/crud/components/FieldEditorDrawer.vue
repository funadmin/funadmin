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
          <el-form-item label="规则">
            <el-select v-model="field.rules" multiple allow-create filterable default-first-option class="w-full" placeholder="请选择或输入规则">
              <el-option v-for="item in CRUD_VALIDATION_RULES" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
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
        <el-form v-else :model="field" label-width="120px">
          <el-form-item label="静态 options"><el-input :model-value="optionsText" type="textarea" :rows="6" placeholder='[{"label":"启用","value":1}]' @change="setOptions" /></el-form-item>
          <el-form-item label="optionsSource"><el-input v-model="field.optionsSource" placeholder="定义中的数据源名称" /></el-form-item>
          <el-form-item v-if="supportsRelation" label="relation"><el-input v-model="field.relation" placeholder="owner" /></el-form-item>
          <el-form-item v-if="supportsRelation" label="references"><el-input v-model="field.references" placeholder="Owner.id" /></el-form-item>
          <el-form-item label="枚举 enum"><el-input :model-value="enumText" placeholder='["draft","published"]' @change="setEnum" /></el-form-item>
        </el-form>
      </el-tab-pane>
    </el-tabs>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { ElMessage } from 'element-plus';
import type { CrudField, CrudOption } from '@/types/development/crud';
import { CRUD_FIELD_COMPONENTS, CRUD_SEARCH_OPERATORS, CRUD_VALIDATION_RULES } from '../fieldOptions';

const props = defineProps<{ modelValue: boolean; field: CrudField | null }>();
defineEmits<{ 'update:modelValue': [value: boolean] }>();
const activeTab = ref('basic');

const formats = ['email', 'url', 'date', 'datetime', 'uuid', 'ip'] as const;
const sourceComponents = ['select', 'radio', 'checkbox', 'switch', 'dictionary'];
const supportsDataSource = computed(() => sourceComponents.includes(props.field?.component || ''));
const supportsRelation = computed(() => ['select', 'radio', 'checkbox', 'dictionary'].includes(props.field?.component || ''));
const optionsText = computed(() => JSON.stringify(props.field?.options || [], null, 2));
const enumText = computed(() => JSON.stringify(props.field?.enum || []));

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
const setOptions = (value: string) => {
  const parsed = parseArray<CrudOption>(value, '静态 options');
  if (props.field && parsed) props.field.options = parsed;
};
const setEnum = (value: string) => {
  const parsed = parseArray<string | number>(value, 'enum');
  if (props.field && parsed) props.field.enum = parsed;
};
</script>
