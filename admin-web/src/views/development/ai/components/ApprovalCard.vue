<template>
  <el-card class="approval-card" shadow="never">
    <template #header><div class="approval-card__title"><strong>{{ t('aiDevelopment.approval.required') }}</strong><el-tag type="danger">{{ aiEnumLabel(t, 'operations', approval.operation) }}</el-tag></div></template>
    <el-alert :title="approval.risk_reason || t('aiDevelopment.approval.defaultRisk')" type="warning" show-icon :closable="false" />
    <details><summary>{{ t('aiDevelopment.approval.details') }}</summary><pre>{{ formattedImpact }}</pre></details>
    <el-input v-model="feedback" type="textarea" :rows="2" :placeholder="t('aiDevelopment.approval.feedbackPlaceholder')" />
    <div class="approval-card__actions">
      <el-button type="success" @click="decide('approve', 'once')">{{ t('aiDevelopment.approval.allowOnce') }}</el-button>
      <el-button type="primary" @click="decide('approve', 'session_operation')">{{ t('aiDevelopment.approval.allowSession') }}</el-button>
      <el-button type="danger" plain @click="decide('reject', 'once')">{{ t('aiDevelopment.approval.reject') }}</el-button>
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
.approval-card { border-color: var(--el-color-warning-light-5); }
.approval-card__title, .approval-card__actions { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
details { margin: 12px 0; } details pre { overflow: auto; white-space: pre-wrap; }
.approval-card__actions { justify-content: flex-end; margin-top: 12px; flex-wrap: wrap; }
</style>
