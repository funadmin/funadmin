<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { cmsApi } from '../api';
import type { Scope, FormData } from '../types';
const { t } = useCmsI18n();
const props=defineProps<{scope:Scope;can:(permission:string)=>boolean;modelValue?:string|string[]|null;multiple?:boolean;images?:boolean;disabled?:boolean}>();
const emit=defineEmits<{ 'update:modelValue':[value:string|string[]|null]; busy:[value:boolean] }>();
const options=ref<FormData[]>([]),loading=ref(false),uploading=ref(false),error=ref(''),input=ref<HTMLInputElement>();
function permitted(){return !props.disabled&&(props.can('cms:content:create')||props.can('cms:content:edit'));}
const editable=computed(permitted);
const ids=computed(()=>Array.isArray(props.modelValue)?props.modelValue:props.modelValue?[props.modelValue]:[]);
const selected=computed(()=>ids.value.map(id=>options.value.find(file=>file.id===id)||{id,original_name:t('cms.labels.attachmentValue', { value1: id })}));
let generation=0,searchGeneration=0;
function image(file:FormData){return ['jpg','jpeg','png','gif','webp','bmp'].includes(String(file.ext).toLowerCase());}
function safeUrl(file:FormData){const url=String(file.url||file.path||'');return /^(?:\/(?!\/)|https?:\/\/)/i.test(url)&&!/[\x00-\x20\\]/.test(url)?url:'';}
async function search(q=''){
 if(!permitted())return;const token=++searchGeneration,context=generation;loading.value=true;
 try{const files=await cmsApi.attachments({...props.scope,q,...(ids.value.length?{ids:ids.value}:{})});if(context!==generation||token!==searchGeneration)return;
 const kept=options.value.filter(file=>ids.value.includes(file.id));options.value=[...new Map([...kept,...files.filter(row=>!props.images||image(row))].map(file=>[file.id,file])).values()];
 }catch{if(context===generation&&token===searchGeneration)error.value=t('cms.labels.couldNotLoadTheAttachmentLibraryPleaseRetry');}finally{if(context===generation&&token===searchGeneration)loading.value=false;}
}
async function upload(event:Event){
 const element=event.target as HTMLInputElement,files=Array.from(element.files||[]);element.value='';
 if(!permitted()||uploading.value||!files.length)return;
 const context=generation,scope={...props.scope};let next=[...ids.value];uploading.value=true;emit('busy',true);error.value='';
 try{for(const file of props.multiple?files:files.slice(0,1)){
   if(props.images&&!/^image\/(?:png|jpeg|gif|webp|bmp)$/.test(file.type))throw new Error(t('cms.labels.selectAPngJpgGifWebpOrBmpImage'));
   const result=await cmsApi.upload(scope,file,!!props.images);
   if(context!==generation||!permitted())return;
   if(!result.id||!safeUrl(result))throw new Error(t('cms.labels.invalidUploadResultPleaseRetry'));
   options.value=[{...result,original_name:result.name},...options.value.filter(item=>item.id!==result.id)];
   next=props.multiple?[...new Set([...next,result.id])]:[result.id];emit('update:modelValue',props.multiple?next:result.id);
 }}catch(e:any){if(context===generation)error.value=e?.msg||e?.message||t('cms.labels.uploadFailedPleaseRetry');}
 finally{if(context===generation){uploading.value=false;emit('busy',false);}}
}
function select(value:string|string[]|null){if(permitted()&&!uploading.value)emit('update:modelValue',value||(props.multiple?[]:null));}
function remove(id:string){if(!permitted()||uploading.value)return;emit('update:modelValue',props.multiple?ids.value.filter(value=>value!==id):null);}
watch(()=>[props.scope.site_id,props.scope.locale],()=>{generation++;searchGeneration++;options.value=[];uploading.value=false;emit('busy',false);void search();},{immediate:true});
onBeforeUnmount(()=>{generation++;emit('busy',false);});
</script>
<template><div class="attachment-field">
 <div class="attachment-controls"><el-select :model-value="modelValue" :multiple="multiple" :loading="loading" :disabled="!editable||uploading" filterable remote :remote-method="search" clearable :placeholder="t('cms.labels.chooseFromLibrary')" @update:model-value="select"><el-option v-for="file in options" :key="file.id" :label="file.original_name||file.name||file.path" :value="file.id" /></el-select>
 <el-button v-if="editable" :loading="uploading" :disabled="uploading" @click="input?.click()">{{ images?t('cms.labels.uploadImage'):t('cms.labels.uploadFile') }}</el-button>
 <input v-if="editable" ref="input" type="file" :multiple="multiple" :accept="images?'.jpg,.jpeg,.png,.gif,.webp,.bmp':undefined" hidden @change="upload" /></div>
 <small v-if="error" class="upload-error" role="alert">{{ error }}</small>
 <div v-if="selected.length" class="selected-files"><div v-for="file in selected" :key="file.id" class="selected-file"><el-image v-if="image(file)&&safeUrl(file)" :src="safeUrl(file)" :preview-src-list="[safeUrl(file)]" preview-teleported fit="cover" /><a v-if="safeUrl(file)" :href="safeUrl(file)" target="_blank" rel="noopener noreferrer">{{ file.original_name||file.name||file.path }}</a><span v-else>{{ file.original_name }}</span><el-button v-if="editable" link type="danger" :disabled="uploading" @click="remove(file.id)">{{ t('cms.labels.remove') }}</el-button></div></div>
 <small class="hint">{{ t('cms.labels.uploadsAreLinkedToThisContentAutomaticallyYouCan') }}</small>
</div></template>
<style scoped>.attachment-field{width:100%;min-width:0}.attachment-controls{display:flex;gap:8px}.attachment-controls .el-select{flex:1;min-width:0}.hint{display:block;color:var(--el-text-color-secondary);font-size:12px;margin-top:6px;line-height:1.6}.upload-error{display:block;color:var(--el-color-danger);margin-top:6px}.selected-files{display:flex;flex-direction:column;gap:6px;margin-top:10px}.selected-file{display:flex;gap:10px;align-items:center;padding:8px;border:1px solid var(--el-border-color-lighter);border-radius:6px;min-width:0}.selected-file .el-image{width:56px;height:56px;flex:none;border-radius:4px}.selected-file a,.selected-file>span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--el-color-primary);font-size:12px}</style>
