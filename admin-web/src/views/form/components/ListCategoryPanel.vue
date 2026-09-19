<template>
  <aside class="list-category-panel" :aria-label="t('formData.categoryPanelAria', '列表分类')">
    <div class="mb-3 font-medium">{{ t('formData.category', '分类') }}</div>
    <slot name="categoryToolbar" />
    <button type="button" :aria-pressed="modelValue === undefined || modelValue === ''" @click="$emit('change', undefined)">{{ t('formData.all', '全部') }}</button>
    <button v-for="option in options" :key="String(option.value)" type="button" :disabled="option.disabled" :aria-pressed="modelValue !== undefined && String(modelValue) === String(option.value)" @click="$emit('change', option.value)"><span>{{ option.label }}</span><slot name="categoryNode" :row="{ id: option.value, label: option.label }" /></button>
  </aside>
</template>
<script setup lang="ts">
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
defineProps<{ options: Array<{ label: string; value: string | number; disabled?: boolean }>; modelValue?: string | number }>();
defineEmits<{ change: [value: string | number | undefined] }>();
</script>
<style scoped>
.list-category-panel { width: 200px; flex-shrink: 0; padding: 16px; border: 1px solid var(--el-border-color); border-radius: 6px; align-self: flex-start; }
button { display: block; width: 100%; padding: 9px 12px; text-align: left; border-radius: 4px; cursor: pointer; }
button[aria-pressed="true"] { color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
button:disabled { opacity: .5; cursor: not-allowed; }
@media (max-width: 768px) { .list-category-panel { width: 100%; } }
</style>
