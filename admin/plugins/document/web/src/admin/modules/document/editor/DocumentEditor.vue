<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { JSONContent } from '@tiptap/core';
import { NodeSelection } from '@tiptap/pm/state';
import { documentExtensions } from './extensions';
import { sanitizeDocumentUrl } from './url';
import { uploadApi } from '@/api/common/upload';
import { ElMessage, ElMessageBox } from 'element-plus';

/** generation 由 store 在切换文档、恢复、重新载入、导入时递增；只有它变化才替换编辑器内容。 */
const props = withDefaults(defineProps<{ modelValue: JSONContent | null; editable?: boolean; generation?: number }>(), { editable: true, generation: 0 });
const emit = defineEmits<{ 'update:modelValue': [value: JSONContent] }>();
const { t } = useI18n();
const tt = (key: string) => t(`pluginDocument.${key}`);

const editor = useEditor({
  extensions: documentExtensions(),
  content: props.modelValue ?? undefined,
  editable: props.editable,
  editorProps: { attributes: { class: 'doc-prose', 'aria-label': tt('toolbar.body'), role: 'textbox', 'aria-multiline': 'true' } },
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

/** 当前选中的 Mermaid 图表（点击图表即选中整个节点）。 */
const selectedMermaid = computed(() => {
  const selection = editor.value?.state.selection;
  return selection instanceof NodeSelection && selection.node.type.name === 'mermaid' ? selection.node : null;
});

async function setLink() {
  const current = editor.value?.getAttributes('link').href || '';
  const { value } = await ElMessageBox.prompt(tt('editor.linkPrompt'), tt('editor.linkTitle'), { inputValue: current }).catch(() => ({ value: null }));
  if (value === null) return;
  if (value === '') return run((c) => c.extendMarkRange('link').unsetLink());
  if (!sanitizeDocumentUrl(value)) return ElMessage.error(tt('editor.unsafeLink'));
  run((c) => c.extendMarkRange('link').setLink({ href: value }));
}

async function upload(event: Event, kind: 'image' | 'file') {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  let result: { url: string; name?: string };
  try {
    result = await uploadApi.upload(file, kind);
  } catch {
    return ElMessage.error(tt('editor.uploadFailed'));
  }
  if (!sanitizeDocumentUrl(result.url)) return ElMessage.error(tt('editor.unsafeUpload'));
  const name = result.name || file.name;
  if (kind === 'image') run((c) => c.setImage({ src: result.url, alt: name }));
  else run((c) => c.insertContent([{ type: 'text', text: name, marks: [{ type: 'link', attrs: { href: result.url } }] }, { type: 'text', text: ' ' }]));
}

/** 选中已有图表时编辑其源码；否则插入新图表。 */
async function editMermaid() {
  const existing = selectedMermaid.value;
  const { value } = await ElMessageBox.prompt(tt('editor.diagramPrompt'), tt(existing ? 'editor.editDiagramTitle' : 'editor.diagramTitle'), {
    inputType: 'textarea',
    inputValue: existing ? String(existing.attrs.source ?? '') : 'graph TD\n  A --> B'
  }).catch(() => ({ value: null }));
  if (!value) return;
  if (existing) run((c) => c.updateAttributes('mermaid', { source: value }));
  else run((c) => c.insertContent({ type: 'mermaid', attrs: { source: value } }));
}

const callout = (tone: string) => run((c) => c.wrapIn('callout', { tone }));
const imageInput = ref<HTMLInputElement>();
const fileInput = ref<HTMLInputElement>();
</script>

<template>
  <div class="doc-editor">
    <div v-if="editable" class="doc-toolbar" role="toolbar" :aria-label="tt('toolbar.aria')" @mousedown.prevent>
      <button type="button" :class="{ active: active('heading', { level: 2 }) }" @click="run((c) => c.toggleHeading({ level: 2 }))">{{ tt('toolbar.h2') }}</button>
      <button type="button" :class="{ active: active('heading', { level: 3 }) }" @click="run((c) => c.toggleHeading({ level: 3 }))">{{ tt('toolbar.h3') }}</button>
      <span class="sep" />
      <button type="button" :aria-label="tt('toolbar.bold')" :title="tt('toolbar.bold')" :class="{ active: active('bold') }" @click="run((c) => c.toggleBold())"><b>B</b></button>
      <button type="button" :aria-label="tt('toolbar.italic')" :title="tt('toolbar.italic')" :class="{ active: active('italic') }" @click="run((c) => c.toggleItalic())"><i>I</i></button>
      <button type="button" :aria-label="tt('toolbar.code')" :title="tt('toolbar.code')" :class="{ active: active('code') }" @click="run((c) => c.toggleCode())">&lt;/&gt;</button>
      <button type="button" :class="{ active: active('link') }" @click="setLink">{{ tt('toolbar.link') }}</button>
      <span class="sep" />
      <button type="button" @click="run((c) => c.toggleBulletList())">{{ tt('toolbar.bullet') }}</button>
      <button type="button" @click="run((c) => c.toggleOrderedList())">{{ tt('toolbar.ordered') }}</button>
      <button type="button" @click="run((c) => c.toggleTaskList())">{{ tt('toolbar.task') }}</button>
      <button type="button" @click="run((c) => c.toggleBlockquote())">{{ tt('toolbar.quote') }}</button>
      <span class="sep" />
      <button type="button" :class="{ active: active('codeBlock') }" @click="run((c) => c.toggleCodeBlock())">{{ tt('toolbar.codeBlock') }}</button>
      <button type="button" @click="run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))">{{ tt('toolbar.table') }}</button>
      <button type="button" @click="callout('info')">{{ tt('toolbar.tip') }}</button>
      <button type="button" @click="callout('warning')">{{ tt('toolbar.warning') }}</button>
      <button type="button" :class="{ active: !!selectedMermaid }" :title="tt(selectedMermaid ? 'toolbar.editDiagram' : 'toolbar.diagram')" @click="editMermaid">{{ tt('toolbar.diagram') }}</button>
      <button type="button" @click="imageInput?.click()">{{ tt('toolbar.image') }}</button>
      <button type="button" @click="fileInput?.click()">{{ tt('toolbar.attachment') }}</button>
      <span class="sep" />
      <button type="button" :aria-label="tt('toolbar.undo')" :disabled="!editor?.can().undo()" @click="run((c) => c.undo())">{{ tt('toolbar.undo') }}</button>
      <button type="button" :aria-label="tt('toolbar.redo')" :disabled="!editor?.can().redo()" @click="run((c) => c.redo())">{{ tt('toolbar.redo') }}</button>
      <input ref="imageInput" data-upload="image" type="file" accept=".jpg,.jpeg,.png,.gif,.webp" hidden @change="upload($event, 'image')" />
      <input ref="fileInput" data-upload="file" type="file" hidden @change="upload($event, 'file')" />
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
