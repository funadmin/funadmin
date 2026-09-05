<template>
  <div class="m-6 rounded border border-red-200 bg-red-50 p-6 text-red-800" role="alert">
    <h2 class="mb-2 text-lg font-semibold">插件模块加载失败</h2>
    <p>插件：{{ plugin }}</p>
    <p>阶段：{{ stage }}</p>
    <p class="mt-2">{{ message }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{ plugin: string; stage: string; message: string }>();
</script>
