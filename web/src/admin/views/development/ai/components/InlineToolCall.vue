<template>
  <div class="inline-tool" :class="`inline-tool--${tone}`" :data-tool-call="call.id">
    <button type="button" class="inline-tool__toggle" :aria-expanded="open" @click="open = !open">
      <span class="inline-tool__status" aria-hidden="true"><i :class="icon" /></span>
      <span class="inline-tool__name">{{ call.tool_name }}<em>{{ aiEnumLabel(t, 'operations', call.operation) }} · {{ aiEnumLabel(t, 'statuses', call.status) }}</em></span>
      <span v-if="call.duration_ms" class="inline-tool__ms">{{ call.duration_ms }}ms</span>
      <i class="inline-tool__caret i-ep-arrow-right" aria-hidden="true" />
    </button>
    <div v-if="open" class="inline-tool__body">
      <small v-if="call.stdout_summary" class="inline-tool__output">{{ t('aiDevelopment.toolCalls.stdout') }}：{{ call.stdout_summary }}</small>
      <small v-if="call.stderr_summary" class="inline-tool__output inline-tool__output--error">{{ t('aiDevelopment.toolCalls.stderr') }}：{{ call.stderr_summary }}</small>
      <small v-if="!call.stdout_summary && !call.stderr_summary" class="inline-tool__empty">{{ aiEnumLabel(t, 'riskLevels', call.risk_level) }}</small>
      <div v-if="call.stdout_summary || call.stderr_summary" class="inline-tool__actions">
        <el-button v-if="call.stdout_summary" link size="small" type="primary" @click="$emit('open-log', call.id, 'stdout')">{{ t('aiDevelopment.toolCalls.viewStdout') }}</el-button>
        <el-button v-if="call.stderr_summary" link size="small" type="danger" @click="$emit('open-log', call.id, 'stderr')">{{ t('aiDevelopment.toolCalls.viewStderr') }}</el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElButton } from 'element-plus';
import type { AiToolCall } from '@/api/development/ai';
import { aiEnumLabel } from '../i18n';

const props = defineProps<{ call: AiToolCall }>();
defineEmits<{ 'open-log': [id: number, stream: 'stdout' | 'stderr'] }>();
const { t } = useI18n();
const tone = computed(() => {
  if (props.call.status === 'succeeded') return 'ok';
  if (props.call.status === 'failed' || props.call.status === 'denied') return 'bad';
  if (props.call.status === 'awaiting_approval') return 'warn';
  return 'run';
});
// 需要处理或已出错的调用默认展开，避免被折叠隐藏。
const open = ref(tone.value === 'warn' || tone.value === 'bad');
const icon = computed(() => ({ ok: 'i-ep-check', bad: 'i-ep-close', warn: 'i-ep-warning', run: 'i-ep-loading' })[tone.value]);
</script>

<style scoped>
.inline-tool { min-width: 0; overflow: hidden; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: var(--el-bg-color); }
.inline-tool--warn { border-color: color-mix(in srgb, var(--el-color-warning) 45%, var(--el-border-color-lighter)); }
.inline-tool--bad { border-color: color-mix(in srgb, var(--el-color-danger) 40%, var(--el-border-color-lighter)); }
.inline-tool__toggle { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; gap: 10px; width: 100%; padding: 7px 12px; border: 0; background: transparent; color: inherit; font: inherit; font-size: 12.5px; text-align: left; cursor: pointer; }
.inline-tool__toggle:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: -2px; }
.inline-tool__status { display: grid; width: 18px; height: 18px; place-items: center; border-radius: 50%; color: #fff; font-size: 11px; }
.inline-tool--ok .inline-tool__status { background: var(--el-color-success); }
.inline-tool--bad .inline-tool__status { background: var(--el-color-danger); }
.inline-tool--warn .inline-tool__status { background: var(--el-color-warning); }
.inline-tool--run .inline-tool__status { background: var(--el-color-primary); }
.inline-tool__name { min-width: 0; overflow: hidden; color: var(--el-text-color-primary); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.inline-tool__name em { margin-left: 8px; color: var(--el-text-color-secondary); font-family: var(--el-font-family); font-style: normal; }
.inline-tool__ms { color: var(--el-text-color-secondary); font-size: 11.5px; font-variant-numeric: tabular-nums; }
.inline-tool__caret { color: var(--el-text-color-secondary); transition: transform .15s ease; }
.inline-tool__toggle[aria-expanded="true"] .inline-tool__caret { transform: rotate(90deg); }
.inline-tool__body { display: grid; gap: 6px; padding: 8px 12px 10px; border-top: 1px solid var(--el-border-color-lighter); }
.inline-tool__output { display: block; padding: 6px 8px; border-radius: 6px; background: var(--el-fill-color-light); color: var(--el-text-color-regular); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
.inline-tool__output--error { background: color-mix(in srgb, var(--el-color-danger) 8%, var(--el-bg-color)); color: var(--el-color-danger); }
.inline-tool__empty { color: var(--el-text-color-secondary); font-size: 12px; }
.inline-tool__actions { display: flex; flex-wrap: wrap; gap: 12px; }
.inline-tool__actions :deep(.el-button + .el-button) { margin-left: 0; }
@media (prefers-reduced-motion: reduce) { .inline-tool__caret { transition: none; } }
</style>
