<script setup lang="ts">
import TablePagination from '@/components/TablePagination/index.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { onBeforeRouteLeave } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { hasPermission } from '@/utils/permission';
import { registerDocumentLocales } from './locales';
import { useDocumentStore } from './store';
import { documentApi } from './api';
import type { DocumentMetaInput, DocumentStatus } from './types';
import DocumentSidebar from './components/DocumentSidebar.vue';
import DocumentList from './components/DocumentList.vue';
import DocumentMetaDialog from './components/DocumentMetaDialog.vue';
import DocumentVersionDrawer from './components/DocumentVersionDrawer.vue';
import DocumentFeedbackDrawer from './components/DocumentFeedbackDrawer.vue';
import DocumentCategoryDialog from './components/DocumentCategoryDialog.vue';
import DocumentRecycleDrawer from './components/DocumentRecycleDrawer.vue';
import DocumentEditor from './editor/DocumentEditor.vue';
import DocumentPreview from './editor/DocumentPreview.vue';
import DocumentOutline from './editor/DocumentOutline.vue';
import { exportDocumentHtml, exportDocumentMarkdown, parseImportedMarkdown } from './editor/content';
import './editor/prose.css';

defineOptions({ name: 'PluginDocumentIndex' });
registerDocumentLocales();
const { t } = useI18n();

const store = useDocumentStore();
// 每个按钮对应一个后台方法，权限码由控制器方法推导（document:document:{方法}）。
const can = {
  create: computed(() => hasPermission('document:document:create')),
  editMeta: computed(() => hasPermission('document:document:update-meta')),
  edit: computed(() => hasPermission('document:document:save-draft')),
  publish: computed(() => hasPermission('document:document:publish')),
  archive: computed(() => hasPermission('document:document:archive')),
  restore: computed(() => hasPermission('document:document:restore')),
  remove: computed(() => hasPermission('document:document:delete')),
  feedback: computed(() => hasPermission('document:feedback:index')),
  recycle: computed(() => hasPermission(['document:document:recycle', 'document:category:recycle'])),
  manageCategories: computed(() => hasPermission(['document:category:create', 'document:category:update', 'document:category:delete']))
};

const keyword = ref('');
const metaOpen = ref(false);
const metaRow = ref<typeof store.current>(null);
const versionsOpen = ref(false);
const feedbackOpen = ref(false);
/** null 表示全部文档的反馈 */
const feedbackDocumentId = ref<number | null>(null);
function openFeedback(documentId: number | null) {
  feedbackDocumentId.value = documentId;
  feedbackOpen.value = true;
}
async function openFromFeedback(id: number) {
  feedbackOpen.value = false;
  await open(id);
}
const categoriesOpen = ref(false);
const recycleOpen = ref(false);
/** 回收站中恢复或彻底删除后刷新目录树与列表。 */
async function recycleChanged() {
  await Promise.all([store.loadTree(), store.loadList()]);
}
/** 空字符串表示默认：草稿与已发布（不含归档）。 */
const statusFilter = ref<'' | 'draft' | 'published' | 'archived'>('');
const filterStatus = () => store.loadList({ status: statusFilter.value, page: 1 });
async function categoriesChanged() {
  await store.loadTree();
}
const importInput = ref<HTMLInputElement>();
const previewRef = ref<HTMLElement>();

const STATUS_TAG: Record<DocumentStatus, 'success' | 'warning' | 'info'> = { draft: 'warning', published: 'success', archived: 'info' };

const showEditor = computed(() => store.editorMode !== 'preview');
const showPreview = computed(() => store.editorMode !== 'edit');
const saveLabel = computed(() => {
  if (store.saving) return t('pluginDocument.saving');
  if (store.conflict) return t('pluginDocument.conflictState');
  if (store.dirty) return t('pluginDocument.unsaved');
  return store.lastSavedAt ? t('pluginDocument.saved') : '';
});

onMounted(async () => {
  await Promise.all([store.loadTree(), store.loadList({ page: 1 })]);
});

