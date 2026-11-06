<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { JSONContent } from '@tiptap/core';
import DOMPurify from 'dompurify';
import { exportDocumentHtml } from './content';

const props = defineProps<{ content: JSONContent | null }>();
const root = ref<HTMLElement>();
const html = computed(() => (props.content ? exportDocumentHtml(props.content) : ''));

let renderToken = 0;
/** 预览时才渲染 Mermaid：源码进、净化后的 SVG 出，SVG 不保存。 */
async function renderMermaid() {
  const token = ++renderToken;
  await nextTick();
  const blocks = root.value?.querySelectorAll<HTMLElement>('[data-mermaid]');
  if (!blocks?.length) return;
  const { default: mermaid } = await import('mermaid');
  // htmlLabels:false 让节点文字用 SVG <text> 绘制；HTML 标签在 <foreignObject> 里，会被 SVG 净化整体移除。
  mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', htmlLabels: false, flowchart: { htmlLabels: false } });
  for (const [index, block] of Array.from(blocks).entries()) {
    if (token !== renderToken) return;
    const source = block.querySelector('code')?.textContent ?? '';
    try {
      const { svg } = await mermaid.render(`doc-mermaid-${token}-${index}`, source);
      if (token !== renderToken) return;
      block.innerHTML = DOMPurify.sanitize(svg, { USE_PROFILES: { svg: true, svgFilters: true } });
    } catch {
      // 语法错误时保留源码显示，并以样式提示。
      block.classList.add('doc-mermaid--error');
    }
  }
}

watch(html, () => void renderMermaid(), { immediate: true });
onBeforeUnmount(() => renderToken++);
</script>

<template>
  <!-- html 由 exportDocumentHtml 从白名单 schema 生成并经 DOMPurify 净化 -->
  <article ref="root" class="doc-prose doc-preview" v-html="html" />
</template>
