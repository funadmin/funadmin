<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { qaApi } from '../api';
import type { Id, QaAnswer, QaQuestionDetail, QaScope } from '../types';
import { qaAllowed } from './permissions';

/**
 * 后台问题详情：标题、内容、提问会员、审核状态、附件与回答列表；可关闭/重新开放、置顶、删除问题，隐藏/显示与删除回答。
 * 每个按钮按自己的权限显示；只采用最后一次请求的响应；问题版本冲突（409）时刷新为最新内容。
 */
const props = defineProps<{ scope: QaScope; id?: Id; site: (operation: string) => boolean }>();
const emit = defineEmits<{ changed: []; deleted: [] }>();
const visible = defineModel<boolean>({ required: true });
const { t } = useCmsI18n();

const detail = ref<QaQuestionDetail>();
const loading = ref(false);
const busy = ref(false);
const allow = (action: Parameters<typeof qaAllowed>[0]) => qaAllowed(action, props.site);
const statusName = (value: string) => t(`pluginQa.status.${value}`);
const capped = computed(() => !!detail.value && detail.value.answers_total > detail.value.answers.length);

const message = (e: unknown) => {
  const m = (e as { msg?: unknown } | null)?.msg;
  return typeof m === 'string' && m ? m : t('pluginQa.list.failed');
};
const code = (e: unknown) => (e as { code?: unknown } | null)?.code;

let requestId = 0;
async function load() {
  const id = props.id;
  const request = ++requestId;
  if (!id) { detail.value = undefined; return; }
  loading.value = true;
  try {
    const data = await qaApi.detail(props.scope, id);
    if (request === requestId) detail.value = data;
  } catch (e) {
    if (request === requestId) { detail.value = undefined; ElMessage.error(message(e)); }
  } finally {
    if (request === requestId) loading.value = false;
  }
}

watch(() => [visible.value, props.id, props.scope.site_id, props.scope.locale] as const, ([open]) => {
  if (open) void load();
  else { requestId++; detail.value = undefined; }
}, { immediate: true });

async function run(action: () => Promise<QaQuestionDetail | unknown>, replaces: boolean) {
  if (busy.value) return;
  busy.value = true;
  const request = ++requestId;
  try {
    const result = await action();
    if (request !== requestId) return;
    if (replaces) detail.value = result as QaQuestionDetail;
    else await load();
    ElMessage.success(t('pluginQa.detail.saved'));
    emit('changed');
  } catch (e) {
    if (request !== requestId) return;
    if (code(e) === 409) { ElMessage.warning(t('pluginQa.detail.conflict')); await load(); }
    else ElMessage.error(message(e));
  } finally {
    busy.value = false;
  }
}

function toggleClosed() {
  const current = detail.value;
  if (!current || !allow('status')) return;
  void run(() => qaApi.status(props.scope, current.id, current.question_version, !current.closed), true);
}
function togglePinned() {
  const current = detail.value;
  if (!current || !allow('pin')) return;
  void run(() => qaApi.pin(props.scope, current.id, current.question_version, !current.pinned), true);
}
function toggleHidden(answer: QaAnswer) {
  if (!allow('answerVisibility')) return;
  void run(() => qaApi.answerVisibility(props.scope, answer.id, !answer.hidden), true);
}
const confirm = (body: string, title: string, button: string) => ElMessageBox.confirm(body, title, { type: 'warning', confirmButtonText: button, cancelButtonText: t('cms.recycle.cancel') }).then(() => true, () => false);

