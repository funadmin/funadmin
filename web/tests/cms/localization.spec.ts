import { defineComponent, h, nextTick, ref } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest';
import { i18n } from '@/locales';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CategoryPanel from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/CategoryPanel.vue';
import ContributionsPanel from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/ContributionsPanel.vue';
import Moderation from '../../../admin/plugins/qa/web/src/admin/modules/qa/pages/Moderation.vue';
import RichTextEditor from '../../src/shared/components/RichTextEditor/index.vue';
import MemberLogin from '../../src/web/views/MemberLogin.vue';
import MemberRegister from '../../src/web/views/MemberRegister.vue';
import MemberProfile from '../../src/web/views/MemberProfile.vue';
import QuestionsPortal from '../../src/web/views/QuestionsPortal.vue';
import { questionsError } from '@/web/api/questions';
import { validateContentForm } from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/contentForm';
import { scalarConfigurationTypes, configurationPresets } from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/configurationModel';
import zhCms from '../../src/shared/locales/zh-CN/cms';
import enCms from '../../src/shared/locales/en-US/cms';
import zhCommunity from '../../src/shared/locales/zh-CN/community';
import enCommunity from '../../src/shared/locales/en-US/community';

const api=vi.hoisted(()=>({categories:vi.fn(async()=>[]),context:vi.fn(async()=>({items:[],is_super:false}))}));
const community=vi.hoisted(()=>({context:vi.fn(),identity:vi.fn(async()=>({member_id:'9007199254740993'})),questions:vi.fn(),question:vi.fn(),mine:vi.fn()}));
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));
vi.mock('../../../admin/plugins/cms/web/src/admin/modules/cms/api',()=>({cmsApi:api}));
vi.mock('@/modules/cms/api',()=>({cmsApi:api}));
vi.mock('@/utils/http',()=>({default:http}));
vi.mock('@/web/api/questions',async importOriginal=>({...await importOriginal<typeof import('@/web/api/questions')>(),questionsApi:community}));
vi.mock('@/web/api/memberAuth',()=>({memberAuthApi:{profile:vi.fn(async()=>({username:'literal-account',nickname:'原样昵称'}))}}));
vi.mock('@/web/session/memberSession',()=>({memberSession:{authenticated:()=>true,accessToken:()=>'',account:()=>null,saveAccount:vi.fn(),clear:vi.fn()}}));
const pass=defineComponent({setup(_, {slots}){return()=>h('div',[slots.header?.(),slots.extra?.(),slots.default?.(),slots.footer?.()]);}});
const empty=defineComponent({render:()=>h('span')});
const button=defineComponent({emits:['click'],setup(_, {slots,emit}){return()=>h('button',{onClick:()=>emit('click')},slots.default?.());}});
const stubs:Record<string,any>={...Object.fromEntries(['ElAlert','ElTable','ElDialog','ElForm','ElFormItem','ElInput','ElSelect','ElOption','ElInputNumber','ElSwitch','ElTag','ElTabs','ElTabPane','ElEmpty'].map(n=>[n,pass])),ElButton:button,ElTableColumn:empty,LogoMark:true,PageWrapper:pass};
const wrappers:Array<{unmount:()=>void}>=[];
function remember<T extends {unmount:()=>void}>(wrapper:T):T{wrappers.push(wrapper);return wrapper;}
function flatten(value: Record<string,any>, prefix=''):Record<string,string>{return Object.fromEntries(Object.entries(value).flatMap(([key,entry])=>typeof entry==='string'?[[prefix+key,entry]]:Object.entries(flatten(entry,prefix+key+'.'))));}
async function routerAt(path='/web/login'){
 const router=createRouter({history:createMemoryHistory(),routes:['/','/web/login','/web/register','/web/profile','/web/questions/:id?'].map(path=>({path,component:{}}))});await router.push(path);await router.isReady();return router;
}
beforeEach(()=>{
 i18n.global.locale.value='en-US';
 api.context.mockResolvedValue({items:[],is_super:false});
 http.get.mockResolvedValue({items:[{id:'1',kind:'answer',status:'pending',title:'原样内容',member_id:'9007199254740993',created_at:'2026-11-06'}],total:1});
 community.context.mockResolvedValue({ready:true,sites:[{id:'7',name:'原样站点',default_locale:'zh-cn',enabled:true,locales:[{locale:'zh-cn',name:'中文'}],models:[]}]});
 community.questions.mockResolvedValue({items:[],total:0});
 community.mine.mockResolvedValue({items:[{id:'1',kind:'answer',status:'pending',title:'原样标题',created_at:'2026-11-06'}],total:1});
 community.question.mockResolvedValue({id:'51',title:'原样问题',body_html:'<p>原样正文</p>',member_id:'other',question_version:4,closed:false,answers:[],answers_total:1});
});
afterEach(()=>{wrappers.splice(0).forEach(w=>w.unmount());i18n.global.locale.value='zh-CN';vi.restoreAllMocks();});

