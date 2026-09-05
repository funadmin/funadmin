<template>
  <el-alert v-if="fields.some((field) => field.legacy)" title="发现 legacy 时间字段，完成 Laravel 字段迁移前禁止生成" type="error" show-icon class="mb-3" />
  <el-table :data="fields" border max-height="520">
    <el-table-column prop="name" label="字段" width="150" /><el-table-column prop="dbType" label="数据库类型" width="150" />
    <el-table-column v-for="flag in flags" :key="flag" :label="flag" width="76"><template #default="{ row }"><el-checkbox v-model="row[flag]" :disabled="row.managed" /></template></el-table-column>
    <el-table-column label="组件" width="150"><template #default="{ row }"><el-select v-model="row.component"><el-option v-for="item in components" :key="item" :value="item" :label="item" /></el-select></template></el-table-column>
    <el-table-column label="规则" min-width="170"><template #default="{ row }"><el-select v-model="row.rules" multiple allow-create filterable /></template></el-table-column>
    <el-table-column label="选项 JSON" min-width="190"><template #default="{ row }"><el-input :model-value="JSON.stringify(row.options || [])" @change="setOptions(row as CrudField, $event)" /></template></el-table-column>
    <el-table-column label="关系" width="140"><template #default="{ row }"><el-input v-model="row.relation" /></template></el-table-column>
    <el-table-column label="references" min-width="190"><template #default="{ row }"><el-input v-model="row.references" placeholder="table.id" /></template></el-table-column>
  </el-table>
</template>
<script setup lang="ts">
import { ElMessage } from 'element-plus';
import type { CrudField, CrudOption } from '@/types/development/crud';
defineProps<{ fields: CrudField[] }>();
const flags = ['list', 'search', 'form', 'detail'] as const;
const components = ['input', 'textarea', 'inputNumber', 'select', 'radio', 'switch', 'datetime'];
function setOptions(field: CrudField, value: string) { try { field.options = JSON.parse(value) as CrudOption[]; } catch { ElMessage.error('选项必须是 JSON 数组'); } }
</script>
