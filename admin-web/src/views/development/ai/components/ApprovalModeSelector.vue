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
.approval-mode-selector { display: grid; gap: 8px; }
.mode-boundary, .capability-error { margin: 0; color: var(--el-text-color-secondary); font-size: 12px; }
.capability-error { color: var(--el-color-danger); }
</style>
