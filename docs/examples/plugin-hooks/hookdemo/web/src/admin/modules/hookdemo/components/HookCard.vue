<template>
  <el-card class="hook-demo-card" shadow="never">
    <template #header>插件扩展卡片</template>
    <p>{{ message }}</p>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { hooks, HookCancelledError } from '@/shared/plugins/hooks';

defineProps<{ context?: Record<string, unknown> }>();
const message = ref('全局钩子可以在普通组件和插件组件中调用。');
const scope = new AbortController();

onMounted(async () => {
  try {
    message.value = await hooks.filter('plugin.hookdemo.decorate', '组件扩展已加载', {
      signal: scope.signal
    });
  } catch (error) {
    if (!(error instanceof HookCancelledError)) console.error(error);
  }
});
onBeforeUnmount(() => scope.abort());
</script>

<style scoped>
.hook-demo-card { margin-top: 16px; }
</style>
