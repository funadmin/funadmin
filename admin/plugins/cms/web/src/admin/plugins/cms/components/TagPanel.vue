<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cmsApi } from '../api';
import type { Scope, TemplateTagListItem, BuiltinTemplateTag, Provider, TagEditorOptions, FormData } from '../types';
import { useOperation } from './useOperation';
const { t, te } = useCmsI18n();
const props=defineProps<{scope:Scope;can:(permission:string)=>boolean}>();const {busy,error,run}=useOperation();const rows=ref<TemplateTagListItem[]>([]),providers=ref<Provider[]>([]),dialog=ref(false),form=ref<FormData>({}),preview=ref(''),builtinDetails=ref<BuiltinTemplateTag>();
const schema=computed(()=>providers.value.find(item=>item.name===form.value.provider)?.parameters||{});
const options=ref<TagEditorOptions>({models:[],categories:[]}),optionsLoading=ref(false),optionsError=ref('');
let optionsRequest=0,listRequest=0;
const providerLabels=computed<Record<string,string>>(()=>({content:t('cms.labels.contentList'),category:t('cms.labels.categoryList'),menu:t('cms.labels.navigationMenu'),text:t('cms.labels.fixedText'),date:t('cms.labels.dateFormat')}));
const parameterLabels=computed<Record<string,string>>(()=>({model:t('cms.labels.contentModel'),category:t('cms.labels.category2'),limit:t('cms.labels.resultCount'),paginate:t('cms.labels.enablePagination'),q:t('cms.labels.searchKeyword'),parent:t('cms.labels.parentId'),code:t('cms.labels.menuCode'),value:t('cms.labels.contentValue'),default:t('cms.labels.defaultValue'),format:t('cms.labels.dateFormat')}));
const parameterHelp=computed<Record<string,string>>(()=>({model:t('cms.labels.chooseAContentTypeToDisplayLeaveBlankFor'),category:t('cms.labels.chooseACategoryToDisplayAllCategoriesRemovesThe'),limit:t('cms.labels.numberOfItemsReturnedPerRequest'),paginate:t('cms.labels.paginateContentUsingTheCurrentPageNumber'),q:t('cms.labels.onlyShowContentWhoseTitleContainsThisKeywordLeave')}));
// 已保存的标识即使暂时不可用也要保留，避免打开编辑框后丢失配置。
const modelOptions=computed(()=>{
  const choices=[...options.value.models];const value=form.value.parameters?.model;
  if(value&&!choices.some(item=>item.value===value))choices.push({value,label:t('cms.labels.savedModelCurrentlyUnavailableValue', { value1: value })});
  return choices;
});
const categoryOptions=computed(()=>{
  const choices=options.value.categories.map(item=>({value:item.value,label:item.path?t('cms.labels.labelWithPath', { label: item.label, path: item.path }):item.label}));
  const value=form.value.parameters?.category;
  if(value&&!choices.some(item=>item.value===value)){
    const matched=options.value.categories.find(item=>item.path===value);
    choices.push({value,label:matched?t('cms.labels.labelWithPath', { label: matched.label, path: matched.path }):t('cms.labels.savedCategoryCurrentlyUnavailableValue', { value1: value })});
  }
  return choices;
});
async function load(){
  if(!props.can('cms:tag:list'))return;
  const request=++listRequest,scope={...props.scope};
  await run(async()=>{
    const [tags,definitions]=await Promise.all([cmsApi.tags(scope),cmsApi.providers(scope)]);
    if(request!==listRequest)return;
    rows.value=tags;providers.value=definitions;
  });
}
async function loadOptions(){
  if(!props.can('cms:tag:list'))return;
  const request=++optionsRequest,scope={...props.scope};
  optionsLoading.value=true;optionsError.value='';
  try{
    const result=await cmsApi.tagOptions(scope);
    if(request===optionsRequest)options.value=result;
  }catch{
    if(request===optionsRequest)optionsError.value=t('cms.labels.couldNotLoadModelAndCategoryOptionsCurrentSettings');
  }finally{if(request===optionsRequest)optionsLoading.value=false;}
}
function defaults(){form.value.parameters={};for(const [name,rule]of Object.entries(schema.value))if(rule.default!==undefined)form.value.parameters[name]=rule.default;}
function open(row?:TemplateTagListItem){ if(row?.builtin)return; if (!(props.can(row ? 'cms:tag:edit' : 'cms:tag:create'))) return;form.value=row?{id:row.id,name:row.name,provider:row.draft.provider,parameters:JSON.parse(JSON.stringify(row.draft.parameters)),template_fragment:row.draft.template_fragment,tag_locale:row.locale,remark:row.remark??''}:{name:'',provider:'content',parameters:{},template_fragment:'',tag_locale:'',remark:''};if(!row)defaults();dialog.value=true;}
async function save(){ if (!(props.can(form.value.id ? 'cms:tag:edit' : 'cms:tag:create'))) return;await run(async()=>{await cmsApi.saveTag({...form.value,...props.scope});dialog.value=false;await load();});}
async function publish(row:TemplateTagListItem){ if(row.builtin)return; if (!(props.can('cms:tag:publish'))) return;await run(async()=>{await cmsApi.publishTag({site_id:props.scope.site_id,id:row.id,version:row.draft_version});await load();});}
async function showPreview(row:TemplateTagListItem){ if(row.builtin)return; if (!(props.can('cms:tag:preview'))) return;await run(async()=>{preview.value=(await cmsApi.previewTag({...props.scope,id:row.id,version:row.draft_version})).html;});}
// 内置标签的用途与参数含义来自界面语言包；自定义标签显示站点维护的备注。
function builtinText(name: string, key: string): string {
  const path = `cms.tagDocs.${name}.${key}`;
  return te(path) ? t(path) : '';
}
function describe(row: TemplateTagListItem): string {
  return row.builtin ? builtinText(row.name, 'description') : (row.remark || '');
}
function inspectBuiltin(row: BuiltinTemplateTag) {
  if (!props.can('cms:tag:list')) return;
  builtinDetails.value = row;
}
function usage(row: TemplateTagListItem): string {
  const literal = (value: unknown): string => {
    if (typeof value === 'boolean' || typeof value === 'number') return String(value);
    return "'" + String(value).replaceAll('\\', '\\\\').replaceAll("'", "\\'") + "'";
  };
  if (!row.builtin) return `{{ $cms->tag(${literal(row.name)}) }}`;
  const attributes = Object.entries(row.parameters).filter(([,rule]) => rule.required).map(([name,rule]) => {
    const value = rule.default ?? rule.enum?.[0] ?? (rule.type === 'integer' ? 1 : rule.type === 'boolean' ? false : t('cms.labels.fieldName2'));
    return `${literal(name)} => ${literal(value)}`;
  });
  const parameters = attributes.length ? `, [${attributes.join(', ')}]` : '';
  const fields: Record<string, string> = { content: 'title', category: 'name', menu: 'title', site: 'name', languages: 'name', breadcrumb: 'name', theme: 'title', fields: 'label' };
  return fields[row.name]
    ? `@foreach($cms->items(${literal(row.name)}${parameters}) as $item){{ $item['${fields[row.name]}'] }}@endforeach`
    : `{{ $cms->tag(${literal(row.name)}${parameters}) }}`;
}

