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
    </el-form>
  </el-card>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import type { FormFieldDef } from '@/api/form';
import type { FormListConfiguration } from '../../schema/types';
const props = defineProps<{ modelValue: FormListConfiguration; fields: FormFieldDef[] }>();
const emit = defineEmits<{ update: [value: FormListConfiguration] }>();
const scalarFields = computed(() => props.fields.filter(field => field.column_type && field.relation_type === 'none' && !['password', 'repeatable', 'subform', 'json', 'checkbox', 'transfer'].includes(field.type) && !field.control_props?.multiple && !field.control_props?.sensitive && !field.control_props?.writeOnly));
const categoryFields = computed(() => scalarFields.value.filter(field => ['static', 'dictionary'].includes(String(field.options_source?.kind ?? field.options_source?.mode ?? ''))));
const category = (patch: Partial<NonNullable<FormListConfiguration['category']>>) => emit('update', { category: { enabled: false, ...props.modelValue.category, ...patch } });
const tree = (patch: Partial<NonNullable<FormListConfiguration['tree']>>) => emit('update', { tree: { enabled: false, ...props.modelValue.tree, ...patch } });
</script>
