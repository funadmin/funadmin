<template>
  <div
    class="inline-edit"
    :class="{ 'is-editing': editing, 'is-disabled': disabled }"
    @click="onWrapClick"
  >
    <template v-if="!editing">
      <span class="inline-edit__text" :title="String(modelValue ?? '')">
        {{ displayText }}
      </span>
      <i v-if="!disabled" class="inline-edit__icon i-ep-edit-pen" />
    </template>

    <template v-else>
      <el-input-number
        v-if="type === 'number'"
        ref="inputRef"
        v-model="draft"
        :min="min"
        :max="max"
        :step="step"
        :controls="false"
        size="small"
        class="inline-edit__input"
        @keyup.enter="onSave"
        @keyup.esc="onCancel"
        @blur="onSave"
      />
      <el-input
        v-else
        ref="inputRef"
        v-model="draft"
        :maxlength="maxlength"
        size="small"
        class="inline-edit__input"
        :placeholder="placeholder"
        @keyup.enter="onSave"
        @keyup.esc="onCancel"
        @blur="onSave"
      />
      <i v-if="saving" class="inline-edit__loading i-ep-loading" />
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * 行内编辑通用组件：
 * - 默认展示纯文本 + hover 显示铅笔
 * - 点击进入编辑态，Enter / blur 保存，Esc 取消
 * - 保存通过外部传入 `save` 函数（async），失败时回滚草稿
 *
 * 用法：
 * <InlineEdit
 *   :model-value="row.sort"
 *   type="number"
 *   :min="0" :max="999"
 *   :save="(v) => menuApi.update(row.id, { sort: v })"
 *   @update:model-value="row.sort = $event"
 * />
 */
import { computed, nextTick, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { ElMessage } from 'element-plus';

interface Props {
  modelValue: string | number | null | undefined;
  type?: 'text' | 'number';
  /** 保存回调，接收新值，返回 Promise；抛错则回滚 */
  save: (value: any) => Promise<unknown>;
  disabled?: boolean;
  placeholder?: string;
  /** number 类型专用 */
  min?: number;
  max?: number;
  step?: number;
  /** text 类型专用 */
  maxlength?: number;
  /** 空值占位（展示态） */
  emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  placeholder: '请输入',
  min: 0,
  max: 9999,
  step: 1,
  maxlength: 60,
  emptyText: '—'
});

const emit = defineEmits<{
  (e: 'update:modelValue', v: any): void;
  (e: 'change', v: any): void;
}>();

const editing = ref(false);
const saving = ref(false);
const draft = ref<any>(props.modelValue);
const inputRef = ref<ComponentPublicInstance | null>(null);

const displayText = computed(() => {
  const v = props.modelValue;
  if (v === null || v === undefined || v === '') return props.emptyText;
  return String(v);
});

watch(
  () => props.modelValue,
  (v) => {
    if (!editing.value) draft.value = v;
  }
);

function onWrapClick() {
  if (props.disabled || editing.value) return;
  draft.value = props.modelValue;
  editing.value = true;
  nextTick(() => {
    // ElInput / ElInputNumber 都暴露 focus 方法
    (inputRef.value as any)?.focus?.();
  });
}

async function onSave() {
  if (!editing.value || saving.value) return;
  const newVal = draft.value;
  // 无变化直接退出
  if (newVal === props.modelValue) {
    editing.value = false;
    return;
  }
  saving.value = true;
  try {
    await props.save(newVal);
    emit('update:modelValue', newVal);
    emit('change', newVal);
    editing.value = false;
  } catch (err: any) {
    // 回滚
    draft.value = props.modelValue;
    ElMessage.error(err?.message || '保存失败');
  } finally {
    saving.value = false;
  }
}

function onCancel() {
  draft.value = props.modelValue;
  editing.value = false;
}
</script>

<style scoped>
.inline-edit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 28px;
  padding: 2px 6px;
  margin: -2px -6px;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.inline-edit:hover:not(.is-disabled):not(.is-editing) {
  background-color: var(--el-fill-color-light);
}

.inline-edit.is-disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.inline-edit.is-editing {
  cursor: text;
  background-color: transparent;
  padding: 0;
  margin: 0;
}

.inline-edit__text {
  color: var(--el-text-color-regular);
  line-height: 1.5;
}

.inline-edit__icon {
  font-size: 13px;
  color: var(--el-text-color-placeholder);
  opacity: 0;
  transition: opacity 0.2s;
}

.inline-edit:hover .inline-edit__icon {
  opacity: 1;
}

.inline-edit__input {
  width: 100%;
  min-width: 80px;
}

.inline-edit__loading {
  font-size: 14px;
  color: var(--el-color-primary);
  animation: inline-edit-spin 0.8s linear infinite;
}

@keyframes inline-edit-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
