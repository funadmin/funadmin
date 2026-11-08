import { computed, defineComponent, h, inject, provide } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ContentPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ContentPanel.vue';
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));
vi.mock('@/utils/http',()=>({default:http}));
const row={id:'8',content_id:'4',model_id:'2',category_id:null,locale:'zh-cn',status:'published',draft_revision_id:'9',published_revision_id:'7',lock_version:2,revision:{id:'9',model_version_id:'2',title:'My saved draft',desired_path:'/draft',data:{},body_html:'',template:'detail',robots:'index,follow'}};
const passthrough=defineComponent({setup(_, {slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const tableShell=defineComponent({setup(_, {slots}){return()=>h('div',[slots.search?.(),slots['toolbar-left']?.(),slots.default?.({size:'default',stripe:false,border:false,headerCellStyle:undefined})]);}});
const button=defineComponent({emits:['click'],setup(_, {slots,emit}){return()=>h('button',{onClick:()=>emit('click')},slots.default?.());}});
const key=Symbol();
const table=defineComponent({props:{data:Array},setup(props,{slots}){provide(key,computed(()=>props.data||[]));return()=>h('section',slots.default?.());}});
const column=defineComponent({setup(_, {slots}){const rows=inject<any>(key,{value:[]});return()=>h('div',rows.value.map((item:any)=>h('div',slots.default?.({row:item}))));}});
it('publishes the revision the editor loaded and keeps its optimistic lock',async()=>{
  http.get.mockImplementation(async(url:string)=>url.endsWith('/lookups')?{models:[],categories:[]}:url.endsWith('/detail')?{...row,draft_revision_id:'10',lock_version:3}:url.endsWith('/content')?{items:[row],total:1}:[]);
  http.post.mockResolvedValue(row);
  const names=['ElTabs','ElTabPane','ElAlert','ElInput','ElSelect','ElOption','ElTag','ElPagination','ElDrawer','ElDialog','ElForm','ElFormItem','ElDivider','ElSwitch','ElInputNumber'];
  const stubs:any=Object.fromEntries(names.map(name=>[name,passthrough]));Object.assign(stubs,{DataTableShell:tableShell,ElButton:button,ElTable:table,ElTableColumn:column,AttachmentField:true});
  const wrapper=mount(ContentPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,languages:[{id:'1',site_id:'7',locale:'zh-cn',name:'中文',url_prefix:'',status:1,seo_title:'',seo_description:''}]},global:{stubs,directives:{loading:()=>{}}}});
  await flushPromises();await wrapper.findAll('button').find(node=>node.text()==='编辑 / 翻译')!.trigger('click');await flushPromises();
  await wrapper.findAll('button').find(node=>node.text()==='发布已保存草稿')!.trigger('click');await flushPromises();
  expect(http.post).toHaveBeenCalledWith('/plugin/cms/content/publish',{site_id:'7',id:'8',revision_id:'9',lock_version:2});
  expect(http.get).not.toHaveBeenCalledWith('/plugin/cms/content/detail',expect.anything());
});

it('ignores a late translation response after the editor changed language again',async()=>{
  let english!: (value:any)=>void, chinese!: (value:any)=>void;
  http.get.mockImplementation(async(url:string,params:any)=>{
    if(url.endsWith('/lookups'))return {models:[],categories:[]};
    if(url.endsWith('/translation'))return new Promise(resolve=>{if(params.locale==='en-us')english=resolve;else chinese=resolve;});
    if(url.endsWith('/content'))return {items:[row],total:1};return [];
  });
  http.post.mockClear();http.post.mockResolvedValue(row);
  const select=defineComponent({name:'LocaleSelect',props:{modelValue:null},emits:['update:modelValue','change'],setup(_, {slots}){return()=>h('div',slots.default?.());}});
  const names=['ElTabs','ElTabPane','ElAlert','ElInput','ElOption','ElTag','ElPagination','ElDrawer','ElDialog','ElForm','ElFormItem','ElDivider','ElSwitch','ElInputNumber'];
  const stubs:any=Object.fromEntries(names.map(name=>[name,passthrough]));Object.assign(stubs,{DataTableShell:tableShell,ElSelect:select,ElButton:button,ElTable:table,ElTableColumn:column,AttachmentField:true});
  const language=(locale:string)=>({id:locale,site_id:'7',locale,name:locale,url_prefix:locale,status:1,seo_title:'',seo_description:''});
  const wrapper=mount(ContentPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,languages:[language('zh-cn'),language('en-us')]},global:{stubs,directives:{loading:()=>{}}}});
  await flushPromises();await wrapper.findAll('button').find(node=>node.text()==='编辑 / 翻译')!.trigger('click');await flushPromises();
  const picker=wrapper.findAllComponents(select).find(component=>component.props('modelValue')==='zh-cn')!;
  picker.vm.$emit('update:modelValue','en-us');picker.vm.$emit('change','en-us');await flushPromises();
  picker.vm.$emit('update:modelValue','zh-cn');picker.vm.$emit('change','zh-cn');await flushPromises();
  chinese(row);await flushPromises();english({...row,id:'80',draft_revision_id:'90',revision:{...row.revision,id:'90',title:'English text'}});await flushPromises();
  await wrapper.findAll('button').find(node=>node.text()==='保存草稿')!.trigger('click');await flushPromises();
  expect(http.post).toHaveBeenCalledWith('/plugin/cms/content/save',expect.objectContaining({locale:'zh-cn',title:'My saved draft'}));
});

