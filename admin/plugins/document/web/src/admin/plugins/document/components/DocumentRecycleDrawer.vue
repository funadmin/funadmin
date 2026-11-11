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

async function load() {
  loading.value = true;
  try {
    if (tab.value === 'documents') {
      if (!can.documents.value) return;
      const data = await documentApi.trashedDocuments({ page: page.value, pageSize, keyword: keyword.value.trim() || undefined });
      documents.value = data.list;
      total.value = data.total;
    } else {
      if (!can.categories.value) return;
      categories.value = (await documentApi.trashedCategories()).list;
    }
  } finally {
    loading.value = false;
  }
}

function search() {
  page.value = 1;
  void load();
}

watch(visible, (open) => {
  if (!open) return;
  tab.value = can.documents.value || !can.categories.value ? 'documents' : 'categories';
  keyword.value = '';
  page.value = 1;
  void load();
});
watch(tab, () => visible.value && void load());

async function confirmDestroy(body: string): Promise<boolean> {
  return ElMessageBox.confirm(body, tt('destroyTitle'), { type: 'error', confirmButtonText: tt('destroy'), confirmButtonClass: 'el-button--danger' })
    .then(() => true, () => false);
}

async function run(action: () => Promise<unknown>, success: string) {
  try {
    await action();
    ElMessage.success(success);
    emit('changed');
    await load();
  } catch (e) {
    ElMessage.error(message(e));
  }
}

const recoverDocument = (row: TrashedDocument) => run(() => documentApi.recoverDocument(row.id), tt('restored'));
async function destroyDocument(row: TrashedDocument) {
  if (await confirmDestroy(tt('destroyDocumentBody', { name: row.title }))) await run(() => documentApi.destroyDocument(row.id), tt('destroyed'));
}
const restoreCategory = (row: TrashedCategory) => run(() => documentApi.restoreCategory(row.id), tt('restored'));
async function destroyCategory(row: TrashedCategory) {
  if (await confirmDestroy(tt('destroyCategoryBody', { name: row.name }))) await run(() => documentApi.destroyCategory(row.id), tt('destroyed'));
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
