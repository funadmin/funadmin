<script setup lang="ts">
import TablePagination from '@/components/TablePagination/index.vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { cmsApi } from '../api';
import type { CmsForm, FormData, FormSubmissionRow, FormSubmissionStatus, Scope } from '../types';
import FormEditor from './FormEditor.vue';
import FormSubmissionDrawer from './FormSubmissionDrawer.vue';
import { FORM_SUBMISSION_STATUSES } from './formFields';
import { useOperation } from './useOperation';
const { t, locale } = useCmsI18n();
const props=defineProps<{scope:Scope;can:(permission:string)=>boolean;languages:Array<{locale:string;name:string}>}>();
const {busy,error,run}=useOperation();
const PAGE_SIZE=20;
const canSubmissions=computed(()=>props.can('cms:form:submissions')),canHandle=computed(()=>props.can('cms:form:handle')),canExport=computed(()=>props.can('cms:form:export')),canDelete=computed(()=>props.can('cms:form:delete')),canManage=computed(()=>props.can('cms:form:manage'));
const tab=ref(canSubmissions.value?'submissions':'forms');
const forms=ref<CmsForm[]>([]),rows=ref<FormSubmissionRow[]>([]),selection=ref<FormSubmissionRow[]>([]),newCount=ref(0),total=ref(0),page=ref(1),loading=ref(false),notice=ref('');
const filters=reactive({form_id:'',status:['new','processing'] as FormSubmissionStatus[],range:[] as string[],keyword:''});
const detailId=ref<string>(),editorOpen=ref(false),editing=ref<CmsForm|null>(null),embed=ref<CmsForm>();
const statuses=computed<Record<string,string>>(()=>Object.fromEntries(FORM_SUBMISSION_STATUSES.map(status=>[status,t(`cms.forms.statuses.${status}`)])));
const embedCodes=computed(()=>embed.value?[{label:t('cms.forms.bladeUsage'),code:`{{ $cms->tag('form', ['name' => '${embed.value.code}']) }}`},{label:t('cms.forms.tagUsage'),code:`{cms:form name="${embed.value.code}" /}`}]:[]);
let generation=0;
/** 读取筛选控件当前值；未选任何状态即“全部状态”，显式发送四个状态，避免服务端回落到默认的新提交+跟进中。 */
function readFilters():FormData{
  const [from,to]=filters.range||[];
  const params:FormData={site_id:props.scope.site_id,form_id:filters.form_id,status:filters.status.length?[...filters.status]:[...FORM_SUBMISSION_STATUSES],from,to,keyword:filters.keyword.trim()};
  return Object.fromEntries(Object.entries(params).filter(([,value])=>value!==undefined&&value!==null&&value!==''));
}
/** 列表、翻页与导出只使用点“查询”时生效的筛选，编辑中未提交的控件不影响它们。 */
const applied=ref<FormData>(readFilters());
async function loadSubmissions(){
  if(!canSubmissions.value)return;
  const token=++generation;loading.value=true;
  try{
    const result=await cmsApi.formSubmissions({...applied.value,page:page.value});
    if(token!==generation)return;
    rows.value=result.list;total.value=result.total;
    // 批量标记无效或删除后总数变少，当前页可能已越界（分页器在只有一页时隐藏）：回到最后一页，由 page 的监听重新加载一次。
    const last=Math.max(1,Math.ceil(result.total/PAGE_SIZE));
    if(page.value>last)page.value=last;
  }
  catch(cause){if(token===generation){rows.value=[];total.value=0;error.value=(cause as {msg?:string})?.msg||t('cms.labels.couldNotLoadPleaseTryAgainLater');}}
  finally{if(token===generation)loading.value=false;}
}
async function loadSummary(){try{newCount.value=(await cmsApi.formSummary(props.scope.site_id)).new_count;}catch{/* 数量只是提醒，失败时保留旧值 */}}
async function loadForms(){try{forms.value=await cmsApi.forms(props.scope.site_id);}catch(cause){error.value=(cause as {msg?:string})?.msg||t('cms.labels.couldNotLoadPleaseTryAgainLater');}}
/** 任何变更后同时刷新列表、新提交数量与表单计数。 */
async function refresh(){await Promise.all([loadSubmissions(),loadSummary(),loadForms()]);}
function search(){applied.value=readFilters();if(page.value!==1)page.value=1;else void loadSubmissions();}
async function invalidate(){
  if(!canHandle.value||!selection.value.length)return;
  const ids=selection.value.map(row=>row.id);notice.value='';
  const result=await run(()=>cmsApi.invalidateFormSubmissions({site_id:props.scope.site_id,ids}));
  if(result){notice.value=t('cms.forms.markedInvalid',{count:result.count});await refresh();}
}
async function removeSelected(){
  if(!canDelete.value||!selection.value.length)return;
  const ids=selection.value.map(row=>row.id);notice.value='';
  const result=await run(()=>cmsApi.removeFormSubmissions({site_id:props.scope.site_id,ids}));
  if(result){notice.value=t('cms.forms.removed',{count:result.count});await refresh();}
}
function exportCsv(){
  if(!canExport.value)return;
  const params:FormData={...applied.value};params.locale=String(locale.value).toLowerCase().startsWith('en')?'en-us':'zh-cn';
  window.open(cmsApi.formExportUrl(params),'_blank','noopener');
}
async function toggle(form:CmsForm,enabled:boolean){
  if(!canManage.value)return;
  notice.value='';
  await run(()=>cmsApi.formStatus({site_id:props.scope.site_id,id:form.id,enabled,lock_version:form.lock_version}));
  await loadForms();
}
async function removeForm(form:CmsForm){
  if(!canManage.value||form.submission_count)return;
  notice.value='';
  const done=await run(async()=>{await cmsApi.deleteForm({site_id:props.scope.site_id,id:form.id});return true;});
  if(done&&filters.form_id===form.id)filters.form_id='';
  if(done&&applied.value.form_id===form.id){const {form_id:_,...rest}=applied.value;applied.value=rest;void loadSubmissions();}
  await loadForms();
}
function openEditor(form?:CmsForm){if(!canManage.value)return;editing.value=form||null;editorOpen.value=true;}
async function saved(){editorOpen.value=false;notice.value=t('cms.forms.saved');await loadForms();}
async function copy(code:string){
  try{await navigator.clipboard.writeText(code);notice.value=t('cms.forms.copied');}
  catch{error.value=t('cms.forms.copyFailed');}
}
watch(page,()=>void loadSubmissions());
onMounted(()=>void refresh());onBeforeUnmount(()=>generation++);
</script>
<template>
  <section class="forms-panel">
    <el-alert v-if="error" :title="error" type="error" :closable="false" /><el-alert v-if="notice" :title="notice" type="success" :closable="false" />
    <el-tabs v-model="tab">
      <el-tab-pane v-if="canSubmissions" name="submissions" :label="t('cms.forms.newSubmissionsTab',{count:newCount})">
        <div class="toolbar">
          <el-select v-model="filters.form_id" clearable :placeholder="t('cms.forms.allForms')" :aria-label="t('cms.forms.form')"><el-option v-for="form in forms" :key="form.id" :label="form.name" :value="form.id" /></el-select>
          <el-select v-model="filters.status" multiple collapse-tags :placeholder="t('cms.forms.allStatuses')" :aria-label="t('cms.forms.status')"><el-option v-for="(label,value) in statuses" :key="value" :label="label" :value="value" /></el-select>
          <el-date-picker v-model="filters.range" type="daterange" value-format="YYYY-MM-DD" :start-placeholder="t('cms.forms.startDate')" :end-placeholder="t('cms.forms.endDate')" :aria-label="t('cms.forms.dateRange')" />
          <el-input v-model="filters.keyword" clearable maxlength="100" :placeholder="t('cms.forms.keyword')" :aria-label="t('cms.forms.keyword')" @keyup.enter="search" />
          <el-button type="primary" :disabled="loading" @click="search">{{ t('cms.forms.search') }}</el-button>
          <el-button v-if="canExport" :disabled="busy" @click="exportCsv">{{ t('cms.forms.export') }}</el-button>
        </div>
        <div v-if="canHandle||canDelete" class="toolbar">
          <el-button v-if="canHandle" :disabled="busy||!selection.length" @click="invalidate">{{ t('cms.forms.markInvalid') }}</el-button>
          <el-popconfirm v-if="canDelete" :title="t('cms.forms.deleteSubmissionsConfirm',{count:selection.length})" width="320" :confirm-button-text="t('cms.forms.confirmDelete')" :cancel-button-text="t('cms.forms.cancel')" @confirm="removeSelected"><template #reference><el-button type="danger" plain :disabled="busy||!selection.length">{{ t('cms.forms.deleteSelected') }}</el-button></template></el-popconfirm>
          <span class="muted">{{ t('cms.forms.selectedCount',{count:selection.length}) }}</span>
        </div>
        <el-table v-loading="loading" :data="rows" row-key="id" :empty-text="t('cms.forms.noSubmissions')" @selection-change="selection=$event">
          <el-table-column v-if="canHandle||canDelete" type="selection" width="44" />
          <el-table-column prop="form_name" :label="t('cms.forms.form')" min-width="120" />
          <el-table-column :label="t('cms.forms.summary')" min-width="220"><template #default="{row}"><span v-for="item in row.summary" :key="item.label" class="summary-item">{{ t('cms.forms.summaryItem',{label:item.label,value:item.value}) }}</span></template></el-table-column>
          <el-table-column :label="t('cms.forms.status')" width="110"><template #default="{row}"><el-tag :type="row.status==='new'?'danger':row.status==='processing'?'warning':'info'">{{ statuses[row.status]||row.status }}</el-tag></template></el-table-column>
          <el-table-column prop="created_at" :label="t('cms.forms.submittedAt')" width="170" />
          <el-table-column :label="t('cms.forms.actions')" width="90"><template #default="{row}"><el-button link type="primary" @click="detailId=row.id">{{ t('cms.forms.view') }}</el-button></template></el-table-column>
        </el-table>
        <TablePagination v-if="total>PAGE_SIZE" v-model:current-page="page" :total="total" :page-size="PAGE_SIZE" layout="total,prev,pager,next" />
      </el-tab-pane>
      <el-tab-pane name="forms" :label="t('cms.forms.manageTab')">
        <div v-if="canManage" class="toolbar"><el-button type="primary" @click="openEditor()">{{ t('cms.forms.newForm') }}</el-button></div>
        <el-table :data="forms" row-key="id" :empty-text="t('cms.forms.noForms')">
          <el-table-column prop="name" :label="t('cms.forms.formName')" min-width="140" />
          <el-table-column prop="code" :label="t('cms.forms.code')" min-width="120" />
          <el-table-column :label="t('cms.forms.enabled')" width="90"><template #default="{row}"><el-switch :model-value="row.enabled" :disabled="!canManage||busy" :aria-label="t('cms.forms.enabled')" @change="toggle(row as CmsForm,!!$event)" /></template></el-table-column>
          <el-table-column prop="submission_count" :label="t('cms.forms.submissionCount')" width="100" />
          <el-table-column prop="new_count" :label="t('cms.forms.statuses.new')" width="100" />
          <el-table-column :label="t('cms.forms.actions')" min-width="200"><template #default="{row}">
            <el-button v-if="canManage" link type="primary" @click="openEditor(row as CmsForm)">{{ t('cms.forms.edit') }}</el-button>
            <el-button link @click="embed=row as CmsForm">{{ t('cms.forms.embedCode') }}</el-button>
            <el-popconfirm v-if="canManage&&!row.submission_count" :title="t('cms.forms.deleteFormConfirm',{name:row.name})" width="300" :confirm-button-text="t('cms.forms.confirmDelete')" :cancel-button-text="t('cms.forms.cancel')" @confirm="removeForm(row as CmsForm)"><template #reference><el-button link type="danger" :disabled="busy">{{ t('cms.forms.delete') }}</el-button></template></el-popconfirm>
          </template></el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>
    <FormSubmissionDrawer v-if="canSubmissions" :id="detailId" :scope="scope" :can="can" @close="detailId=undefined" @changed="refresh" />
    <FormEditor v-if="editorOpen&&canManage" :site-id="scope.site_id" :form="editing" :languages="languages" @saved="saved" @close="editorOpen=false" />
    <el-dialog :model-value="!!embed" :title="t('cms.forms.embedCode')" width="min(640px,94vw)" @close="embed=undefined">
      <div v-for="item in embedCodes" :key="item.label" class="embed-code"><h4>{{ item.label }}</h4><pre>{{ item.code }}</pre><el-button size="small" @click="copy(item.code)">{{ t('cms.forms.copy') }}</el-button></div>
      <template #footer><el-button @click="embed=undefined">{{ t('cms.forms.close') }}</el-button></template>
    </el-dialog>
  </section>
</template>
<style scoped>
.forms-panel .el-alert{margin-bottom:16px}.toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:16px}.toolbar .el-select{width:180px}.toolbar .el-input{width:200px}.muted{color:var(--el-text-color-secondary);font-size:12px}.summary-item{display:inline-block;margin-right:12px;overflow-wrap:anywhere}.embed-code{margin-bottom:16px}.embed-code h4{margin:0 0 8px;font-size:13px}.embed-code pre{white-space:pre-wrap;overflow-wrap:anywhere;background:var(--el-fill-color-light);padding:12px;margin:0 0 8px;font-size:13px}
</style>
