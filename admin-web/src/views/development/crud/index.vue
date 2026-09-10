<template>
  <PageWrapper title="CRUD Workbench 已迁移" subtitle="数据库生成已统一迁移到业务开发中心">
    <el-result icon="info" title="旧 CRUD Workbench 已下线" sub-title="正在前往“业务开发 / 从数据库生成”">
      <template #extra><el-button type="primary" @click="goNow">立即前往</el-button></template>
    </el-result>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';

defineOptions({ name: 'LegacyDevelopmentCrudRedirect' });
const router = useRouter();
const goNow = () => router.replace('/development/business/database');
onMounted(goNow);
</script>
