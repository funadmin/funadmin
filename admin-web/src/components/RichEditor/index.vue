<template>
  <div
    class="app-rich"
    :class="[{ 'is-disabled': disabled, 'is-fullscreen': fullscreen, 'is-source': sourceMode }]"
    :style="!fullscreen ? { height: containerHeight } : undefined"
  >
    <div class="app-rich__toolbar-row" @mousedown.prevent>
      <div v-show="!sourceMode" class="app-rich__w-bar">
        <Toolbar v-if="editorInst" :editor="editorInst" :default-config="toolbarConfig" mode="default" />
      </div>
      <div v-show="sourceMode" class="app-rich__source-label">HTML 源码</div>
      <div class="app-rich__extra">
        <ElTooltip content="源码" placement="top" :hide-after="0">
          <button
            type="button"
            class="app-rich__icon-btn"
            :class="{ active: sourceMode }"
            :disabled="disabled"
            @click="toggleSource"
          >
            <i class="i-ep-view" />
          </button>
        </ElTooltip>
        <ElTooltip :content="fullscreen ? '退出全屏' : '全屏'" placement="top" :hide-after="0">
          <button type="button" class="app-rich__icon-btn" :disabled="disabled" @click="toggleFullscreen">
            <i class="i-ep-full-screen" />
          </button>
        </ElTooltip>
      </div>
    </div>

    <div class="app-rich__body">
      <div v-show="!sourceMode" class="app-rich__editor-shell">
        <Editor
          :model-value="modelValue"
          :default-config="editorConfig"
          mode="default"
          @on-created="handleCreated"
          @update:model-value="onHtmlInput"
          @on-focus="emit('focus')"
          @on-blur="emit('blur')"
        />
      </div>
      <textarea
        v-show="sourceMode"
        v-model="sourceCode"
        class="app-rich__source"
        spellcheck="false"
        :disabled="disabled"
        @input="onSourceInput"
      />
    </div>

    <div class="app-rich__footer">
      <span>字数：{{ wordCount }}</span>
      <span class="app-rich__footer-tip">{{ sourceMode ? 'HTML 源码模式' : 'WangEditor 富文本' }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import '@wangeditor/editor/dist/css/style.css';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Editor, Toolbar } from '@wangeditor/editor-for-vue';
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor';
import { ElMessage, ElTooltip } from 'element-plus';
import { uploadApi } from '@/api/common/upload';

defineOptions({ name: 'RichEditor' });

interface Props {
  /** v-model：HTML 字符串 */
  modelValue?: string;
  /** 编辑器高度（仅非全屏） */
  height?: number | string;
  /** 占位文本 */
  placeholder?: string;
  /** 禁用 */
  disabled?: boolean;
  /** 业务分类，传给 uploadApi */
  bizType?: 'image' | 'file' | 'avatar';
  /** 单图 ≤ MB */
  maxImageSize?: number;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  height: 360,
  placeholder: '请输入内容…',
  disabled: false,
  bizType: 'image',
  maxImageSize: 5
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'change', value: string): void;
  (e: 'focus'): void;
  (e: 'blur'): void;
}>();

const editorInst = shallowRef<IDomEditor | null>(null);
const sourceMode = ref(false);
const sourceCode = ref('');
const fullscreen = ref(false);

const toolbarConfig: Partial<IToolbarConfig> = {};

const editorConfig = computed<Partial<IEditorConfig>>(() => ({
  placeholder: props.placeholder,
  MENU_CONF: {
    uploadImage: {
      customUpload: async (file: File, insertFn: (url: string, alt?: string, href?: string) => void) => {
        if (file.size > props.maxImageSize * 1024 * 1024) {
          ElMessage.warning(`图片超过 ${props.maxImageSize}MB`);
          return;
        }
        try {
          const res = await uploadApi.upload(file, props.bizType);
          insertFn(res.url, file.name);
        } catch {
          /* http 拦截器已提示 */
        }
      }
    }
  }
}));

const containerHeight = computed(() => {
  const h = props.height;
  return typeof h === 'number' ? `${h}px` : String(h);
});

const wordCount = computed(() => {
  const html = sourceMode.value ? sourceCode.value : props.modelValue || '';
  const text = html.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ');
  return text.trim().length;
});

function handleCreated(editor: IDomEditor) {
  editorInst.value = editor;
  if (props.disabled) editor.disable();
}

function onHtmlInput(html: string) {
  emit('update:modelValue', html);
  emit('change', html);
}

function onSourceInput() {
  emit('update:modelValue', sourceCode.value);
  emit('change', sourceCode.value);
}

function toggleSource() {
  if (sourceMode.value) {
    sourceMode.value = false;
    const html = sourceCode.value;
    emit('update:modelValue', html);
    emit('change', html);
    nextTick(() => editorInst.value?.setHtml(html));
  } else {
    sourceCode.value = props.modelValue || '';
    sourceMode.value = true;
  }
}

function toggleFullscreen() {
  fullscreen.value = !fullscreen.value;
}

watch(
  () => props.modelValue,
  (val) => {
    if (sourceMode.value && (val ?? '') !== sourceCode.value) {
      sourceCode.value = val ?? '';
    }
  }
);

