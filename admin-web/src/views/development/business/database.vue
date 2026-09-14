<template>
  <BusinessVisual initial-mode="adopted" />
</template>

<script setup lang="ts">
import BusinessVisual from './visual.vue';

// 保留原动态路由和 inspect 权限绑定，避免只读用户被重定向到无权访问的创建路由。
defineOptions({ name: 'BusinessDatabase' });
</script>
