<template>
  <el-timeline class="tool-call-timeline">
    <el-timeline-item v-for="call in toolCalls" :key="call.id" :type="statusType(call.status)">
      <div class="tool-call__header"><strong>{{ call.tool_name }}</strong><el-tag :type="riskType(call.risk_level)" size="small">{{ aiEnumLabel(t, 'riskLevels', call.risk_level) }}</el-tag></div>
      <p>{{ aiEnumLabel(t, 'operations', call.operation) }} · {{ aiEnumLabel(t, 'statuses', call.status) }}<span v-if="call.duration_ms"> · {{ call.duration_ms }}ms</span></p>
      <small v-if="call.stdout_summary">{{ t('aiDevelopment.toolCalls.stdout') }}：{{ call.stdout_summary }}</small>
      <small v-if="call.stderr_summary">{{ t('aiDevelopment.toolCalls.stderr') }}：{{ call.stderr_summary }}</small>
      <div><el-button v-if="call.stdout_summary" link size="small" @click="$emit('open-log', call.id, 'stdout')">{{ t('aiDevelopment.toolCalls.viewStdout') }}</el-button><el-button v-if="call.stderr_summary" link size="small" @click="$emit('open-log', call.id, 'stderr')">{{ t('aiDevelopment.toolCalls.viewStderr') }}</el-button></div>
    </el-timeline-item>
  </el-timeline>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { TagProps } from 'element-plus';
import type { AiRiskLevel, AiToolCall } from '@/api/development/ai';
import { aiEnumLabel } from '../i18n';
defineProps<{ toolCalls: AiToolCall[] }>();
defineEmits<{ 'open-log': [id: number, stream: 'stdout' | 'stderr'] }>();
const { t } = useI18n();
function riskType(risk: AiRiskLevel): TagProps['type'] { return ({ low: 'success', medium: 'warning', high: 'danger', critical: 'danger' } as Partial<Record<AiRiskLevel, TagProps['type']>>)[risk] || 'info'; }
function statusType(status: AiToolCall['status']): 'primary' | 'success' | 'warning' | 'danger' { if (status === 'succeeded') return 'success'; if (status === 'failed' || status === 'denied') return 'danger'; if (status === 'awaiting_approval') return 'warning'; return 'primary'; }
</script>

<style scoped>
.tool-call__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
p { margin: 4px 0; color: var(--el-text-color-secondary); } small { display: block; overflow-wrap: anywhere; }
</style>