watch(
  () => props.disabled,
  (d) => {
    const ed = editorInst.value;
    if (!ed) return;
    d ? ed.disable() : ed.enable();
  }
);

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && fullscreen.value) {
    fullscreen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  editorInst.value?.destroy();
  editorInst.value = null;
});

defineExpose({
  focus: () => editorInst.value?.focus(true),
  getHtml: () => editorInst.value?.getHtml() ?? props.modelValue,
  getText: () => editorInst.value?.getText() ?? '',
  clear: () => {
    editorInst.value?.clear();
    emit('update:modelValue', '');
    emit('change', '');
  }
});
</script>

<style lang="scss" scoped>
.app-rich {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--app-border, var(--el-border-color));
  border-radius: var(--app-radius, 8px);
  background: var(--app-card-bg, var(--el-bg-color));
  box-shadow: var(--app-shadow-sm, 0 1px 2px rgba(15, 23, 42, 0.04));
  overflow: hidden;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;

  &:focus-within:not(.is-disabled) {
    border-color: color-mix(in srgb, var(--el-color-primary) 42%, var(--app-border));
    box-shadow:
      var(--app-shadow-sm, 0 1px 2px rgba(15, 23, 42, 0.04)),
      0 0 0 1px color-mix(in srgb, var(--el-color-primary) 22%, transparent);
  }

  &.is-disabled {
    opacity: 0.65;
    pointer-events: none;
  }

  &.is-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 2000;
    height: 100vh !important;
    border-radius: 0;
    box-shadow: none;
  }

  &__toolbar-row {
    display: flex;
    align-items: stretch;
    flex-wrap: nowrap;
    gap: 8px;
    padding: 6px 8px;
    background: color-mix(in srgb, var(--el-fill-color-lighter) 88%, var(--app-card-bg, #fff));
    border-bottom: 1px solid var(--app-border, var(--el-border-color-lighter));
    min-height: 42px;
  }

  &__w-bar {
    flex: 1;
    min-width: 0;
    border: 1px solid color-mix(in srgb, var(--app-border) 55%, transparent);
    border-radius: var(--app-radius-sm, 6px);
    background: var(--el-bg-color);
    overflow: hidden;

    :deep(.w-e-toolbar) {
      border-bottom: none !important;
    }
  }

  &__source-label {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 0 12px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
    border: 1px dashed var(--el-border-color);
    border-radius: var(--app-radius-sm, 6px);
    background: var(--el-fill-color-blank);
  }

  &__extra {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
  }

  &__icon-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid color-mix(in srgb, var(--app-border) 55%, transparent);
    border-radius: var(--app-radius-sm, 6px);
    background: var(--el-fill-color-blank);
    color: var(--el-text-color-regular);
    cursor: pointer;
    transition:
      background-color 0.15s ease,
      color 0.15s ease,
      border-color 0.15s ease;

    &:hover:not(:disabled) {
      background: var(--el-fill-color-light);
      color: var(--el-color-primary);
      border-color: color-mix(in srgb, var(--el-color-primary) 22%, transparent);
    }

    &.active:not(:disabled) {
      background: var(--el-color-primary-light-9);
      color: var(--el-color-primary);
      border-color: var(--el-color-primary-light-5);
    }

    &:disabled {
      cursor: not-allowed;
      opacity: 0.45;
    }

    > i {
      font-size: 16px;
    }
  }

  &__body {
    flex: 1;
    min-height: 0;
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--el-bg-color);
  }

  &__editor-shell {
    flex: 1;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    overflow: hidden;

    :deep(.w-e-text-container) {
      background: var(--el-bg-color) !important;
    }

    :deep(.w-e-text-placeholder) {
      color: var(--el-text-color-placeholder);
    }

    :deep(.w-e-scroll) {
      flex: 1 !important;
    }

    /* Editor 根节点撑满，否则高度为 0 */
    :deep(> div) {
      height: 100%;
      display: flex;
      flex-direction: column;
    }
  }

  &__source {
    flex: 1;
    min-height: 120px;
    width: 100%;
    box-sizing: border-box;
    border: 0;
    padding: 14px 16px;
    resize: none;
    outline: none;
    font-family: 'JetBrains Mono', Consolas, 'SF Mono', monospace;
    font-size: 13px;
    line-height: 1.75;
    color: var(--el-text-color-primary);
    background: var(--el-bg-color);
    border-left: 3px solid var(--el-color-primary);
    tab-size: 2;
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--app-text) 28%, transparent) transparent;

    &::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    &::-webkit-scrollbar-thumb {
      background: color-mix(in srgb, var(--app-text) 22%, transparent);
      border-radius: 3px;
    }
  }

  &__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 14px;
    border-top: 1px solid var(--app-border, var(--el-border-color-lighter));
    background: color-mix(in srgb, var(--el-fill-color-lighter) 75%, var(--app-card-bg, #fff));
    font-size: 12px;
    color: var(--app-text-secondary, var(--el-text-color-secondary));
  }

  &__footer-tip {
    opacity: 0.92;
    font-variant-numeric: tabular-nums;
  }
}
</style>

<style lang="scss">
html.dark .app-rich .app-rich__source::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
}
html.dark .app-rich .app-rich__source::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.32);
}
html.dark .app-rich .app-rich__source {
  scrollbar-color: rgba(255, 255, 255, 0.22) transparent;
}
</style>
