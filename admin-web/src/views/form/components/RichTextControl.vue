<template>
  <el-input
    :model-value="String(modelValue ?? '')"
    type="textarea"
    v-bind="bindings"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue?: unknown;
  bindings?: Record<string, unknown>;
}>(), { modelValue: () => '', bindings: () => ({}) });

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>();
</script>
