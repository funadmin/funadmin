<script setup lang="ts">
import TablePagination from '@/components/TablePagination/index.vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http from '@/utils/http';
import type { Scope, MemberSubmission } from '../types';
const { t } = useCmsI18n();
const props=withDefaults(defineProps<{scope:Scope;can:(permission:string)=>boolean;endpoint?:string;qa?:boolean}>(),{endpoint:'/plugins/cms/contributions',qa:false});
const items=ref<MemberSubmission[]>([]),selected=ref<MemberSubmission>(),status=ref('pending'),page=ref(1),total=ref(0),reason=ref(''),error=ref(''),success=ref(''),loading=ref(false),busy=ref(false);
const names=computed<Record<string,string>>(()=>({pending:t('cms.labels.pendingReview'),published:t('cms.labels.published'),rejected:t('cms.labels.rejected'),withdrawn:t('cms.labels.withdrawn'),hidden:t('cms.labels.unpublished')}));
const kinds=computed<Record<string,string>>(()=>({content:t('cms.labels.submission'),question:t('cms.labels.question2'),answer:t('cms.labels.answer')}));
const canReview=computed(()=>props.can('cms:submission:review'));
const canPublish=computed(()=>canReview.value&&(props.qa||props.can('cms:content:create')&&props.can('cms:content:publish')));
let generation=0;
function message(e:unknown){const value=e as {msg?:string;message?:string};return value?.msg||value?.message||t('cms.labels.couldNotLoadPleaseTryAgainLater');}
async function load(){const token=++generation;loading.value=true;error.value='';try{const result=await http.get<{items:MemberSubmission[];total:number}>(props.endpoint,{...props.scope,status:status.value,page:page.value});if(token===generation){items.value=result.items;total.value=result.total;}}catch(e){if(token===generation){items.value=[];total.value=0;error.value=message(e);}}finally{if(token===generation)loading.value=false;}}
function open(row:MemberSubmission){selected.value=row;reason.value='';error.value='';success.value='';}
async function review(decision:'publish'|'reject'|'hide'){
 if(!selected.value||busy.value||!canReview.value||decision==='publish'&&!canPublish.value)return;
 if(decision!=='publish'&&!reason.value.trim()){error.value=t('cms.labels.enterAReviewComment');return;}
 const target=selected.value,token=generation;busy.value=true;error.value='';
 try{await http.post(`${props.endpoint}/review`,{...props.scope,id:target.id,lock_version:target.lock_version,decision,reason:reason.value.trim()});if(token===generation){selected.value=undefined;success.value=t('cms.labels.reviewSaved');await load();}}
 catch(e){if(token===generation)error.value=message(e);}finally{busy.value=false;}
}
watch(status,()=>{if(page.value!==1)page.value=1;else void load();});
watch(page,()=>void load());
watch(()=>[props.scope.site_id,props.scope.locale],()=>{selected.value=undefined;page.value=1;void load();});
onMounted(()=>void load());onBeforeUnmount(()=>generation++);
</script>
<template>
 <section v-loading="loading" class="contributions-panel">
  <div class="toolbar"><el-select v-model="status" :aria-label="t('cms.labels.reviewStatus')" :disabled="busy"><el-option v-for="(label,value) in names" :key="value" :label="label" :value="value" /></el-select><el-button :disabled="busy" @click="load">{{ t('cms.labels.refreshList') }}</el-button></div>
  <el-alert v-if="error" :title="error" type="error" :closable="false">{{ error }}</el-alert><el-alert v-if="success" :title="success" type="success" :closable="false" />
  <article v-for="row in items" :key="row.id" class="submission-row"><div><h3>{{ row.title||t('cms.labels.answer') }}</h3><p>{{ kinds[row.kind]||row.kind }} {{ t('cms.labels.member2') }} {{ row.member_id }} · {{ row.created_at }} <el-tag>{{ names[row.status] }}</el-tag></p><p v-if="row.parent_id">{{ t('cms.labels.question') }}{{ row.parent_id }}</p></div><el-button @click="open(row)">{{ canReview?t('cms.labels.review'):t('cms.labels.view') }}</el-button></article>
  <el-empty v-if="!items.length&&!loading&&!error" :description="t('cms.labels.noSubmissionsWithThisStatus')" />
  <TablePagination v-if="total>20" v-model:current-page="page" :total="total" :page-size="20" layout="total,prev,pager,next" />
  <el-dialog :model-value="!!selected" :title="t('cms.labels.submissionReview')" width="min(880px,94vw)" :close-on-click-modal="false" :show-close="!busy" @close="!busy&&(selected=undefined)">
   <template v-if="selected"><el-alert v-if="error" :title="error" type="error" :closable="false">{{ error }}</el-alert><h3>{{ selected.title||t('cms.labels.answer') }}</h3><p>{{ t('cms.labels.member') }} {{ selected.member_id }} · {{ names[selected.status] }}</p><h4>{{ t('cms.labels.body') }}</h4><pre class="submission-body">{{ selected.body_html }}</pre><template v-if="selected.data&&Object.keys(selected.data).length"><h4>{{ t('cms.labels.modelAndFields') }}</h4><pre>{{ JSON.stringify(selected.data,null,2) }}</pre></template><p v-if="selected.review_reason">{{ t('cms.labels.previousReview') }}{{ selected.review_reason }}</p><el-input v-if="canReview" v-model="reason" type="textarea" :aria-label="t('cms.labels.reviewComment')" :placeholder="t('cms.labels.giveAReasonWhenRejectingOrUnpublishing')" maxlength="1000" :disabled="busy" /></template>
   <template #footer><el-button :disabled="busy" @click="selected=undefined">{{ t('cms.labels.close') }}</el-button><template v-if="selected&&canReview"><el-button v-if="selected.status==='pending'" :loading="busy" @click="review('reject')">{{ t('cms.labels.reject') }}</el-button><el-button v-if="selected.status==='pending'&&canPublish" type="primary" :loading="busy" @click="review('publish')">{{ t('cms.labels.approveAndPublish') }}</el-button><el-button v-if="selected.status==='published'" type="danger" :loading="busy" @click="review('hide')">{{ t('cms.labels.unpublish') }}</el-button></template></template>
  </el-dialog>
 </section>
</template>
<style scoped>.toolbar{display:flex;gap:12px;margin-bottom:20px}.toolbar .el-select{width:180px}.submission-row{display:flex;justify-content:space-between;align-items:center;gap:20px;border-bottom:1px solid var(--el-border-color-lighter);padding:20px 0}.submission-row h3{font-size:16px;margin:0 0 8px}.submission-row p{font-size:13px;color:var(--el-text-color-secondary);margin:6px 0}.contributions-panel pre{white-space:pre-wrap;overflow-wrap:anywhere;max-height:320px;overflow:auto;background:var(--el-fill-color-light);padding:16px;line-height:1.8}.contributions-panel .el-alert{margin-bottom:16px}</style>