describe('CMS and community bundled localization',()=>{
 it('provides equal keys and placeholder sets with translated English',()=>{
  for(const [zh,en] of [[zhCms,enCms],[zhCommunity,enCommunity]]){
   const a=flatten(zh),b=flatten(en);expect(Object.keys(b).sort()).toEqual(Object.keys(a).sort());expect(Object.keys(a).length).toBeGreaterThan(20);
   for(const key of Object.keys(a)){
    expect([...new Set(b[key].match(/\{\w+\}/g)||[])].sort(),key).toEqual([...new Set(a[key].match(/\{\w+\}/g)||[])].sort());
    expect(b[key],key).not.toMatch(/[\u4e00-\u9fff]/);
   }
  }
 });
 it('renders English CMS labels and switches the mounted view to Chinese',async()=>{
  const w=remember(mount(CategoryPanel,{global:{stubs,directives:{loading:()=>{}}},props:{scope:{site_id:'1',locale:'zh-cn'},can:()=>true}}));
  await flushPromises();expect(w.text()).toContain('New category');expect(w.text()).not.toContain('新建栏目');
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.text()).toContain('新建栏目');
 });
 it('reacts to locale changes for review status and kind labels without translating submission data',async()=>{
  const w=remember(mount(ContributionsPanel,{global:{stubs,directives:{loading:()=>{}}},props:{scope:{site_id:'1',locale:'zh-cn'},can:()=>true}}));await flushPromises();
  expect(w.text()).toContain('Pending review');expect(w.text()).toContain('Answer');expect(w.text()).toContain('原样内容');
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.text()).toContain('待审核');expect(w.text()).toContain('回答');expect(w.text()).toContain('原样内容');
 });
 it('renders QA management headings in both locales',async()=>{
  const w=remember(mount(Moderation,{global:{stubs:{...stubs,ContributionsPanel:true},directives:{loading:()=>{}}}}));await flushPromises();expect(w.text()).toContain('Q&A moderation');expect(w.text()).toContain('Refresh sites');
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.text()).toContain('问答审核');expect(w.text()).toContain('刷新站点');
 });
 it.each([[MemberLogin,'Sign in to your account','登录前台账号'],[MemberRegister,'Create an account','创建前台账号'],[MemberProfile,'My account','个人中心']])('renders and switches member views without changing account data',async(component,english,chinese)=>{
  const router=await routerAt();const w=remember(mount(component,{global:{plugins:[router],stubs}}));await flushPromises();expect(w.text()).toContain(english);
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.text()).toContain(chinese);
 });
 it('renders community statuses and plural answer counts through the actual Composer',async()=>{
  const router=await routerAt('/web/questions/51?site_id=7&locale=zh-cn');const w=remember(mount(QuestionsPortal,{global:{plugins:[router],stubs:{...stubs,RichTextEditor:true},directives:{loading:()=>{}}}}));await flushPromises();
  expect(w.text()).toContain('1 answer');expect(w.text()).toContain('Awaiting answers');expect(w.text()).toContain('原样问题');
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.text()).toContain('1 个回答');expect(w.text()).toContain('待解答');
  i18n.global.locale.value='en-US';await nextTick();
  const mine=w.findAll('button').find(b=>b.text()==='My content')!;await mine.trigger('click');await flushPromises();expect(w.text()).toContain('Pending review');
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.text()).toContain('待审核');expect(w.text()).toContain('原样标题');
 });
 it('pluralizes zero, singular and plural counts in English and Chinese',()=>{
  const t=i18n.global.t;expect(t('community.labels.answerCount',{count:0},0)).toBe('No answers');expect(t('community.labels.answerCount',{count:1},1)).toBe('1 answer');expect(t('community.labels.answerCount',{count:2},2)).toBe('2 answers');expect(t('cms.labels.operationCount',{count:1},1)).toBe('1 operation');expect(t('cms.labels.operationCount',{count:2},2)).toBe('2 operations');
  i18n.global.locale.value='zh-CN';expect(t('community.labels.answerCount',{count:0},0)).toBe('0 个回答');expect(t('community.labels.answerCount',{count:2},2)).toBe('2 个回答');
 });
 it('updates rich-text toolbar and Tiptap accessibility labels on locale changes',async()=>{
  const w=remember(mount(RichTextEditor,{props:{modelValue:'<p>原样正文</p>'},global:{stubs}}));await flushPromises();expect(w.find('button[aria-label="Bold"]').exists()).toBe(true);expect(w.find('[contenteditable=true]').attributes('aria-label')).toBe('Body editor');
  i18n.global.locale.value='zh-CN';await nextTick();expect(w.find('button[aria-label="粗体"]').exists()).toBe(true);expect(w.find('[contenteditable=true]').attributes('aria-label')).toBe('正文编辑器');expect(w.text()).toContain('原样正文');
 });
 it('uses the active locale for validation, module options, unsaved prompts and fallback errors',async()=>{
  expect(validateContentForm({title:'',desired_path:'/ok'},[]).title).toBe('Enter a title');expect(scalarConfigurationTypes[0]!.label).toBe('Single-line text');expect(configurationPresets[0]!.label).toBe('Partners');expect(questionsError({})).toBe('Operation failed. Please try again later.');expect(questionsError({msg:'原样服务器错误'})).toBe('原样服务器错误');
  const confirm=vi.spyOn(ElMessageBox,'confirm').mockResolvedValue('confirm');let discard!:()=>Promise<boolean>;
  const w=remember(mount(defineComponent({setup(){discard=useUnsavedChanges(ref(true)).confirmDiscard;return()=>h('div');}})));await discard();expect(confirm).toHaveBeenLastCalledWith('You have unsaved changes. Continuing will discard them.','Unsaved changes',expect.objectContaining({confirmButtonText:'Discard changes',cancelButtonText:'Continue editing'}));
  i18n.global.locale.value='zh-CN';await nextTick();expect(scalarConfigurationTypes[0]!.label).toBe('单行文本');expect(configurationPresets[0]!.label).toBe('合作伙伴');expect(validateContentForm({title:'',desired_path:'/ok'},[]).title).toBe('请填写标题');await discard();expect(confirm).toHaveBeenLastCalledWith('当前内容尚未保存，继续将丢弃这些修改。','未保存修改',expect.objectContaining({confirmButtonText:'放弃修改'}));expect(w.exists()).toBe(true);
 });
});
