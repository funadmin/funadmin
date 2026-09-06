<template>
  <el-form label-width="130px">
    <el-form-item label="CRUD 能力">
      <el-checkbox v-for="key in keys" :key="key" v-model="model.capabilities[key]" :label="key">{{ key }}</el-checkbox>
    </el-form-item>
    <el-form-item label="启用数据范围"><el-switch v-model="model.dataScope.enabled" /></el-form-item>
    <template v-if="model.dataScope.enabled">
      <el-form-item label="范围字段"><el-select v-model="model.dataScope.field" filterable><el-option v-for="field in scopeFields" :key="field.name" :label="field.label || field.comment || field.name" :value="field.name" /></el-select></el-form-item>
      <el-form-item label="范围解析器"><el-select v-model="model.dataScope.resolver"><el-option label="管理员部门范围" value="adminDepartmentIds" /></el-select></el-form-item>
    </template>
    <el-form-item label="表单容器"><el-radio-group v-model="model.features.formMode"><el-radio-button value="dialog">Dialog</el-radio-button><el-radio-button value="drawer">Drawer</el-radio-button></el-radio-group></el-form-item>
    <el-form-item label="导入/导出上限"><el-input-number v-model="model.features.importLimit" :min="1" :max="10000" /><el-input-number v-model="model.features.exportLimit" :min="1" :max="10000" class="ml-2" /></el-form-item>
  </el-form>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import type { CrudDefinition } from '@/types/development/crud';

const props = defineProps<{ model: CrudDefinition }>();
const scopeFields = computed(() => props.model.fields.filter((field) => !field.managed));
const keys = ['list', 'search', 'form', 'detail', 'create', 'update', 'delete', 'import', 'export'] as const;
</script>