it.each(['language', 'article'] as const)('ignores a saved draft response after changing the editor %s', async (change) => {
  let finishSave!: (value: any) => void;
  const other = { ...row, id: '80', content_id: change === 'article' ? '40' : '4', locale: change === 'language' ? 'en-us' : 'zh-cn', draft_revision_id: '90', revision: { ...row.revision, id: '90', title: 'Other content' } };
  http.get.mockImplementation(async (url: string) => url.endsWith('/lookups') ? { models: [], categories: [] } : url.endsWith('/translation') ? other : url.endsWith('/content') ? { items: [row, other], total: 2 } : []);
  http.post.mockReset();
  http.post.mockImplementation((url: string) => url.endsWith('/save') ? new Promise(resolve => { finishSave = resolve; }) : Promise.resolve(other));
  const select = defineComponent({ name: 'LocaleSelect', props: { modelValue: null }, emits: ['update:modelValue', 'change'], setup(_, { slots }) { return () => h('div', slots.default?.()); } });
  const names = ['ElTabs','ElTabPane','ElAlert', 'ElInput', 'ElOption', 'ElTag', 'ElPagination', 'ElDrawer', 'ElDialog', 'ElForm', 'ElFormItem', 'ElDivider', 'ElSwitch', 'ElInputNumber'];
  const stubs: any = Object.fromEntries(names.map(name => [name, passthrough]));
  Object.assign(stubs, { DataTableShell: tableShell, ElSelect: select, ElButton: button, ElTable: table, ElTableColumn: column, AttachmentField: true });
  const language = (locale: string) => ({ id: locale, site_id: '7', locale, name: locale, url_prefix: locale, status: 1, seo_title: '', seo_description: '' });
  const wrapper = mount(ContentPanel, { props: { scope: { site_id: '7', locale: 'zh-cn' }, can: () => true, languages: [language('zh-cn'), language('en-us')] }, global: { stubs, directives: { loading: () => {} } } });
  await flushPromises();
  await wrapper.findAll('button').filter(node => node.text() === '编辑 / 翻译')[0]!.trigger('click');
  await flushPromises();
  await wrapper.findAll('button').find(node => node.text() === '保存草稿')!.trigger('click');
  await flushPromises();
  if (change === 'language') {
    const picker = wrapper.findAllComponents(select).find(component => component.props('modelValue') === 'zh-cn')!;
    picker.vm.$emit('update:modelValue', 'en-us'); picker.vm.$emit('change', 'en-us');
  } else {
    await wrapper.findAll('button').filter(node => node.text() === '编辑 / 翻译')[1]!.trigger('click');
  }
  await flushPromises();
  finishSave({ ...row, draft_revision_id: '10', lock_version: 3 });
  await flushPromises();
  await wrapper.findAll('button').find(node => node.text() === '发布已保存草稿')!.trigger('click');
  await flushPromises();
  expect(http.post).toHaveBeenCalledWith('/plugin/cms/content/publish', { site_id: '7', id: '80', revision_id: '90', lock_version: 2 });
  wrapper.unmount();
});

