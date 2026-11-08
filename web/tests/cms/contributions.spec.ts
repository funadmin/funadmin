import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import SettingsPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ContributionSettingsPanel.vue';
import ReviewPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ContributionsPanel.vue';
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));
vi.mock('@/utils/http',()=>({default:http}));
const pass=defineComponent({setup(_, {slots}){return()=>h('div',slots.default?.());}});
const button=defineComponent({props:['disabled'],emits:['click'],setup(p,{slots,emit}){return()=>h('button',{disabled:p.disabled,onClick:()=>emit('click')},slots.default?.());}});
const input=defineComponent({props:['modelValue'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{value:p.modelValue,onInput:(e:any)=>emit('update:modelValue',e.target.value)});}});
const stubs={...Object.fromEntries(['ElAlert','ElForm','ElFormItem','ElSelect','ElOption','ElSwitch','ElTag','ElPagination','ElEmpty','ElCheckbox','ElCheckboxGroup'].map(n=>[n,pass])),ElButton:button,ElInput:input,ElDialog:defineComponent({setup(_,{slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}}),ElFormItem:defineComponent({props:['label'],setup(p,{slots}){return()=>h('div',[p.label,slots.default?.()]);}})};
const pending={id:'9007199254740993',kind:'content',title:'投稿标题',body_html:'<p>正文</p>',member_id:'27',status:'pending',lock_version:3,data:{model_code:'article',fields:{intro:'简介'}}};
beforeEach(()=>{vi.clearAllMocks();http.get.mockImplementation(async(url:string)=>url.endsWith('/settings')?{enabled:false,model_codes:['article'],models:[{code:'article',name:'文章'}]}:{items:[pending],total:1,page:1,limit:20});http.post.mockResolvedValue({});});
it('saves site participation with the current typed model allowlist',async()=>{
 const w=mount(SettingsPanel,{props:{siteId:'7',can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 expect(w.text()).toContain('会员投稿与问答');
 const state=(w.vm as any).$.setupState;state.enabled=true;
 await w.findAll('button').find(b=>b.text()==='保存会员互动设置')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/cms/contributions/settings/save',{site_id:'7',enabled:true,model_codes:['article']});w.unmount();
});
it('reviews the exact submission version and requires a rejection reason',async()=>{
 const w=mount(ReviewPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 await w.findAll('button').find(b=>b.text()==='审核')!.trigger('click');await flushPromises();
 await w.findAll('button').find(b=>b.text()==='退回')!.trigger('click');await flushPromises();expect(http.post).not.toHaveBeenCalled();
 await w.get('input[aria-label="审核意见"]').setValue('请补充来源');
 await w.findAll('button').find(b=>b.text()==='退回')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/cms/contributions/review',{site_id:'7',locale:'zh-cn',id:pending.id,lock_version:3,decision:'reject',reason:'请补充来源'});w.unmount();
});
it('requires content publishing rights and scopes QA reviews to their own endpoint',async()=>{
 const w=mount(ReviewPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:(code:string)=>code==='cms:submission:review'},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 await w.findAll('button').find(b=>b.text()==='审核')!.trigger('click');await flushPromises();
 expect(w.findAll('button').some(b=>b.text()==='通过并发布')).toBe(false);w.unmount();
 const qa=mount(ReviewPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,endpoint:'/plugins/qa/moderation',qa:true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 await qa.findAll('button').find(b=>b.text()==='审核')!.trigger('click');await flushPromises();await qa.findAll('button').find(b=>b.text()==='通过并发布')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/qa/moderation/review',expect.objectContaining({id:pending.id,lock_version:3,decision:'publish'}));qa.unmount();
});
