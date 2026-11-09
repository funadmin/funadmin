<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { DocumentStatus, DocumentSummary } from '../types';

const { t } = useI18n();

defineProps<{ documents: DocumentSummary[]; currentId: number | null; loading: boolean }>();
const emit = defineEmits<{ open: [id: number] }>();

const STATUS_TAG: Record<DocumentStatus, 'success' | 'warning' | 'info'> = { draft: 'warning', published: 'success', archived: 'info' };
const time = (value: string) => value.slice(0, 16);
</script>

<template>
  <div v-loading="loading" class="doc-list">
    <el-empty v-if="!documents.length && !loading" :description="t('pluginDocument.empty')" :image-size="64" />
    <button
      v-for="doc in documents"
      :key="doc.id"
      type="button"
      class="doc-card"
      :class="{ active: doc.id === currentId }"
      :data-doc-id="doc.id"
      @click="emit('open', doc.id)"
    >
      <span class="doc-card-title">{{ doc.title }}</span>
      <span class="doc-card-meta">
        <el-tag size="small" :type="STATUS_TAG[doc.status]" disable-transitions>{{ t(`pluginDocument.status.${doc.status}`) }}</el-tag>
        <span>{{ t(`pluginDocument.type.${doc.type}`) }}</span>
        <span>· {{ doc.pluginCode || t(`pluginDocument.scope.${doc.scope}`) }}</span>
        <span v-if="doc.appliesTo">· {{ doc.appliesTo }}</span>
      </span>
      <span class="doc-card-foot">
        <span class="doc-card-time">{{ time(doc.updatedAt) }}</span>
        <span
          v-if="doc.feedback && doc.feedback.helpful + doc.feedback.unhelpful > 0"
          class="doc-card-feedback"
          :title="`${t('pluginDocument.feedback.helpful')} ${doc.feedback.helpful} · ${t('pluginDocument.feedback.unhelpful')} ${doc.feedback.unhelpful}`"
        >👍 {{ doc.feedback.helpful }}<span :class="{ 'is-warn': doc.feedback.unhelpful > 0 }">👎 {{ doc.feedback.unhelpful }}</span></span>
      </span>
    </button>
  </div>
</template>

<style scoped>
.doc-list { display: flex; flex-direction: column; gap: 2px; min-height: 120px; }
.doc-card { display: flex; flex-direction: column; gap: 6px; width: 100%; padding: 10px; border: 1px solid transparent; border-radius: 8px; background: transparent; font: inherit; text-align: left; cursor: pointer; }
.doc-card:hover { background: var(--el-bg-color); border-color: var(--el-border-color-lighter); }
.doc-card.active { background: var(--el-bg-color); border-color: var(--el-color-primary-light-5); box-shadow: 0 2px 8px rgba(0, 0, 0, .04); }
.doc-card-title { font-size: 13px; font-weight: 600; color: var(--el-text-color-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-card-meta { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--el-text-color-secondary); flex-wrap: wrap; }
.doc-card-time { font-size: 11px; color: var(--el-text-color-placeholder); }
.doc-card-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.doc-card-feedback { display: inline-flex; gap: 8px; font-size: 11px; color: var(--el-text-color-secondary); white-space: nowrap; }
.doc-card-feedback .is-warn { color: var(--el-color-danger); }
</style>
