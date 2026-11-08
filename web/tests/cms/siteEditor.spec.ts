import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import SourcePanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/SitePanel.vue';
import RuntimePanel from '../../src/admin/plugins/cms/components/SitePanel.vue';
const api=vi.hoisted(()=>({siteOptions:vi.fn(),saveSite:vi.fn(),saveLocale:vi.fn(),saveDomain:vi.fn()}));
vi.mock('../../../admin/plugins/cms/web/src/admin/plugins/cms/api',()=>({cmsApi:api}));
vi.mock('../../src/admin/plugins/cms/api',()=>({cmsApi:api}));
const pass=defineComponent({setup(_props,{slots}){return()=>h('div',slots.default?.());}});
const button=defineComponent({emits:['click'],setup(_props,{slots,emit}){return()=>h('button',{onClick:()=>emit('click')},slots.default?.());}});
const select=defineComponent({props:['modelValue','disabled'],emits:['update:modelValue','change'],setup(props,{slots,emit}){return()=>h('select',{value:props.modelValue,disabled:props.disabled,onChange:(event:Event)=>{const value=(event.target as HTMLSelectElement).value;emit('update:modelValue',value);emit('change',value);}},slots.default?.());}});
const option=defineComponent({props:['value','label','disabled'],setup(props){return()=>h('option',{value:props.value,disabled:props.disabled},props.label);}});
const dialog=defineComponent({props:['modelValue','title'],setup(props,{slots}){return()=>props.modelValue?h('section',{role:'dialog','aria-label':props.title},[slots.default?.(),slots.footer?.()]):null;}});
const settings:any={site:{id:'7',code:'main',name:'Main',default_locale:'zh-cn',theme:'legacy',status:1},locales:[{id:'1',locale:'zh-cn',name:'Main',status:1},{id:'2',locale:'en-us',name:'English site',status:1},{id:'3',locale:'ja-jp',name:'Japanese site',status:0}],domains:[],models:[]};
const wrappers:ReturnType<typeof mount>[]=[];
beforeEach(()=>{vi.clearAllMocks();api.siteOptions.mockResolvedValue({themes:[{value:'default',label:'默认企业与资讯主题（default）'},{value:'corporate',label:'corporate（自定义主题）'}]});api.saveSite.mockResolvedValue({id:'8'});api.saveLocale.mockResolvedValue(true);});
afterEach(()=>wrappers.splice(0).forEach(wrapper=>wrapper.unmount()));
describe.each([['源插件',SourcePanel],['运行副本',RuntimePanel]] as const)('%s 站点编辑器',(_name,panel)=>{
 async function render(existing=false,can=(_permission:string)=>true){
  const wrapper=mount(panel,{props:{settings:existing?settings:undefined,isSuper:true,can},global:{stubs:{...Object.fromEntries(['ElForm','ElFormItem','ElTabs','ElTabPane','ElDescriptions','ElDescriptionsItem'].map(name=>[name,pass])),ElButton:button,ElSelect:select,ElOption:option,ElDialog:dialog,ElInput:true,ElSwitch:true,ElAlert:true,ElTable:true,ElTableColumn:true,ElTag:true,ConfigurationPanel:true,ContributionSettingsPanel:true}}});
  wrappers.push(wrapper);await flushPromises();return wrapper;
 }
 it('新建站点通过中文下拉选择默认语言和实际主题，并保存对应代码',async()=>{
  const wrapper=await render();await wrapper.findAll('button').find(item=>item.text()==='新建站点')!.trigger('click');await flushPromises();
  const editor=wrapper.get('[aria-label="站点配置"]');
  const locale=editor.get('select[aria-label="默认语言"]');expect(locale.text()).toContain('简体中文');expect(locale.text()).toContain('英语');
  const theme=editor.get('select[aria-label="主题"]');expect(theme.text()).toContain('默认企业与资讯主题');
  await locale.setValue('en-us');await theme.setValue('corporate');
  const state=(wrapper.vm as any).$.setupState;Object.assign(state.form,{code:'example',name:'Example',host:'example.test'});
  await editor.findAll('button').find(item=>item.text()==='保存')!.trigger('click');await flushPromises();
  expect(api.saveSite).toHaveBeenCalledWith(expect.objectContaining({default_locale:'en-us',theme:'corporate'}));
 });
 it('编辑站点只提供本站启用语言，保留未安装的旧主题配置',async()=>{
  const wrapper=await render(true);await wrapper.findAll('button').find(item=>item.text()==='编辑站点')!.trigger('click');await flushPromises();
  const editor=wrapper.get('[aria-label="站点配置"]');const locale=editor.get('select[aria-label="默认语言"]');
  expect(locale.find('option[value="en-us"]').exists()).toBe(true);expect(locale.find('option[value="ja-jp"]').exists()).toBe(false);
  const theme=editor.get('select[aria-label="主题"]');expect((theme.element as HTMLSelectElement).value).toBe('legacy');expect(theme.text()).toContain('当前不可用');
  await editor.findAll('button').find(item=>item.text()==='保存')!.trigger('click');await flushPromises();
  expect(api.saveSite).toHaveBeenCalledWith(expect.objectContaining({id:'7',default_locale:'zh-cn',theme:'legacy'}));
 });
 it('没有语言管理权限不能更改已有站点默认语言',async()=>{
  const wrapper=await render(true,permission=>permission!=='cms:site:locale');
  await wrapper.findAll('button').find(item=>item.text()==='编辑站点')!.trigger('click');await flushPromises();
  expect(wrapper.get('select[aria-label="默认语言"]').attributes('disabled')).toBeDefined();
 });
 it('主题选项加载失败保留表单并允许重试，只有一套主题时解释原因',async()=>{
  api.siteOptions.mockRejectedValueOnce(new Error('offline'));
  const wrapper=await render();await wrapper.findAll('button').find(item=>item.text()==='新建站点')!.trigger('click');await flushPromises();
  const editor=wrapper.get('[aria-label="站点配置"]');
  expect(editor.findAll('el-alert-stub').some(item=>item.attributes('title')==='主题选项加载失败，请重试')).toBe(true);
  api.siteOptions.mockResolvedValue({themes:[{value:'default',label:'默认企业与资讯主题（default）'}]});
  await editor.findAll('button').find(item=>item.text()==='重新加载主题')!.trigger('click');await flushPromises();
  expect(editor.text()).toContain('当前仅安装一套主题');
  expect((editor.get('select[aria-label="主题"]').element as HTMLSelectElement).value).toBe('default');
 });
});
