<template>
  <el-alert v-if="definition.fields.some((field) => field.legacy)" title="发现 legacy 时间字段，完成 Laravel 字段迁移前禁止生成" type="error" show-icon class="mb-3" />
  <el-table :data="definition.fields" border max-height="620" table-layout="auto">
    <el-table-column prop="name" label="字段" min-width="140" fixed />
    <el-table-column label="名称" min-width="150"><template #default="{ row }">{{ row.label || row.comment || row.name }}</template></el-table-column>
    <el-table-column prop="dbType" label="数据库类型" min-width="150" />
    <el-table-column v-for="flag in flags" :key="flag.value" :label="flag.label" width="72" align="center">
      <template #default="{ row }"><el-checkbox v-model="row[flag.value]" :disabled="row.managed && flag.value === 'form'" /></template>
    </el-table-column>
    <el-table-column label="组件" min-width="150"><template #default="{ row }">{{ componentLabel(row.component) }}</template></el-table-column>
    <el-table-column label="校验摘要" min-width="190"><template #default="{ row }"><el-tag v-if="row.required" size="small" type="danger">必填</el-tag><span class="ml-1">{{ ruleSummary(row as CrudField) }}</span></template></el-table-column>
    <el-table-column label="操作" width="90" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="edit(row as CrudField)">配置</el-button></template></el-table-column>
  </el-table>
  <FieldEditorDrawer v-model="drawerVisible" :definition="definition" :field-name="activeFieldName" />
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { CrudDefinition, CrudField } from '@/types/development/crud';
import { CRUD_FIELD_COMPONENTS } from '../fieldOptions';
import FieldEditorDrawer from './FieldEditorDrawer.vue';

defineProps<{ definition: CrudDefinition }>();
const flags = [
  { value: 'list', label: '列表' }, { value: 'search', label: '搜索' },
  { value: 'form', label: '表单' }, { value: 'detail', label: '详情' }
] as const;
const drawerVisible = ref(false);
const activeFieldName = ref('');
const edit = (field: CrudField) => {
  activeFieldName.value = field.name;
  drawerVisible.value = true;
};
const componentLabel = (value?: string) => CRUD_FIELD_COMPONENTS.find((item) => item.value === value)?.label || value || '未设置';
const ruleSummary = (field: CrudField) => {
  const rules = [...(field.rules || [])];
  if (field.unique) rules.push('unique');
  if (field.minLength !== undefined) rules.push(`minLength:${field.minLength}`);
  if (field.maxLength !== undefined) rules.push(`maxLength:${field.maxLength}`);
  return rules.join('、') || '无';
};
</script>
