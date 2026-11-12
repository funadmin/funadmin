<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import TablePagination from '@/components/TablePagination/index.vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import RecycleDrawer from '@/plugins/cms/components/RecycleDrawer.vue';
import { qaApi } from '../api';
import type { Id, QaQuestionRow, QaScope } from '../types';
import { qaAllowed } from './permissions';
import { qaRecycleSources } from './recycle';
import QuestionDetailDrawer from './QuestionDetailDrawer.vue';

/**
 * 后台问题管理：按审核状态与标题筛选问题（置顶在前），打开详情，删除（移入回收站），以及问题与回答回收站。
 * 只采用最后一次请求的响应；按钮按各自的权限显示。
 */
const props = defineProps<{ scope: QaScope; site: (operation: string) => boolean }>();
const { t } = useCmsI18n();
const statuses = ['pending', 'published', 'rejected', 'withdrawn', 'hidden'];
const rows = ref<QaQuestionRow[]>([]), total = ref(0), page = ref(1), status = ref(''), keyword = ref(''), loading = ref(false), busy = ref(false);
const detailOpen = ref(false), detailId = ref<Id>(), recycleOpen = ref(false);
const allow = (action: Parameters<typeof qaAllowed>[0]) => qaAllowed(action, props.site);
const canRecycle = computed(() => allow('recycle') || allow('answerRecycle'));
const sources = computed(() => qaRecycleSources(props.scope, props.site, (key, args) => (args ? t(key, args) : t(key))));
const message = (e: unknown) => {
  const m = (e as { msg?: unknown } | null)?.msg;
  return typeof m === 'string' && m ? m : t('pluginQa.list.failed');
};

let requestId = 0;
async function load() {
  const request = ++requestId;
  if (!allow('index')) { rows.value = []; total.value = 0; return; }
  loading.value = true;
  try {
    const data = await qaApi.questions(props.scope, { status: status.value || '', q: (keyword.value || '').trim(), page: page.value });
    if (request !== requestId) return;
    if (!data.items.length && page.value > 1) { page.value -= 1; return; }
    rows.value = data.items;
    total.value = data.total;
  } catch (e) {
    if (request === requestId) { rows.value = []; total.value = 0; ElMessage.error(message(e)); }
  } finally {
    if (request === requestId) loading.value = false;
  }
}

function search() { if (page.value !== 1) page.value = 1; else void load(); }
function open(row: QaQuestionRow) { if (!allow('detail')) return; detailId.value = row.id; detailOpen.value = true; }
async function remove(row: QaQuestionRow) {
  if (!allow('delete') || busy.value) return;
  const confirmed = await ElMessageBox.confirm(t('pluginQa.list.deleteBody', { name: row.title }), t('pluginQa.list.deleteTitle'), { type: 'warning', confirmButtonText: t('pluginQa.list.delete'), cancelButtonText: t('cms.recycle.cancel') }).then(() => true, () => false);
  if (!confirmed) return;
  busy.value = true;
  try {
    await qaApi.deleteQuestion(props.scope, row.id);
    ElMessage.success(t('pluginQa.list.deleted'));
  } catch (e) {
    ElMessage.error(message(e));
    return;
  } finally {
    busy.value = false;
  }
  await load();
}

watch(status, search);
watch(page, () => void load());
watch(() => [props.scope.site_id, props.scope.locale], () => { detailOpen.value = false; recycleOpen.value = false; if (page.value !== 1) page.value = 1; else void load(); }, { immediate: true });
onBeforeUnmount(() => { requestId++; });
</script>

<template>
  <section v-loading="loading" class="qa-questions">
    <div class="qa-toolbar">
      <el-select v-model="status" :aria-label="t('pluginQa.list.allStatus')" :placeholder="t('pluginQa.list.allStatus')" clearable>
        <el-option v-for="value in statuses" :key="value" :label="t(`pluginQa.status.${value}`)" :value="value" />
      </el-select>
      <el-input v-model="keyword" :placeholder="t('pluginQa.list.search')" :aria-label="t('pluginQa.list.search')" clearable maxlength="100" @keyup.enter="search" @clear="search" />
      <el-button data-testid="qa-refresh" @click="search">{{ t('pluginQa.list.refresh') }}</el-button>
      <el-button v-if="canRecycle" data-testid="open-recycle" @click="recycleOpen = true">{{ t('cms.recycle.open') }}</el-button>
    </div>
    <article v-for="row in rows" :key="row.id" class="qa-row" data-testid="qa-question-row">
      <div class="qa-row-main">
        <h3>{{ row.title }}</h3>
        <p>
          <el-tag size="small">{{ t(`pluginQa.status.${row.status}`) }}</el-tag>
          <el-tag v-if="row.pinned" size="small" type="warning">{{ t('pluginQa.list.pinned') }}</el-tag>
          <el-tag v-if="row.closed" size="small" type="info">{{ t('pluginQa.list.closed') }}</el-tag>
          {{ t('pluginQa.list.member', { name: row.member.nickname || row.member.username || row.member.id }) }} · {{ t('pluginQa.list.answers', { n: row.answers_count }) }} · {{ row.created_at }}
        </p>
      </div>
      <div class="qa-row-actions">
        <el-button v-if="allow('detail')" size="small" data-testid="qa-open-detail" @click="open(row)">{{ t('pluginQa.list.detail') }}</el-button>
        <el-button v-if="allow('delete')" size="small" type="danger" plain :disabled="busy" data-testid="qa-delete" @click="remove(row)">{{ t('pluginQa.list.delete') }}</el-button>
      </div>
    </article>
    <el-empty v-if="!rows.length && !loading" :description="t('pluginQa.list.empty')" />
    <TablePagination v-if="total > 20" v-model:current-page="page" :total="total" :page-size="20" layout="total,prev,pager,next" />
    <QuestionDetailDrawer v-model="detailOpen" :scope="scope" :id="detailId" :site="site" @changed="load" @deleted="load" />
    <RecycleDrawer v-model="recycleOpen" :sources="sources" @changed="load" />
  </section>
</template>

<style scoped>
.qa-toolbar { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.qa-toolbar .el-select { width: 160px; }
.qa-toolbar .el-input { width: 240px; }
.qa-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; border-bottom: 1px solid var(--el-border-color-lighter); padding: 14px 0; }
.qa-row-main { min-width: 0; }
.qa-row h3 { font-size: 15px; margin: 0 0 6px; overflow-wrap: anywhere; }
.qa-row p { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; color: var(--el-text-color-secondary); margin: 0; }
.qa-row-actions { display: flex; gap: 6px; flex-shrink: 0; }
</style>
