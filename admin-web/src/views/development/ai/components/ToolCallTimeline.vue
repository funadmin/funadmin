<template>
  <el-timeline class="tool-call-timeline">
    <el-timeline-item v-for="call in toolCalls" :key="call.id" :type="statusType(call.status)">
      <div class="tool-call__header"><strong>{{ call.tool_name }}</strong><el-tag :type="riskType(call.risk_level)" size="small">{{ call.risk_level }}</el-tag></div>
      <p>{{ call.operation }} · {{ call.status }}<span v-if="call.duration_ms"> · {{ call.duration_ms }}ms</span></p>
      <small v-if="call.stdout_summary">stdout：{{ call.stdout_summary }}</small>
      <small v-if="call.stderr_summary">stderr：{{ call.stderr_summary }}</small>
      <div><el-button v-if="call.stdout_summary" link size="small" @click="$emit('open-log', call.id, 'stdout')">查看 stdout</el-button><el-button v-if="call.stderr_summary" link size="small" @click="$emit('open-log', call.id, 'stderr')">查看 stderr</el-button></div>
    </el-timeline-item>
  </el-timeline>
</template>

<script setup lang="ts">
import type { TagProps } from 'element-plus';
import type { AiRiskLevel, AiToolCall } from '@/api/development/ai';
defineProps<{ toolCalls: AiToolCall[] }>();
defineEmits<{ 'open-log': [id: number, stream: 'stdout' | 'stderr'] }>();
function riskType(risk: AiRiskLevel): TagProps['type'] { return ({ low: 'success', medium: 'warning', high: 'danger', critical: 'danger' } as const)[risk]; }
function statusType(status: AiToolCall['status']): 'primary' | 'success' | 'warning' | 'danger' { if (status === 'succeeded') return 'success'; if (status === 'failed' || status === 'denied') return 'danger'; if (status === 'awaiting_approval') return 'warning'; return 'primary'; }
</script>

<style scoped>
.tool-call__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
p { margin: 4px 0; color: var(--el-text-color-secondary); } small { display: block; overflow-wrap: anywhere; }
</style>
