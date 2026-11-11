<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import TablePagination from '@/components/TablePagination/index.vue';
import { hasPermission } from '@/utils/permission';
import { documentApi } from '../api';
import type { TrashedCategory, TrashedDocument } from '../types';

/**
 * 回收站：已删除的文档与目录。恢复与彻底删除各有独立权限（document:document:recover / destroy、
 * document:category:restore / destroy）；彻底删除需二次确认，不可恢复。
 */
const emit = defineEmits<{ changed: [] }>();
const visible = defineModel<boolean>({ required: true });
const { t } = useI18n();
const tt = (key: string, args?: Record<string, unknown>) => (args ? t(`pluginDocument.recycle.${key}`, args) : t(`pluginDocument.recycle.${key}`));

const can = {
  documents: computed(() => hasPermission('document:document:recycle')),
  recoverDocument: computed(() => hasPermission('document:document:recover')),
  destroyDocument: computed(() => hasPermission('document:document:destroy')),
  categories: computed(() => hasPermission('document:category:recycle')),
  restoreCategory: computed(() => hasPermission('document:category:restore')),
  destroyCategory: computed(() => hasPermission('document:category:destroy'))
};

const tab = ref<'documents' | 'categories'>('documents');
const keyword = ref('');
const page = ref(1);
const pageSize = 20;
const documents = ref<TrashedDocument[]>([]);
const total = ref(0);
const categories = ref<TrashedCategory[]>([]);
const loading = ref(false);

const message = (e: unknown) => {
  const m = (e as { msg?: unknown } | null)?.msg;
  return typeof m === 'string' && m ? m : tt('failed');
};

/** 请求序号：只采用最后一次请求的响应，先发出、后返回的过期响应直接丢弃。 */
let requestId = 0;

async function load() {
  const id = ++requestId;
  loading.value = true;
  try {
    if (tab.value === 'documents') {
      if (!can.documents.value) return;
      const data = await documentApi.trashedDocuments({ page: page.value, pageSize, keyword: keyword.value.trim() || undefined });
      if (id !== requestId) return;
      // 当前页已被清空（恢复或彻底删除了最后一条）时回到上一页。
      if (!data.list.length && page.value > 1) {
        page.value -= 1;
        await load();
        return;
      }
      documents.value = data.list;
      total.value = data.total;
    } else {
      if (!can.categories.value) return;
      const data = await documentApi.trashedCategories();
      if (id !== requestId) return;
      categories.value = data.list;
    }
  } catch (e) {
    if (id === requestId) ElMessage.error(message(e));
  } finally {
    if (id === requestId) loading.value = false;
  }
}

function search() {
  page.value = 1;
  void load();
}

/** 打开时选定页签后只加载一次：页签变化由 tab 的监听加载，未变化时在这里加载。 */
watch(visible, (open) => {
  if (!open) return;
  keyword.value = '';
  page.value = 1;
  const next = can.documents.value || !can.categories.value ? 'documents' : 'categories';
  if (tab.value === next) void load();
  else tab.value = next;
});
watch(tab, () => {
  page.value = 1;
  if (visible.value) void load();
});

async function confirmDestroy(body: string): Promise<boolean> {
  return ElMessageBox.confirm(body, tt('destroyTitle'), { type: 'error', confirmButtonText: tt('destroy'), confirmButtonClass: 'el-button--danger' })
    .then(() => true, () => false);
}

/** 执行恢复或彻底删除；notice 按接口结果返回提示（警告用于部分未恢复的情况）。 */
async function run<T>(action: () => Promise<T>, notice: (result: T) => { type: 'success' | 'warning'; text: string }) {
  let result: T;
  try {
    result = await action();
  } catch (e) {
    ElMessage.error(message(e));
    return;
  }
  const { type, text } = notice(result);
  ElMessage[type](text);
  emit('changed');
  await load();
}
const done = (text: string) => () => ({ type: 'success' as const, text });

