<template>
  <section class="file-diff-viewer">
    <header><strong>{{ file.path }}</strong><el-tag size="small">{{ file.status }}</el-tag></header>
    <el-alert v-if="file.contentKind === 'binary'" :title="t('aiDevelopment.diff.binary')" type="warning" :closable="false" />
    <el-alert v-if="file.contentOmitted" :title="t('aiDevelopment.diff.omitted')" type="info" :closable="false" />
    <pre v-if="file.contentKind !== 'binary' && !file.contentOmitted"><code>{{ diff || t('aiDevelopment.diff.missing') }}</code></pre>
    <dl><dt>Base</dt><dd>{{ file.baseHash || '-' }}</dd><dt>Local</dt><dd>{{ file.localHash || '-' }}</dd><dt>Remote</dt><dd>{{ file.remoteHash || '-' }}</dd></dl>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { AiChangeSetFile } from '@/api/development/ai';
defineProps<{ file: AiChangeSetFile; diff?: string }>();
const { t } = useI18n();
</script>

<style scoped>
.file-diff-viewer { display: grid; gap: 10px; }
header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
pre { overflow: auto; max-height: 55vh; margin: 0; padding: 12px; border-radius: 8px; background: var(--el-fill-color-dark); white-space: pre; }
dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 8px; margin: 0; font-size: 12px; } dd { overflow-wrap: anywhere; margin: 0; color: var(--el-text-color-secondary); }
</style>
