<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { JSONContent } from '@tiptap/core';
import { documentExtensions } from './extensions';
import { sanitizeDocumentUrl } from './url';
import { uploadApi } from '@/api/common/upload';
import { ElMessage, ElMessageBox } from 'element-plus';

/** generation 由 store 在切换文档、恢复、重新载入、导入时递增；只有它变化才替换编辑器内容。 */
const props = withDefaults(defineProps<{ modelValue: JSONContent | null; editable?: boolean; generation?: number }>(), { editable: true, generation: 0 });
const emit = defineEmits<{ 'update:modelValue': [value: JSONContent] }>();

const editor = useEditor({
  extensions: documentExtensions(),
  content: props.modelValue ?? undefined,
  editable: props.editable,
  editorProps: { attributes: { class: 'doc-prose', 'aria-label': '文档正文', role: 'textbox', 'aria-multiline': 'true' } },
  onUpdate: ({ editor }) => emit('update:modelValue', editor.getJSON())
});

// 只在 generation 变化时替换内容。不能按 JSON 字符串比较：服务端与编辑器的键顺序不同，
// 比较会误判为“内容变了”，导致每次保存都重设内容、光标跳到文末并吞掉途中输入。
watch(() => props.generation, () => {
  const instance = editor.value;
  if (instance && props.modelValue) instance.commands.setContent(props.modelValue, { emitUpdate: false });
});
defineExpose({ editor });
watch(() => props.editable, (value) => editor.value?.setEditable(value));
onBeforeUnmount(() => editor.value?.destroy());

const run = (fn: (chain: any) => any) => editor.value && fn(editor.value.chain().focus()).run();
const active = (name: string, attrs?: Record<string, unknown>) => !!editor.value?.isActive(name, attrs);

async function setLink() {
  const current = editor.value?.getAttributes('link').href || '';
  const { value } = await ElMessageBox.prompt('链接地址（http(s)、mailto、站内路径或 #锚点，留空取消链接）', '设置链接', { inputValue: current }).catch(() => ({ value: null }));
  if (value === null) return;
  if (value === '') return run((c) => c.extendMarkRange('link').unsetLink());
  if (!sanitizeDocumentUrl(value)) return ElMessage.error('链接地址不安全，已拒绝');
  run((c) => c.extendMarkRange('link').setLink({ href: value }));
}

async function insertImage(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  const result = await uploadApi.upload(file, 'image');
  if (!sanitizeDocumentUrl(result.url)) return ElMessage.error('上传返回的地址无效');
  run((c) => c.setImage({ src: result.url, alt: file.name }));
}

async function insertMermaid() {
  const { value } = await ElMessageBox.prompt('Mermaid 源码', '插入图表', { inputType: 'textarea', inputValue: 'graph TD\n  A[开始] --> B[结束]' }).catch(() => ({ value: null }));
  if (value) run((c) => c.insertContent({ type: 'mermaid', attrs: { source: value } }));
}

const callout = (tone: string) => run((c) => c.wrapIn('callout', { tone }));
const imageInput = ref<HTMLInputElement>();
</script>

<template>
  <div class="doc-editor">
    <div v-if="editable" class="doc-toolbar" role="toolbar" aria-label="文档排版工具" @mousedown.prevent>
      <button type="button" :class="{ active: active('heading', { level: 2 }) }" @click="run((c) => c.toggleHeading({ level: 2 }))">H2</button>
      <button type="button" :class="{ active: active('heading', { level: 3 }) }" @click="run((c) => c.toggleHeading({ level: 3 }))">H3</button>
      <span class="sep" />
      <button type="button" aria-label="粗体" :class="{ active: active('bold') }" @click="run((c) => c.toggleBold())"><b>B</b></button>
      <button type="button" aria-label="斜体" :class="{ active: active('italic') }" @click="run((c) => c.toggleItalic())"><i>I</i></button>
      <button type="button" aria-label="行内代码" :class="{ active: active('code') }" @click="run((c) => c.toggleCode())">&lt;/&gt;</button>
      <button type="button" aria-label="链接" :class="{ active: active('link') }" @click="setLink">链接</button>
      <span class="sep" />
      <button type="button" @click="run((c) => c.toggleBulletList())">• 列表</button>
      <button type="button" @click="run((c) => c.toggleOrderedList())">1. 列表</button>
      <button type="button" @click="run((c) => c.toggleTaskList())">☑ 任务</button>
      <button type="button" @click="run((c) => c.toggleBlockquote())">引用</button>
      <span class="sep" />
      <button type="button" :class="{ active: active('codeBlock') }" @click="run((c) => c.toggleCodeBlock())">代码块</button>
      <button type="button" @click="run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))">表格</button>
      <button type="button" @click="callout('info')">提示</button>
      <button type="button" @click="callout('warning')">警告</button>
      <button type="button" @click="insertMermaid">图表</button>
      <button type="button" @click="imageInput?.click()">图片</button>
      <span class="sep" />
      <button type="button" aria-label="撤销" :disabled="!editor?.can().undo()" @click="run((c) => c.undo())">撤销</button>
      <button type="button" aria-label="重做" :disabled="!editor?.can().redo()" @click="run((c) => c.redo())">重做</button>
      <input ref="imageInput" type="file" accept=".jpg,.jpeg,.png,.gif,.webp" hidden @change="insertImage" />
    </div>
    <EditorContent :editor="editor" class="doc-editor-body" />
  </div>
</template>

<style scoped>
.doc-editor { display: flex; flex-direction: column; min-height: 0; height: 100%; }
.doc-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 2px; padding: 6px 8px; border-bottom: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-light); position: sticky; top: 0; z-index: 1; }
.doc-toolbar button { border: 0; background: transparent; padding: 4px 8px; border-radius: 4px; color: var(--el-text-color-regular); font: inherit; font-size: 12px; cursor: pointer; line-height: 1.4; }
.doc-toolbar button:hover, .doc-toolbar button.active { background: var(--el-color-primary-light-9); color: var(--el-color-primary); }
.doc-toolbar button:disabled { opacity: .4; cursor: default; }
.doc-toolbar .sep { width: 1px; height: 16px; background: var(--el-border-color); margin: 0 4px; }
.doc-editor-body { flex: 1; overflow: auto; }
</style>
