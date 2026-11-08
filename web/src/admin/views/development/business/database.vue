<template>
  <BusinessVisual v-if="!redirected" initial-mode="adopted" />
</template>

<script setup lang="ts">
import BusinessVisual from './visual.vue';
import { useLegacyCodegenRedirect } from './codegen/useLegacyCodegenRedirect';

// 保留原动态路由和 inspect 权限绑定，避免只读用户被重定向到无权访问的创建路由。
// 拥有代码生成页权限时改为跳到代码生成页并打开「装载数据表」弹窗。
defineOptions({ name: 'BusinessDatabase' });

const { redirected } = useLegacyCodegenRedirect({ load: '1' });
</script>