/** 恢复文档：有目录关联因目录已删除或范围变化未恢复时，提示跳过的数量。 */
const recoverDocument = (row: TrashedDocument) => run(() => documentApi.recoverDocument(row.id), (result) => {
  const skipped = result?.skippedLinks ?? 0;
  return skipped > 0 ? { type: 'warning', text: tt('restoredSkipped', { n: skipped }) } : { type: 'success', text: tt('restored') };
});
async function destroyDocument(row: TrashedDocument) {
  if (await confirmDestroy(tt('destroyDocumentBody', { name: row.title }))) await run(() => documentApi.destroyDocument(row.id), done(tt('destroyed')));
}
const restoreCategory = (row: TrashedCategory) => run(() => documentApi.restoreCategory(row.id), done(tt('restored')));
async function destroyCategory(row: TrashedCategory) {
  if (await confirmDestroy(tt('destroyCategoryBody', { name: row.name }))) await run(() => documentApi.destroyCategory(row.id), done(tt('destroyed')));
}
</script>

<template>
  <el-drawer v-model="visible" :title="tt('title')" size="min(640px, 92vw)" append-to-body>
    <div v-loading="loading" class="rb">
      <p class="rb-hint">{{ tt('hint') }}</p>
      <el-radio-group v-model="tab" size="small">
        <el-radio-button v-if="can.documents.value" value="documents">{{ tt('documents') }}</el-radio-button>
        <el-radio-button v-if="can.categories.value" value="categories">{{ tt('categories') }}</el-radio-button>
      </el-radio-group>

      <template v-if="tab === 'documents'">
        <el-input v-model="keyword" size="small" :placeholder="tt('search')" clearable @keyup.enter="search" @clear="search" />
        <el-empty v-if="!documents.length" :description="tt('empty')" :image-size="64" />
        <ul v-else class="rb-list">
          <li v-for="row in documents" :key="row.id" class="rb-item" data-testid="trashed-document">
            <div class="rb-main">
              <span class="rb-name">{{ row.title }}</span>
              <span class="rb-meta">{{ row.slug }} · {{ t(`pluginDocument.scope.${row.scope}`) }} · {{ tt('deletedAt', { time: row.deletedAt.slice(0, 16) }) }}</span>
            </div>
            <div class="rb-actions">
              <el-button v-if="can.recoverDocument.value" size="small" data-testid="recover" @click="recoverDocument(row)">{{ tt('restore') }}</el-button>
              <el-button v-if="can.destroyDocument.value" size="small" type="danger" plain data-testid="destroy" @click="destroyDocument(row)">{{ tt('destroy') }}</el-button>
            </div>
          </li>
        </ul>
        <TablePagination
          v-if="total > pageSize"
          small layout="prev, pager, next" :total="total" :page-size="pageSize" :current-page="page"
          @current-change="(p: number) => { page = p; void load(); }"
        />
      </template>

      <template v-else>
        <el-empty v-if="!categories.length" :description="tt('empty')" :image-size="64" />
        <ul v-else class="rb-list">
          <li v-for="row in categories" :key="row.id" class="rb-item" data-testid="trashed-category">
            <div class="rb-main">
              <span class="rb-name">{{ row.name }}</span>
              <span class="rb-meta">{{ row.slug }} · {{ t(`pluginDocument.scope.${row.scope}`) }} · {{ tt('deletedAt', { time: row.deletedAt.slice(0, 16) }) }}</span>
            </div>
            <div class="rb-actions">
              <el-button v-if="can.restoreCategory.value" size="small" data-testid="restore" @click="restoreCategory(row)">{{ tt('restore') }}</el-button>
              <el-button v-if="can.destroyCategory.value" size="small" type="danger" plain data-testid="destroy" @click="destroyCategory(row)">{{ tt('destroy') }}</el-button>
            </div>
          </li>
        </ul>
      </template>
    </div>
  </el-drawer>
</template>

<style scoped>
.rb { display: flex; flex-direction: column; gap: 12px; min-height: 200px; }
.rb-hint { margin: 0; font-size: 12px; color: var(--el-text-color-secondary); }
.rb-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.rb-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.rb-main { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.rb-name { font-size: 14px; color: var(--el-text-color-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rb-meta { font-size: 12px; color: var(--el-text-color-placeholder); }
.rb-actions { display: flex; gap: 6px; flex-shrink: 0; }
</style>
