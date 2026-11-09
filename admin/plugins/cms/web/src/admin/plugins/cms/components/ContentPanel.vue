<script setup lang="ts">
import TablePagination from '@/components/TablePagination/index.vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onMounted, onBeforeUnmount, ref, nextTick, watch } from 'vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { cmsApi } from '../api';
import type { Scope, ContentRow, ContentModel, Category, Revision, FormData, Locale, ModelField, FormOption } from '../types';
import { useOperation } from './useOperation';
import AttachmentField from './AttachmentField.vue';
import RichTextEditor from '@/components/RichTextEditor/index.vue';
import DataTableShell from '@/components/DataTable/DataTableShell.vue';
import { planModelUpgrade } from './modelUpgrade';
import { fieldDefaults, validateContentForm, compatibleCategories, contentFingerprint } from './contentForm';
import { batchTargets, runBatch, type BatchAction, type BatchFailure } from './contentBatch';
import { AUTOSAVE_INTERVALS, autosaveChanges, autosaveKey, clearAutosave, readAutosave, readAutosaveInterval, writeAutosave, writeAutosaveInterval, type AutosaveEntry } from './contentAutosave';
const { t } = useCmsI18n();
const props = defineProps<{scope: Scope; can: (permission: string) => boolean; languages: Locale[]}>();
const { busy, error, run } = useOperation();
const rows = ref<ContentRow[]>([]), models = ref<ContentModel[]>([]), categories = ref<Category[]>([]), total = ref(0), page = ref(1), query = ref(''), modelFilter = ref('');
const editor = ref(false), preview = ref(''), revisions = ref<Revision[]>([]), form = ref<FormData>({}), editLocale = ref(props.scope.locale), historyOpen = ref(false);
const activeEditorTab=ref('basic'),fieldErrors=ref<Record<string,string>>({}),attempted=ref(false),savedFingerprint=ref(''),savedForm=ref<FormData>({});
const editorCategoryRows=ref<Category[]>([]);
const dirty=computed(()=>editor.value&&contentFingerprint(form.value)!==savedFingerprint.value);
const {confirmDiscard}=useUnsavedChanges(dirty);
const editorCategories=computed(()=>compatibleCategories(editorCategoryRows.value,String(form.value.model_id||'')));
const unavailableCategory=computed(()=>form.value.category_id&&!editorCategories.value.some(item=>item.id===form.value.category_id));
const templates = ref<Array<{value:string;label:string}>>([]), formOptions = ref<FormOption[]>([]);
const uploadingFields = ref(new Set<string>());
const uploadsBusy = computed(() => uploadingFields.value.size > 0);
const templateOptions = computed(() => form.value.template && !templates.value.some(item=>item.value===form.value.template) ? [...templates.value,{value:form.value.template,label:t('cms.labels.originalTemplateValue', { value1: form.value.template })}] : templates.value);
function uploadBusy(name:string,value:boolean){const next=new Set(uploadingFields.value);if(value)next.add(name);else next.delete(name);uploadingFields.value=next;}
const uploadBody = (file:File,images:boolean) => cmsApi.upload({...props.scope,locale:editLocale.value},file,images);
const historyTarget = ref<ContentRow>();
const loadedLocale = ref(props.scope.locale), languageBusy = ref(false), upgradeOpen = ref(false), upgrade = ref<ReturnType<typeof planModelUpgrade>>({data:{},removed:[]});
let languageGeneration = 0;
const latestModel = computed(() => models.value.find(model => model.id === form.value.model_id));
const canUpgrade = computed(() => !!form.value.content_id && latestModel.value && form.value.model_version_id !== latestModel.value.schema.id);
const fields = computed<ModelField[]>(() => form.value.field_schema || models.value.find(model => model.id === form.value.model_id)?.schema.field_schema || []);
async function load() { if (!(props.can('cms:content:list'))) return; await run(async () => { const result = await cmsApi.content({...props.scope, page: page.value, limit: 12, q: query.value, model: modelFilter.value}); rows.value = result.items; total.value = result.total; }); }
function hydrate(value:FormData):FormData{
  const schema=value.field_schema||models.value.find(model=>model.id===value.model_id)?.schema.field_schema||[];
  return {...value,data:fieldDefaults(schema,value.data||{})};
}
async function beforeClose(done:()=>void){
  if(busy.value||languageBusy.value||uploadsBusy.value){error.value=t('cms.labels.savingSwitchingLanguageOrUploadingPleaseWait');return;}
  const wasDirty=dirty.value,slot=autosaveSlot.value;
  if(await confirmDiscard()){if(wasDirty)discardAutosave(slot);recovery.value=null;languageGeneration++;done();}
}
async function open(row?: ContentRow) {
  if(!props.can(row?'cms:content:edit':'cms:content:create'))return;
  if(editor.value){const wasDirty=dirty.value,slot=autosaveSlot.value;if(!await confirmDiscard())return;if(wasDirty)discardAutosave(slot);}
  languageGeneration++;languageBusy.value=false;loadedLocale.value=props.scope.locale;
  editLocale.value=props.scope.locale;revisions.value=[];historyOpen.value=false;error.value='';attempted.value=false;fieldErrors.value={};activeEditorTab.value='basic';editorCategoryRows.value=categories.value;
  form.value=hydrate(row?{...JSON.parse(JSON.stringify(row.revision)),content_id:row.content_id,model_id:row.model_id,category_id:row.category_id||'',id:row.id,draft_revision_id:row.draft_revision_id,lock_version:row.lock_version}:{model_id:models.value[0]?.id||'',title:'',summary:'',body_html:'',desired_path:'',data:{},template:'',robots:'index,follow',category_id:'',form_code:''});
  savedFingerprint.value=contentFingerprint(form.value);savedForm.value=JSON.parse(JSON.stringify(form.value));editor.value=true;checkRecovery();
}
function changeModel(){
  if(form.value.content_id)return;
  form.value.data=fieldDefaults(fields.value);
  if(!editorCategories.value.some(category=>category.id===form.value.category_id))form.value.category_id='';
  attempted.value=false;fieldErrors.value={};
}
async function changeLanguage(value:string){
  if(!props.can('cms:content:list'))return;
  const generation=++languageGeneration;
  if(uploadsBusy.value){editLocale.value=loadedLocale.value;error.value=t('cms.labels.uploadingPleaseWait');return;}
  const wasDirty=dirty.value,slot=autosaveSlot.value;
  if(!await confirmDiscard()){if(generation===languageGeneration)editLocale.value=loadedLocale.value;return;}
  if(wasDirty)discardAutosave(slot);
  if(generation!==languageGeneration)return;
  const source=JSON.parse(JSON.stringify(dirty.value?savedForm.value:form.value));languageBusy.value=true;error.value='';
  try{
    const target={...props.scope,locale:value};
    const [lookup,result]=await Promise.all([cmsApi.lookups(target),source.content_id?cmsApi.translation({...target,content_id:source.content_id}):Promise.resolve(null)]);
    if(generation!==languageGeneration)return;
    let next=source;
    if(result?.revision)next={...result.revision,content_id:result.content_id,model_id:result.model_id,category_id:result.category_id||'',id:result.id,draft_revision_id:result.draft_revision_id,lock_version:result.lock_version};
    else if(result){
      const prefix=props.languages.find(item=>item.locale===value)?.url_prefix||'';
      const last=String(source.desired_path).split('/').filter(Boolean).pop()||'new-page.html';
      delete next.id;delete next.lock_version;delete next.draft_revision_id;
      next.desired_path=`/${prefix?`${prefix}/`:''}${last}`;
    }
    editorCategoryRows.value=lookup.categories||[];templates.value=lookup.templates||[];form.value=hydrate(next);
    loadedLocale.value=value;editLocale.value=value;revisions.value=[];attempted.value=false;fieldErrors.value={};
    // An unsaved translation copied from another language still needs a first save.
    savedFingerprint.value=result&&!result.revision?'':contentFingerprint(form.value);savedForm.value=JSON.parse(JSON.stringify(form.value));checkRecovery();
  }catch{if(generation===languageGeneration){editLocale.value=loadedLocale.value;error.value=t('cms.labels.couldNotLoadTranslationOrCategoriesOriginalLanguageContent');}}
  finally{if(generation===languageGeneration)languageBusy.value=false;}
}
function validate(){
  const errors=validateContentForm(form.value,fields.value);
  if(unavailableCategory.value)errors.category_id=t('cms.labels.categoryIsDisabledOrIncompatibleWithThisModelSelect');
  fieldErrors.value=errors;return Object.keys(errors).length===0;
}
async function focusError(){
  const name=Object.keys(fieldErrors.value)[0];activeEditorTab.value=name?.startsWith('data.')?'fields':'basic';
  await nextTick();const target=document.querySelector<HTMLElement>(`[data-content-field="${name}"]`);target?.scrollIntoView?.({block:'center',behavior:'smooth'});target?.querySelector<HTMLElement>('input,textarea,[tabindex]')?.focus();
}
watch(form,()=>{if(attempted.value)validate();},{deep:true});
function prepareUpgrade() { if (!(props.can(form.value.content_id ? 'cms:content:edit' : 'cms:content:create'))) return;
  if (!latestModel.value) return;
  upgrade.value = planModelUpgrade(fields.value,latestModel.value.schema.field_schema,form.value.data);
  upgradeOpen.value = true;
}
function adoptUpgrade() { if (!(props.can(form.value.content_id ? 'cms:content:edit' : 'cms:content:create'))) return;
  if (!latestModel.value) return;
  form.value.model_version_id = latestModel.value.schema.id;
  form.value.field_schema = JSON.parse(JSON.stringify(latestModel.value.schema.field_schema));
  form.value.data = upgrade.value.data; upgradeOpen.value = false;
}
onBeforeUnmount(() => { languageGeneration++; persistAutosave(); if (autosaveTimer) clearInterval(autosaveTimer); window.removeEventListener('beforeunload', flushBeforeUnload); document.removeEventListener('visibilitychange', flushAutosave); });
async function save() { if (!(props.can(form.value.content_id ? 'cms:content:edit' : 'cms:content:create'))) return;
  if (uploadsBusy.value || languageBusy.value || editLocale.value !== loadedLocale.value) return;
  if(busy.value)return;attempted.value=true;if(!validate()){await focusError();return;}
  const target = form.value, generation = languageGeneration;
  const fingerprint=contentFingerprint(target);
  const submitted = JSON.parse(JSON.stringify({...target, ...props.scope, locale: editLocale.value})), slot = autosaveSlot.value;
  await run(async () => {
    const result = await cmsApi.saveContent(submitted);
    discardAutosave(slot);
    if (editor.value && generation === languageGeneration && form.value === target) {
      const unchanged=contentFingerprint(target)===fingerprint;
      target.id = result.id;
      target.lock_version = result.lock_version;
      target.content_id = result.content_id;
      target.draft_revision_id = result.draft_revision_id;
      const saved=hydrate({...submitted,...result.revision,model_id:result.model_id||submitted.model_id,id:result.id,content_id:result.content_id,draft_revision_id:result.draft_revision_id,lock_version:result.lock_version});
      target.model_version_id=saved.model_version_id;target.field_schema=saved.field_schema;
      if(unchanged){Object.assign(target,saved);}
      savedFingerprint.value=contentFingerprint(saved);savedForm.value=JSON.parse(JSON.stringify(saved));
      // 保存后仍有新的修改（保存期间继续编辑）时立即重新暂存到新位置。
      if(!unchanged)persistAutosave();
    }
    await load();
  });
}
async function publish(row: ContentRow, revisionId?: string) { if (!(props.can('cms:content:publish'))) return; await run(async () => { const result = await cmsApi.publishContent({site_id: props.scope.site_id, id: row.id, revision_id: revisionId || row.draft_revision_id, lock_version: row.lock_version}); if (form.value.id === result.id) form.value.lock_version = result.lock_version; await load(); }); }
async function publishEditor() { if (!(props.can('cms:content:publish'))) return;
  if (uploadsBusy.value || !form.value.id || languageBusy.value || editLocale.value !== loadedLocale.value) return;
  if(dirty.value){error.value=t('cms.labels.unsavedChangesSaveTheDraftBeforePublishing');return;}
  await publish({id: form.value.id, draft_revision_id: form.value.draft_revision_id, lock_version: form.value.lock_version} as ContentRow);
}
async function offline(row: ContentRow) { if (!(props.can('cms:content:offline'))) return; await run(async () => { await cmsApi.offline({site_id: props.scope.site_id, id: row.id, draft_revision_id: row.draft_revision_id, lock_version: row.lock_version}); await load(); }); }
// 本地草稿暂存：未保存修改按间隔写入本机，离开页面或切走标签页时立即写入；保存成功或主动丢弃后清除。
const autosaveInterval = ref(readAutosaveInterval()), autosavedAt = ref(0);
const recovery = ref<{entry: AutosaveEntry; changes: string[]; conflict: boolean} | null>(null);
const autosaveSlot = computed(() => autosaveKey(String(props.scope.site_id), loadedLocale.value, form.value));
function persistAutosave() {
  if (!editor.value || !dirty.value || languageBusy.value || recovery.value) return;
  if (writeAutosave(autosaveSlot.value, form.value)) autosavedAt.value = Date.now();
}
function discardAutosave(key = autosaveSlot.value) { clearAutosave(key); autosavedAt.value = 0; }
function checkRecovery() {
  recovery.value = null; autosavedAt.value = 0;
  const entry = readAutosave(autosaveSlot.value); if (!entry) return;
  if (contentFingerprint(entry.form) === savedFingerprint.value) { discardAutosave(); return; }
  recovery.value = {entry, changes: autosaveChanges(entry.form, form.value), conflict: entry.base_lock_version !== null && entry.base_lock_version !== form.value.lock_version};
}
/** 恢复时保留服务端当前的译文身份与版本号，保存仍走乐观锁；冲突场景已向作者展示两边摘要。 */
function restoreAutosave() {
  if (!recovery.value) return; const local = recovery.value.entry.form, current = form.value;
  form.value = hydrate({...JSON.parse(JSON.stringify(local)), id: current.id, content_id: current.content_id, draft_revision_id: current.draft_revision_id, lock_version: current.lock_version, revision_no: current.revision_no, model_id: current.content_id ? current.model_id : local.model_id});
  recovery.value = null;
}
function rejectAutosave() { discardAutosave(); recovery.value = null; }
function changeLabel(key: string) {
  if (key.startsWith('data.')) return fields.value.find(field => `data.${field.name}` === key)?.label || key.slice(5);
  const labels: Record<string, string> = {title: 'title', summary: 'summary', body_html: 'body', desired_path: 'urlPath', category_id: 'category', seo_title: 'seoTitle', seo_description: 'seoDescription', form_code: 'attachedForm'};
  return labels[key] ? t(`cms.labels.${labels[key]}`) : key;
}
const formatTime = (value: number) => new Date(value).toLocaleString();
let autosaveTimer: ReturnType<typeof setInterval> | undefined;
watch([editor, autosaveInterval], ([open, seconds]) => {
  if (autosaveTimer) clearInterval(autosaveTimer); autosaveTimer = undefined;
  if (open && seconds > 0) autosaveTimer = setInterval(persistAutosave, seconds * 1000);
});
watch(autosaveInterval, seconds => writeAutosaveInterval(seconds));
const flushAutosave = () => { if (document.visibilityState === 'hidden') persistAutosave(); };
const flushBeforeUnload = () => persistAutosave();
const selection = ref<ContentRow[]>([]), batchFailures = ref<BatchFailure[]>([]), batchNotice = ref('');
const publishTargets = computed(() => batchTargets(selection.value, 'publish')), offlineTargets = computed(() => batchTargets(selection.value, 'offline'));
/** 批量发布/下架：逐条调用单条接口，结果汇总显示；失败条目（如版本已变化）列出原因，其余照常完成。 */
async function batch(action: BatchAction) {
  if (!props.can(action === 'publish' ? 'cms:content:publish' : 'cms:content:offline')) return;
  const targets = action === 'publish' ? publishTargets.value : offlineTargets.value; if (!targets.length || busy.value) return;
  batchFailures.value = []; batchNotice.value = '';
  await run(async () => {
    const result = await runBatch(targets, row => action === 'publish'
      ? cmsApi.publishContent({site_id: props.scope.site_id, id: row.id, revision_id: row.draft_revision_id, lock_version: row.lock_version})
      : cmsApi.offline({site_id: props.scope.site_id, id: row.id, draft_revision_id: row.draft_revision_id, lock_version: row.lock_version}), t('cms.labels.operationFailedCheckYourInputAndRetry'));
    batchFailures.value = result.failed;
    batchNotice.value = t(action === 'publish' ? 'cms.labels.batchPublishResult' : 'cms.labels.batchOfflineResult', {succeeded: result.succeeded.length, failed: result.failed.length});
    await load();
  });
}
async function showPreview(row?: ContentRow) { if (!(props.can('cms:content:preview'))) return; if(!row&&dirty.value){error.value=t('cms.labels.unsavedChangesSaveTheDraftBeforePreviewing');return;} await run(async () => { preview.value = (await cmsApi.previewContent({site_id: props.scope.site_id, id: row?.id || form.value.id})).html; }); }
async function history(row?:ContentRow) { if (!props.can('cms:content:list')) return; const target=row||{id:form.value.id,draft_revision_id:form.value.draft_revision_id,lock_version:form.value.lock_version} as ContentRow; historyTarget.value=JSON.parse(JSON.stringify(target)); await run(async()=>{revisions.value=await cmsApi.revisions({site_id:props.scope.site_id,id:target.id});historyOpen.value=true;}); }
async function restore(revision: Revision) { if (!(props.can('cms:content:publish'))) return; if(dirty.value){error.value=t('cms.labels.unsavedChangesSaveTheDraftBeforePublishingARevision');return;} if(!historyTarget.value)return;await publish(historyTarget.value,revision.id);historyOpen.value=false; }
onMounted(async () => { window.addEventListener('beforeunload', flushBeforeUnload); document.addEventListener('visibilitychange', flushAutosave); if (!props.can('cms:content:list')) return; await run(async () => { const lookup = await cmsApi.lookups(props.scope); models.value = lookup.models; categories.value = lookup.categories;editorCategoryRows.value=lookup.categories; templates.value = lookup.templates || []; formOptions.value = lookup.forms || []; }); await load(); });
</script>
<template>
  <div class="panel"><el-alert v-if="error" :title="error" type="error" :closable="false" />
    <DataTableShell storage-key="cms-content" :loading="busy" :show-column-setting="false" @refresh="load">
      <template #search>
        <el-form inline class="cms-search" @submit.prevent="page = 1; load()">
          <el-form-item :label="t('cms.labels.title')"><el-input v-model="query" :placeholder="t('cms.labels.enterATitle')" clearable @keyup.enter="page = 1; load()" /></el-form-item>
          <el-form-item :label="t('cms.labels.contentModel')"><el-select v-model="modelFilter" :placeholder="t('cms.labels.allModels')" clearable><el-option v-for="model in models" :key="model.id" :label="model.name" :value="model.code" /></el-select></el-form-item>
          <el-form-item><el-button type="primary" @click="page = 1; load()">{{ t('cms.labels.search') }}</el-button><el-button @click="query = ''; modelFilter = ''; page = 1; load()">{{ t('cms.labels.reset') }}</el-button></el-form-item>
        </el-form>
      </template>
      <template #toolbar-left><el-button v-if="can('cms:content:create')" type="primary" plain :disabled="busy || !models.length" @click="open()"><i class="i-ep-plus" />{{ t('cms.labels.newContent') }}</el-button>
        <el-popconfirm v-if="can('cms:content:publish')" :title="t('cms.labels.batchPublishConfirm', {count: publishTargets.length})" width="320" @confirm="batch('publish')"><template #reference><el-button type="success" plain :disabled="busy || !publishTargets.length">{{ t('cms.labels.batchPublish', {count: publishTargets.length}) }}</el-button></template></el-popconfirm>
        <el-popconfirm v-if="can('cms:content:offline')" :title="t('cms.labels.batchOfflineConfirm', {count: offlineTargets.length})" width="320" @confirm="batch('offline')"><template #reference><el-button type="warning" plain :disabled="busy || !offlineTargets.length">{{ t('cms.labels.batchOffline', {count: offlineTargets.length}) }}</el-button></template></el-popconfirm>
        <span v-if="selection.length" class="muted">{{ t('cms.labels.batchSelected', {count: selection.length}) }}</span></template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-alert v-if="batchNotice" :title="batchNotice" :type="batchFailures.length ? 'warning' : 'success'" show-icon @close="batchNotice = ''; batchFailures = []"><ul v-if="batchFailures.length" class="batch-failures"><li v-for="item in batchFailures" :key="item.id">{{ t('cms.labels.batchFailureItem', {title: item.title, message: item.message}) }}</li></ul></el-alert>
        <el-table :data="rows" row-key="id" v-loading="busy" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" :empty-text="t('cms.labels.noContent')" @selection-change="selection = $event">
          <el-table-column v-if="can('cms:content:publish') || can('cms:content:offline')" type="selection" width="44" />
          <el-table-column :label="t('cms.labels.title')" min-width="300" show-overflow-tooltip><template #default="{row}"><strong>{{ row.revision?.title }}</strong><div class="muted">{{ row.revision?.desired_path }}</div></template></el-table-column>
          <el-table-column prop="locale" :label="t('cms.labels.language')" width="110" />
          <el-table-column :label="t('cms.labels.status')" width="110" align="center"><template #default="{row}"><el-tag :type="row.status === 'published' ? 'success' : 'info'">{{ {published:t('cms.labels.published'),draft:t('cms.labels.draft'),offline:t('cms.labels.unpublished')}[row.status as string] }}</el-tag></template></el-table-column>
          <el-table-column :label="t('cms.labels.revision')" width="150" align="center"><template #default="{row}">V{{ row.revision?.revision_no }}<span v-if="row.published_revision_id && row.published_revision_id !== row.draft_revision_id" class="muted"> {{ t('cms.labels.draftAvailable') }}</span></template></el-table-column>
          <el-table-column :label="t('cms.labels.actions')" width="370" align="center" fixed="right"><template #default="{row}"><el-button v-if="can('cms:content:edit')" link type="primary" @click="open(row as ContentRow)">{{ t('cms.labels.editTranslate') }}</el-button><el-button v-if="can('cms:content:list')" link @click="history(row as ContentRow)">{{ t('cms.labels.revisionHistory') }}</el-button><el-button v-if="can('cms:content:preview')" link @click="showPreview(row as ContentRow)">{{ t('cms.labels.preview') }}</el-button><el-button v-if="can('cms:content:publish')" link type="success" :disabled="busy" @click="publish(row as ContentRow)">{{ t('cms.labels.publishDraft') }}</el-button><el-button v-if="can('cms:content:offline') && row.status === 'published'" link type="warning" :disabled="busy" @click="offline(row as ContentRow)">{{ t('cms.labels.unpublish') }}</el-button></template></el-table-column>
        </el-table>
        <TablePagination v-model:current-page="page" :page-size="12" :total="total" layout="total, prev, pager, next, jumper" @current-change="load" />
      </template>
    </DataTableShell>
    <el-drawer v-model="editor" :title="t('cms.labels.contentEditor')" size="min(900px, 96vw)" destroy-on-close :close-on-click-modal="false" :before-close="beforeClose"><el-alert v-if="error" :title="error" type="error" :closable="false" /><el-alert v-if="recovery" :title="t(recovery.conflict ? 'cms.labels.autosaveConflictTitle' : 'cms.labels.autosaveRecoverTitle')" :type="recovery.conflict ? 'error' : 'info'" :closable="false" class="dirty-notice autosave-recovery"><p>{{ t('cms.labels.autosaveRecoverDetail', {time: formatTime(recovery.entry.saved_at), fields: recovery.changes.map(changeLabel).join('、') || '—'}) }}</p><p v-if="recovery.conflict">{{ t('cms.labels.autosaveConflictDetail', {local: String(recovery.entry.form.title || '—'), server: String(form.title || '—'), revision: String(form.revision_no ?? '')}) }}</p><div class="autosave-actions"><el-button size="small" type="primary" @click="restoreAutosave">{{ t('cms.labels.autosaveRestore') }}</el-button><el-button size="small" @click="rejectAutosave">{{ t('cms.labels.autosaveDiscard') }}</el-button></div></el-alert><el-alert v-if="dirty" :title="t('cms.labels.youHaveUnsavedChangesSaveTheDraftBeforePreviewing')" type="warning" :closable="false" class="dirty-notice" /><el-tabs v-model="activeEditorTab" class="editor-tabs"><el-tab-pane :label="t('cms.labels.basicContent')" name="basic"><el-form label-position="top" class="editor-form" :disabled="busy||languageBusy||uploadsBusy"><div class="two-columns"><el-form-item :label="t('cms.labels.contentLanguage')"><el-select v-model="editLocale" @change="changeLanguage"><el-option v-for="language in languages" :key="language.locale" :label="language.name" :value="language.locale" /></el-select></el-form-item><el-form-item :label="t('cms.labels.contentModel')"><el-select v-model="form.model_id" :disabled="!!form.content_id" @change="changeModel"><el-option v-for="model in models" :key="model.id" :label="model.name" :value="model.id" /></el-select></el-form-item></div>
      <el-form-item :label="t('cms.labels.title')" required :error="fieldErrors.title" data-content-field="title"><el-input v-model="form.title" maxlength="255" /></el-form-item><el-form-item :label="t('cms.labels.urlPath')" required :error="fieldErrors.desired_path" data-content-field="desired_path"><el-input v-model="form.desired_path" placeholder="/company/about.html" /><small class="muted">{{ t('cms.labels.takesEffectAfterPublishingOldUrlsWillRedirectAfter') }}</small></el-form-item>
      <el-form-item :label="t('cms.labels.category')" :error="fieldErrors.category_id" data-content-field="category_id"><el-select v-model="form.category_id" clearable><el-option v-if="unavailableCategory" :value="form.category_id" :label="t('cms.labels.originalCategoryUnavailableSelectAnotherCategory')" disabled /><el-option v-for="category in editorCategories" :key="category.id" :label="category.name" :value="category.id" /></el-select></el-form-item>
      <el-form-item :label="t('cms.labels.summary')"><el-input v-model="form.summary" type="textarea" :rows="3" /></el-form-item><el-form-item :label="t('cms.labels.body')"><RichTextEditor v-if="editor" :key="`${form.content_id||'new'}:${editLocale}`" v-model="form.body_html" :disabled="busy||languageBusy" :upload="uploadBody" @busy="uploadBusy('body',$event)" /></el-form-item>
      </el-form></el-tab-pane><el-tab-pane :label="t('cms.labels.modelFields')" name="fields"><el-form label-position="top" class="editor-form" :disabled="busy||languageBusy||uploadsBusy"><el-alert v-if="canUpgrade" :title="t('cms.labels.thisContentUsesAnEarlierFieldDefinitionKeepIt')" type="info" :closable="false" /><el-button v-if="canUpgrade && can('cms:content:edit')" @click="prepareUpgrade">{{ t('cms.labels.adoptLatestFieldDefinition') }}</el-button><el-form-item v-for="field in fields" :key="field.name" :label="field.label" :required="field.required" :error="fieldErrors[`data.${field.name}`]" :data-content-field="`data.${field.name}`"><el-switch v-if="field.type === 'boolean'" v-model="form.data[field.name]" /><el-input-number v-else-if="field.type === 'number'" v-model="form.data[field.name]" :min="field.min??undefined" :max="field.max??undefined" :placeholder="field.placeholder" /><el-select v-else-if="field.type === 'select'" v-model="form.data[field.name]" clearable :placeholder="field.placeholder||t('cms.labels.selectAnOption')"><el-option v-for="option in field.options" :key="option" :label="option" :value="option" /></el-select><AttachmentField v-else-if="['image','file','gallery'].includes(field.type)" :key="`${form.content_id||'new'}:${editLocale}:${field.name}`" :disabled="busy||languageBusy" @busy="uploadBusy(field.name,$event)" v-model="form.data[field.name]" :scope="{...scope,locale:editLocale}" :can="can" :images="field.type !== 'file'" :multiple="field.type === 'gallery'" /><el-input v-else v-model="form.data[field.name]" :type="field.type === 'textarea' ? 'textarea' : 'text'" :placeholder="field.placeholder" :maxlength="field.max_length??(field.type==='textarea'?20000:2000)" show-word-limit /><small v-if="field.description" class="muted field-help">{{ field.description }}</small></el-form-item>
      </el-form></el-tab-pane><el-tab-pane :label="t('cms.labels.seoAndDisplay')" name="seo"><el-form label-position="top" class="editor-form" :disabled="busy||languageBusy||uploadsBusy"><el-form-item :label="t('cms.labels.seoTitle')"><el-input v-model="form.seo_title" maxlength="255" /></el-form-item><el-form-item :label="t('cms.labels.seoDescription')"><el-input v-model="form.seo_description" type="textarea" maxlength="500" /></el-form-item><div class="two-columns"><el-form-item :label="t('cms.labels.author')"><el-input v-model="form.author_name" /></el-form-item><el-form-item :label="t('cms.labels.detailTemplate')"><el-select v-model="form.template" filterable clearable :placeholder="t('cms.labels.useCategoryModelDefaultTemplate')" @clear="form.template=''"><el-option :label="t('cms.labels.useCategoryModelDefaultTemplate')" value="" /><el-option v-for="template in templateOptions" :key="template.value" :label="template.label" :value="template.value" /></el-select><small class="muted">{{ t('cms.labels.onlyDetailPageTemplatesFromThisSiteSTheme') }}</small></el-form-item><el-form-item :label="t('cms.labels.attachedForm')" data-content-field="form_code"><el-select v-model="form.form_code" clearable :placeholder="t('cms.labels.noAttachedForm')" @clear="form.form_code=''"><el-option :label="t('cms.labels.noAttachedForm')" value="" /><el-option v-for="item in formOptions" :key="item.code" :label="item.status ? item.name : t('cms.labels.disabledFormOption', {name: item.name})" :value="item.code" /></el-select><small class="muted">{{ t('cms.labels.attachedFormHelp') }}</small></el-form-item><el-form-item :label="t('cms.labels.cover')"><AttachmentField :key="`${form.content_id||'new'}:${editLocale}:cover`" :disabled="busy||languageBusy" @busy="uploadBusy('cover',$event)" v-model="form.cover_attach_id" :scope="{...scope,locale:editLocale}" :can="can" images /></el-form-item><el-form-item :label="t('cms.labels.indexingRules')"><el-select v-model="form.robots"><el-option :label="t('cms.labels.allowIndexing')" value="index,follow" /><el-option :label="t('cms.labels.noIndexFollowLinks')" value="noindex,follow" /><el-option :label="t('cms.labels.noIndexNoFollow')" value="noindex,nofollow" /></el-select></el-form-item></div>
    </el-form></el-tab-pane></el-tabs><template #footer><div class="editor-actions"><div class="autosave-setting"><span class="muted">{{ t('cms.labels.autosaveInterval') }}</span><el-select v-model="autosaveInterval" size="small" class="autosave-select" :aria-label="t('cms.labels.autosaveInterval')"><el-option v-for="seconds in AUTOSAVE_INTERVALS" :key="seconds" :value="seconds" :label="seconds ? t('cms.labels.autosaveSeconds', {count: seconds}) : t('cms.labels.autosaveOff')" /></el-select><span v-if="autosavedAt && dirty" class="muted">{{ t('cms.labels.autosavedAt', {time: new Date(autosavedAt).toLocaleTimeString()}) }}</span></div><el-button v-if="form.id && can('cms:content:list')" @click="history()">{{ t('cms.labels.revisionHistory') }}</el-button><el-button v-if="form.id && can('cms:content:preview')" @click="showPreview()">{{ t('cms.labels.previewDraft') }}</el-button><el-button v-if="can(form.content_id ? 'cms:content:edit' : 'cms:content:create')" :loading="busy || languageBusy || uploadsBusy" :disabled="uploadsBusy" @click="save">{{ t('cms.labels.saveDraft') }}</el-button><el-button v-if="can('cms:content:publish')" type="primary" :disabled="busy || languageBusy || uploadsBusy || !form.id" @click="publishEditor">{{ t('cms.labels.publishSavedDraft') }}</el-button></div></template></el-drawer>
    <el-dialog :model-value="!!preview" :title="t('cms.labels.contentPreview')" width="min(1200px, 96vw)" @close="preview = ''"><iframe :title="t('cms.labels.cmsContentPreview')" sandbox="" :srcdoc="preview" class="preview-frame" /></el-dialog>
    <el-dialog v-model="upgradeOpen" :title="t('cms.labels.adoptLatestFieldDefinition')" width="min(700px, 94vw)"><p>{{ t('cms.labels.compatibleFieldsWithMatchingNamesRetainTheirValuesComplete') }}</p><el-alert v-if="upgrade.removed.length" :title="t('cms.labels.theseFieldsWereRemovedOrChangedTypeTheirValues')" type="warning" :closable="false" /><div v-for="field in upgrade.removed" :key="field.name"><strong>{{ field.label }}</strong><pre>{{ JSON.stringify(field.value,null,2) }}</pre></div><template #footer><el-button @click="upgradeOpen=false">{{ t('cms.labels.keepOriginalDefinition') }}</el-button><el-button v-if="can(form.content_id ? 'cms:content:edit' : 'cms:content:create')" type="primary" @click="adoptUpgrade">{{ t('cms.labels.confirmLatestDefinition') }}</el-button></template></el-dialog>
    <el-dialog v-model="historyOpen" :title="t('cms.labels.revisionHistory')" width="min(800px, 94vw)"><el-alert v-if="error" :title="error" type="error" :closable="false" /><el-alert v-if="dirty" :title="t('cms.labels.thisContentHasUnsavedChangesSaveTheDraftBefore')" type="warning" :closable="false" /><el-table :data="revisions"><el-table-column prop="revision_no" :label="t('cms.labels.version')" /><el-table-column prop="title" :label="t('cms.labels.title')" /><el-table-column prop="created_at" :label="t('cms.labels.savedAt')" /><el-table-column :label="t('cms.labels.actions')"><template #default="{row}"><el-button v-if="can('cms:content:publish')" link :disabled="busy||dirty" @click="restore(row as Revision)">{{ t('cms.labels.publishThisRevision') }}</el-button></template></el-table-column></el-table></el-dialog>
  </div>
</template>
<style scoped>.panel{width:100%;min-width:0}.cms-search .el-form-item{margin-bottom:12px;margin-right:24px}.cms-search .el-input{width:240px}.cms-search .el-select{width:180px}.muted{color:var(--el-text-color-secondary);font-size:12px}.batch-failures{margin:4px 0 0;padding-left:18px}.autosave-recovery p{margin:4px 0}.autosave-actions{display:flex;gap:8px;margin-top:6px}.autosave-setting{display:flex;align-items:center;gap:8px;margin-right:auto;flex-wrap:wrap}.autosave-select{width:110px}.two-columns{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}.field-help{display:block;width:100%;margin-top:6px}.dirty-notice{margin-bottom:16px}.editor-tabs :deep(.el-form-item__content){min-width:0}.editor-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.preview-frame{width:100%;height:65vh;border:1px solid var(--el-border-color)}.code-input :deep(textarea){font-family:monospace}@media(max-width:640px){.two-columns{grid-template-columns:1fr}.cms-search .el-form-item{margin-right:12px}.cms-search .el-input{width:190px}.cms-search .el-select{width:150px}}</style>