watch([dialog,()=>form.value.provider],([visible,provider])=>{
  if(visible&&provider==='content')void loadOptions();
  else{++optionsRequest;optionsLoading.value=false;}
});
watch(()=>[props.scope.site_id,props.scope.locale],()=>{
  ++optionsRequest;++listRequest;
  dialog.value=false;builtinDetails.value=undefined;preview.value='';
  options.value={models:[],categories:[]};optionsError.value='';optionsLoading.value=false;
  rows.value=[];providers.value=[];void load();
});
onBeforeUnmount(()=>{++optionsRequest;++listRequest;});
onMounted(load);
</script>
<template>
<div>
  <el-alert :title="t('cms.labels.bladeTemplateUsage')" type="info" :closable="false" />
  <el-alert v-if="error" :title="error" type="error" :closable="false" />
  <div class="toolbar"><el-button v-if="can('cms:tag:create')" type="primary" @click="open()">{{ t('cms.labels.customTag') }}</el-button></div>
  <el-table :data="rows" row-key="id">
    <el-table-column :label="t('cms.labels.tagName2')" min-width="170">
      <template #default="{ row }"><span>{{ row.name }}</span><el-tag v-if="row.builtin" class="builtin-label" size="small" type="info">{{ t('cms.labels.builtIn') }}</el-tag></template>
    </el-table-column>
    <el-table-column :label="t('cms.labels.tagDescription')" min-width="240"><template #default="{ row }"><span class="tag-description">{{ describe(row as TemplateTagListItem) }}</span></template></el-table-column>
    <el-table-column :label="t('cms.labels.scope')"><template #default="{ row }">{{ row.builtin ? t('cms.labels.allSitesLanguages') : row.locale || t('cms.labels.allSiteLanguages') }}</template></el-table-column>
    <el-table-column :label="t('cms.labels.version')"><template #default="{ row }"><span v-if="row.builtin">{{ t('cms.labels.updatedWithSystem') }}</span><span v-else>{{ t('cms.labels.liveDraftVersions', { live: row.current_version, draft: row.draft_version }) }}</span></template></el-table-column>
    <el-table-column :label="t('cms.labels.usageCode')" min-width="270"><template #default="{ row }"><code>{{ usage(row as TemplateTagListItem) }}</code></template></el-table-column>
    <el-table-column :label="t('cms.labels.actions')" min-width="220">
      <template #default="{ row }">
        <el-button v-if="row.builtin" link @click="inspectBuiltin(row as BuiltinTemplateTag)">{{ t('cms.labels.view') }}</el-button>
        <template v-else>
          <el-button v-if="can('cms:tag:edit')" link @click="open(row as TemplateTagListItem)">{{ t('cms.labels.edit') }}</el-button>
          <el-button v-if="can('cms:tag:preview')" link @click="showPreview(row as TemplateTagListItem)">{{ t('cms.labels.preview') }}</el-button>
          <el-button v-if="can('cms:tag:publish')" link type="success" :disabled="busy" @click="publish(row as TemplateTagListItem)">{{ t('cms.labels.publishDraft') }}</el-button>
        </template>
      </template>
    </el-table-column>
  </el-table>
  <el-dialog :model-value="!!builtinDetails" :title="t('cms.labels.builtInTag')" width="min(760px, 94vw)" @close="builtinDetails=undefined">
    <template v-if="builtinDetails">
      <p>{{ t('cms.labels.builtInTagsAreProvidedByCodeTheyCan') }}</p>
      <code>{{ usage(builtinDetails) }}</code>
      <p class="tag-description">{{ describe(builtinDetails) }}</p>
      <h4>{{ t('cms.labels.parameterDefinitions') }}</h4>
      <table v-if="Object.keys(builtinDetails.parameters).length" class="tag-parameters">
        <thead><tr><th>{{ t('cms.labels.tagParameterName') }}</th><th>{{ t('cms.labels.tagParameterType') }}</th><th>{{ t('cms.labels.tagParameterDefault') }}</th><th>{{ t('cms.labels.tagParameterMeaning') }}</th></tr></thead>
        <tbody>
          <tr v-for="(rule, name) in builtinDetails.parameters" :key="name">
            <td><code>{{ name }}</code><span v-if="rule.required" class="tag-required">{{ t('cms.labels.tagRequired') }}</span></td>
            <td>{{ rule.type }}</td>
            <td><code v-if="rule.default !== undefined">{{ String(rule.default) }}</code></td>
            <td>{{ builtinText(builtinDetails.name, `params.${String(name)}`) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>{{ t('cms.labels.tagNoParameters') }}</p>
    </template>
    <template #footer><el-button @click="builtinDetails=undefined">{{ t('cms.labels.close') }}</el-button></template>
  </el-dialog>
  <el-dialog v-model="dialog" :title="t('cms.labels.customTemplateTag')" width="min(820px, 96vw)" :close-on-click-modal="false">
    <el-alert v-if="error" :title="error" type="error" :closable="false" />
    <el-form label-position="top">
      <el-form-item :label="t('cms.labels.tagName')" required><el-input v-model="form.name" :disabled="!!form.id" :placeholder="t('cms.labels.eGCompanyNews')" /></el-form-item>
      <el-form-item :label="t('cms.labels.tagRemark')"><el-input v-model="form.remark" maxlength="255" show-word-limit :placeholder="t('cms.labels.tagRemarkPlaceholder')" /></el-form-item>
      <el-form-item :label="t('cms.labels.applicableLanguage')"><el-select v-model="form.tag_locale" :disabled="!!form.id"><el-option :label="t('cms.labels.allSiteLanguages')" value="" /><el-option :label="scope.locale" :value="scope.locale" /></el-select></el-form-item>
      <el-form-item :label="t('cms.labels.tagType')">
        <el-select v-model="form.provider" @change="defaults"><el-option v-for="provider in providers" :key="provider.name" :label="providerLabels[provider.name] || provider.name" :value="provider.name" /></el-select>
      </el-form-item>
      <div v-if="form.provider==='content' && optionsError" class="options-error" role="alert">
        {{ optionsError }} <el-button link type="primary" :loading="optionsLoading" @click="loadOptions">{{ t('cms.labels.reloadOptions') }}</el-button>
      </div>
      <el-form-item v-for="(rule,name) in schema" :key="name" :label="parameterLabels[String(name)] || String(name)" :required="rule.required">
        <template v-if="form.provider==='content' && name==='model'">
          <el-select v-model="form.parameters[name]" :aria-label="t('cms.labels.contentModel')" filterable clearable :loading="optionsLoading" :placeholder="t('cms.labels.allModels')" @clear="form.parameters[name]=''">
            <el-option :label="t('cms.labels.allModels')" value="" />
            <el-option v-for="item in modelOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
          <p v-if="!optionsLoading && !optionsError && !options.models.length" class="parameter-help">{{ t('cms.labels.noContentModelsAreAvailableForThisSiteEnable') }}</p>
        </template>
        <template v-else-if="form.provider==='content' && name==='category'">
          <el-select v-model="form.parameters[name]" :aria-label="t('cms.labels.category2')" filterable clearable :loading="optionsLoading" :placeholder="t('cms.labels.allCategories')" @clear="form.parameters[name]=''">
            <el-option :label="t('cms.labels.allCategories')" value="" />
            <el-option v-for="item in categoryOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
          <p v-if="!optionsLoading && !optionsError && !options.categories.length" class="parameter-help">{{ t('cms.labels.noEnabledCategoriesForThisSiteAndLanguageYou') }}</p>
        </template>
        <el-switch v-else-if="rule.type==='boolean'" v-model="form.parameters[name]" />
        <el-input-number v-else-if="rule.type==='integer'" v-model="form.parameters[name]" :min="rule.min" :max="rule.max" />
        <el-input v-else v-model="form.parameters[name]" />
        <p v-if="form.provider==='content' && parameterHelp[String(name)]" class="parameter-help">{{ parameterHelp[String(name)] }}</p>
      </el-form-item>
      <p class="tag-description">{{ t('cms.labels.restrictedFragmentHelp') }}</p>
      <el-form-item :label="t('cms.labels.displayFragment')"><el-input v-model="form.template_fragment" type="textarea" :rows="12" placeholder="<a href=&quot;{$item.url}&quot;>{$item.title}</a>" class="code-input" /></el-form-item>
      <el-alert :title="t('cms.labels.savingCreatesADraftPublishingMakesItPublicCall')" :closable="false" />
    </el-form>
    <template #footer><el-button @click="dialog=false">{{ t('cms.labels.cancel') }}</el-button><el-button v-if="can(form.id ? 'cms:tag:edit' : 'cms:tag:create')" type="primary" :loading="busy" @click="save">{{ t('cms.labels.saveDraft') }}</el-button></template>
  </el-dialog>
<el-dialog :model-value="!!preview" :title="t('cms.labels.tagPreview')" width="min(900px, 94vw)" @close="preview=''"><iframe :title="t('cms.labels.cmsTagPreview')" sandbox="" :srcdoc="preview" style="width:100%;height:50vh;border:0" /></el-dialog></div></template>
<style scoped>.toolbar{margin:16px 0}.parameter-help{width:100%;margin:6px 0 0;color:var(--el-text-color-secondary);font-size:12px;line-height:1.6}.options-error{margin-bottom:16px;color:var(--el-color-danger)}.builtin-label{margin-left:8px}.tag-parameters{width:100%;border-collapse:collapse;font-size:13px}.tag-parameters th,.tag-parameters td{padding:6px 8px;border-bottom:1px solid var(--el-border-color-lighter);text-align:left;vertical-align:top;overflow-wrap:anywhere}.tag-parameters th{color:var(--el-text-color-secondary);font-weight:500}.tag-required{margin-left:6px;color:var(--el-color-danger);font-size:12px}.tag-description{color:var(--el-text-color-regular);line-height:1.6}.code-input :deep(textarea){font-family:monospace}</style>
