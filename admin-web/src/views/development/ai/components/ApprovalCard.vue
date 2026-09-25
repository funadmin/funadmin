<template>
  <el-card class="approval-card" shadow="never">
    <template #header><div class="approval-card__title"><strong><i class="i-ep-warning-filled" />{{ t('aiDevelopment.approval.required') }}</strong><el-tag type="danger" effect="light" round>{{ aiEnumLabel(t, 'operations', approval.operation) }}</el-tag></div></template>
    <el-alert :title="approval.risk_reason || t('aiDevelopment.approval.defaultRisk')" type="warning" show-icon :closable="false" />
    <details class="approval-card__details"><summary>{{ t('aiDevelopment.approval.details') }}</summary><pre>{{ formattedImpact }}</pre></details>
    <el-input v-model="feedback" type="textarea" :rows="2" resize="none" :placeholder="t('aiDevelopment.approval.feedbackPlaceholder')" />
    <div class="approval-card__actions">
      <el-button type="danger" plain @click="decide('reject', 'once')">{{ t('aiDevelopment.approval.reject') }}</el-button>
      <el-button type="primary" plain @click="decide('approve', 'session_operation')">{{ t('aiDevelopment.approval.allowSession') }}</el-button>
      <el-button type="success" @click="decide('approve', 'once')">{{ t('aiDevelopment.approval.allowOnce') }}</el-button>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiApproval, AiApprovalScope } from '@/api/development/ai';
import { aiEnumLabel } from '../i18n';

const props = defineProps<{ approval: AiApproval }>();
const emit = defineEmits<{ decision: [action: 'approve' | 'reject', scope: AiApprovalScope, feedback: string] }>();
const feedback = ref('');
const { t } = useI18n();
const formattedImpact = computed(() => JSON.stringify(props.approval.impact, null, 2));
function decide(action: 'approve' | 'reject', scope: AiApprovalScope) { emit('decision', action, scope, feedback.value); }
</script>

<style scoped>
.approval-card { position: relative; overflow: hidden; border-color: color-mix(in srgb, var(--el-color-warning) 45%, var(--el-border-color-lighter)); border-radius: 12px; }
.approval-card::before { position: absolute; top: 0; bottom: 0; left: 0; width: 3px; background: var(--el-color-warning); content: ''; }
.approval-card :deep(.el-card__header) { padding: 12px 16px; background: color-mix(in srgb, var(--el-color-warning) 7%, var(--el-bg-color)); }
.approval-card :deep(.el-card__body) { display: grid; gap: 12px; padding: 14px 16px 16px; }
.approval-card__title, .approval-card__actions { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
.approval-card__title strong { display: inline-flex; align-items: center; gap: 6px; color: var(--el-text-color-primary); font-size: 14px; }
.approval-card__title i { color: var(--el-color-warning); font-size: 16px; }
.approval-card__details { border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }
.approval-card__details summary { padding: 8px 12px; color: var(--el-text-color-regular); font-size: 13px; cursor: pointer; }
.approval-card__details pre { overflow: auto; max-height: 240px; margin: 0; padding: 10px 12px; border-top: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-lighter); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; white-space: pre-wrap; }
.approval-card__actions { justify-content: flex-end; flex-wrap: wrap; }
.approval-card__actions :deep(.el-button + .el-button) { margin-left: 0; }
</style>
