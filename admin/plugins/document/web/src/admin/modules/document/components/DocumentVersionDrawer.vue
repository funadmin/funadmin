<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { documentApi } from '../api';
import type { RevisionSummary } from '../types';
import DocumentPreview from '../editor/DocumentPreview.vue';
import type { JSONContent } from '@tiptap/core';

const props = defineProps<{ documentId: number; canRestore: boolean }>();
const emit = defineEmits<{ restore: [revisionNo: number] }>();
const visible = defineModel<boolean>({ required: true });

const revisions = ref<RevisionSummary[]>([]);
const selected = ref<number | null>(null);
const preview = ref<JSONContent | null>(null);
const loading = ref(false);

async function load() {
  loading.value = true;
  try {
    revisions.value = (await documentApi.revisions(props.documentId)).list;
    selected.value = revisions.value[0]?.revisionNo ?? null;
  } finally {
    loading.value = false;
  }
}

watch(selected, async (revisionNo) => {
  preview.value = null;
  if (revisionNo === null) return;
  const detail = await documentApi.revision(props.documentId, revisionNo);
  if (selected.value === revisionNo) preview.value = detail.content;
});
watch(visible, (open) => open && void load());
onMounted(() => visible.value && void load());

async function restore(revisionNo: number) {
  const ok = await ElMessageBox.confirm(`将第 ${revisionNo} 版的内容恢复为当前草稿？历史版本不会被删除，当前编辑中的内容会被替换。`, '恢复版本', { type: 'warning' }).then(() => true, () => false);
  if (!ok) return;
  emit('restore', revisionNo);
  ElMessage.success('已恢复');
  visible.value = false;
}
</script>

<template>
  <el-drawer v-model="visible" title="版本历史" size="min(860px, 92vw)" append-to-body>
    <div v-loading="loading" class="rev-layout">
      <el-empty v-if="!revisions.length && !loading" description="还没有发布过的版本" :image-size="64" />
      <ul v-else class="rev-list">
        <li v-for="rev in revisions" :key="rev.revisionNo">
          <button type="button" class="rev-item" :class="{ active: selected === rev.revisionNo }" @click="selected = rev.revisionNo">
            <strong>第 {{ rev.revisionNo }} 版</strong>
            <span>{{ rev.changeSummary || '无变更说明' }}</span>
            <small>{{ rev.createdAt.slice(0, 16) }}</small>
          </button>
          <el-button v-if="canRestore" size="small" link type="primary" @click="restore(rev.revisionNo)">恢复</el-button>
        </li>
      </ul>
      <div class="rev-preview"><DocumentPreview :content="preview" /></div>
    </div>
  </el-drawer>
</template>

<style scoped>
.rev-layout { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 16px; min-height: 300px; }
.rev-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.rev-list li { display: flex; align-items: flex-start; gap: 4px; }
.rev-item { flex: 1; display: flex; flex-direction: column; gap: 2px; padding: 8px; border: 1px solid transparent; border-radius: 6px; background: transparent; font: inherit; font-size: 12px; text-align: left; cursor: pointer; color: var(--el-text-color-regular); }
.rev-item.active { border-color: var(--el-color-primary-light-5); background: var(--el-color-primary-light-9); }
.rev-item small { color: var(--el-text-color-placeholder); }
.rev-preview { border-left: 1px solid var(--el-border-color-lighter); padding-left: 16px; overflow: auto; }
@media (max-width: 720px) { .rev-layout { grid-template-columns: 1fr; } .rev-preview { border-left: 0; padding-left: 0; } }
</style>
