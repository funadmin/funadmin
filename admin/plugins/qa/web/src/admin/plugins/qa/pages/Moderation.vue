<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PageWrapper from '@/components/PageWrapper/index.vue';
import ContributionsPanel from '@/plugins/cms/components/ContributionsPanel.vue';
import { cmsApi } from '@/plugins/cms/api';
import type { SiteContext } from '@/plugins/cms/types';
import { checkPermission, hasPermission } from '@/utils/permission';
import QuestionsPanel from '../components/QuestionsPanel.vue';
import { registerQaLocales } from '../locales';
registerQaLocales();
const { t } = useCmsI18n();
const sites=ref<SiteContext[]>([]),siteId=ref(''),locale=ref(''),isSuper=ref(false),error=ref(''),loading=ref(false);
const current=computed(()=>sites.value.find(site=>site.id===siteId.value));
const scope=computed(()=>({site_id:siteId.value,locale:locale.value}));
const site=(code:string)=>checkPermission(isSuper.value?['*']:current.value?.permissions,code);
const canQuestions=computed(()=>hasPermission('qa:questions:index')&&site('cms:submission:list'));
const tab=ref('moderation');
const can=(code:string)=>checkPermission(isSuper.value?['*']:current.value?.permissions,code)&&(code!=='cms:submission:review'||hasPermission('qa:moderation:review'));
let generation=0;
async function load(){const token=++generation;loading.value=true;error.value='';try{const result=await cmsApi.context('cms:submission:list');if(token!==generation)return;sites.value=result.items;isSuper.value=result.is_super;siteId.value=sites.value.find(site=>site.id===siteId.value)?.id||sites.value[0]?.id||'';}catch(e){if(token===generation)error.value=(e as {msg?:string})?.msg||t('cms.labels.couldNotLoadSiteContextCheckCmsAndQ');}finally{if(token===generation)loading.value=false;}}
watch(siteId,()=>{locale.value=current.value?.default_locale||'';});onMounted(()=>void load());onBeforeUnmount(()=>generation++);
</script>
<template>
 <PageWrapper v-loading="loading">
  <template #header><div class="qa-context"><strong>{{ t('cms.labels.qAModeration') }}</strong><el-select v-if="sites.length" v-model="siteId" :aria-label="t('cms.labels.qASite')"><el-option v-for="site in sites" :key="site.id" :label="site.name" :value="site.id" /></el-select><el-select v-if="current" v-model="locale" :aria-label="t('cms.labels.qALanguage')"><el-option v-for="language in current.locales" :key="language.locale" :label="language.name" :value="language.locale" /></el-select></div></template>
  <template #extra><el-button @click="load">{{ t('cms.labels.refreshSites') }}</el-button></template>
  <el-alert v-if="error" :title="error" type="error" :closable="false" />
  <template v-if="siteId&&locale&&!error">
   <el-tabs v-if="canQuestions" v-model="tab"><el-tab-pane :label="t('pluginQa.tabs.moderation')" name="moderation" /><el-tab-pane :label="t('pluginQa.tabs.questions')" name="questions" /></el-tabs>
   <QuestionsPanel v-if="canQuestions&&tab==='questions'" :key="`q:${siteId}:${locale}`" :scope="scope" :site="site" />
   <ContributionsPanel v-else :key="`${siteId}:${locale}`" :scope="scope" :can="can" endpoint="/plugins/qa/moderation" qa />
  </template>
  <el-empty v-else-if="!sites.length&&!error&&!loading" :description="t('cms.labels.noSitesAvailableForReviewAssignCmsSiteSubmission')" />
 </PageWrapper>
</template>
<style scoped>.qa-context{display:flex;align-items:center;flex-wrap:wrap;gap:12px}.qa-context .el-select{width:180px}</style>
