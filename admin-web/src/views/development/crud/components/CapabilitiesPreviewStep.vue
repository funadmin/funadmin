<template>
  <el-form label-width="130px" class="capability-form">
    <el-form-item label="CRUD 能力"><el-checkbox v-for="key in keys" :key="key" v-model="model.capabilities[key]" :label="key">{{ key }}</el-checkbox></el-form-item>
    <el-form-item label="功能开关"><el-checkbox v-for="item in featureKeys" :key="item.key" v-model="model.features[item.key]" :label="item.key">{{ item.label }}</el-checkbox></el-form-item>
    <el-form-item label="启用数据范围"><el-switch v-model="model.dataScope.enabled" /></el-form-item>
    <template v-if="model.dataScope.enabled">
      <el-form-item label="范围字段"><el-select v-model="model.dataScope.field" filterable class="w-full"><el-option v-for="field in scopeFields" :key="field.name" :label="field.label || field.comment || field.name" :value="field.name" /></el-select></el-form-item>
      <el-form-item label="范围解析器"><el-select v-model="model.dataScope.resolver" class="w-full"><el-option label="管理员部门范围" value="adminDepartmentIds" /></el-select></el-form-item>
    </template>
    <el-form-item label="表单容器"><el-radio-group v-model="model.features.formMode"><el-radio-button value="dialog">Dialog</el-radio-button><el-radio-button value="drawer">Drawer</el-radio-button></el-radio-group></el-form-item>
    <el-form-item label="导入/导出上限"><el-input-number v-model="model.features.importLimit" :min="1" :max="10000" /><el-input-number v-model="model.features.exportLimit" :min="1" :max="10000" class="ml-2" /></el-form-item>
  </el-form>
  <div class="preview-actions"><el-button type="primary" :loading="loading" @click="$emit('refresh')">校验并刷新预览</el-button><span class="hint">保存当前能力配置后生成新的安全确认 token</span></div>
  <el-alert v-if="invalidated" title="Definition 已变化，原预览与确认授权已失效，请重新校验并刷新预览" type="warning" show-icon :closable="false" class="mb-3" />
  <PreviewStep :preview="preview" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { CrudDefinition, CrudPreview } from '@/types/development/crud';
import PreviewStep from './PreviewStep.vue';

const props = defineProps<{ model: CrudDefinition; preview: CrudPreview | null; loading: boolean; invalidated: boolean }>();
defineEmits<{ refresh: [] }>();
const scopeFields = computed(() => props.model.fields.filter((field) => !field.managed));
const keys = ['list', 'search', 'form', 'detail', 'create', 'update', 'delete', 'import', 'export'] as const;
const featureKeys = [
  { key: 'batchDelete', label: '批量删除' }, { key: 'status', label: '状态切换' },
  { key: 'detail', label: '详情' }, { key: 'import', label: '导入' },
  { key: 'export', label: '导出' }, { key: 'upload', label: '上传' },
  { key: 'dictionary', label: '字典' }, { key: 'referenceProtection', label: '引用保护' }
] as const;
</script>

<style scoped>
.preview-actions { display: flex; align-items: center; gap: 12px; margin: 16px 0; }
.hint { color: var(--el-text-color-secondary); font-size: 13px; }
@media (max-width: 640px) { .preview-actions { align-items: flex-start; flex-direction: column; } }
</style>
