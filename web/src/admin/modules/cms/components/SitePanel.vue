<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import ConfigurationPanel from './ConfigurationPanel.vue';
import ContributionSettingsPanel from './ContributionSettingsPanel.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { cmsApi } from '../api';
import type { Settings, SiteEditorOptions, FormData } from '../types';
import { useOperation } from './useOperation';
const { t } = useCmsI18n();
const props = defineProps<{settings?: Settings; can: (permission: string) => boolean; isSuper: boolean}>();
const emit = defineEmits<{updated: [id?: string]}>();
const { busy, error, run } = useOperation();
const dialog = ref(''), form = ref<FormData>({});
const activeTab = ref('site');
const commonLanguages = computed(() => [
  {value:'zh-cn',label:t('cms.labels.simplifiedChineseZhCn')}, {value:'zh-tw',label:t('cms.labels.traditionalChineseZhTw')},
  {value:'en-us',label:t('cms.labels.englishEnUs')}, {value:'en-gb',label:t('cms.labels.englishEnGb')},
  {value:'ja-jp',label:t('cms.labels.japaneseJaJp')}, {value:'ko-kr',label:t('cms.labels.koreanKoKr')},
  {value:'fr-fr',label:t('cms.labels.frenchFranAisFrFr')}, {value:'de-de',label:t('cms.labels.germanDeutschDeDe')},
  {value:'es-es',label:t('cms.labels.spanishEspaOlEsEs')}, {value:'pt-br',label:t('cms.labels.portuguesePortuguSPtBr')},
  {value:'ru-ru',label:t('cms.labels.russianRuRu')}, {value:'ar-sa',label:t('cms.labels.arabicArSa')},
  {value:'it-it',label:t('cms.labels.italianItalianoItIt')}, {value:'vi-vn',label:t('cms.labels.vietnameseTiNgViTViVn')},
  {value:'th-th',label:t('cms.labels.thaiThTh')}, {value:'id-id',label:t('cms.labels.indonesianBahasaIndonesiaIdId')},
]);
function languageLabel(locale:string):string { return commonLanguages.value.find(item=>item.value===locale)?.label || locale; }
const defaultLanguageOptions=computed(()=>{
  const choices:Array<{value:string;label:string;disabled?:boolean}>=form.value.id
    ?(props.settings?.locales||[]).filter(item=>Number(item.status)===1).map(item=>({value:item.locale,label:languageLabel(item.locale)}))
    :[...commonLanguages.value];
  const value=form.value.default_locale;
  if(value&&!choices.some(item=>item.value===value))choices.push({value,label:form.value.id?t('cms.labels.valueNotEnabled', { value1: languageLabel(value) }):value,disabled:!!form.value.id});
  return choices;
});
const themes=ref<SiteEditorOptions['themes']>([]),themesLoading=ref(false),themesReady=ref(false),themesError=ref('');
let themeRequest=0;
const themeOptions=computed(()=>{
  const choices:Array<{value:string;label:string;disabled?:boolean}>=[...themes.value];
  const value=form.value.theme;
  if(value&&!choices.some(item=>item.value===value))choices.push({value,label:themesReady.value?t('cms.labels.savedThemeCurrentlyUnavailableValue', { value1: value }):themeLabel(value),disabled:true});
  return choices;
});
function themeLabel(theme:string):string { return themes.value.find(item=>item.value===theme)?.label || (theme==='default'?t('cms.labels.defaultBusinessAndNewsThemeDefault'):theme); }
async function loadThemes(){
  if(!allowed('site',!!form.value.id))return;
  const request=++themeRequest,siteId=form.value.id as string|undefined;
  themesLoading.value=true;themesError.value='';
  try{
    const result=await cmsApi.siteOptions(siteId);
    if(!Array.isArray(result.themes))throw new Error('Invalid theme options');
    if(request===themeRequest){themes.value=result.themes;themesReady.value=true;}
  }catch{if(request===themeRequest)themesError.value=t('cms.labels.couldNotLoadThemesPleaseRetry');}
  finally{if(request===themeRequest)themesLoading.value=false;}
}
watch(dialog,value=>{if(value!=='site'){++themeRequest;themesLoading.value=false;}});
onBeforeUnmount(()=>{++themeRequest;});

