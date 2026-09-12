<template>
  <div class="approval-mode-selector">
    <el-radio-group :model-value="modelValue" @update:model-value="updateMode">
      <el-radio-button value="request_approval">请求批准</el-radio-button>
      <el-radio-button value="agent_approval" :disabled="!canAgentApprove">替我审批</el-radio-button>
      <el-radio-button value="full_access" :disabled="!canFullAccess">完全访问权限</el-radio-button>
    </el-radio-group>
    <p class="mode-boundary">{{ boundary }}</p>
    <el-alert v-if="modelValue === 'full_access' && !canFullAccess" title="403：缺少 development:ai:full-access capability" type="error" :closable="false" />
    <p v-else-if="!canFullAccess" class="capability-error">403：完全访问权限不可用</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { AiApprovalMode } from '@/api/development/ai';

const props = defineProps<{ modelValue: AiApprovalMode; canAgentApprove: boolean; canFullAccess: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [mode: AiApprovalMode] }>();
const boundary = computed(() => ({
  request_approval: '危险工具执行前逐次请求批准。',
  agent_approval: '仅在已授权 capability 范围内由代理审批。',
  full_access: '允许高风险操作，但最终应用 ChangeSet 仍须二次确认。'
}[props.modelValue]));
function updateMode(value: string | number | boolean | undefined) { emit('update:modelValue', value as AiApprovalMode); }
</script>

<style scoped>
.approval-mode-selector { display: grid; gap: 8px; }
.mode-boundary, .capability-error { margin: 0; color: var(--el-text-color-secondary); font-size: 12px; }
.capability-error { color: var(--el-color-danger); }
</style>
