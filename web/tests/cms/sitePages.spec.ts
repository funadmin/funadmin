import { computed, defineComponent, h, inject, provide } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import ContentPage from '../../../admin/plugins/cms/web/src/admin/plugins/cms/pages/Content.vue';
import AccessPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/AccessPanel.vue';
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));
vi.mock('@/utils/http',()=>({default:http}));
vi.mock('@/store/modules/user',()=>({useUserStore:()=>({permissions:['*']})}));
const pass=defineComponent({setup(_, {slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const button=defineComponent({emits:['click'],setup(_, {slots,emit}){return()=>h('button',{onClick:()=>emit('click')},slots.default?.());}});
const key=Symbol();const table=defineComponent({props:{data:Array},setup(props,{slots}){provide(key,computed(()=>props.data||[]));return()=>h('section',slots.default?.());}});
const column=defineComponent({setup(_, {slots}){const rows=inject<any>(key,{value:[]});return()=>h('div',rows.value.map((row:any)=>h('div',slots.default?.({row}))));}});
const stubs:any=Object.fromEntries(['ElAlert','ElSelect','ElOption','ElTag','ElInput','ElForm','ElFormItem','ElSwitch','ElTabs','ElTabPane','ElPagination','ElEmpty','ElInputNumber','ElCheckbox','ElCheckboxGroup','ElTree','ElDivider','ElPopconfirm'].map(n=>[n,pass]));
Object.assign(stubs,{ElButton:button,ElTable:table,ElTableColumn:column,ElDrawer:true,ElDialog:true,PageWrapper:defineComponent({setup(_, {slots}){return()=>h('main',[slots.header?.(),slots.default?.()]);}}),DataTableShell:defineComponent({setup(_, {slots}){return()=>h('div',[slots.search?.(),slots['toolbar-left']?.(),slots.default?.({})]);}})});
beforeEach(()=>{sessionStorage.clear();vi.clearAllMocks();http.post.mockResolvedValue(true);http.get.mockImplementation(async(url:string)=>url.endsWith('/context')?{items:[{id:'7',name:'A',default_locale:'zh-cn',locales:[{locale:'zh-cn',name:'中文',status:1}],permissions:['cms:content:list','cms:content:edit']}],is_super:false}:url.endsWith('/content')?{items:[{id:'8',content_id:'4',locale:'zh-cn',revision:{title:'Test'},status:'draft'}],total:1}:url.endsWith('/lookups')?{models:[],categories:[]}:[]);});
it('loads only minimal context for its menu and separates create from edit buttons',async()=>{
 const w=mount(ContentPage,{global:{stubs,directives:{loading:()=>{},perm:()=>{}}}});await flushPromises();
 expect(http.get).toHaveBeenCalledWith('/plugins/cms/context',{menu:'cms:content:list'});expect(http.get).not.toHaveBeenCalledWith('/plugins/cms/settings',expect.anything());
 expect(w.text()).not.toContain('新建内容');expect(w.text()).toContain('编辑 / 翻译');expect(w.text()).not.toContain('发布草稿');w.unmount();
});
it('refreshes site-scoped button rights when switching sites',async()=>{
 http.get.mockImplementation(async(url:string)=>url.endsWith('/context')?{items:[{id:'7',name:'A',default_locale:'zh-cn',locales:[{locale:'zh-cn',status:1}],permissions:['cms:content:list','cms:content:create']},{id:'8',name:'B',default_locale:'fr',locales:[{locale:'fr',status:1}],permissions:['cms:content:list']}],is_super:false}:url.endsWith('/content')?{items:[],total:0}:url.endsWith('/lookups')?{models:[],categories:[]}:[]);
 const w=mount(ContentPage,{global:{stubs,directives:{loading:()=>{},perm:()=>{}}}});await flushPromises();expect(w.text()).toContain('新建内容');
 w.getComponent('[data-testid="cms-site-select"]').vm.$emit('update:modelValue','8');await flushPromises();expect(w.text()).not.toContain('新建内容');expect(http.get).toHaveBeenCalledWith('/plugins/cms/content',expect.objectContaining({site_id:'8',locale:'fr'}));w.unmount();
});
it('assigns multiple site roles using only scoped IDs',async()=>{
 const dialog=defineComponent({props:{modelValue:Boolean},setup(props,{slots}){return()=>props.modelValue?h('div',[slots.default?.(),slots.footer?.()]):null;}});
 http.get.mockImplementation(async(url:string)=>url.endsWith('/candidates')?[{id:'91',username:'A',host_permissions:['cms:content:list']}]:{catalog:{content:{name:'内容管理',actions:{list:'查看内容'},menu:'list'}},ceilings:{'cms:content:list':['cms:content:list']},host_permissions:['cms:content:list'],roles:[{id:'2',code:'viewer',name:'只读',status:1,permissions:['cms:content:list']}],members:[{id:'91',username:'A',role_ids:['2'],manageable:true,effective:['cms:content:list'],host_permissions:['cms:content:list']}]});
 const w=mount(AccessPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,isSuper:true},global:{stubs:{...stubs,ElDialog:dialog},directives:{loading:()=>{}}}});await flushPromises();
 await w.findAll('button').find(n=>n.text()==='分配角色')!.trigger('click');await flushPromises();await w.findAll('button').find(n=>n.text()==='保存成员角色')!.trigger('click');await flushPromises();
 expect(http.post).toHaveBeenCalledWith('/plugins/cms/access/members/save',{site_id:'7',admin_id:'91',role_ids:['2']});w.unmount();
});
