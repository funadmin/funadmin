<template>
  <div class="basics-grid">
    <el-form label-width="125px">
      <el-form-item label="数据源"><el-select :model-value="connection" class="w-full" @update:model-value="$emit('update:connection', $event)"><el-option v-for="item in connections" :key="item.name" :label="item.name" :value="item.name" /></el-select></el-form-item>
      <el-form-item label="数据表"><el-select :model-value="table" filterable class="w-full" @update:model-value="$emit('update:table', $event)"><el-option v-for="item in tables" :key="item.name" :label="item.comment ? `${item.name} — ${item.comment}` : item.name" :value="item.name" /></el-select></el-form-item>
    </el-form>
    <el-skeleton v-if="inferring" :rows="6" animated />
    <template v-else-if="model">
      <el-divider content-position="left">模块定义</el-divider>
      <el-form :model="model" label-width="125px" class="definition-form">
        <el-form-item label="连接"><el-input v-model="model.connection" disabled /></el-form-item>
        <el-form-item label="表名"><el-input v-model="model.table" disabled /></el-form-item>
        <el-form-item label="模块"><el-input v-model="model.module" /></el-form-item>
        <el-form-item label="实体"><el-input v-model="model.entity" /></el-form-item>
        <el-form-item label="标题"><el-input v-model="model.title" /></el-form-item>
        <el-form-item label="API 前缀"><el-input v-model="model.apiPrefix" /></el-form-item>
        <el-form-item label="路由路径"><el-input v-model="model.routePath" /></el-form-item>
        <el-form-item label="权限前缀"><el-input v-model="model.permissionPrefix" /></el-form-item>
        <el-form-item label="主键"><el-select v-model="model.primaryKey" class="w-full"><el-option v-for="field in model.fields" :key="field.name" :label="field.name" :value="field.name" /></el-select></el-form-item>
        <el-form-item label="时间戳"><el-switch v-model="model.timestamps" /></el-form-item>
        <el-form-item label="软删除"><el-switch v-model="model.softDeletes" /></el-form-item>
      </el-form>
      <el-divider content-position="left">表结构摘要</el-divider>
      <div class="summary-cards">
        <el-card shadow="never"><template #header>字段（{{ model.fields.length }}）</template><div class="tag-list"><el-tag v-for="field in model.fields" :key="field.name" size="small" :type="field.primary ? 'success' : 'info'">{{ field.name }} · {{ field.dbType }}</el-tag></div></el-card>
        <el-card shadow="never"><template #header>索引（{{ indexes.length }}）</template><div v-if="indexes.length" class="tag-list"><el-tag v-for="(index, key) in indexes" :key="String(key)" size="small">{{ indexName(index) }}</el-tag></div><el-empty v-else description="未返回索引信息" :image-size="40" /></el-card>
      </div>
      <el-alert :title="laravelHint" :type="laravelReady ? 'success' : 'warning'" show-icon :closable="false" class="mt-3" />
      <el-divider content-position="left">生成目标摘要</el-divider>
      <el-descriptions :column="1" border><el-descriptions-item v-for="(path, key) in model.generationTargets" :key="key" :label="String(key)"><el-input v-model="model.generationTargets[key]" /></el-descriptions-item></el-descriptions>
    </template>
    <el-empty v-else description="选择数据表后将自动推断模块与字段" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { CrudConnection, CrudDefinition, CrudTable } from '@/types/development/crud';

const props = defineProps<{ connection: string; table: string; connections: CrudConnection[]; tables: CrudTable[]; model: CrudDefinition | null; schema: Record<string, unknown> | null; inferring: boolean }>();
defineEmits<{ 'update:connection': [value: string]; 'update:table': [value: string] }>();
const indexes = computed<unknown[]>(() => {
  const value = props.schema?.indexes ?? props.schema?.indices;
  return Array.isArray(value) ? value : [];
});
const indexName = (value: unknown) => {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') {
    const row = value as Record<string, unknown>;
    return String(row.name ?? row.Key_name ?? row.key_name ?? '未命名索引');
  }
  return '未命名索引';
};
const fieldNames = computed(() => new Set(props.model?.fields.map((field) => field.name) || []));
const laravelReady = computed(() => fieldNames.value.has('id') && fieldNames.value.has('created_at') && fieldNames.value.has('updated_at'));
const laravelHint = computed(() => laravelReady.value
  ? '符合 Laravel 常用约定：id 主键及 created_at、updated_at 时间戳已就绪'
  : 'Laravel 规范提示：建议使用 id 主键，并提供 created_at、updated_at；软删除表应提供 deleted_at');
</script>

<style scoped>
.definition-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 18px; }
.summary-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.tag-list { display: flex; flex-wrap: wrap; gap: 8px; }
@media (max-width: 768px) { .definition-form, .summary-cards { grid-template-columns: 1fr; } }
</style>