it('updates the saved revision and optimistic lock while the same editor stays open', async () => {
  http.get.mockImplementation(async (url: string) => url.endsWith('/lookups') ? { models: [], categories: [] } : url.endsWith('/content') ? { items: [row], total: 1 } : []);
  http.post.mockReset();
  http.post.mockResolvedValue({ ...row, draft_revision_id: '10', lock_version: 3 });
  const names = ['ElTabs','ElTabPane','ElAlert', 'ElInput', 'ElSelect', 'ElOption', 'ElTag', 'ElPagination', 'ElDrawer', 'ElDialog', 'ElForm', 'ElFormItem', 'ElDivider', 'ElSwitch', 'ElInputNumber'];
  const stubs: any = Object.fromEntries(names.map(name => [name, passthrough]));
  Object.assign(stubs, { DataTableShell: tableShell, ElButton: button, ElTable: table, ElTableColumn: column, AttachmentField: true });
  const wrapper = mount(ContentPanel, { props: { scope: { site_id: '7', locale: 'zh-cn' }, can: () => true, languages: [] }, global: { stubs, directives: { loading: () => {} } } });
  await flushPromises();
  await wrapper.findAll('button').find(node => node.text() === '编辑 / 翻译')!.trigger('click'); await flushPromises();
  await wrapper.findAll('button').find(node => node.text() === '保存草稿')!.trigger('click'); await flushPromises();
  await wrapper.findAll('button').find(node => node.text() === '发布已保存草稿')!.trigger('click'); await flushPromises();
  expect(http.post).toHaveBeenCalledWith('/plugin/cms/content/publish', { site_id: '7', id: '8', revision_id: '10', lock_version: 3 });
  wrapper.unmount();
});

it('lets a publisher inspect and publish historical revisions without edit rights', async()=>{
 http.get.mockImplementation(async(url:string)=>url.endsWith('/lookups')?{models:[],categories:[]}:url.endsWith('/revisions')?[{...row.revision,id:'6',revision_no:1}]:url.endsWith('/content')?{items:[row],total:1}:[]);http.post.mockReset();http.post.mockResolvedValue(row);
 const names=['ElTabs','ElTabPane','ElAlert','ElInput','ElSelect','ElOption','ElTag','ElPagination','ElDialog','ElForm','ElFormItem','ElDivider','ElSwitch','ElInputNumber'];const stubs:any=Object.fromEntries(names.map(name=>[name,passthrough]));Object.assign(stubs,{DataTableShell:tableShell,ElButton:button,ElTable:table,ElTableColumn:column,ElDrawer:true,AttachmentField:true});
 const wrapper=mount(ContentPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:(code:string)=>['cms:content:list','cms:content:publish'].includes(code),languages:[]},global:{stubs,directives:{loading:()=>{},perm:()=>{}}}});await flushPromises();
 expect(wrapper.findAll('button').some(n=>n.text()==='编辑 / 翻译')).toBe(false);
 const history=wrapper.findAll('button').find(n=>n.text()==='历史版本');expect(history).toBeDefined();await history!.trigger('click');await flushPromises();
 await wrapper.findAll('button').find(n=>n.text()==='发布此版本')!.trigger('click');await flushPromises();expect(http.post).toHaveBeenCalledWith('/plugin/cms/content/publish',{site_id:'7',id:'8',revision_id:'6',lock_version:2});wrapper.unmount();
});

it('selects an actual theme template and sends formatted body HTML when saving',async()=>{
 http.get.mockImplementation(async(url:string)=>url.endsWith('/lookups')?{models:[],categories:[],templates:[{value:'article',label:'文章详情 (article)'},{value:'download',label:'下载详情 (download)'}]}:url.endsWith('/content')?{items:[row],total:1}:[]);
 http.post.mockReset();http.post.mockResolvedValue(row);
 const select=defineComponent({name:'TemplatePicker',props:{modelValue:null},emits:['update:modelValue'],setup(_, {slots}){return()=>h('select',slots.default?.());}});
 const option=defineComponent({props:['value','label'],setup(props){return()=>h('option',{value:props.value},props.label);}});
 const names=['ElTabs','ElTabPane','ElAlert','ElInput','ElTag','ElPagination','ElDrawer','ElDialog','ElForm','ElFormItem','ElDivider','ElSwitch','ElInputNumber'];
 const stubs:any=Object.fromEntries(names.map(name=>[name,passthrough]));Object.assign(stubs,{DataTableShell:tableShell,ElSelect:select,ElOption:option,ElButton:button,ElTable:table,ElTableColumn:column,AttachmentField:true,RichTextEditor:true});
 const wrapper=mount(ContentPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,languages:[]},global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
 await wrapper.findAll('button').find(n=>n.text()==='编辑 / 翻译')!.trigger('click');await flushPromises();
 const picker=wrapper.findAllComponents(select).find(c=>c.props('modelValue')==='detail');expect(picker).toBeDefined();expect(picker!.text()).toContain('文章详情');expect(picker!.text()).toContain('原模板 (detail)');
 picker!.vm.$emit('update:modelValue','article');wrapper.findComponent({name:'RichTextEditor'}).vm.$emit('update:modelValue','<h2>简介</h2><p><strong>正文</strong></p>');await flushPromises();
 await wrapper.findAll('button').find(n=>n.text()==='保存草稿')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugin/cms/content/save',expect.objectContaining({template:'article',body_html:'<h2>简介</h2><p><strong>正文</strong></p>'}));wrapper.unmount();
});
