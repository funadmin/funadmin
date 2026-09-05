<template><el-alert title="确认 token 仅保存在当前页面内存中，离开或失败后立即清除" type="warning" show-icon class="mb-3" /><el-checkbox-group :model-value="allowOverwrite" @update:model-value="update"><div v-for="path in conflicts" :key="path" class="mb-2"><el-checkbox :value="path" :disabled="!canOverwrite">覆盖 {{ path }}</el-checkbox></div></el-checkbox-group><el-alert v-if="conflicts.length && !canOverwrite" title="存在冲突，但当前账号没有 overwrite 权限" type="error" show-icon /></template>
<script setup lang="ts">
import type { CheckboxGroupValueType } from 'element-plus';
defineProps<{ conflicts: string[]; allowOverwrite: string[]; canOverwrite: boolean }>();
const emit = defineEmits<{ 'update:allowOverwrite': [value: string[]] }>();
function update(value: CheckboxGroupValueType) { emit('update:allowOverwrite', value.map(String)); }
</script>
