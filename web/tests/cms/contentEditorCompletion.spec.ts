import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { ElTabs, ElTabPane } from 'element-plus';
import { beforeEach, expect, it, vi } from 'vitest';
import { createRouter, createMemoryHistory, RouterView } from 'vue-router';
import ContentPanel from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/ContentPanel.vue';
const api=vi.hoisted(()=>({get:vi.fn(),post:vi.fn(),confirm:vi.fn()}));
vi.mock('@/utils/http',()=>({default:api}));
vi.mock('element-plus',async original=>({...await original<any>(),ElMessageBox:{confirm:api.confirm}}));
const pass=defineComponent({setup(_,{slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const field=defineComponent({props:['label','error'],setup(p,{slots}){return()=>h('div',{'data-label':p.label},[h('label',p.label),slots.default?.(),p.error&&h('p',{role:'alert'},p.error)]);}});
const input=defineComponent({props:['modelValue'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{value:p.modelValue,onInput:(e:any)=>emit('update:modelValue',e.target.value)});}});
const button=defineComponent({props:['disabled'],emits:['click'],setup(p,{slots,emit}){return()=>h('button',{disabled:p.disabled,onClick:()=>emit('click')},slots.default?.());}});
const stubs={...Object.fromEntries(['ElAlert','ElSelect','ElOption','ElSwitch','ElInputNumber','ElForm','ElDivider','ElTable','ElTableColumn','ElTag','ElEmpty','ElPagination'].map(n=>[n,pass])),ElTableColumn:true,ElFormItem:field,ElDrawer:pass,ElDialog:pass,ElInput:input,RichTextEditor:input,AttachmentField:true,ElButton:button,DataTableShell:defineComponent({setup(_,{slots}){return()=>h('div',[slots.search?.(),slots['toolbar-left']?.(),slots.default?.({})]);}})};
const fields=[{name:'enabled',label:'启用',type:'boolean',required:true},{name:'count',label:'数量',type:'number',min:0,max:5,default:0},{name:'source',label:'来源',type:'text',default:'本站',max_length:4}];
const models=[{id:'10',code:'article',name:'文章',schema:{id:'100',field_schema:fields}},{id:'20',code:'product',name:'产品',schema:{id:'200',field_schema:[{name:'sku',label:'型号',type:'text',default:'NEW'}]}}];
const categories=[{id:'1',name:'通用',model_id:null,status:1},{id:'2',name:'文章栏目',model_id:'10',status:1},{id:'3',name:'产品栏目',model_id:'20',status:1},{id:'4',name:'停用',model_id:null,status:0}];
const row={id:'8',content_id:'4',model_id:'10',category_id:'2',locale:'zh-cn',draft_revision_id:'9',lock_version:2,revision:{title:'原文章',desired_path:'/article',body_html:'',model_version_id:'100',field_schema:fields,data:{enabled:false,count:0,source:'旧值'}}};
async function render(){const w=mount(ContentPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,languages:[{locale:'zh-cn',name:'中文'},{locale:'en-us',name:'English'}] as any},global:{stubs,components:{ElTabs,ElTabPane},directives:{loading:()=>{}}}});await flushPromises();return w;}
const state=(w:any)=>w.vm.$.setupState;
const click=async(w:any,label:string)=>{await w.findAll('button').find((n:any)=>n.text()===label)!.trigger('click');await flushPromises();};
beforeEach(()=>{vi.clearAllMocks();api.confirm.mockResolvedValue('confirm');api.get.mockImplementation(async(url:string,params:any)=>url.endsWith('/lookups')?{models,categories:params.locale==='en-us'?categories.map(c=>({...c,name:`EN ${c.name}`})):categories,templates:[]}:url.endsWith('/content')?{items:[],total:0}:url.endsWith('/translation')?{...row,locale:'en-us',revision:{...row.revision,title:'English'}}:[]);api.post.mockResolvedValue({...row,lock_version:3,draft_revision_id:'11'});});
it('hydrates defaults, renders three actual tabs and resets typed data on model change',async()=>{
 const w=await render();await click(w,'新建内容');expect(state(w).form.data).toEqual({enabled:false,count:0,source:'本站'});
 for(const label of ['基本内容','模型字段','SEO 与展示'])expect(w.findAll('[role=tab]').some(t=>t.text()===label)).toBe(true);
 state(w).form.model_id='20';await state(w).changeModel();await flushPromises();expect(state(w).form.data).toEqual({sku:'NEW'});w.unmount();
});
it('selects the error tab and prevents invalid saves while accepting false and zero',async()=>{
 const w=await render();await click(w,'新建内容');Object.assign(state(w).form,{title:'标题',desired_path:'/path'});state(w).form.data.count=6;
 await click(w,'保存草稿');expect(api.post).not.toHaveBeenCalled();expect(state(w).activeEditorTab).toBe('fields');expect(w.text()).toContain('不能大于 5');
 state(w).form.data.count=0;await click(w,'保存草稿');expect(api.post).toHaveBeenCalledWith('/plugin/cms/content/save',expect.objectContaining({data:{enabled:false,count:0,source:'本站'}}));w.unmount();
});
it('filters model categories and refreshes their translated labels when changing edit language',async()=>{
 const w=await render();await click(w,'新建内容');expect(state(w).editorCategories.map((c:any)=>c.id)).toEqual(['1','2']);
 state(w).form.category_id='2';state(w).form.model_id='20';await state(w).changeModel();await flushPromises();expect(state(w).form.category_id).toBe('');expect(state(w).editorCategories.map((c:any)=>c.id)).toEqual(['1','3']);
 api.confirm.mockResolvedValue('confirm');state(w).editLocale='en-us';await state(w).changeLanguage('en-us');expect(api.get).toHaveBeenCalledWith('/plugin/cms/content/lookups',{site_id:'7',locale:'en-us'});expect(state(w).editorCategories[0].name).toBe('EN 通用');w.unmount();
});
it('keeps the dirty draft when closing or changing languages is cancelled',async()=>{
 const w=await render();await state(w).open(row);state(w).form.title='未保存';await flushPromises();expect(state(w).dirty).toBe(true);
 api.confirm.mockRejectedValue('cancel');const done=vi.fn();await state(w).beforeClose(done);expect(done).not.toHaveBeenCalled();
 state(w).editLocale='en-us';await state(w).changeLanguage('en-us');expect(state(w).editLocale).toBe('zh-cn');expect(state(w).form.title).toBe('未保存');expect(api.get).not.toHaveBeenCalledWith('/plugin/cms/content/translation',expect.anything());w.unmount();
});
it('blocks unsaved preview and publication and marks only the submitted snapshot clean',async()=>{
 const w=await render();await state(w).open(row);state(w).form.title='第一稿';await flushPromises();
 await state(w).showPreview();await state(w).publishEditor();expect(api.get).not.toHaveBeenCalledWith('/plugin/cms/content/preview',expect.anything());expect(api.post).not.toHaveBeenCalled();
 let finish!:(value:any)=>void;api.post.mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));const saving=state(w).save();await flushPromises();state(w).form.title='第二稿';finish({...row,lock_version:3,draft_revision_id:'11'});await saving;expect(state(w).form.title).toBe('第二稿');expect(state(w).dirty).toBe(true);w.unmount();
});
it('marks a successful save clean while retaining locale IDs and the returned revision version',async()=>{
 const w=await render();await state(w).open(row);state(w).form.title='新标题';api.post.mockResolvedValue({...row,lock_version:3,draft_revision_id:'11',revision:{...row.revision,id:'11',title:'新标题'}});
 await state(w).save();expect(state(w).form.id).toBe('8');expect(state(w).form.lock_version).toBe(3);expect(state(w).form.draft_revision_id).toBe('11');expect(state(w).dirty).toBe(false);
 await state(w).publishEditor();expect(api.post).toHaveBeenCalledWith('/plugin/cms/content/publish',{site_id:'7',id:'8',revision_id:'11',lock_version:3});w.unmount();
});
it('discards only after consent and uses the saved source when starting a missing translation',async()=>{
 const w=await render();await state(w).open(row);state(w).form.title='不应复制的未保存标题';api.get.mockImplementation(async(url:string)=>url.endsWith('/lookups')?{models,categories,templates:[]}:url.endsWith('/translation')?{...row,revision:null}:{items:[],total:0});
 state(w).editLocale='en-us';await state(w).changeLanguage('en-us');expect(state(w).form.title).toBe('原文章');expect(state(w).dirty).toBe(true);expect(api.confirm).toHaveBeenCalledOnce();
 const event=new Event('beforeunload',{cancelable:true});window.dispatchEvent(event);expect(event.defaultPrevented).toBe(true);w.unmount();
 const cleanEvent=new Event('beforeunload',{cancelable:true});window.dispatchEvent(cleanEvent);expect(cleanEvent.defaultPrevented).toBe(false);
});
it('pins the saved schema after a first save even when input changes during the request',async()=>{
 const w=await render();await click(w,'新建内容');Object.assign(state(w).form,{title:'第一稿',desired_path:'/new'});
 let finish!:(value:any)=>void;api.post.mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));const saving=state(w).save();await flushPromises();state(w).form.title='第二稿';
 finish({...row,model_id:'10',revision:{...row.revision,title:'第一稿',field_schema:fields,model_version_id:'100'}});await saving;
 expect(state(w).form.title).toBe('第二稿');expect(state(w).form.model_version_id).toBe('100');expect(state(w).form.field_schema).toEqual(fields);expect(state(w).dirty).toBe(true);w.unmount();
});
it('guards actual route navigation and keeps the editor mounted after cancellation',async()=>{
 const router=createRouter({history:createMemoryHistory(),routes:[{path:'/editor',component:ContentPanel,props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,languages:[]}},{path:'/other',component:{template:'<p>Other page</p>'}}]});
 await router.push('/editor');await router.isReady();const root=mount(RouterView,{global:{plugins:[router],stubs,components:{ElTabs,ElTabPane},directives:{loading:()=>{}}}});await flushPromises();const w=root.findComponent(ContentPanel);
 await click(w,'新建内容');state(w).form.title='未保存';api.confirm.mockRejectedValue('cancel');await router.push('/other');expect(router.currentRoute.value.path).toBe('/editor');expect(state(w).form.title).toBe('未保存');
 api.confirm.mockResolvedValue('confirm');await router.push('/other');expect(router.currentRoute.value.path).toBe('/other');root.unmount();
});
it('requires saving dirty content before publishing a historical revision',async()=>{
 const w=await render();await state(w).open(row);await state(w).history();state(w).form.title='未保存修改';await state(w).restore({id:'3'});
 expect(api.post).not.toHaveBeenCalled();expect(state(w).error).toContain('先保存');expect(state(w).historyOpen).toBe(true);w.unmount();
});
