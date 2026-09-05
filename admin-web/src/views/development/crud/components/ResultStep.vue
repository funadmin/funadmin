<template><el-result v-if="result" icon="success" title="CRUD 生成完成" :sub-title="`审计记录 #${result.generationId}`" /><el-empty v-else description="尚无生成结果" /></template>
<script setup lang="ts">import type { CrudGeneration } from '@/types/development/crud'; defineProps<{ result: CrudGeneration | null }>();</script>