async function removeAnswer(answer: QaAnswer) {
  if (!allow('answerDelete') || busy.value) return;
  if (await confirm(t('pluginQa.detail.deleteAnswerBody'), t('pluginQa.detail.deleteAnswerTitle'), t('pluginQa.detail.deleteAnswer'))) void run(() => qaApi.deleteAnswer(props.scope, answer.id), false);
}
async function removeQuestion() {
  const current = detail.value;
  if (!current || !allow('delete') || busy.value) return;
  if (!(await confirm(t('pluginQa.list.deleteBody', { name: current.title }), t('pluginQa.list.deleteTitle'), t('pluginQa.list.delete')))) return;
  busy.value = true;
  try {
    await qaApi.deleteQuestion(props.scope, current.id);
    ElMessage.success(t('pluginQa.list.deleted'));
    emit('deleted');
    visible.value = false;
  } catch (e) {
    ElMessage.error(message(e));
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <el-drawer v-model="visible" :title="t('pluginQa.detail.title')" size="min(760px, 94vw)" append-to-body>
    <div v-loading="loading" class="qa-detail">
      <template v-if="detail">
        <h2 class="qa-title" data-testid="qa-detail-title">{{ detail.title }}</h2>
        <div class="qa-tags">
          <el-tag>{{ statusName(detail.status) }}</el-tag>
          <el-tag :type="detail.closed ? 'info' : 'success'">{{ detail.closed ? t('pluginQa.detail.closed') : t('pluginQa.detail.open') }}</el-tag>
          <el-tag v-if="detail.pinned" type="warning">{{ t('pluginQa.list.pinned') }}</el-tag>
        </div>
        <dl class="qa-meta">
          <dt>{{ t('pluginQa.detail.member') }}</dt><dd>{{ detail.member.nickname || detail.member.username || detail.member.id }} <span class="qa-muted">#{{ detail.member.id }} {{ detail.member.username }}</span></dd>
          <dt>{{ t('pluginQa.detail.status') }}</dt><dd>{{ statusName(detail.status) }} · {{ detail.created_at }}</dd>
          <template v-if="detail.review_reason"><dt>{{ t('pluginQa.detail.reviewReason') }}</dt><dd>{{ detail.review_reason }}</dd></template>
        </dl>
        <div class="qa-actions">
          <el-button v-if="allow('status')" size="small" :disabled="busy" data-testid="qa-toggle-closed" @click="toggleClosed">{{ detail.closed ? t('pluginQa.detail.reopen') : t('pluginQa.detail.close') }}</el-button>
          <el-button v-if="allow('pin')" size="small" :disabled="busy" data-testid="qa-toggle-pinned" @click="togglePinned">{{ detail.pinned ? t('pluginQa.detail.unpin') : t('pluginQa.detail.pin') }}</el-button>
          <el-button v-if="allow('delete')" size="small" type="danger" plain :disabled="busy" data-testid="qa-delete-question" @click="removeQuestion">{{ t('pluginQa.detail.delete') }}</el-button>
        </div>
        <h4>{{ t('pluginQa.detail.body') }}</h4>
        <pre class="qa-body">{{ detail.body_html }}</pre>
        <template v-if="detail.attachments.length">
          <h4>{{ t('pluginQa.detail.attachments') }}</h4>
          <ul class="qa-files"><li v-for="(file, index) in detail.attachments" :key="index">{{ file.name || file.url }}</li></ul>
        </template>
        <h4>{{ t('pluginQa.detail.answers', { n: detail.answers_total }) }}</h4>
        <p v-if="capped" class="qa-muted">{{ t('pluginQa.detail.capped', { n: detail.answers.length }) }}</p>
        <el-empty v-if="!detail.answers.length" :description="t('pluginQa.detail.noAnswers')" :image-size="48" />
        <article v-for="answer in detail.answers" :key="answer.id" class="qa-answer" data-testid="qa-answer">
          <div class="qa-answer-head">
            <span>{{ answer.member.nickname || answer.member.username || answer.member.id }} · {{ answer.created_at }}</span>
            <el-tag size="small">{{ statusName(answer.status) }}</el-tag>
            <el-tag v-if="answer.accepted" size="small" type="success">{{ t('pluginQa.detail.accepted') }}</el-tag>
            <el-tag v-if="answer.hidden" size="small" type="info">{{ t('pluginQa.detail.hidden') }}</el-tag>
            <span class="qa-answer-actions">
              <el-button v-if="allow('answerVisibility')" link size="small" :disabled="busy" data-testid="qa-toggle-hidden" @click="toggleHidden(answer)">{{ answer.hidden ? t('pluginQa.detail.unhide') : t('pluginQa.detail.hide') }}</el-button>
              <el-button v-if="allow('answerDelete')" link size="small" type="danger" :disabled="busy" data-testid="qa-delete-answer" @click="removeAnswer(answer)">{{ t('pluginQa.detail.deleteAnswer') }}</el-button>
            </span>
          </div>
          <pre class="qa-body">{{ answer.body_html }}</pre>
        </article>
      </template>
    </div>
  </el-drawer>
</template>

<style scoped>
.qa-detail { min-height: 200px; display: flex; flex-direction: column; gap: 8px; }
.qa-title { margin: 0; font-size: 18px; overflow-wrap: anywhere; }
.qa-tags, .qa-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.qa-meta { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; margin: 4px 0; font-size: 13px; }
.qa-meta dt { color: var(--el-text-color-secondary); }
.qa-meta dd { margin: 0; overflow-wrap: anywhere; }
.qa-muted { color: var(--el-text-color-secondary); font-size: 12px; }
.qa-body { white-space: pre-wrap; overflow-wrap: anywhere; max-height: 320px; overflow: auto; background: var(--el-fill-color-light); padding: 12px; margin: 0; line-height: 1.7; }
.qa-files { margin: 0; padding-left: 20px; }
.qa-answer { border-top: 1px solid var(--el-border-color-lighter); padding: 10px 0; display: flex; flex-direction: column; gap: 6px; }
.qa-answer-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px; }
.qa-answer-actions { margin-left: auto; display: flex; gap: 4px; }
h4 { margin: 8px 0 0; }
</style>
