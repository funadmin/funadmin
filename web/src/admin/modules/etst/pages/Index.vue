<template>
  <section>
    <h1>etst</h1>
    <el-button v-perm="'etst:item:list'" @click="load">{{ t('common.refresh', '刷新') }}</el-button>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { listItems } from '../api';

const { t } = useI18n();

const load = async () => { await listItems(); };
</script>