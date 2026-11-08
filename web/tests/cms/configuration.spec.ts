import { computed, defineComponent, h, inject, provide } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import ConfigurationPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ConfigurationPanel.vue';
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));vi.mock('@/utils/http',()=>({default:http}));
const pass=defineComponent({setup(_, {slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const button=defineComponent({emits:['click'],setup(_, {slots,emit}){return()=>h('button',{onClick:()=>emit('click')},slots.default?.());}});
const input=defineComponent({props:{modelValue:null},emits:['update:modelValue'],setup(props,{emit}){return()=>h('input',{value:props.modelValue as string,onInput:(e:any)=>emit('update:modelValue',e.target.value)});}});
const stubs:any=Object.fromEntries(['ElAlert','ElForm','ElFormItem','ElSelect','ElOption','ElSwitch','ElCheckbox','ElTag','ElEmpty','ElPopconfirm','ElInputNumber','ElTable','ElTableColumn'].map(n=>[n,pass]));Object.assign(stubs,{ElButton:button,ElInput:input,ElDialog:true});
const fields=[{key:'headline',label:'首页标题',type:'text',group:'首页',localized:true,default:''},{key:'enabled',label:'显示开关',type:'boolean',group:'首页',localized:true,default:true},{key:'count',label:'数量',type:'number',group:'首页',localized:true,default:3}];
const response={fields,values:{headline:'Site title',enabled:true,count:3},overrides:{headline:'',enabled:false,count:0},effective:{headline:'',enabled:false,count:0},locale:''};
beforeEach(()=>{vi.clearAllMocks();http.get.mockResolvedValue(response);http.post.mockResolvedValue(response);});
it('saves site values and schema using the target site and scoped permission',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:(c:string)=>['cms:site:detail','cms:site:edit'].includes(c)},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 expect(http.get).toHaveBeenCalledWith('/plugin/cms/configuration',{site_id:'7'});
 await w.findAll('button').find(b=>b.text()==='保存站点配置')!.trigger('click');await flushPromises();expect(http.post).toHaveBeenCalledWith('/plugin/cms/configuration/save',expect.objectContaining({site_id:'7',values:{headline:'Site title',enabled:true,count:3}}));w.unmount();
});
it('preserves false zero and empty localized overrides in the submitted payload',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[{locale:'en-us',name:'English',status:1}] as any,can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 const state=(w.vm as any).$.setupState;state.selectedLocale='en-us';await flushPromises();await w.findAll('button').find(b=>b.text()==='保存语言配置')!.trigger('click');await flushPromises();expect(http.post).toHaveBeenCalledWith('/plugin/cms/configuration/save',{site_id:'7',locale:'en-us',values:{headline:'',enabled:false,count:0}});w.unmount();
});
it('rejects saving when a displayed form loses its site operation right',async()=>{
 let allow=true;const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:(c:string)=>c==='cms:site:detail'||allow},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();allow=false;await (w.vm as any).$.setupState.save();expect(http.post).not.toHaveBeenCalled();w.unmount();
});
it('ignores a late response after switching to a different site',async()=>{
 let finish!:(v:any)=>void;http.get.mockImplementation((_u:string,p:any)=>p.site_id==='7'?new Promise(r=>{finish=r}):Promise.resolve({...response,values:{headline:'Second site'}}));
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();await w.setProps({siteId:'8'});await flushPromises();finish(response);await flushPromises();expect((w.vm as any).$.setupState.values.headline).toBe('Second site');w.unmount();
});
it('switching a language field back to inherit immediately shows its site value',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();const state=(w.vm as any).$.setupState;state.selectedLocale='en-us';await flushPromises();state.setOverride(fields[2],false);expect(state.value(fields[2])).toBe(3);w.unmount();
});
it('creates a custom typed field without dropping existing values',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();const state=(w.vm as any).$.setupState;state.openField();state.fieldForm={key:'wechat',label:'客服微信',type:'text',group:'联系信息',localized:false,default:'',options:[]};await state.saveField();expect(http.post).toHaveBeenCalledWith('/plugin/cms/configuration/save',expect.objectContaining({site_id:'7',fields:expect.arrayContaining([expect.objectContaining({key:'wechat',label:'客服微信'})]),values:{headline:'Site title',enabled:true,count:3}}));w.unmount();
});
it('metadata editing does not send an old configuration snapshot back to the server',async()=>{
 const {default:SitePanel}=await import('../../../admin/plugins/cms/web/src/admin/plugins/cms/components/SitePanel.vue');
 const settings:any={site:{id:'7',code:'main',name:'Main',default_locale:'zh-cn',theme:'default',status:1,settings:{configuration:{values:{headline:'Old'}}}},locales:[{locale:'zh-cn',settings:{configuration:{values:{headline:'Old locale'}}}}],domains:[],models:[]};
 const w=mount(SitePanel,{props:{settings,can:()=>true,isSuper:true},global:{stubs:{...stubs,ConfigurationPanel:true,ElTableColumn:true,ElDescriptions:true,ElDescriptionsItem:true},directives:{loading:()=>{}}}});await flushPromises();const state=(w.vm as any).$.setupState;state.dialog='site';state.form={...settings.site,name:'New name'};await state.save();expect(http.post).toHaveBeenCalledWith('/plugin/cms/sites/save',expect.not.objectContaining({settings:expect.anything()}));
 state.dialog='locale';state.form={...settings.locales[0],name:'New locale'};await state.save();expect(http.post).toHaveBeenCalledWith('/plugin/cms/locales/save',expect.not.objectContaining({settings:expect.anything()}));w.unmount();
});
