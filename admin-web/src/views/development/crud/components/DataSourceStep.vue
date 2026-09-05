<template>
  <el-form label-width="100px">
    <el-form-item label="数据源"><el-select :model-value="connection" class="w-full" @update:model-value="$emit('update:connection', $event)"><el-option v-for="item in connections" :key="item.name" :label="item.name" :value="item.name" /></el-select></el-form-item>
    <el-form-item label="数据表"><el-select :model-value="table" filterable class="w-full" @update:model-value="$emit('update:table', $event)"><el-option v-for="item in tables" :key="item.name" :label="item.comment ? `${item.name} — ${item.comment}` : item.name" :value="item.name" /></el-select></el-form-item>
  </el-form>
</template>
<script setup lang="ts">
import type { CrudConnection, CrudTable } from '@/types/development/crud';
defineProps<{ connection: string; table: string; connections: CrudConnection[]; tables: CrudTable[] }>();
defineEmits<{ 'update:connection': [value: string]; 'update:table': [value: string] }>();
</script>