// —— 自动保存：停止输入 4 秒后保存草稿（定时器由 store 管理，切换/发布/归档时会取消）——
function onContent(value: Parameters<typeof store.setContent>[0]) {
  store.setContent(value);
  if (can.edit.value && !store.conflict) store.scheduleAutosave(4000);
}
onBeforeUnmount(() => store.cancelAutosave());

/** 有未保存的修改时，切换文档/离开页面前先询问。 */
async function confirmDiscard(): Promise<boolean> {
  if (!store.dirty) return true;
  return ElMessageBox.confirm(t('pluginDocument.discardBody'), t('pluginDocument.discardTitle'), { type: 'warning', confirmButtonText: t('pluginDocument.discardOk'), cancelButtonText: t('pluginDocument.discardCancel') })
    .then(() => true, () => false);
}
onBeforeRouteLeave(async () => confirmDiscard());

async function open(id: number) {
  if (store.current?.id === id || !(await confirmDiscard())) return;
  await store.openDocument(id);
}
async function selectCategory(scope: Parameters<typeof store.selectCategory>[0], id: number | null) {
  await store.selectCategory(scope, id);
}
const search = () => store.loadList({ keyword: keyword.value.trim(), page: 1 });

async function save() {
  if (await store.saveDraft()) ElMessage.success(t('pluginDocument.saved'));
  else if (!store.conflict && store.error) ElMessage.error(store.error);
}
async function publish() {
  const { value } = await ElMessageBox.prompt(t('pluginDocument.publishPrompt'), t('pluginDocument.publishTitle'), { inputPlaceholder: t('pluginDocument.publishPlaceholder') }).catch(() => ({ value: null }));
  if (value === null) return;
  if (await store.publish(value || '')) ElMessage.success(t('pluginDocument.published'));
  else if (!store.conflict) ElMessage.error(store.error || t('pluginDocument.saveFailed'));
}
async function archive() {
  const ok = await ElMessageBox.confirm(t('pluginDocument.archiveBody'), t('pluginDocument.archiveTitle'), { type: 'warning' }).then(() => true, () => false);
  if (!ok) return;
  if (!(await store.archive()) && !store.conflict) ElMessage.error(store.error || t('pluginDocument.saveFailed'));
}
async function remove() {
  if (!store.current) return;
  const ok = await ElMessageBox.confirm(t('pluginDocument.removeBody', { title: store.current.title }), t('pluginDocument.removeTitle'), { type: 'warning', confirmButtonText: t('pluginDocument.remove') }).then(() => true, () => false);
  if (ok) { await store.remove(); ElMessage.success(t('pluginDocument.removed')); }
}
async function restore(revisionNo: number) {
  try {
    await store.restoreRevision(revisionNo);
    versionsOpen.value = false;
    ElMessage.success(t('pluginDocument.restored'));
  } catch {
    ElMessage.error(store.error || t('pluginDocument.saveFailed'));
  }
}

function openMeta(row: typeof store.current) {
  metaRow.value = row;
  metaOpen.value = true;
}
async function submitMeta(data: DocumentMetaInput) {
  try {
    if (metaRow.value) {
      // 只合并元数据，保留本地正文、lockVersion 与冲突状态。
      store.applyMeta(await documentApi.updateMeta(metaRow.value.id, { ...data, metaVersion: store.current?.metaVersion ?? metaRow.value.metaVersion }));
    } else {
      const created = await documentApi.create({ ...data, content: { type: 'doc', content: [{ type: 'paragraph' }] } });
      await store.loadList();
      await store.openDocument(created.id);
      store.editorMode = 'edit';
    }
    metaOpen.value = false;
  } catch (e) {
    ElMessage.error((e as { msg?: string })?.msg || t('pluginDocument.saveFailed'));
  }
}

