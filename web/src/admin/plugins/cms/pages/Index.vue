<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { onMounted, ref } from 'vue';
import { checkPermission } from '@/utils/permission';
import { useRouter } from 'vue-router';
import { cmsApi } from '../api';
const { t } = useCmsI18n();
const router=useRouter(),error=ref('');
onMounted(async()=>{try{const result=await cmsApi.context();const pages=[['sites','cms:site:detail'],['categories','cms:category:list'],['content','cms:content:list'],['menus','cms:menu:list'],['tags','cms:tag:list'],['models','cms:model:list'],['access','cms:access:list']];const page=pages.find(([,code])=>result.items.some(s=>checkPermission(s.permissions,code!)))?.[0]||(result.is_super?'sites':'');if(page)await router.replace(`/admin/plugins/cms/${page}`);else error.value=t('cms.labels.noAccessibleCmsMenusAskAnAdministratorToAssign');}catch{error.value=t('cms.labels.couldNotLoadCmsCheckPluginUpgradesAndAccess');}});
</script>
<template><el-alert v-if="error" :title="error" type="error" :closable="false" /></template>
