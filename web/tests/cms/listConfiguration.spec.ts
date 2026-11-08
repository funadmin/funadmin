import { defineComponent, h } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import ConfigurationPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ConfigurationPanel.vue';
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));
vi.mock('@/utils/http',()=>({default:http}));
vi.mock('@/components/Upload/index.vue',()=>({default:{name:'AppUpload',template:'<div />'}}));
const pass=defineComponent({setup(_, {slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const button=defineComponent({props:{disabled:Boolean},emits:['click'],setup(props,{slots,emit}){return()=>h('button',{disabled:props.disabled,onClick:()=>emit('click')},slots.default?.());}});
const input=defineComponent({props:['modelValue','disabled'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{value:p.modelValue,disabled:p.disabled,onInput:(e:any)=>emit('update:modelValue',e.target.value)});}});
const toggle=defineComponent({props:['modelValue','disabled'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{type:'checkbox',checked:p.modelValue,disabled:p.disabled,onChange:(e:any)=>emit('update:modelValue',e.target.checked)});}});
const stubs={...Object.fromEntries(['ElAlert','ElForm','ElFormItem','ElSelect','ElOption','ElTabs','ElTabPane','ElTag','ElEmpty','ElPopconfirm'].map(n=>[n,pass])),ElInput:input,ElButton:button,ElSwitch:toggle,ElDialog:true,ElInputNumber:input};
const field={key:'partners',label:'合作伙伴',group:'合作伙伴',type:'list',localized:true,default:[],fields:[{key:'name',label:'名称',type:'text',group:'条目字段',localized:false,default:''},{key:'url',label:'链接',type:'url',group:'条目字段',localized:false,default:''}]};
const response={fields:[field],values:{partners:[{name:'A',url:'/a',enabled:true},{name:'B',url:'/b',enabled:true}]},overrides:{},effective:{},locale:''};
beforeEach(()=>{vi.clearAllMocks();http.get.mockResolvedValue(response);http.post.mockResolvedValue(response);});

it('edits ordered rows, toggles visibility and submits the complete list to the owning site',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 expect(w.findAll('.configuration-row')).toHaveLength(2);
 await w.findAll('.configuration-row')[0].get('input:not([type="checkbox"])').setValue('Changed A');
 await w.findAll('.configuration-row')[1].findAll('button').find(b=>b.text()==='上移')!.trigger('click');
 await w.findAll('.configuration-row')[0].get('input[type="checkbox"]').setValue(false);
 await w.findAll('button').find(b=>b.text()==='添加一条')!.trigger('click');
 await w.findAll('button').find(b=>b.text()==='保存站点配置')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/cms/configuration/save',{site_id:'7',values:{partners:[{name:'B',url:'/b',enabled:false},{name:'Changed A',url:'/a',enabled:true},{name:'',url:'',enabled:true}]}});
 w.unmount();
});
it('provides a configurable list field with the partner preset through the field editor',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:()=>true},global:{stubs:{...stubs,ElDialog:pass},directives:{loading:()=>{}}}});await flushPromises();
 const state=(w.vm as any).$.setupState;state.openField();state.fieldForm.type='list';await flushPromises();
 await w.findAll('button').find(b=>b.text()==='合作伙伴')!.trigger('click');
 state.fieldForm.key='sponsors';state.fieldForm.label='赞助伙伴';state.fieldForm.group='合作伙伴';
 await w.findAll('button').find(b=>b.text()==='保存配置项')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/cms/configuration/save',expect.objectContaining({site_id:'7',fields:expect.arrayContaining([expect.objectContaining({key:'sponsors',type:'list',fields:expect.arrayContaining([expect.objectContaining({key:'logo',type:'image'}),expect.objectContaining({key:'url',type:'url'})])})])}));w.unmount();
});
it('prevents editing list rows when the current role can only view configuration',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:(code:string)=>code==='cms:site:detail'},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 expect(w.findAll('.configuration-row')).toHaveLength(2);
 expect(w.findAll('.configuration-row input').every(n=>(n.element as HTMLInputElement).disabled)).toBe(true);
 expect(w.findAll('button').find(b=>b.text()==='添加一条')?.attributes('disabled')).toBeDefined();
 expect(http.post).not.toHaveBeenCalled();w.unmount();
});
it('keeps an empty translated list as an explicit override when saving the language',async()=>{
 http.get.mockImplementation((_url:string,params:any)=>Promise.resolve(params.locale?{...response,overrides:{partners:[]},locale:params.locale}:response));
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[{locale:'en-us',name:'English',status:1}] as any,can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 (w.vm as any).$.setupState.selectedLocale='en-us';await flushPromises();
 expect(w.findAll('.configuration-row')).toHaveLength(0);
 await w.findAll('button').find(b=>b.text()==='保存语言配置')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/cms/configuration/save',{site_id:'7',locale:'en-us',values:{partners:[]}});w.unmount();
});
it('creates homepage module presets with stable template keys and preserves existing definitions',async()=>{
 const w=mount(ConfigurationPanel,{props:{siteId:'7',locales:[],can:()=>true},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 const state=(w.vm as any).$.setupState;state.openPreset('slides');await flushPromises();
 expect(state.fieldForm).toMatchObject({key:'slides',label:'轮播图',type:'list',group:'轮播图',localized:true});
 await state.saveField();expect(http.post).toHaveBeenCalledWith('/plugins/cms/configuration/save',expect.objectContaining({site_id:'7',fields:expect.arrayContaining([expect.objectContaining({key:'partners'}),expect.objectContaining({key:'slides',fields:expect.arrayContaining([expect.objectContaining({key:'mobile_image',type:'image'})])})])}));
 state.openPreset('partners');expect(state.fieldForm.key).toBe('partners');expect(state.fieldForm.fields).toEqual(field.fields);w.unmount();
});
