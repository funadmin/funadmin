<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onMounted, ref, watch } from 'vue';
import { checkPermission } from '@/utils/permission';
import PageWrapper from '@/components/PageWrapper/index.vue';
import { cmsApi } from '../api';
import type { SiteContext, Settings } from '../types';
import ContentPanel from './ContentPanel.vue';
import ContributionsPanel from './ContributionsPanel.vue';
import FormsPanel from './FormsPanel.vue';
import SitePanel from './SitePanel.vue';
import CategoryPanel from './CategoryPanel.vue';
import ModelPanel from './ModelPanel.vue';
import MenuPanel from './MenuPanel.vue';
import TagPanel from './TagPanel.vue';
import AccessPanel from './AccessPanel.vue';
const { t } = useCmsI18n();
const props=defineProps<{page:'sites'|'categories'|'content'|'menus'|'tags'|'models'|'access'|'contributions'|'forms'}>();
const definitions=computed(()=>({contributions:[t('cms.labels.memberSubmissions'),'cms:submission:list'],forms:[t('cms.forms.title'),'cms:form:list'],sites:[t('cms.labels.sites'),'cms:site:detail'],categories:[t('cms.labels.categories'),'cms:category:list'],content:[t('cms.labels.content'),'cms:content:list'],menus:[t('cms.labels.navigation'),'cms:menu:list'],tags:[t('cms.labels.templateTags'),'cms:tag:list'],models:[t('cms.labels.contentModel'),'cms:model:list'],access:[t('cms.labels.sitePermissions'),'cms:access:list']} as const));
const definition=computed(()=>definitions.value[props.page]);
const sites=ref<SiteContext[]>([]),siteId=ref(''),locale=ref(''),isSuper=ref(false),settings=ref<Settings>(),bindings=ref<Settings['models']>([]),error=ref(''),loading=ref(false),ready=ref(false),generation=ref(0);
const current=computed(()=>sites.value.find(s=>s.id===siteId.value));
const languages=computed(()=>current.value?.locales||[]);
const scope=computed(()=>({site_id:siteId.value,locale:locale.value}));
const can=(code:string)=>checkPermission(isSuper.value?['*']:current.value?.permissions,code);
let requestVersion=0,contextVersion=0;
function refreshPage(){ window.location.reload(); }
async function loadSite(){
  const version=++requestVersion;ready.value=false;settings.value=undefined;bindings.value=[];error.value='';
  if(!current.value)return;
  const saved=sessionStorage.getItem(`cms.locale.${siteId.value}`);locale.value=languages.value.some(l=>l.locale===saved)?saved!:current.value.default_locale;
  loading.value=true;
  try{
    const detail=props.page==='sites'?await cmsApi.settings(siteId.value):undefined;
    const models=props.page==='models'?await cmsApi.siteModels(scope.value):[];
    if(version!==requestVersion)return;settings.value=detail;bindings.value=models;
    if(version===requestVersion){ready.value=true;generation.value++;}
  }catch{if(version===requestVersion)error.value=t('cms.labels.couldNotLoadTheCurrentSiteRefreshPermissionsAnd');}
  finally{if(version===requestVersion)loading.value=false;}
}
async function refresh(preferred?:string){
  const version=++contextVersion;ready.value=false;requestVersion++;loading.value=true;error.value='';
  try{
    const result=await cmsApi.context(definition.value[1]);if(version!==contextVersion)return;
    sites.value=result.items;isSuper.value=result.is_super;
    const requested=preferred||siteId.value||sessionStorage.getItem('cms.site');const next=sites.value.find(s=>s.id===requested)?.id||sites.value[0]?.id||'';
    if(next!==siteId.value)siteId.value=next;else await loadSite();
  }catch{if(version===contextVersion){sites.value=[];error.value=t('cms.labels.couldNotLoadCmsPermissionsCheckPluginUpgradesAnd');}}
  finally{if(version===contextVersion)loading.value=false;}
}
watch(siteId,value=>{if(value)sessionStorage.setItem('cms.site',value);void loadSite();});
watch(locale,value=>{if(value&&siteId.value)sessionStorage.setItem(`cms.locale.${siteId.value}`,value);});
onMounted(()=>{void refresh();});
</script>
<template>
  <PageWrapper class="cms-page" v-loading="loading">
    <template #header><div class="cms-header"><span class="cms-page-title">{{ definition[0] }}</span><div v-if="sites.length" class="cms-context"><span class="context-label">{{ t('cms.labels.site') }}</span><el-select v-if="sites.length>1" v-model="siteId" data-testid="cms-site-select" :aria-label="t('cms.labels.currentSite')"><el-option v-for="site in sites" :key="site.id" :label="site.name" :value="site.id" /></el-select><strong v-else class="context-value">{{ current?.name }}</strong><template v-if="page!=='access'&&page!=='forms'"><span class="context-label">{{ t('cms.labels.language') }}</span><el-select v-if="languages.length>1" v-model="locale" data-testid="cms-locale-select" :aria-label="t('cms.labels.contentLanguage')"><el-option v-for="language in languages" :key="language.locale" :label="`${language.name} (${language.locale})`" :value="language.locale" /></el-select><span v-else class="context-value">{{ locale }}</span></template></div></div></template>
    <template #extra><el-button @click="refreshPage"><i class="i-ep-refresh" />{{ t('cms.labels.refresh') }}</el-button></template>
    <el-alert v-if="error" :title="error" type="error" :closable="false" />
    <div v-if="ready&&current&&locale" :key="`${siteId}:${locale}:${generation}`">
      <ContributionsPanel v-if="page==='contributions'" :scope="scope" :can="can" />
      <FormsPanel v-if="page==='forms'" :scope="scope" :can="can" :languages="languages" />
      <ContentPanel v-if="page==='content'" :scope="scope" :can="can" :languages="languages" />
      <CategoryPanel v-if="page==='categories'" :scope="scope" :can="can" />
      <MenuPanel v-if="page==='menus'" :scope="scope" :can="can" />
      <TagPanel v-if="page==='tags'" :scope="scope" :can="can" />
      <ModelPanel v-if="page==='models'" :scope="scope" :can="can" :is-super="isSuper" :settings="{models:bindings}" @updated="refresh()" />
      <SitePanel v-if="page==='sites'" :settings="settings" :can="can" :is-super="isSuper" @updated="refresh" />
      <AccessPanel v-if="page==='access'" :scope="scope" :can="can" :is-super="isSuper" @updated="refresh()" />
    </div>
    <SitePanel v-else-if="page==='sites'&&!sites.length&&isSuper&&!error" :can="can" :is-super="isSuper" @updated="refresh" />
    <el-empty v-else-if="!sites.length&&!error&&!loading" :description="t('cms.labels.noAccessibleSitesForThisMenuAskAnAdministrator')" />
  </PageWrapper>
</template>
<style scoped>
.cms-page{width:100%;min-width:0;flex:1;align-self:stretch;margin:0}.cms-header{display:flex;align-items:center;flex-wrap:wrap;gap:12px 24px;min-width:0}.cms-page-title{font-size:16px;font-weight:600;white-space:nowrap}.cms-context{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.context-label{color:var(--el-text-color-secondary);font-size:13px}.context-value{font-size:13px;font-weight:500}.cms-context .el-select{width:180px}.cms-page :deep(main>header){flex-wrap:wrap}@media(max-width:640px){.cms-page-title{width:100%}.cms-context .el-select{width:145px}}
</style>