// —— 导入 / 导出（浏览器端完成）——
async function importMarkdown(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file || !store.current) return;
  if (file.size > 2 * 1024 * 1024) return ElMessage.error(t('pluginDocument.tooLarge'));
  const ok = await ElMessageBox.confirm(t('pluginDocument.importBody', { name: file.name }), t('pluginDocument.importMd'), { type: 'warning' }).then(() => true, () => false);
  if (!ok) return;
  store.replaceContent(parseImportedMarkdown(await file.text()));
  if (can.edit.value && !store.conflict) store.scheduleAutosave(4000);
  ElMessage.success(t('pluginDocument.imported'));
}
function download(name: string, body: string, type: string) {
  const url = URL.createObjectURL(new Blob([body], { type }));
  const a = Object.assign(document.createElement('a'), { href: url, download: name });
  a.click();
  URL.revokeObjectURL(url);
}
function exportAs(format: 'markdown' | 'html') {
  if (!store.current || !store.draft) return;
  const name = store.current.slug;
  if (format === 'markdown') download(`${name}.md`, exportDocumentMarkdown(store.draft), 'text/markdown;charset=utf-8');
  else download(`${name}.html`, `<!doctype html><meta charset="utf-8"><title>${store.current.title.replace(/[<&]/g, '')}</title>\n${exportDocumentHtml(store.draft)}`, 'text/html;charset=utf-8');
}

