<template>
  <section class="file-diff-viewer">
    <header><strong>{{ file.path }}</strong><el-tag size="small">{{ file.status }}</el-tag></header>
    <el-alert v-if="file.contentKind === 'binary'" title="二进制文件无法显示文本 Diff" type="warning" :closable="false" />
    <el-alert v-if="file.contentOmitted" title="内容已省略：文件为二进制或超过展示上限" type="info" :closable="false" />
    <pre v-if="file.contentKind !== 'binary' && !file.contentOmitted"><code>{{ diff || 'Diff 正文未由后端返回，仅展示文件状态与哈希。' }}</code></pre>
    <dl><dt>Base</dt><dd>{{ file.baseHash || '-' }}</dd><dt>Local</dt><dd>{{ file.localHash || '-' }}</dd><dt>Remote</dt><dd>{{ file.remoteHash || '-' }}</dd></dl>
  </section>
</template>

<script setup lang="ts">
import type { AiChangeSetFile } from '@/api/development/ai';
defineProps<{ file: AiChangeSetFile; diff?: string }>();
</script>

<style scoped>
.file-diff-viewer { display: grid; gap: 10px; }
header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
pre { overflow: auto; max-height: 55vh; margin: 0; padding: 12px; border-radius: 8px; background: var(--el-fill-color-dark); white-space: pre; }
dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 8px; margin: 0; font-size: 12px; } dd { overflow-wrap: anywhere; margin: 0; color: var(--el-text-color-secondary); }
</style>