const domains = computed(() => props.settings?.domains || []);
function allowed(type:string, existing=true) { return type==='site' ? existing ? props.can('cms:site:edit') : props.isSuper : props.can(type==='locale'?'cms:site:locale':'cms:site:domain'); }
function open(type: string, value?: FormData) { if(!allowed(type,!!value))return;
  dialog.value = type;
  form.value = value ? JSON.parse(JSON.stringify(value)) : type === 'site' ? {code:'',name:'',host:'',default_locale:'zh-cn',theme:'default',status:1,settings:{}} : type === 'locale' ? {locale:'en-us',name:'English',url_prefix:'en',status:1,seo_title:'',seo_description:''} : {host:'',primary:false,status:1};
  if(type==='site'){themesReady.value=false;void loadThemes();}
}
async function save() { if(!allowed(dialog.value,!!form.value.id))return;
  if(dialog.value==='site'&&(themesLoading.value||themesError.value))return;
  await run(async () => {
    const site_id = props.settings?.site.id;
    const {settings: _settings, ...metadata} = form.value;
    if (dialog.value === 'site') { const result = await cmsApi.saveSite(metadata); emit('updated',result.id); }
    if (dialog.value === 'locale') { await cmsApi.saveLocale({...metadata,site_id}); emit('updated',site_id); }
    if (dialog.value === 'domain') { await cmsApi.saveDomain({...form.value,site_id}); emit('updated',site_id); }
    dialog.value = '';
  });
}
</script>
<template>
  <div><el-alert v-if="error" :title="error" type="error" :closable="false" /><div class="actions"><el-button v-if="isSuper && can('cms:sites:save')" type="primary" @click="open('site')">{{ t('cms.labels.newSite') }}</el-button><el-button v-if="settings && can('cms:site:edit')" @click="open('site', settings.site)">{{ t('cms.labels.editSite') }}</el-button></div>
    <el-tabs v-if="settings" v-model="activeTab" class="site-tabs">
      <el-tab-pane :label="t('cms.labels.basicInformation')" name="site"><el-descriptions :column="2" border><el-descriptions-item :label="t('cms.labels.siteName')">{{ settings.site.name }}</el-descriptions-item><el-descriptions-item :label="t('cms.labels.defaultLanguage')">{{ languageLabel(settings.site.default_locale) }}</el-descriptions-item><el-descriptions-item :label="t('cms.labels.theme')">{{ themeLabel(settings.site.theme) }}</el-descriptions-item><el-descriptions-item :label="t('cms.labels.status')">{{ settings.site.status ? t('cms.labels.enabled') : t('cms.labels.disabled2') }}</el-descriptions-item></el-descriptions></el-tab-pane>
      <el-tab-pane :label="t('cms.labels.websiteSettings2')" name="configuration">
      <ConfigurationPanel :site-id="settings.site.id" :locales="settings.locales" :can="can" />
      </el-tab-pane>
      <el-tab-pane v-if="can('cms:site:detail')" :label="t('cms.labels.memberInteraction')" name="member-content" lazy><ContributionSettingsPanel :site-id="settings.site.id" :can="can" /></el-tab-pane>
      <el-tab-pane :label="t('cms.labels.languageSettings2')" name="locales">
      <div class="section-title"><h3>{{ t('cms.labels.websiteLanguages') }}</h3><el-button v-if="can('cms:site:locale')" @click="open('locale')">{{ t('cms.labels.addLanguage') }}</el-button></div><el-table :data="settings.locales"><el-table-column prop="name" :label="t('cms.labels.name2')" /><el-table-column prop="locale" :label="t('cms.labels.languageCode')" /><el-table-column prop="url_prefix" :label="t('cms.labels.defaultUrlPrefix')" /><el-table-column :label="t('cms.labels.status')"><template #default="{row}">{{ row.status ? t('cms.labels.enabled') : t('cms.labels.disabled2') }}</template></el-table-column><el-table-column :label="t('cms.labels.actions')"><template #default="{row}"><el-button v-if="can('cms:site:locale')" link @click="open('locale',row)">{{ t('cms.labels.edit') }}</el-button></template></el-table-column></el-table>
      </el-tab-pane>
      <el-tab-pane :label="t('cms.labels.domainBindings')" name="domains">
      <div class="section-title"><h3>{{ t('cms.labels.bindDomain') }}</h3><el-button v-if="can('cms:site:domain')" @click="open('domain')">{{ t('cms.labels.bindDomain') }}</el-button></div><el-alert :title="t('cms.labels.afterBindingConfigureDnsHttpsCertificatesAndTheWebsite')" type="info" :closable="false" /><el-table :data="domains"><el-table-column prop="host" :label="t('cms.labels.domain')" /><el-table-column :label="t('cms.labels.purpose')"><template #default="{row}"><el-tag>{{ row.id === settings.site.primary_domain_id ? t('cms.labels.primaryDomain') : t('cms.labels.alias') }}</el-tag></template></el-table-column><el-table-column :label="t('cms.labels.status')"><template #default="{row}">{{ row.status ? t('cms.labels.bindingEnabled') : t('cms.labels.disabled2') }}</template></el-table-column><el-table-column :label="t('cms.labels.actions')"><template #default="{row}"><el-button v-if="can('cms:site:domain')" link @click="open('domain',{...row,primary:row.id === settings.site.primary_domain_id})">{{ t('cms.labels.edit') }}</el-button></template></el-table-column></el-table>
      </el-tab-pane>
    </el-tabs>
    <el-dialog :model-value="!!dialog" :title="{site:t('cms.labels.siteSettings'),locale:t('cms.labels.languageSettings'),domain:t('cms.labels.domainSettings'),grant:t('cms.labels.siteAdministrators')}[dialog]" width="min(640px, 94vw)" :close-on-click-modal="false" @close="dialog = ''"><el-alert v-if="error" :title="error" type="error" :closable="false" /><el-form label-position="top">
      <template v-if="dialog === 'site'"><el-form-item :label="t('cms.labels.siteCode')" required><el-input v-model="form.code" :disabled="!!form.id" /></el-form-item><el-form-item :label="t('cms.labels.siteName')" required><el-input v-model="form.name" /></el-form-item><el-form-item v-if="!form.id" :label="t('cms.labels.primaryDomain')" required><el-input v-model="form.host" placeholder="www.example.com" /></el-form-item><el-form-item :label="t('cms.labels.defaultLanguage')">
        <el-select v-model="form.default_locale" :aria-label="t('cms.labels.defaultLanguage')" filterable :allow-create="!form.id" default-first-option :disabled="!!form.id && !can('cms:site:locale')" :placeholder="t('cms.labels.selectTheWebsiteSDefaultLanguage')">
          <el-option v-for="language in defaultLanguageOptions" :key="language.value" :label="language.label" :value="language.value" :disabled="language.disabled" />
        </el-select>
        <p class="field-help">{{ form.id ? t('cms.labels.onlyEnabledSiteLanguagesAreShownAddMoreIn') : t('cms.labels.selectTheWebsiteSDefaultLanguageOrEnterAnother') }}</p>
      </el-form-item><el-form-item :label="t('cms.labels.theme')">
        <el-alert v-if="themesError" :title="themesError" type="error" :closable="false" />
        <el-button v-if="themesError" link type="primary" :loading="themesLoading" @click="loadThemes">{{ t('cms.labels.reloadThemes') }}</el-button>
        <el-select v-model="form.theme" :aria-label="t('cms.labels.theme')" filterable :loading="themesLoading" :disabled="themesLoading || !!themesError" :placeholder="t('cms.labels.selectAnInstalledTheme')">
          <el-option v-for="theme in themeOptions" :key="theme.value" :label="theme.label" :value="theme.value" :disabled="theme.disabled" />
        </el-select>
        <p v-if="themesReady && themes.length===1" class="field-help">{{ t('cms.labels.onlyOneThemeIsInstalledAdditionalInstalledThemesWill') }}</p>
        <p v-else-if="themesReady && !themes.length" class="field-help">{{ t('cms.labels.noThemesAvailableInstallAThemeFirst') }}</p>
        <p v-else class="field-help">{{ t('cms.labels.theThemeControlsTheEntireWebsiteSAppearanceSelect') }}</p>
      </el-form-item><el-form-item :label="t('cms.labels.enabled')"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item></template>
      <template v-if="dialog === 'locale'"><el-form-item :label="t('cms.labels.languageCode')" required><el-input v-model="form.locale" :disabled="!!form.id" placeholder="en-us" /></el-form-item><el-form-item :label="t('cms.labels.websiteName')" required><el-input v-model="form.name" /></el-form-item><el-form-item :label="t('cms.labels.defaultPathPrefix')"><el-input v-model="form.url_prefix" :placeholder="t('cms.labels.enLeaveBlankForTheRootDirectory')" /></el-form-item><el-form-item :label="t('cms.labels.homePageUrlPath')"><el-input v-model="form.home_path" :placeholder="t('cms.labels.leaveBlankToGenerateFromTheLanguagePrefix')" /></el-form-item><el-form-item :label="t('cms.labels.defaultSeoTitle')"><el-input v-model="form.seo_title" /></el-form-item><el-form-item :label="t('cms.labels.defaultSeoDescription')"><el-input v-model="form.seo_description" type="textarea" /></el-form-item><el-form-item :label="t('cms.labels.enabled')"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item><el-alert :title="t('cms.labels.thePrefixSuggestsNewUrlsChangingItDoesNot')" :closable="false" /></template>
      <template v-if="dialog === 'domain'"><el-form-item :label="t('cms.labels.domain')" required><el-input v-model="form.host" :disabled="!!form.id" placeholder="www.example.com" /></el-form-item><el-form-item :label="t('cms.labels.setAsPrimaryDomain')"><el-switch v-model="form.primary" /></el-form-item><el-form-item :label="t('cms.labels.enabled')"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item></template>

    </el-form><template #footer><el-button @click="dialog = ''">{{ t('cms.labels.cancel') }}</el-button><el-button v-if="allowed(dialog,!!form.id)" type="primary" :loading="busy" :disabled="dialog==='site' && (themesLoading || !!themesError)" @click="save">{{ t('cms.labels.save') }}</el-button></template></el-dialog>
  </div>
</template>
<style scoped>.field-help{width:100%;margin:6px 0 0;color:var(--el-text-color-secondary);font-size:12px;line-height:1.6}.actions{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 24px}.site-tabs{min-width:0}.site-tabs :deep(.el-tabs__header){margin-bottom:24px}.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 18px}.section-title h3{margin:0;font-size:16px}.site-tabs :deep(.configuration-panel){margin-top:0;padding-top:0;border-top:0}.site-tabs :deep(.el-descriptions__label){width:120px}@media(max-width:640px){.site-tabs :deep(.el-tabs__item){padding:0 14px}.site-tabs :deep(.el-descriptions__label){width:auto}}</style>
