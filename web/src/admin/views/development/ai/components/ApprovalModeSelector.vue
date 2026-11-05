<template>
  <div class="approval-mode-selector">
    <el-radio-group :model-value="modelValue" @update:model-value="updateMode">
      <el-radio-button value="request_approval">{{ t('aiDevelopment.approvalModes.request') }}</el-radio-button>
      <el-radio-button value="agent_approval" :disabled="!canAgentApprove">{{ t('aiDevelopment.approvalModes.agent') }}</el-radio-button>
      <el-radio-button value="full_access" :disabled="!canFullAccess">{{ t('aiDevelopment.approvalModes.fullAccess') }}</el-radio-button>
    </el-radio-group>
    <p class="mode-boundary">{{ boundary }}</p>
    <el-alert v-if="modelValue === 'full_access' && !canFullAccess" :title="t('aiDevelopment.approvalModes.missingCapability')" type="error" :closable="false" />
    <p v-else-if="!canFullAccess" class="capability-error">{{ t('aiDevelopment.approvalModes.unavailable') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiApprovalMode } from '@/api/development/ai';

const props = defineProps<{ modelValue: AiApprovalMode; canAgentApprove: boolean; canFullAccess: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [mode: AiApprovalMode] }>();
const { t } = useI18n();
const boundary = computed(() => t({
  request_approval: 'aiDevelopment.approvalModes.requestBoundary',
  agent_approval: 'aiDevelopment.approvalModes.agentBoundary',
  full_access: 'aiDevelopment.approvalModes.fullAccessBoundary'
}[props.modelValue]));
function updateMode(value: string | number | boolean | undefined) { emit('update:modelValue', value as AiApprovalMode); }
</script>

<style scoped>
.approval-mode-selector { display: grid; gap: 10px; min-width: 0; }
.approval-mode-selector :deep(.el-radio-group) { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
.approval-mode-selector :deep(.el-radio-button) { flex: 1 1 auto; }
.approval-mode-selector :deep(.el-radio-button__inner) { width: 100%; padding-inline: 10px; border: 1px solid var(--el-border-color); border-radius: 8px !important; box-shadow: none !important; }
.approval-mode-selector :deep(.el-radio-button.is-active .el-radio-button__inner) { border-color: var(--el-color-primary); }
.mode-boundary, .capability-error { margin: 0; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; }
.mode-boundary { padding: 8px 10px; border-radius: 8px; background: var(--el-fill-color-light); }
.capability-error { color: var(--el-color-danger); }
</style>
