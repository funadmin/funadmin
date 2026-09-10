<template>
  <PageWrapper title="表单管理已迁移" subtitle="表单与源码生成已统一迁移到业务开发中心">
    <el-result icon="info" title="旧表单管理入口已下线" sub-title="正在前往“业务开发 / 我的业务”">
      <template #extra><el-button type="primary" @click="goNow">立即前往</el-button></template>
    </el-result>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';

defineOptions({ name: 'LegacyFormListRedirect' });
const router = useRouter();
const goNow = () => router.replace('/development/business/mine');
onMounted(goNow);
</script>