/** 大纲索引是正文顶层节点的下标，与预览中 .doc-prose 的直接子元素一一对应。 */
function jumpTo(index: number) {
  const blocks = previewRef.value?.querySelectorAll<HTMLElement>('.doc-prose > *') ?? [];
  blocks[index]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
  <div class="doc-workspace">
    <!-- 左栏：范围与目录 -->
    <DocumentSidebar class="doc-col doc-col--side" :tree="store.tree" :scope="store.selectedScope" :category-id="store.selectedCategoryId" @select="selectCategory" />

    <!-- 中栏：文档列表 -->
    <section class="doc-col doc-col--list">
      <div class="doc-list-head">
        <el-input v-model="keyword" :placeholder="t('pluginDocument.search')" clearable @keyup.enter="search" @clear="search" />
        <el-button v-if="can.create.value" type="primary" @click="openMeta(null)">{{ t('pluginDocument.newDoc') }}</el-button>
      </div>
      <div class="doc-list-tools">
        <el-select v-model="statusFilter" size="small" class="doc-status-filter" :aria-label="t('pluginDocument.statusFilter.active')" @change="filterStatus">
          <el-option value="" :label="t('pluginDocument.statusFilter.active')" />
          <el-option value="draft" :label="t('pluginDocument.statusFilter.draft')" />
          <el-option value="published" :label="t('pluginDocument.statusFilter.published')" />
          <el-option value="archived" :label="t('pluginDocument.statusFilter.archived')" />
        </el-select>
        <el-button v-if="can.manageCategories.value" size="small" data-testid="manage-categories" @click="categoriesOpen = true">{{ t('pluginDocument.categories.manage') }}</el-button>
      </div>
      <div class="doc-list-count"><span>{{ t('pluginDocument.count', { n: store.total }) }}</span><span class="doc-list-links"><button v-if="can.recycle.value" type="button" class="doc-public-link doc-link-button" data-testid="open-recycle" @click="recycleOpen = true">{{ t('pluginDocument.recycle.open') }}</button><button v-if="can.feedback.value" type="button" class="doc-public-link doc-link-button" @click="openFeedback(null)">{{ t('pluginDocument.feedback.title') }}</button><a href="/docs" target="_blank" rel="noopener noreferrer" class="doc-public-link">{{ t('pluginDocument.publicHome') }} ↗</a></span></div>
      <DocumentList :documents="store.documents" :current-id="store.current?.id ?? null" :loading="store.loadingList" @open="open" />
      <TablePagination
        v-if="store.total > store.query.pageSize"
        small layout="prev, pager, next" :total="store.total" :page-size="store.query.pageSize" :current-page="store.query.page"
        @current-change="(p: number) => store.loadList({ page: p })"
      />
    </section>

    <!-- 右栏：阅读与编辑 -->
    <main v-loading="store.loadingDocument" class="doc-col doc-col--main">
      <el-empty v-if="!store.current" :description="t('pluginDocument.pick')" />
      <template v-else>
        <header class="doc-head">
          <div class="doc-head-main">
            <div class="doc-crumb">{{ t(`pluginDocument.scope.${store.current.scope}`) }}<template v-if="store.current.pluginCode"> / {{ store.current.pluginCode }}</template></div>
            <h1 class="doc-title">{{ store.current.title }}</h1>
            <div class="doc-meta">
              <el-tag size="small" :type="STATUS_TAG[store.current.status]" disable-transitions>{{ t(`pluginDocument.status.${store.current.status}`) }}</el-tag>
              <span v-if="store.current.appliesTo">{{ t('pluginDocument.appliesTo', { v: store.current.appliesTo }) }}</span>
              <span v-if="store.current.currentRevisionNo">{{ t('pluginDocument.revision', { n: store.current.currentRevisionNo }) }}</span>
              <span class="doc-save-state" :class="{ 'is-warn': store.dirty || store.conflict }">{{ saveLabel }}</span>
            </div>
          </div>
          <div class="doc-actions">
            <a v-if="store.current.status === 'published' && store.current.visibility === 'public'" :href="`/docs/${encodeURIComponent(store.current.slug)}`" target="_blank" rel="noopener noreferrer" class="doc-public-link">{{ t('pluginDocument.publicRead') }} ↗</a>
            <el-radio-group v-model="store.editorMode" size="small">
              <el-radio-button value="edit">{{ t('pluginDocument.mode.edit') }}</el-radio-button>
              <el-radio-button value="split">{{ t('pluginDocument.mode.split') }}</el-radio-button>
              <el-radio-button value="preview">{{ t('pluginDocument.mode.preview') }}</el-radio-button>
            </el-radio-group>
            <el-button v-if="can.edit.value" size="small" :loading="store.saving" :disabled="!store.dirty || store.conflict" @click="save">{{ t('pluginDocument.save') }}</el-button>
            <el-button v-if="can.publish.value" size="small" type="primary" :disabled="store.conflict" @click="publish">{{ t('pluginDocument.publish') }}</el-button>
            <el-dropdown trigger="click">
              <el-button size="small">{{ t('pluginDocument.more') }}</el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item v-if="can.editMeta.value" @click="openMeta(store.current)">{{ t('pluginDocument.info') }}</el-dropdown-item>
                  <el-dropdown-item @click="versionsOpen = true">{{ t('pluginDocument.history') }}</el-dropdown-item>
                  <el-dropdown-item v-if="can.feedback.value" @click="openFeedback(store.current.id)">{{ t('pluginDocument.feedback.title') }}</el-dropdown-item>
                  <el-dropdown-item v-if="can.edit.value" @click="importInput?.click()">{{ t('pluginDocument.importMd') }}</el-dropdown-item>
                  <el-dropdown-item @click="exportAs('markdown')">{{ t('pluginDocument.exportMd') }}</el-dropdown-item>
                  <el-dropdown-item @click="exportAs('html')">{{ t('pluginDocument.exportHtml') }}</el-dropdown-item>
                  <el-dropdown-item v-if="can.archive.value && store.current.status !== 'archived'" divided @click="archive">{{ t('pluginDocument.archive') }}</el-dropdown-item>
                  <el-dropdown-item v-if="can.remove.value" @click="remove">{{ t('pluginDocument.remove') }}</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
            <input ref="importInput" type="file" accept=".md,.markdown,text/markdown" hidden @change="importMarkdown" />
          </div>
        </header>

        <el-alert v-if="store.conflict" type="warning" :closable="false" show-icon class="doc-conflict" :title="t('pluginDocument.conflictTitle')">
          <template #default>
            {{ t('pluginDocument.conflictHint') }}
            <el-button link type="primary" @click="store.reload()">{{ t('pluginDocument.reload') }}</el-button>
            {{ t('pluginDocument.conflictTail') }}
          </template>
        </el-alert>

        <div class="doc-body" :class="`is-${store.editorMode}`">
          <div v-if="showEditor" class="doc-pane doc-pane--editor">
            <DocumentEditor :model-value="store.draft" :generation="store.contentGeneration" :editable="can.edit.value" @update:model-value="onContent" />
          </div>
          <div v-if="showPreview" ref="previewRef" class="doc-pane doc-pane--preview">
            <DocumentPreview :content="store.draft" />
          </div>
          <aside v-if="store.editorMode === 'preview'" class="doc-outline-col">
            <DocumentOutline :content="store.draft" @jump="jumpTo" />
          </aside>
        </div>
      </template>
    </main>

    <DocumentMetaDialog v-model="metaOpen" :row="metaRow" :tree="store.tree" :default-scope="store.selectedScope" :default-category-id="store.selectedCategoryId" @submit="submitMeta" />
    <DocumentCategoryDialog v-model="categoriesOpen" :tree="store.tree" @changed="categoriesChanged" />
    <DocumentRecycleDrawer v-model="recycleOpen" @changed="recycleChanged" />
    <DocumentFeedbackDrawer v-model="feedbackOpen" :document-id="feedbackDocumentId" :document-title="feedbackDocumentId === store.current?.id ? store.current?.title : ''" @open="openFromFeedback" />
    <DocumentVersionDrawer v-if="store.current" v-model="versionsOpen" :document-id="store.current.id" :can-restore="can.restore.value" @restore="restore" />
  </div>
</template>

<style scoped>
.doc-workspace { display: grid; grid-template-columns: 220px 280px minmax(0, 1fr); height: calc(100vh - 140px); min-height: 520px; background: var(--el-bg-color); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; overflow: hidden; }
.doc-col { min-height: 0; overflow: auto; }
.doc-col--side { border-right: 1px solid var(--el-border-color-lighter); background: var(--el-bg-color); }
.doc-col--list { border-right: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-extra-light); padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.doc-col--main { display: flex; flex-direction: column; }
.doc-list-head { display: flex; gap: 8px; }
.doc-list-tools { display: flex; gap: 8px; align-items: center; }
.doc-status-filter { flex: 1; min-width: 0; }
.doc-list-count { font-size: 12px; color: var(--el-text-color-secondary); padding: 0 2px; }
.doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 16px 20px 12px; border-bottom: 1px solid var(--el-border-color-lighter); flex-wrap: wrap; }
.doc-crumb { font-size: 12px; color: var(--el-text-color-secondary); }
.doc-title { margin: 4px 0 8px; font-size: 22px; line-height: 1.3; color: var(--el-text-color-primary); }
.doc-meta { display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--el-text-color-secondary); flex-wrap: wrap; }
.doc-save-state.is-warn { color: var(--el-color-warning); }
.doc-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.doc-conflict { margin: 12px 20px 0; }
.doc-body { flex: 1; min-height: 0; display: grid; }
.doc-body.is-edit, .doc-body.is-preview { grid-template-columns: minmax(0, 1fr); }
.doc-body.is-split { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.doc-body.is-preview { grid-template-columns: minmax(0, 1fr) 200px; }
.doc-pane { min-height: 0; overflow: auto; }
.doc-pane--preview { padding: 20px 28px 40px; }
.doc-body.is-split .doc-pane--editor { border-right: 1px solid var(--el-border-color-lighter); }
.doc-outline-col { border-left: 1px solid var(--el-border-color-lighter); padding: 16px 12px; overflow: auto; }

@media (max-width: 1200px) {
  .doc-workspace { grid-template-columns: 190px 240px minmax(0, 1fr); }
  .doc-body.is-split { grid-template-columns: minmax(0, 1fr); }
  .doc-body.is-split .doc-pane--preview { display: none; }
}
@media (max-width: 900px) {
  .doc-workspace { grid-template-columns: 1fr; height: auto; }
  .doc-col--side, .doc-col--list { border-right: 0; border-bottom: 1px solid var(--el-border-color-lighter); max-height: 320px; }
  .doc-body.is-preview { grid-template-columns: 1fr; }
  .doc-outline-col { display: none; }
}
</style>

<style scoped>
.doc-list-count { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.doc-public-link { color: var(--el-color-primary); font-size: 13px; white-space: nowrap; text-decoration: none; }
.doc-public-link:hover { text-decoration: underline; }
.doc-list-links { display: inline-flex; align-items: center; gap: 12px; }
.doc-link-button { padding: 0; border: 0; background: none; font: inherit; font-size: 13px; cursor: pointer; }
</style>
