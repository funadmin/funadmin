import { defineComponent, h } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory, RouterView } from 'vue-router';
import { beforeEach, expect, it, vi } from 'vitest';
import QuestionsPortal from '../../src/web/views/QuestionsPortal.vue';
const api=vi.hoisted(()=>({context:vi.fn(),identity:vi.fn(),mine:vi.fn(),withdraw:vi.fn(),questions:vi.fn(),question:vi.fn(),ask:vi.fn(),answer:vi.fn(),accept:vi.fn()}));
vi.mock('@/web/api/questions',()=>({questionsApi:api,questionsError:(error:any)=>error?.msg||'操作失败'}));
vi.mock('@/web/session/memberSession',()=>({memberSession:{authenticated:()=>true}}));
const pass=defineComponent({setup(_, {slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const button=defineComponent({props:['disabled'],emits:['click'],setup(p,{slots,emit}){return()=>h('button',{disabled:p.disabled,onClick:()=>emit('click')},slots.default?.());}});
const input=defineComponent({props:['modelValue'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{value:p.modelValue,onInput:(e:any)=>emit('update:modelValue',e.target.value)});}});
const stubs={...Object.fromEntries(['ElAlert','ElSelect','ElOption','ElForm','ElFormItem','ElTabPane','ElTabs','ElTag','ElEmpty','ElPagination','ElSwitch'].map(n=>[n,pass])),ElButton:button,ElInput:input,ElInputNumber:input,ElDialog:pass,RichTextEditor:input,LogoMark:true};
async function render(path='/web/questions?site_id=7&locale=zh-cn'){
 const router=createRouter({history:createMemoryHistory(),routes:[{path:'/',component:{}},{path:'/web/profile',component:{}},{path:'/web/questions/:id?',component:{}},{path:'/web/login',component:{}}]});
 await router.push(path);await router.isReady();const wrapper=mount(QuestionsPortal,{global:{plugins:[router],stubs,directives:{loading:()=>{}}}});await flushPromises();return wrapper;
}
beforeEach(()=>{
 vi.clearAllMocks();api.context.mockResolvedValue({ready:true,sites:[{id:'7',name:'Site',default_locale:'zh-cn',enabled:true,locales:[{locale:'zh-cn',name:'中文'}],models:[]}]});api.identity.mockResolvedValue({member_id:'9007199254740993'});api.questions.mockResolvedValue({items:[],total:0,page:1,limit:20});api.mine.mockResolvedValue({items:[],total:0,page:1,limit:20});api.ask.mockResolvedValue({id:'51',status:'pending',lock_version:0});
});
it('submits questions with scoped request keys and explicit pending feedback',async()=>{
 const w=await render();const state=(w.vm as any).$.setupState;state.askOpen=true;state.title='如何使用模板标签？';state.body='<p>请说明用法</p>';await flushPromises();
 await w.findAll('button').find(b=>b.text()==='提交问题')!.trigger('click');await flushPromises();
 expect(api.ask).toHaveBeenCalledWith(expect.objectContaining({site_id:'7',locale:'zh-cn',title:'如何使用模板标签？',body_html:'<p>请说明用法</p>',request_key:expect.any(String)}));expect(w.text()).toContain('审核通过后公开展示');w.unmount();
});
it('uses exact string identity and latest question version for author acceptance',async()=>{
 api.question.mockResolvedValue({id:'51',title:'Question',body_html:'<p>Body</p>',member_id:'9007199254740993',question_version:4,closed:false,answers:[{id:'71',body_html:'<p>Answer</p>',author_name:'Helper'}],answers_total:1});api.accept.mockResolvedValue({});
 const w=await render('/web/questions/51?site_id=7&locale=zh-cn');await w.findAll('button').find(b=>b.text()==='采纳回答')!.trigger('click');await flushPromises();
 expect(api.accept).toHaveBeenCalledWith('51',{site_id:'7',locale:'zh-cn',answer_id:'71',question_version:4});w.unmount();
});
it('renders a concrete unavailable error instead of an empty successful question list',async()=>{
 api.questions.mockRejectedValue({code:404,msg:'问答插件尚未启用'});const w=await render();expect(w.text()).toContain('问答插件尚未启用');w.unmount();
});
it('can submit on a plain HTTP backend IP where crypto.randomUUID is unavailable',async()=>{
 const descriptor=Object.getOwnPropertyDescriptor(globalThis.crypto,'randomUUID');Object.defineProperty(globalThis.crypto,'randomUUID',{value:undefined,configurable:true});
 try{const w=await render();const state=(w.vm as any).$.setupState;state.askOpen=true;state.title='HTTP IP 提问';state.body='<p>问题详情</p>';await flushPromises();await w.findAll('button').find(b=>b.text()==='提交问题')!.trigger('click');await flushPromises();expect(w.text()).toContain('审核通过后公开展示');expect(api.ask).toHaveBeenCalled();w.unmount();}
 finally{if(descriptor)Object.defineProperty(globalThis.crypto,'randomUUID',descriptor);else delete (globalThis.crypto as any).randomUUID;}
});
it('rejects empty question or answer bodies even if a disabled control receives a click',async()=>{
 const questions=await render();await questions.findAll('button').find(b=>b.text()==='我要提问')!.trigger('click');await questions.find('[label="问题标题"] input').setValue('只有标题的问题');
 const ask=questions.findAll('button').find(b=>b.text()==='提交问题')!;expect((ask.element as HTMLButtonElement).disabled).toBe(true);(ask.element as HTMLButtonElement).disabled=false;await ask.trigger('click');await flushPromises();expect(api.ask).not.toHaveBeenCalled();questions.unmount();
 api.question.mockResolvedValue({id:'51',title:'Question',body_html:'<p>Body</p>',member_id:'owner',question_version:0,closed:false,answers:[],answers_total:0});const detail=await render('/web/questions/51?site_id=7&locale=zh-cn');
 const answer=detail.findAll('button').find(b=>b.text()==='提交回答')!;expect((answer.element as HTMLButtonElement).disabled).toBe(true);(answer.element as HTMLButtonElement).disabled=false;await answer.trigger('click');await flushPromises();expect(api.answer).not.toHaveBeenCalled();detail.unmount();
});
it('opens owned Q&A in browse mode while the real route reuses the question component and preserves string scope',async()=>{
 const site='9007199254740995',id='9007199254740997';api.context.mockResolvedValue({ready:true,sites:[{id:site,name:'Exact site',default_locale:'en-us',enabled:true,locales:[{locale:'en-us',name:'English'}],models:[]}]});
 api.mine.mockResolvedValue({items:[{id:'9007199254740999',parent_id:id,kind:'answer',title:'Owned answer',status:'published',lock_version:1,result:{}}],total:1,page:1,limit:20});api.question.mockResolvedValue({id,title:'Scoped detail',body_html:'<p>Question detail</p>',member_id:'9007199254740993',question_version:7,closed:false,answers:[],answers_total:0});
 const router=createRouter({history:createMemoryHistory(),routes:[{path:'/',component:{}},{path:'/web/profile',component:{}},{path:'/web/questions/:id?',component:QuestionsPortal},{path:'/web/login',component:{}}]});await router.push(`/web/questions?site_id=${site}&locale=en-us`);await router.isReady();
 const w=mount(defineComponent({setup:()=>()=>h(RouterView)}),{global:{plugins:[router],stubs,directives:{loading:()=>{}}}});await flushPromises();const uid=w.findComponent(QuestionsPortal).vm.$.uid;
 await w.findAll('button').find(b=>b.text()==='我的内容')!.trigger('click');await flushPromises();await w.findAll('a').find(a=>a.text()==='查看问答')!.trigger('click');await flushPromises();
 expect(router.currentRoute.value.params.id).toBe(id);expect(router.currentRoute.value.query).toEqual({site_id:site,locale:'en-us'});expect(w.findComponent(QuestionsPortal).vm.$.uid).toBe(uid);expect(api.question).toHaveBeenCalledWith(id,{site_id:site,locale:'en-us'});expect(w.find('.question-detail h1').text()).toBe('Scoped detail');expect(w.find('.portal-tabs button.active').text()).toBe('公开问答');
 await w.findAll('button').find(b=>b.text()==='我的内容')!.trigger('click');await flushPromises();expect(w.text()).toContain('Owned answer');expect(w.find('.question-detail').exists()).toBe(false);w.unmount();
});
