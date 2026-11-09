<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import TablePagination from '@/components/TablePagination/index.vue';
import { documentApi } from '../api';
import type { FeedbackPage, FeedbackQuery } from '../types';

const { t } = useI18n();
const tt = (key: string, args?: Record<string, unknown>) => (args ? t(`pluginDocument.feedback.${key}`, args) : t(`pluginDocument.feedback.${key}`));

/** documentId 为 null 时查看全部文档的反馈。 */
const props = defineProps<{ documentId: number | null; documentTitle?: string }>();
const emit = defineEmits<{ open: [documentId: number] }>();
const visible = defineModel<boolean>({ required: true });

const helpful = ref<'' | '1' | '0'>('');
const withComment = ref(false);
const page = ref(1);
const pageSize = 20;
const result = ref<FeedbackPage | null>(null);
const loading = ref(false);
let requestId = 0;

const title = computed(() => (props.documentId !== null && props.documentTitle ? tt('titleFor', { title: props.documentTitle }) : tt('title')));
const rate = computed(() => {
  const summary = result.value?.summary;
  const total = summary ? summary.helpful + summary.unhelpful : 0;
  return summary && total ? Math.round((summary.helpful / total) * 100) : null;
});

async function load() {
  const id = ++requestId;
  loading.value = true;
  try {
    const query: FeedbackQuery = { helpful: helpful.value, withComment: withComment.value, page: page.value, pageSize };
    if (props.documentId !== null) query.documentId = props.documentId;
    const data = await documentApi.feedback(query);
    if (id === requestId) result.value = data;
  } finally {
    if (id === requestId) loading.value = false;
  }
}

function refilter() {
  page.value = 1;
  void load();
}

watch(visible, (open) => {
  if (!open) return;
  helpful.value = '';
  withComment.value = false;
  page.value = 1;
  result.value = null;
  void load();
});
watch(() => props.documentId, () => visible.value && refilter());
</script>

<template>
  <el-drawer v-model="visible" :title="title" size="min(640px, 92vw)" append-to-body>
    <div v-loading="loading" class="fb">
      <div v-if="result" class="fb-summary">
        <span class="fb-count is-up">👍 {{ tt('helpful') }} <strong>{{ result.summary.helpful }}</strong></span>
        <span class="fb-count is-down">👎 {{ tt('unhelpful') }} <strong>{{ result.summary.unhelpful }}</strong></span>
        <span v-if="rate !== null" class="fb-rate">{{ tt('rate', { n: rate }) }}</span>
      </div>
      <div class="fb-filters">
        <el-radio-group v-model="helpful" size="small" @change="refilter">
          <el-radio-button value="">{{ tt('all') }}</el-radio-button>
          <el-radio-button value="1">{{ tt('helpful') }}</el-radio-button>
          <el-radio-button value="0">{{ tt('unhelpful') }}</el-radio-button>
        </el-radio-group>
        <el-checkbox v-model="withComment" size="small" @change="refilter">{{ tt('withComment') }}</el-checkbox>
      </div>

      <el-empty v-if="result && !result.list.length" :description="tt('none')" :image-size="64" />
      <ul v-else-if="result" class="fb-list">
        <li v-for="item in result.list" :key="item.id" class="fb-item">
          <div class="fb-item-head">
            <el-tag size="small" :type="item.helpful ? 'success' : 'danger'" disable-transitions>{{ item.helpful ? tt('helpful') : tt('unhelpful') }}</el-tag>
            <button v-if="documentId === null" type="button" class="fb-doc" @click="emit('open', item.documentId)">{{ item.title }}</button>
            <span class="fb-meta">{{ tt('revision', { n: item.revisionNo }) }} · {{ item.updatedAt.slice(0, 16) }}</span>
          </div>
          <div v-if="item.reasons.length" class="fb-reasons">
            <el-tag v-for="reason in item.reasons" :key="reason" size="small" type="info" effect="plain" disable-transitions>{{ tt(`reason.${reason}`) }}</el-tag>
          </div>
          <p v-if="item.comment" class="fb-comment">{{ item.comment }}</p>
          <p v-else-if="!item.helpful" class="fb-comment is-empty">{{ tt('noComment') }}</p>
        </li>
      </ul>
      <TablePagination
        v-if="result && result.total > pageSize"
        small layout="prev, pager, next" :total="result.total" :page-size="pageSize" :current-page="page"
        @current-change="(p: number) => { page = p; void load(); }"
      />
    </div>
  </el-drawer>
</template>

<style scoped>
.fb { display: flex; flex-direction: column; gap: 14px; min-height: 200px; }
.fb-summary { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 12px 14px; border-radius: 8px; background: var(--el-fill-color-light); font-size: 13px; color: var(--el-text-color-regular); }
.fb-count strong { margin-left: 4px; font-size: 18px; color: var(--el-text-color-primary); }
.fb-rate { margin-left: auto; color: var(--el-text-color-secondary); }
.fb-filters { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.fb-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.fb-item { padding: 12px 0; border-bottom: 1px solid var(--el-border-color-lighter); display: flex; flex-direction: column; gap: 8px; }
.fb-item-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; }
.fb-doc { padding: 0; border: 0; background: none; font: inherit; font-size: 13px; font-weight: 600; color: var(--el-color-primary); cursor: pointer; }
.fb-doc:hover { text-decoration: underline; }
.fb-meta { margin-left: auto; color: var(--el-text-color-placeholder); }
.fb-reasons { display: flex; gap: 6px; flex-wrap: wrap; }
.fb-comment { margin: 0; font-size: 13px; line-height: 1.7; color: var(--el-text-color-primary); white-space: pre-wrap; word-break: break-word; }
.fb-comment.is-empty { color: var(--el-text-color-placeholder); }
</style>
