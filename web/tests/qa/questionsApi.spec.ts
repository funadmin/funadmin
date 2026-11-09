import axios, { type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
const session=vi.hoisted(()=>({token:'opaque-member',clear:vi.fn()}));
vi.mock('@/web/session/memberSession',()=>({memberSession:{accessToken:()=>session.token,clear:session.clear}}));
let calls:InternalAxiosRequestConfig[]=[];
const original=axios.defaults.adapter;
beforeEach(()=>{vi.resetModules();calls=[];vi.clearAllMocks();axios.defaults.adapter=async config=>{calls.push(config);return {config,data:{code:200,data:{member_id:'9007199254740993'}},status:200,statusText:'OK',headers:{}};};});
afterEach(()=>{axios.defaults.adapter=original;vi.unstubAllEnvs();});
it('keeps member bearer identity separate from administrator cookies and tokens',async()=>{
 sessionStorage.setItem('ADMIN_ACCESS_TOKEN','admin-token');const {questionsApi}=await import('../../src/web/api/questions');const identity=await questionsApi.identity();
 expect(identity.member_id).toBe('9007199254740993');expect(calls[0]!.headers.Authorization).toBe('Bearer opaque-member');expect(calls[0]!.withCredentials).toBe(false);expect(calls[0]!.url).toBe('/cms/member/identity');
});
it('sends the interface language independently from the requested content language',async()=>{
 const {i18n}=await import('../../src/shared/locales');const locale=i18n.global.locale.value;i18n.global.locale.value='en-US';
 try{
  const {questionsApi}=await import('../../src/web/api/questions');await questionsApi.questions({site_id:'7',locale:'zh-cn'});
  const {memberAuthApi}=await import('../../src/web/api/memberAuth');await memberAuthApi.login({account:'member',password:'example'});
  expect(calls[0]!.headers['X-Locale']).toBe('en-US');expect(calls[0]!.params.locale).toBe('zh-cn');expect(calls[1]!.headers['X-Locale']).toBe('en-US');
 }finally{i18n.global.locale.value=locale;}
});
it('uses the configured backend origin for independently hosted community and member login pages',async()=>{
 vi.stubEnv('VITE_APP_BASE_API','https://backend.example.test/admin');
 const {questionsApi}=await import('../../src/web/api/questions');await questionsApi.questions({site_id:'7',locale:'en-us'});
 const {memberAuthApi}=await import('../../src/web/api/memberAuth');await memberAuthApi.login({account:'member',password:'example'});
 expect(axios.getUri(calls[0])).toBe('https://backend.example.test/qa/api/questions?site_id=7&locale=en-us&q=&page=1');
 expect(axios.getUri(calls[1])).toBe('https://backend.example.test/api/v2/token');expect(calls[1]!.data).toBe(JSON.stringify({username:'member',password:'example'}));
});
it('clears revoked membership on a real HTTP 401 response',async()=>{
 axios.defaults.adapter=async config=>Promise.reject({response:{status:401,data:{code:401,msg:'令牌已失效'}},config});
 const {questionsApi}=await import('../../src/web/api/questions');await expect(questionsApi.identity()).rejects.toMatchObject({code:401,msg:'令牌已失效'});expect(session.clear).toHaveBeenCalledOnce();
});
