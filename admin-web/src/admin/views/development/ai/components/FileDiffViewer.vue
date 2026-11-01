<template>
  <section class="file-diff-viewer">
    <header><i class="i-ep-document" /><strong>{{ file.path }}</strong><el-tag size="small" effect="light">{{ aiEnumLabel(t, 'fileStatuses', file.status) }}</el-tag></header>
    <el-alert v-if="file.contentKind === 'binary'" :title="t('aiDevelopment.diff.binary')" type="warning" show-icon :closable="false" />
    <el-alert v-if="file.contentOmitted" :title="t('aiDevelopment.diff.omitted')" type="info" show-icon :closable="false" />
    <pre v-if="file.contentKind !== 'binary' && !file.contentOmitted" :class="{ 'is-empty': !diff }"><code>{{ diff || t('aiDevelopment.diff.missing') }}</code></pre>
    <dl><dt>{{ t('aiDevelopment.fields.base') }}</dt><dd>{{ file.baseHash || '-' }}</dd><dt>{{ t('aiDevelopment.fields.local') }}</dt><dd>{{ file.localHash || '-' }}</dd><dt>{{ t('aiDevelopment.fields.remote') }}</dt><dd>{{ file.remoteHash || '-' }}</dd></dl>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { AiChangeSetFile } from '@/api/development/ai';
import { aiEnumLabel } from '../i18n';
defineProps<{ file: AiChangeSetFile; diff?: string }>();
const { t } = useI18n();
</script>

<style scoped>
.file-diff-viewer { display: grid; align-content: start; gap: 12px; min-width: 0; padding: 14px; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; }
header { display: flex; align-items: center; gap: 8px; min-width: 0; }
header i { flex: none; color: var(--el-text-color-secondary); }
header strong { flex: 1; min-width: 0; overflow: hidden; color: var(--el-text-color-primary); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
pre { overflow: auto; max-height: 55vh; margin: 0; padding: 12px 14px; border-radius: 10px; background: #0f172a; color: #e2e8f0; font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; line-height: 1.6; white-space: pre; }
pre.is-empty { background: var(--el-fill-color-light); color: var(--el-text-color-secondary); white-space: pre-wrap; }
dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 6px 12px; margin: 0; padding: 10px 12px; border-radius: 8px; background: var(--el-fill-color-lighter); font-size: 12px; }
dt { color: var(--el-text-color-secondary); }
dd { overflow-wrap: anywhere; margin: 0; color: var(--el-text-color-regular); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); }
</style>
