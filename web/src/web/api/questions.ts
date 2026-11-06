import { cmsTranslate } from '@/composables/useCmsI18n';
import { i18n } from '@/locales';
import axios from 'axios';
import { memberSession } from '@/web/session/memberSession';
import { backendOrigin } from './backendOrigin';

export interface QuestionSite { id:string; name:string; default_locale:string; enabled:boolean; locales:Array<{locale:string;name:string}> }
export interface QuestionScope { site_id:string; locale:string }
export interface Submission { id:string; member_id:string; parent_id?:string|null; kind:string; title:string; body_html:string; status:string; lock_version:number; review_reason?:string; created_at:string; result?:Record<string,unknown> }
export interface QuestionPage<T> { items:T[]; total:number; page:number; limit:number }
export interface Answer { id:string; body_html:string; member_id:string; author_name:string; created_at:string }
export interface Question extends Answer { title:string; question_version:number; accepted_answer_id:string|null; closed:boolean; answers?:Answer[]; answers_total?:number }

const client=axios.create({baseURL:`${backendOrigin}/`,timeout:30000,withCredentials:false});
client.interceptors.request.use(config=>{config.headers['X-Locale']=i18n.global.locale.value;const token=memberSession.accessToken();if(token)config.headers.Authorization=`Bearer ${token}`;return config;});
client.interceptors.response.use(response=>response,error=>{if(error.response?.status===401)memberSession.clear();return Promise.reject(error.response?.data||error);});
async function request<T>(method:'GET'|'POST',url:string,values:Record<string,unknown>={}):Promise<T>{
 const response=await client.request<{code:number;msg:string;data:T}>({method,url,...(method==='GET'?{params:values}:{data:values})});
 if(response.data.code!==200)throw response.data;return response.data.data;
}
export function questionsError(error:unknown):string {const e=error as {msg?:string;message?:string};return e?.msg||e?.message||cmsTranslate('community.labels.operationFailedPleaseTryAgainLater');}
// QA reuses CMS member identity, site context and submission history; no CMS content creation API.
export const questionsApi={
 context:()=>request<{ready:boolean;sites:QuestionSite[]}>('GET','/cms/member/context'),
 identity:()=>request<{member_id:string}>('GET','/cms/member/identity'),
 mine:(scope:QuestionScope,page=1)=>request<QuestionPage<Submission>>('GET','/cms/member/submissions',{...scope,page}),
 withdraw:(id:string,payload:Record<string,unknown>)=>request<Submission>('POST',`/cms/member/submissions/${encodeURIComponent(id)}/withdraw`,payload),
 questions:(scope:QuestionScope,q='',page=1)=>request<QuestionPage<Question>>('GET','/qa/api/questions',{...scope,q,page}),
 question:(id:string,scope:QuestionScope)=>request<Question>('GET',`/qa/api/questions/${encodeURIComponent(id)}`,{...scope}),
 ask:(payload:Record<string,unknown>)=>request<Submission>('POST','/qa/api/questions',payload),
 answer:(id:string,payload:Record<string,unknown>)=>request<Submission>('POST',`/qa/api/questions/${encodeURIComponent(id)}/answers`,payload),
 accept:(id:string,payload:Record<string,unknown>)=>request<Question>('POST',`/qa/api/questions/${encodeURIComponent(id)}/accept`,payload),
};
