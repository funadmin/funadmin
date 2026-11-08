<template>
  <PageWrapper v-if="!redirected" :title="t('business.records.title', '生成记录')" :subtitle="subtitle">
    <RecordsPanel @subtitle-change="subtitle = $event" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import RecordsPanel from './codegen/RecordsPanel.vue';
import { useLegacyCodegenRedirect } from './codegen/useLegacyCodegenRedirect';

// 菜单 BusinessRecords 已并入代码生成页的「生成记录」页签；无代码生成权限的角色仍使用本页面。
defineOptions({ name: 'BusinessRecords' });

const { t } = useI18n();
const subtitle = ref('');
const { redirected } = useLegacyCodegenRedirect({ tab: 'records' });
</script>
