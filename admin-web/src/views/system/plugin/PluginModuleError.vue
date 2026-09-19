<template>
  <div class="m-6 rounded border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
    <h2 class="mb-2 text-lg font-semibold">{{ t('plugin.moduleLoadFailed', '插件模块加载失败') }}</h2>
    <p>{{ t('plugin.pluginLine', { name: plugin }, { default: '插件：{name}' }) }}</p>
    <p>{{ t('plugin.stageLine', { stage }, { default: '阶段：{stage}' }) }}</p>
    <p class="mt-2">{{ message }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{ plugin: string; stage: string; message: string }>();

const { t } = useI18n();
</script>
