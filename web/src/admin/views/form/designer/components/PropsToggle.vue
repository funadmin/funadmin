<template>
  <div class="props-toggle" :class="{ 'is-on': modelValue, 'is-disabled': disabled }" @click="onBlockClick">
    <span class="props-toggle__label" data-toggle-label :title="label">{{ label }}</span>
    <el-switch
      :model-value="modelValue"
      :disabled="disabled"
      :aria-label="label"
      size="small"
      @update:model-value="emit('update:modelValue', Boolean($event))"
    />
  </div>
</template>

<script setup lang="ts">
/** 属性面板的切换块：文字 + 开关，整块可点；一行可并排多个。 */
defineOptions({ name: 'PropsToggle' });

const props = defineProps<{ label: string; modelValue: boolean; disabled?: boolean }>();
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>();

const onBlockClick = (event: MouseEvent) => {
  // 开关自身的点击由 el-switch 处理，避免重复切换。
  if (props.disabled || (event.target as Element | null)?.closest('.el-switch')) return;
  emit('update:modelValue', !props.modelValue);
};
</script>

<style scoped>
.props-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  min-width: 0;
  padding: 5px 8px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
  background: var(--el-fill-color-blank);
  color: var(--el-text-color-regular);
  font-size: 12px;
  cursor: pointer;
  transition: border-color 0.15s, background-color 0.15s;
}
.props-toggle:hover {
  border-color: var(--el-border-color);
}
.props-toggle.is-on {
  border-color: color-mix(in srgb, var(--el-color-primary) 45%, var(--el-border-color-lighter));
  background: color-mix(in srgb, var(--el-color-primary) 8%, var(--el-bg-color));
  color: var(--el-text-color-primary);
}
.props-toggle.is-disabled {
  cursor: not-allowed;
  opacity: 0.6;
}
.props-toggle__label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.props-toggle :deep(.el-switch) {
  flex: none;
  height: 20px;
}
</style>
