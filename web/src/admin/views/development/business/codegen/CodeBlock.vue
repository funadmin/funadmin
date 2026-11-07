<template>
  <div class="code-block" :data-language="language">
    <div class="code-block__toolbar">
      <span class="code-block__language">{{ language }}</span>
      <el-button class="code-block__copy" size="small" text :icon="DocumentCopy" @click="copy">
        {{ t('business.codegen.copy', '复制') }}
      </el-button>
    </div>
    <div class="code-block__scroller" :aria-busy="loading">
      <ol class="code-block__gutter" aria-hidden="true">
        <li v-for="line in lineCount" :key="line" class="code-block__line-number">{{ line }}</li>
      </ol>
      <!-- Only trusted Shiki output is rendered as HTML; everything else goes through Vue text escaping. -->
      <div v-if="html" class="code-block__body" v-html="html" />
      <pre v-else class="code-block__body code-block__plain"><code>{{ code }}</code></pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { DocumentCopy } from '@element-plus/icons-vue';

import { highlight, type CodeLanguage } from './highlighter';

const props = withDefaults(defineProps<{ code: string; language?: CodeLanguage }>(), { language: 'text' });

const { t } = useI18n();

const html = ref('');
const loading = ref(false);
let requestId = 0;

const lineCount = computed(() => Math.max(1, props.code.split('\n').length));

watch(
  () => [props.code, props.language] as const,
  async ([code, language]) => {
    const current = ++requestId;
    html.value = '';
    if (language === 'text') {
      loading.value = false;
      return;
    }
    loading.value = true;
    try {
      const result = await highlight(code, language);
      if (current === requestId) html.value = result;
    } catch {
      // Keep the escaped plain-text fallback.
      if (current === requestId) html.value = '';
    } finally {
      if (current === requestId) loading.value = false;
    }
  },
  { immediate: true }
);

function legacyCopy(text: string): boolean {
  if (typeof document === 'undefined' || typeof document.execCommand !== 'function') return false;
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  try {
    return document.execCommand('copy');
  } catch {
    return false;
  } finally {
    document.body.removeChild(textarea);
  }
}

async function copy() {
  let copied = false;
  try {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(props.code);
      copied = true;
    }
  } catch {
    copied = false;
  }
  if (!copied) copied = legacyCopy(props.code);
  if (copied) ElMessage.success(t('business.codegen.copySuccess', '已复制'));
  else ElMessage.error(t('business.codegen.copyFailed', '复制失败，请手动选择复制'));
}
</script>

<style scoped>
.code-block {
  --code-block-bg: #24292e;
  --code-block-fg: #e1e4e8;
  --code-block-muted: #6a737d;
  --code-block-border: #1b1f23;
  --code-block-line-height: 20px;
  overflow: hidden;
  border: 1px solid var(--code-block-border);
  border-radius: 6px;
  background: var(--code-block-bg);
  color: var(--code-block-fg);
}

.code-block__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 8px 4px 12px;
  border-bottom: 1px solid var(--code-block-border);
  font-size: 12px;
}

.code-block__language {
  color: var(--code-block-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.code-block__copy.el-button,
.code-block__copy.el-button:hover,
.code-block__copy.el-button:focus-visible {
  color: var(--code-block-fg);
}

.code-block__copy.el-button:hover {
  background: rgb(255 255 255 / 8%);
}

.code-block__scroller {
  display: flex;
  overflow: auto;
  max-height: 70vh;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace;
  font-size: 13px;
  line-height: var(--code-block-line-height);
}

.code-block__gutter {
  position: sticky;
  left: 0;
  flex: none;
  margin: 0;
  padding: 12px 12px 12px 8px;
  list-style: none;
  background: var(--code-block-bg);
  color: var(--code-block-muted);
  text-align: right;
  user-select: none;
}

.code-block__line-number {
  min-width: 2ch;
  height: var(--code-block-line-height);
}

.code-block__body {
  flex: 1 0 auto;
  min-width: 0;
}

.code-block__body :deep(pre),
.code-block__plain {
  margin: 0;
  padding: 12px 16px 12px 4px;
  background: transparent !important;
  color: var(--code-block-fg);
  font: inherit;
  line-height: inherit;
  white-space: pre;
  tab-size: 4;
}

.code-block__body :deep(code) {
  font: inherit;
}
</style>
