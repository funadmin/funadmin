<template>
  <el-alert v-if="fields.some((field) => field.legacy)" title="发现 legacy 时间字段，完成 Laravel 字段迁移前禁止生成" type="error" show-icon class="mb-3" />
  <el-table :data="fields" border max-height="520">
    <el-table-column prop="name" label="字段" width="150" /><el-table-column prop="dbType" label="数据库类型" width="150" />
    <el-table-column v-for="flag in flags" :key="flag" :label="flag" width="76"><template #default="{ row }"><el-checkbox v-model="row[flag]" :disabled="row.managed" /></template></el-table-column>
    <el-table-column label="组件" width="170"><template #default="{ row }"><el-select v-model="row.component" filterable><el-option v-for="item in components" :key="item.value" :value="item.value" :label="item.label" /></el-select></template></el-table-column>
    <el-table-column label="规则" min-width="210"><template #default="{ row }"><el-select v-model="row.rules" multiple allow-create filterable default-first-option placeholder="请选择或输入规则"><el-option v-for="item in validationRules" :key="item.value" :value="item.value" :label="item.label" /></el-select></template></el-table-column>
    <el-table-column label="选项 JSON" min-width="190"><template #default="{ row }"><el-input :model-value="JSON.stringify(row.options || [])" @change="setOptions(row as CrudField, $event)" /></template></el-table-column>
    <el-table-column label="搜索操作符" width="140"><template #default="{ row }"><el-select v-model="row.searchOperator" clearable><el-option v-for="item in operators" :key="item" :value="item" :label="item" /></el-select></template></el-table-column>
    <el-table-column label="关系" width="140"><template #default="{ row }"><el-input v-model="row.relation" placeholder="owner" /></template></el-table-column>
    <el-table-column label="references" min-width="190"><template #default="{ row }"><el-input v-model="row.references" placeholder="Owner.id" /></template></el-table-column>
  </el-table>
</template>
<script setup lang="ts">
import { ElMessage } from 'element-plus';
import type { CrudField, CrudOption } from '@/types/development/crud';
defineProps<{ fields: CrudField[] }>();
const flags = ['list', 'search', 'form', 'detail'] as const;
const components = [
  { value: 'input', label: '单行输入' },
  { value: 'password', label: '密码输入' },
  { value: 'textarea', label: '多行文本' },
  { value: 'inputNumber', label: '数字输入' },
  { value: 'select', label: '下拉选择' },
  { value: 'radio', label: '单选框' },
  { value: 'checkbox', label: '复选框' },
  { value: 'switch', label: '开关' },
  { value: 'datetime', label: '日期时间' },
  { value: 'date', label: '日期' },
  { value: 'time', label: '时间' },
  { value: 'image', label: '单图上传' },
  { value: 'images', label: '多图上传' },
  { value: 'file', label: '单文件上传' },
  { value: 'files', label: '多文件上传' },
  { value: 'dictionary', label: '字典选择' },
  { value: 'json', label: 'JSON 编辑' }
];
const validationRules = [
  { value: 'require', label: '必填 require' },
  { value: 'integer', label: '整数 integer' },
  { value: 'number', label: '数字 number' },
  { value: 'float', label: '浮点数 float' },
  { value: 'boolean', label: '布尔值 boolean' },
  { value: 'email', label: '邮箱 email' },
  { value: 'url', label: '网址 url' },
  { value: 'date', label: '日期 date' },
  { value: 'alpha', label: '纯字母 alpha' },
  { value: 'alphaNum', label: '字母数字 alphaNum' },
  { value: 'max:', label: '最大值/长度 max:' },
  { value: 'min:', label: '最小值/长度 min:' },
  { value: 'length:', label: '固定长度 length:' },
  { value: 'regex:', label: '正则 regex:' }
];
const operators = ['like', 'eq', 'in', 'range', 'gte', 'lte'];
function setOptions(field: CrudField, value: string) { try { field.options = JSON.parse(value) as CrudOption[]; } catch { ElMessage.error('选项必须是 JSON 数组'); } }
</script>
