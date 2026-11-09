<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { cmsApi } from '../api';
import type { FormSubmissionDetail, FormSubmissionEvent, FormSubmissionFile, Scope } from '../types';
import { FORM_SUBMISSION_STATUSES } from './formFields';
const { t } = useCmsI18n();
const props=defineProps<{scope:Scope;can:(permission:string)=>boolean;id?:string}>();
const emit=defineEmits<{close:[];changed:[]}>();
/** 与服务端一致：只有这些图片类型能以 preview=1 内联显示，其余附件一律下载。 */
const PREVIEW_MIMES=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
const MAX_NOTE=2000;
const detail=ref<FormSubmissionDetail>(),note=ref(''),target=ref(''),loading=ref(false),busy=ref(false),error=ref('');
const canHandle=computed(()=>props.can('cms:form:handle'));
const statuses=computed<Record<string,string>>(()=>Object.fromEntries(FORM_SUBMISSION_STATUSES.map(status=>[status,t(`cms.forms.statuses.${status}`)])));
const targets=computed(()=>FORM_SUBMISSION_STATUSES.filter(status=>status!==detail.value?.status));
let generation=0;
function message(cause:unknown,fallback:string){const value=cause as {msg?:string};return typeof value?.msg==='string'&&value.msg?value.msg:fallback;}
async function load(){
  const id=props.id,token=++generation;
  if(!id){detail.value=undefined;return;}
  loading.value=true;
  try{const value=await cmsApi.formSubmission(props.scope.site_id,id);if(token===generation){detail.value=value;target.value='';}}
  catch(cause){if(token===generation)error.value=message(cause,t('cms.labels.couldNotLoadPleaseTryAgainLater'));}
  finally{if(token===generation)loading.value=false;}
}
/** 备注与改状态都返回最新详情；409 表示他人已处理，提示后重新加载。 */
async function act(action:()=>Promise<FormSubmissionDetail>,done?:()=>void){
  if(!detail.value||busy.value||!canHandle.value)return;
  const token=generation;busy.value=true;error.value='';
  try{const value=await action();if(token===generation){detail.value=value;done?.();}emit('changed');}
  catch(cause){
    if(token!==generation)return;
    error.value=message(cause,t('cms.labels.operationFailedCheckYourInputAndRetry'));
    if((cause as {code?:number})?.code===409){await load();emit('changed');}
  }
  finally{busy.value=false;}
}
function addNote(){
  const body=note.value.trim();
  if(!body||[...body].length>MAX_NOTE){error.value=t('cms.forms.noteLength',{max:MAX_NOTE});return;}
  const id=detail.value?.id;if(id)void act(()=>cmsApi.noteFormSubmission({site_id:props.scope.site_id,id,body}),()=>{note.value='';});
}
function changeStatus(){
  const current=detail.value;if(!current||!target.value||target.value===current.status)return;
  void act(()=>cmsApi.transitionFormSubmission({site_id:props.scope.site_id,id:current.id,status:target.value,lock_version:current.lock_version}));
}
function display(field:FormSubmissionDetail['fields'][number]){
  if(field.type==='consent')return field.value===true?t('cms.forms.agreed'):field.value===false?t('cms.forms.notAgreed'):'—';
  if(field.text)return field.text;
  if(Array.isArray(field.value))return field.value.join(', ')||'—';
  return field.value===null||field.value===undefined||field.value===''?'—':String(field.value);
}
function eventText(event:FormSubmissionEvent){
  if(event.type==='created')return t('cms.forms.events.created');
  if(event.type==='status')return t('cms.forms.events.status',{from:statuses.value[event.from_status||'']||event.from_status||'',to:statuses.value[event.to_status||'']||event.to_status||''});
  return t('cms.forms.events.note');
}
const fileUrl=(file:FormSubmissionFile,preview=false)=>cmsApi.formFileUrl(props.scope.site_id,file.id,preview);
const previewable=(file:FormSubmissionFile)=>PREVIEW_MIMES.includes(file.mime);
function size(bytes:number){return bytes<1024?`${bytes} B`:bytes<1048576?`${(bytes/1024).toFixed(1)} KB`:`${(bytes/1048576).toFixed(1)} MB`;}
watch(()=>props.id,()=>{error.value='';note.value='';void load();},{immediate:true});
onBeforeUnmount(()=>generation++);
</script>
<template>
  <el-drawer :model-value="!!id" :title="t('cms.forms.submissionDetail')" size="min(720px,96vw)" :close-on-click-modal="!busy" @close="emit('close')">
    <div v-loading="loading" class="submission-drawer">
      <el-alert v-if="error" :title="error" type="error" :closable="false" />
      <template v-if="detail">
        <dl class="meta">
          <dt>{{ t('cms.forms.form') }}</dt><dd>{{ detail.form_name }}<span class="muted">{{ detail.form_code }}</span></dd>
          <dt>{{ t('cms.forms.status') }}</dt><dd><el-tag>{{ statuses[detail.status]||detail.status }}</el-tag></dd>
          <dt>{{ t('cms.forms.submittedAt') }}</dt><dd>{{ detail.created_at }}</dd>
          <dt>{{ t('cms.forms.language') }}</dt><dd>{{ detail.locale }}</dd>
          <dt>{{ t('cms.forms.sourcePage') }}</dt><dd class="wrap">{{ detail.source_url||'—' }}</dd>
          <dt>{{ t('cms.forms.ip') }}</dt><dd>{{ detail.ip||'—' }}</dd>
          <template v-if="detail.member_id"><dt>{{ t('cms.forms.member') }}</dt><dd>{{ detail.member_id }}</dd></template>
        </dl>
        <h4>{{ t('cms.forms.submittedValues') }}</h4>
        <dl class="values"><template v-for="field in detail.fields" :key="field.name"><dt>{{ field.label }}</dt><dd class="wrap">{{ display(field) }}</dd></template></dl>
        <template v-if="detail.files.length">
          <h4>{{ t('cms.forms.attachments') }}</h4>
          <ul class="files">
            <li v-for="file in detail.files" :key="file.id">
              <a v-if="previewable(file)" :href="fileUrl(file)" target="_blank" rel="noopener" class="preview"><img :src="fileUrl(file,true)" :alt="file.name"></a>
              <a :href="fileUrl(file)" target="_blank" rel="noopener">{{ file.name }}</a><span class="muted">{{ size(file.size) }}</span>
            </li>
          </ul>
        </template>
        <h4>{{ t('cms.forms.timeline') }}</h4>
        <el-timeline>
          <el-timeline-item v-for="event in detail.events" :key="event.id" :timestamp="event.created_at">
            <strong>{{ eventText(event) }}</strong><span class="muted">{{ event.admin_id?t('cms.forms.byAdmin',{id:event.admin_id}):t('cms.forms.visitor') }}</span>
            <p v-if="event.type==='note'" class="note">{{ event.body }}</p>
          </el-timeline-item>
        </el-timeline>
        <template v-if="canHandle">
          <h4>{{ t('cms.forms.followUp') }}</h4>
          <el-input v-model="note" type="textarea" :rows="3" :maxlength="MAX_NOTE" show-word-limit :aria-label="t('cms.forms.note')" :placeholder="t('cms.forms.notePlaceholder')" :disabled="busy" />
          <div class="actions">
            <el-button :loading="busy" :disabled="!note.trim()" @click="addNote">{{ t('cms.forms.addNote') }}</el-button>
            <el-select v-model="target" :aria-label="t('cms.forms.newStatus')" :placeholder="t('cms.forms.newStatus')" :disabled="busy"><el-option v-for="status in targets" :key="status" :label="statuses[status]" :value="status" /></el-select>
            <el-button type="primary" :loading="busy" :disabled="!target" @click="changeStatus">{{ t('cms.forms.changeStatus') }}</el-button>
          </div>
        </template>
      </template>
    </div>
  </el-drawer>
</template>
<style scoped>
.submission-drawer .el-alert{margin-bottom:16px}.submission-drawer h4{margin:20px 0 10px;font-size:14px}.meta,.values{display:grid;grid-template-columns:minmax(80px,max-content) 1fr;gap:8px 16px;margin:0;font-size:13px}.meta dt,.values dt{color:var(--el-text-color-secondary)}.meta dd,.values dd{margin:0}.wrap,.note{white-space:pre-wrap;overflow-wrap:anywhere}.files{list-style:none;padding:0;margin:0;display:grid;gap:10px}.files li{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.preview img{max-width:160px;max-height:120px;border:1px solid var(--el-border-color-lighter);border-radius:4px;display:block}.muted{color:var(--el-text-color-secondary);font-size:12px;margin-left:8px}.note{margin:6px 0 0}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}.actions .el-select{width:160px}
</style>
