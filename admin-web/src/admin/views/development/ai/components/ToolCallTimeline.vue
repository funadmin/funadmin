<template>
  <el-timeline class="tool-call-timeline">
    <el-timeline-item v-for="call in toolCalls" :key="call.id" :type="statusType(call.status)" :hollow="call.status === 'awaiting_approval' || call.status === 'running'">
      <div class="tool-call">
        <div class="tool-call__header"><strong>{{ call.tool_name }}</strong><el-tag :type="riskType(call.risk_level)" size="small" effect="light" round>{{ aiEnumLabel(t, 'riskLevels', call.risk_level) }}</el-tag></div>
        <p class="tool-call__meta"><span>{{ aiEnumLabel(t, 'operations', call.operation) }}</span><span class="tool-call__status" :class="`tool-call__status--${statusType(call.status)}`">{{ aiEnumLabel(t, 'statuses', call.status) }}</span><span v-if="call.duration_ms">{{ call.duration_ms }}ms</span></p>
        <small v-if="call.stdout_summary" class="tool-call__output">{{ t('aiDevelopment.toolCalls.stdout') }}：{{ call.stdout_summary }}</small>
        <small v-if="call.stderr_summary" class="tool-call__output tool-call__output--error">{{ t('aiDevelopment.toolCalls.stderr') }}：{{ call.stderr_summary }}</small>
        <div v-if="call.stdout_summary || call.stderr_summary" class="tool-call__actions"><el-button v-if="call.stdout_summary" link size="small" type="primary" @click="$emit('open-log', call.id, 'stdout')"><i class="i-ep-document" />{{ t('aiDevelopment.toolCalls.viewStdout') }}</el-button><el-button v-if="call.stderr_summary" link size="small" type="danger" @click="$emit('open-log', call.id, 'stderr')"><i class="i-ep-warning" />{{ t('aiDevelopment.toolCalls.viewStderr') }}</el-button></div>
      </div>
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
.tool-call-timeline { margin: 0; padding: 0 0 0 2px; }
.tool-call-timeline :deep(.el-timeline-item) { padding-bottom: 14px; }
.tool-call-timeline :deep(.el-timeline-item:last-child) { padding-bottom: 0; }
.tool-call-timeline :deep(.el-timeline-item__wrapper) { top: -3px; padding-left: 22px; }
.tool-call { display: grid; gap: 6px; min-width: 0; padding: 10px 12px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: var(--el-bg-color); }
.tool-call__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-width: 0; }
.tool-call__header strong { min-width: 0; overflow: hidden; color: var(--el-text-color-primary); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.tool-call__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; margin: 0; color: var(--el-text-color-secondary); font-size: 12px; }
.tool-call__meta > span + span::before { margin-right: 10px; color: var(--el-border-color); content: '·'; }
.tool-call__status--success { color: var(--el-color-success); }
.tool-call__status--danger { color: var(--el-color-danger); }
.tool-call__status--warning { color: var(--el-color-warning); }
.tool-call__output { display: block; padding: 6px 8px; border-radius: 6px; background: var(--el-fill-color-light); color: var(--el-text-color-regular); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
.tool-call__output--error { background: color-mix(in srgb, var(--el-color-danger) 8%, var(--el-bg-color)); color: var(--el-color-danger); }
.tool-call__actions { display: flex; flex-wrap: wrap; gap: 12px; }
.tool-call__actions :deep(.el-button + .el-button) { margin-left: 0; }
.tool-call__actions :deep(.el-button i) { margin-right: 4px; }
</style>
