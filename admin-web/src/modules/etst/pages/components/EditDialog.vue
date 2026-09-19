<template>
  <el-dialog :title="t('common.edit', '编辑')" />
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>
