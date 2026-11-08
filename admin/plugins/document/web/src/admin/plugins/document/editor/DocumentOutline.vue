<script setup lang="ts">
import { computed } from 'vue';
import type { JSONContent } from '@tiptap/core';

const props = defineProps<{ content: JSONContent | null }>();

interface Heading { level: number; text: string; index: number }
const headings = computed<Heading[]>(() => {
  const result: Heading[] = [];
  (props.content?.content || []).forEach((node, index) => {
    if (node.type !== 'heading') return;
    const text = (node.content || []).map((child) => child.text || '').join('').trim();
    if (text) result.push({ level: Number(node.attrs?.level) || 2, text, index });
  });
  return result;
});

const minLevel = computed(() => Math.min(...headings.value.map((h) => h.level), 6));
const emit = defineEmits<{ jump: [index: number] }>();
</script>

<template>
  <nav class="doc-outline" aria-label="文档大纲">
    <div class="doc-outline-title">大纲</div>
    <p v-if="!headings.length" class="doc-outline-empty">添加二级、三级标题后显示大纲</p>
    <button
      v-for="h in headings"
      :key="h.index"
      type="button"
      class="doc-outline-item"
      :style="{ paddingLeft: `${(h.level - minLevel) * 12 + 8}px` }"
      @click="emit('jump', h.index)"
    >
      {{ h.text }}
    </button>
  </nav>
</template>

<style scoped>
.doc-outline-title { font-size: 12px; font-weight: 600; color: var(--el-text-color-primary); margin-bottom: 8px; }
.doc-outline-empty { font-size: 12px; color: var(--el-text-color-placeholder); margin: 0; }
.doc-outline-item { display: block; width: 100%; text-align: left; border: 0; background: transparent; padding: 4px 8px; border-radius: 4px; font: inherit; font-size: 12px; color: var(--el-text-color-regular); cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-outline-item:hover { background: var(--el-fill-color-light); color: var(--el-color-primary); }
</style>
