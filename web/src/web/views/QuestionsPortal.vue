<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { questionsApi, questionsError, type QuestionSite, type Submission, type Question, type QuestionFile } from '@/web/api/questions';
import { memberSession } from '@/web/session/memberSession';
import RichTextEditor from '@/components/RichTextEditor/index.vue';
import LogoMark from '@/components/LogoMark.vue';
const { t } = useCmsI18n();
const route=useRoute(),router=useRouter();
const sites=ref<QuestionSite[]>([]),siteId=ref(''),locale=ref(''),ready=ref(false),loading=ref(false),busy=ref(false),error=ref(''),success=ref('');
const loggedIn=ref(memberSession.authenticated()),identity=ref(''),activeTab=ref('browse'),page=ref(1),total=ref(0),query=ref('');
const rows=ref<Question[]>([]),mine=ref<Submission[]>([]),question=ref<Question>(),askOpen=ref(false),title=ref(''),body=ref('');
// 会员附件（问答 1.3.0）：随提问或回答以 multipart 提交，服务端校验类型、大小与数量。
const MAX_FILES=5,ACCEPT='.jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.zip,.rar,.7z,.tar,.gz';
const files=ref<File[]>([]),fileInput=ref(0);
const current=computed(()=>sites.value.find(site=>site.id===siteId.value));
const languages=computed(()=>current.value?.locales||[]);
const scope=computed(()=>({site_id:siteId.value,locale:locale.value}));
const canPost=computed(()=>ready.value&&loggedIn.value&&!!current.value?.enabled&&!busy.value&&!loading.value);
const questionId=computed(()=>typeof route.params.id==='string'?route.params.id:'');
const ownsQuestion=computed(()=>!!identity.value&&question.value?.member_id===identity.value);
const statusNames=computed<Record<string,string>>(()=>({pending:t('community.labels.pendingReview'),published:t('community.labels.published'),rejected:t('community.labels.rejected'),withdrawn:t('community.labels.withdrawn'),hidden:t('community.labels.unpublished')}));
let generation=0,disposed=false,lastSignature='',requestKey='';
function submission(action:string,payload:Record<string,unknown>){
 const signature=action+JSON.stringify(payload)+JSON.stringify(files.value.map(file=>[file.name,file.size,file.lastModified]));
 if(signature!==lastSignature){lastSignature=signature;requestKey=Array.from(crypto.getRandomValues(new Uint8Array(16)),byte=>byte.toString(16).padStart(2,'0')).join('');}
 return {...payload,request_key:requestKey};
}
function clearDraft(){title.value='';body.value='';files.value=[];fileInput.value++;lastSignature='';}
function pickFiles(event:Event){
 const selected=Array.from((event.target as HTMLInputElement).files||[]);
 if(selected.length>MAX_FILES){error.value=t('community.labels.tooManyAttachments');files.value=[];fileInput.value++;return;}
 error.value='';files.value=selected;
}
function fileSize(bytes:number){return bytes>=1048576?`${(bytes/1048576).toFixed(1)} MB`:`${Math.max(1,Math.round(bytes/1024))} KB`;}
async function download(file:QuestionFile){
 if(!loggedIn.value)return;error.value='';
 try{await questionsApi.download(file.id,{...scope.value},file.name);}catch(e){error.value=questionsError(e);}
}
async function loadIdentity(){
 loggedIn.value=memberSession.authenticated();identity.value='';
 if(loggedIn.value)try{const result=await questionsApi.identity();if(!disposed)identity.value=result.member_id;}catch(e){if(!disposed)error.value=questionsError(e);}
}
async function load(){
 const token=++generation;error.value='';rows.value=[];mine.value=[];question.value=undefined;total.value=0;
 if(!ready.value||!siteId.value||!locale.value)return;
 loading.value=true;const target={...scope.value};
 try{
  if(activeTab.value==='mine'){
   if(!loggedIn.value)return;
   const result=await questionsApi.mine(target,page.value);if(token===generation){mine.value=result.items;total.value=result.total;}
  }else{
   if(questionId.value){const result=await questionsApi.question(questionId.value,target);if(token===generation)question.value=result;}
   else{const result=await questionsApi.questions(target,query.value,page.value);if(token===generation){rows.value=result.items;total.value=result.total;}}
  }
 }catch(e){if(token===generation)error.value=questionsError(e);}
 finally{if(token===generation)loading.value=false;}
}
async function initialize(){
 loading.value=true;error.value='';
 try{
  const context=await questionsApi.context();if(disposed)return;sites.value=context.sites;ready.value=context.ready;
  const desired=typeof route.query.site_id==='string'?route.query.site_id:'';
  siteId.value=sites.value.find(site=>site.id===desired)?.id||sites.value[0]?.id||'';
  await loadIdentity();await load();
 }catch(e){if(!disposed)error.value=questionsError(e);}
 finally{if(!disposed)loading.value=false;}
}
watch(siteId,()=>{
 const requested=typeof route.query.locale==='string'?route.query.locale:'';
 locale.value=languages.value.find(item=>item.locale===requested)?.locale||current.value?.default_locale||'';
 page.value=1;clearDraft();askOpen.value=false;void load();
});
watch([locale,questionId,activeTab],()=>{page.value=1;clearDraft();void load();});
watch(page,()=>void load());
function login(){return {path:'/web/login',query:{redirect:route.fullPath}};}
function questionLink(id:string){return {path:`/web/questions/${id}`,query:{site_id:siteId.value,locale:locale.value}};}
function canSend(kind:'question'|'answer'){
 return canPost.value&&(kind==='answer'||!!title.value.trim())&&!!body.value.trim();
}
async function send(kind:'question'|'answer'){
 if(!canSend(kind))return;
 busy.value=true;error.value='';success.value='';const target={...scope.value},token=generation;
 try{
  const payload={...target,body_html:body.value,...(kind==='answer'?{}:{title:title.value})};
  if(kind==='question')await questionsApi.ask(submission(kind,payload),[...files.value]);
  else await questionsApi.answer(questionId.value,submission(`answer:${questionId.value}`,payload),[...files.value]);
  if(token===generation){clearDraft();askOpen.value=false;success.value=t('community.labels.submittedYourContentWillBePublicAfterApproval');await load();}
 }catch(e){if(token===generation)error.value=questionsError(e);}
 finally{busy.value=false;}
}
async function accept(answerId:string){
 if(!question.value||!ownsQuestion.value||busy.value)return;busy.value=true;error.value='';
 const selected=question.value,target={...scope.value},token=generation;
 try{await questionsApi.accept(selected.id,{...target,answer_id:answerId,question_version:selected.question_version});if(token===generation){success.value=t('community.labels.answerAccepted');await load();}}
 catch(e){if(token===generation)error.value=questionsError(e);}finally{busy.value=false;}
}
async function withdraw(row:Submission){
 if(busy.value)return;busy.value=true;error.value='';const token=generation;
 try{await questionsApi.withdraw(row.id,{...scope.value,lock_version:row.lock_version});if(token===generation){success.value=t('community.labels.withdrawn');await load();}}
 catch(e){if(token===generation)error.value=questionsError(e);}finally{busy.value=false;}
}
function sessionChanged(){void loadIdentity().then(()=>load());}
onMounted(()=>{window.addEventListener('plugin-hooks-session',sessionChanged);void initialize();});
onBeforeUnmount(()=>{disposed=true;generation++;window.removeEventListener('plugin-hooks-session',sessionChanged);});
</script>
<template>
 <main class="community-page">
  <header class="portal-header"><RouterLink to="/" class="portal-brand"><LogoMark :size="30" /> FunAdmin</RouterLink><nav><RouterLink to="/web/questions">{{ t('community.labels.qACommunity') }}</RouterLink><RouterLink :to="loggedIn?'/web/profile':login()">{{ loggedIn?t('community.labels.myAccount'):t('community.labels.signInRegister') }}</RouterLink></nav></header>
  <section class="portal-shell" v-loading="loading">
   <div class="portal-intro"><div><span class="portal-eyebrow">{{ t('community.labels.eyebrow') }}</span><h1>{{ t('community.labels.qACommunity') }}</h1><p>{{ t('community.labels.shareQuestionsAndExperienceMakeEveryAnswerCount') }}</p></div><el-button v-if="canPost&&!questionId" type="primary" @click="askOpen=true">{{ t('community.labels.askAQuestion2') }}</el-button></div>
   <div class="portal-context"><el-select v-model="siteId" :aria-label="t('community.labels.communitySite')" :disabled="busy"><el-option v-for="site in sites" :key="site.id" :value="site.id" :label="site.name" /></el-select><el-select v-model="locale" :aria-label="t('community.labels.communityLanguage')" :disabled="busy"><el-option v-for="language in languages" :key="language.locale" :value="language.locale" :label="language.name||language.locale" /></el-select></div>
   <el-alert v-if="error" class="portal-message" :title="error" type="error" :closable="false">{{ error }}</el-alert><el-alert v-if="success" class="portal-message" :title="success" type="success" :closable="false">{{ success }}</el-alert>
   <el-alert v-if="!ready&&!loading&&!error" :title="t('community.labels.theMemberContentModuleNeedsAnUpgradeContactAn')" type="warning" :closable="false" />
   <el-empty v-if="!sites.length&&ready&&!loading&&!error" :description="ready?t('community.labels.noAccessibleSites'):t('community.labels.theMemberContentModuleNeedsAnUpgradeContactAn')" />
   <template v-if="current">
    <div class="portal-tabs"><button :class="{active:activeTab==='browse'}" @click="activeTab='browse'">{{ t('community.labels.publicQA') }}</button><button :class="{active:activeTab==='mine'}" @click="activeTab='mine'">{{ t('community.labels.myContent') }}</button></div>
    <div v-if="!loggedIn" class="login-prompt"><RouterLink :to="login()">{{ t('community.labels.signInAsAMember') }}</RouterLink>{{ t('community.labels.toAskAndAnswerPublicQuestions') }}</div>
    <el-alert v-if="!current.enabled" type="info" :title="t('community.labels.questionsAreClosedPublishedContentRemainsAvailable')" :closable="false" />
    <template v-if="activeTab==='mine'&&loggedIn">
     <article v-for="row in mine" :key="row.id" class="portal-card"><div class="card-meta"><span>{{ {question:t('community.labels.question'),answer:t('community.labels.answer')}[row.kind]||t('community.labels.myContent') }}</span><el-tag>{{ statusNames[row.status]||row.status }}</el-tag><span>{{ row.created_at }}</span></div><h2>{{ row.title||t('community.labels.myAnswer') }}</h2><p v-if="row.review_reason">{{ t('community.labels.review') }}{{ row.review_reason }}</p><RouterLink v-if="row.status==='published'&&['question','answer'].includes(row.kind)" :to="questionLink(row.kind==='question'?row.id:row.parent_id||'')" @click="activeTab='browse'">{{ t('community.labels.viewQuestion') }}</RouterLink><el-button v-if="['pending','rejected'].includes(row.status)" :disabled="busy" @click="withdraw(row)">{{ t('community.labels.withdraw') }}</el-button></article>
     <el-empty v-if="!mine.length&&!loading&&!error" :description="t('community.labels.noSubmissionsOrQARecordsYet')" />
    </template>
    <template v-else-if="activeTab==='browse'">
     <template v-if="questionId">
      <RouterLink :to="{path:'/web/questions',query:{site_id:siteId,locale}}">{{ t('community.labels.backToQuestions') }}</RouterLink>
      <article v-if="question" class="portal-card question-detail"><div class="card-meta"><span>{{ question.author_name }}</span><el-tag>{{ question.accepted_answer_id?t('community.labels.resolved'):question.closed?t('community.labels.closed'):t('community.labels.awaitingAnswers') }}</el-tag></div><h1>{{ question.title }}</h1><div class="published-body" v-html="question.body_html" /><div v-if="question.attachments?.length" class="attachment-list" data-testid="question-files"><span>{{ t('community.labels.attachments') }}</span><template v-for="file in question.attachments" :key="file.id"><button v-if="loggedIn" type="button" class="attachment-link" @click="download(file)">{{ file.name }} · {{ fileSize(file.size) }}</button><span v-else>{{ file.name }} · {{ fileSize(file.size) }}</span></template><small v-if="!loggedIn">{{ t('community.labels.signInToDownload') }}</small></div></article>
      <h2 v-if="question" class="answers-title">{{ t('community.labels.answerCount', { count: question.answers_total || 0 }, question.answers_total || 0) }}</h2>
      <article v-for="answer in question?.answers||[]" :key="answer.id" class="portal-card" :class="{accepted:question?.accepted_answer_id===answer.id}"><div class="card-meta"><span>{{ answer.author_name }}</span><el-tag v-if="question?.accepted_answer_id===answer.id" type="success">{{ t('community.labels.accepted') }}</el-tag></div><div class="published-body" v-html="answer.body_html" /><div v-if="answer.attachments?.length" class="attachment-list" data-testid="answer-files"><span>{{ t('community.labels.attachments') }}</span><template v-for="file in answer.attachments" :key="file.id"><button v-if="loggedIn" type="button" class="attachment-link" @click="download(file)">{{ file.name }} · {{ fileSize(file.size) }}</button><span v-else>{{ file.name }} · {{ fileSize(file.size) }}</span></template></div><el-button v-if="ownsQuestion&&!question?.closed" :disabled="busy" @click="accept(answer.id)">{{ t('community.labels.acceptAnswer') }}</el-button></article>
      <div v-if="question&&!question.closed&&loggedIn" class="portal-card"><h2>{{ t('community.labels.myAnswer') }}</h2><RichTextEditor v-model="body" :disabled="!canPost" /><div class="attachment-picker"><label>{{ t('community.labels.attachments') }} <input :key="fileInput" type="file" multiple :accept="ACCEPT" :disabled="!canPost" data-testid="answer-file-input" @change="pickFiles" /></label><p class="form-hint">{{ t('community.labels.attachmentHint') }}</p></div><el-button type="primary" :loading="busy" :disabled="!canSend('answer')" @click="send('answer')">{{ t('community.labels.submitAnswer') }}</el-button><p class="form-hint">{{ t('community.labels.answersArePublicAfterApproval') }}</p></div>
     </template>
     <template v-else><div class="question-search"><el-input v-model="query" :placeholder="t('community.labels.searchPublishedQuestions')" :aria-label="t('community.labels.searchQuestions')" @keyup.enter="page=1;load()" /><el-button @click="page=1;load()">{{ t('community.labels.search') }}</el-button></div><article v-for="row in rows" :key="row.id" class="portal-card"><div class="card-meta"><span>{{ row.author_name }}</span><el-tag>{{ row.accepted_answer_id?t('community.labels.resolved'):row.closed?t('community.labels.closed'):t('community.labels.awaitingAnswers') }}</el-tag></div><h2><RouterLink :to="questionLink(row.id)">{{ row.title }}</RouterLink></h2></article><el-empty v-if="!rows.length&&!loading&&!error" :description="t('community.labels.noPublishedQuestions')" /></template>
    </template>
    <el-pagination v-if="total>20&&(activeTab==='mine'||!questionId)" v-model:current-page="page" :page-size="20" :total="total" layout="prev,pager,next" />
   </template>
  </section>
  <el-dialog v-model="askOpen" :title="t('community.labels.askAQuestion')" width="min(760px,94vw)" :close-on-click-modal="false"><el-form label-position="top"><el-form-item :label="t('community.labels.questionTitle')"><el-input v-model="title" maxlength="255" :disabled="busy" /></el-form-item><el-form-item :label="t('community.labels.questionDescription')"><RichTextEditor v-model="body" :disabled="busy" /></el-form-item><el-form-item :label="t('community.labels.attachments')"><div class="attachment-picker"><input :key="fileInput" type="file" multiple :accept="ACCEPT" :disabled="busy" data-testid="question-file-input" @change="pickFiles" /><p class="form-hint">{{ t('community.labels.attachmentHint') }}</p></div></el-form-item></el-form><template #footer><el-button :disabled="busy" @click="askOpen=false">{{ t('community.labels.cancel') }}</el-button><el-button type="primary" :disabled="!canSend('question')" :loading="busy" @click="send('question')">{{ t('community.labels.submitQuestion') }}</el-button></template></el-dialog>
 </main>
