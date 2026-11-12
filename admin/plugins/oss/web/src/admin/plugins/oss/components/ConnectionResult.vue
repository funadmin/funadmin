<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { ConnectionTestResult } from '../types';

/** 连接测试的逐步结果：上传、读取校验、删除。 */
defineProps<{ result: ConnectionTestResult }>();
const { t } = useI18n({ useScope: 'global' });
const tagType = { ok: 'success', failed: 'danger', skipped: 'info' } as const;
</script>

<template>
  <div class="oss-connection-result" data-testid="oss-connection-result">
    <el-alert :type="result.success ? 'success' : 'error'" :closable="false" :title="t(result.success ? 'pluginOss.test.success' : 'pluginOss.test.failure', { ms: result.elapsed_ms })" />
    <ol class="mt-2 mb-0 pl-5 text-sm">
      <li v-for="step in result.steps" :key="step.step" class="mb-1" :data-step="step.step" :data-status="step.status">
        <span class="mr-2">{{ t(`pluginOss.test.steps.${step.step}`) }}</span>
        <el-tag size="small" :type="tagType[step.status]">{{ t(`pluginOss.test.status.${step.status}`) }}</el-tag>
        <span v-if="step.status === 'ok'" class="ml-2 text-xs text-[var(--el-text-color-secondary)]">{{ step.ms }} ms</span>
        <span v-else-if="step.reason" class="ml-2 text-xs text-[var(--el-text-color-secondary)]">{{ t(`pluginOss.test.reasons.${step.reason}`) }}</span>
      </li>
    </ol>
  </div>
</template>