</template>
<style scoped>
.community-page{min-height:100vh;background:#f5f7f7;color:#20362e}.portal-header{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:20px max(24px,calc((100vw - 1100px)/2));background:#fff;border-bottom:1px solid #e4eae6}.portal-brand{display:flex;gap:10px;align-items:center;font-size:20px;font-weight:700}.portal-header nav{display:flex;gap:24px;font-size:14px}.portal-shell{max-width:1040px;margin:auto;padding:42px 24px 80px}.portal-intro{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:28px}.portal-intro h1{font-size:32px;margin:8px 0 12px}.portal-intro p,.form-hint{color:#74857c;font-size:13px}.portal-eyebrow{font-size:11px;letter-spacing:.18em}.portal-context{display:flex;gap:12px;margin-bottom:20px}.portal-context .el-select{width:210px}.portal-tabs{display:flex;border-bottom:1px solid #d9e3dc;margin:24px 0}.portal-tabs button{border:0;background:none;padding:14px 24px;color:#74857c;cursor:pointer}.portal-tabs button.active{color:#1f5e43;border-bottom:2px solid currentColor}.portal-card{background:#fff;border:1px solid #e0e7e2;border-radius:10px;padding:26px;margin:18px 0;min-width:0}.portal-card h2{font-size:20px;line-height:1.5;margin:12px 0}.portal-card h1{font-size:27px;line-height:1.5}.card-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:12px;color:#7d8b83}.portal-card.accepted{border-color:#61a480;background:#f4fbf5}.published-body{line-height:1.9;overflow-wrap:anywhere;margin:18px 0}.published-body :deep(img){max-width:100%;height:auto}.portal-message{margin:12px 0}.login-prompt{padding:18px;background:#eaf3ed;font-size:14px;margin-bottom:18px}.login-prompt a{color:#1e6b47;text-decoration:underline}.question-search{display:flex;gap:12px;margin-bottom:24px}.question-search .el-input{max-width:420px}.answers-title{font-size:20px;margin-top:30px}.portal-card>.el-button{margin-top:18px}.portal-card .rich-editor{width:100%}.attachment-list{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;font-size:13px;color:#5f7068;margin-top:8px}.attachment-link{border:0;background:none;padding:0;color:#1e6b47;text-decoration:underline;cursor:pointer;font:inherit;overflow-wrap:anywhere}.attachment-picker{margin:12px 0;font-size:13px}.attachment-picker input{max-width:100%}@media(max-width:640px){.portal-header{padding:16px;align-items:flex-start;flex-direction:column}.portal-header nav{gap:18px}.portal-shell{padding:28px 16px}.portal-intro{align-items:flex-start}.portal-intro h1{font-size:27px}.portal-context .el-select{width:50%}.portal-card{padding:20px}.card-meta{gap:10px}}
</style>
