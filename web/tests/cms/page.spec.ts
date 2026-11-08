import { defineComponent, h } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CmsPage from '../../../admin/plugins/cms/web/src/admin/plugins/cms/pages/Content.vue';
const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@/utils/http', () => ({ default: http }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: ['*'] }) }));
const passthrough = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.()); } });
const stubs: Record<string, any> = Object.fromEntries(['ElCard','ElTabs','ElTabPane','ElButton','ElSelect','ElOption','ElAlert','ElTable','ElTableColumn','ElPagination','ElInput','ElEmpty','ElTag','ElFormItem','ElDivider','ElSwitch','ElInputNumber','ElForm','ElCheckbox'].map(name => [name, passthrough]));
stubs.DataTableShell = defineComponent({ setup(_, { slots }) { return () => h('div', [slots.search?.(), slots['toolbar-left']?.(), slots.default?.({ size: 'default', stripe: false, border: false, headerCellStyle: undefined })]); } });
for (const name of ['ElTableColumn','ElDrawer','ElDialog']) stubs[name] = defineComponent({ setup() { return () => h('div'); } });
beforeEach(() => {
  vi.clearAllMocks(); sessionStorage.clear();
  http.get.mockImplementation(async (url: string) => {
    if (url.endsWith('/context')) return { items: [{ id: '7', name: 'Main', default_locale: 'zh-cn', locales: [{ id: '1', locale: 'zh-cn', name: '中文', status: 1 }], permissions: ['cms:content:list'] }], is_super: true };
    if (url.endsWith('/settings')) return { site: { id: '7', name: 'Main', default_locale: 'zh-cn' }, locales: [{ id: '1', locale: 'zh-cn', name: '中文', status: 1 }], domains: [], models: [], admin_ids: [] };
    if (url.endsWith('/lookups')) return { models: [], categories: [] };
    if (url.endsWith('/content')) return { items: [], total: 0, page: 1, limit: 12 };
    return [];
  });
});
describe('CMS management context', () => {
  it('automatically selects the only site and language and loads scoped content', async () => {
    const wrapper = mount(CmsPage, { global: { stubs, directives: { loading: () => {} } } });
    await flushPromises();
    expect(http.get).toHaveBeenCalledWith('/plugins/cms/context', { menu: 'cms:content:list' });
    expect(http.get).not.toHaveBeenCalledWith('/plugins/cms/settings', expect.anything());
    expect(http.get).toHaveBeenCalledWith('/plugins/cms/content', expect.objectContaining({ site_id: '7', locale: 'zh-cn' }));
    expect(wrapper.find('[data-testid="cms-site-select"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="cms-locale-select"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('内容管理');
  });
});
it('supports mixed site language combinations and resets to the selected site default', async () => {
  http.get.mockImplementation(async (url:string,params:any) => {
    if(url.endsWith('/context'))return {items:[{id:'7',name:'A',default_locale:'zh-cn',locales:[{locale:'zh-cn',name:'中文',status:1},{locale:'en-us',name:'English',status:1}],permissions:['cms:content:list']},{id:'8',name:'B',default_locale:'fr',locales:[{locale:'fr',name:'Français',status:1}],permissions:['cms:content:list']}],is_super:true};
    if(url.endsWith('/settings'))return params.site_id==='7'?{site:{id:'7',name:'A',default_locale:'zh-cn'},locales:[{locale:'zh-cn',name:'中文',status:1},{locale:'en-us',name:'English',status:1}],domains:[],models:[],admin_ids:[]}:{site:{id:'8',name:'B',default_locale:'fr'},locales:[{locale:'fr',name:'Français',status:1}],domains:[],models:[],admin_ids:[]};
    if(url.endsWith('/lookups'))return {models:[],categories:[]};if(url.endsWith('/content'))return {items:[],total:0,page:1,limit:12};return [];
  });
  const wrapper=mount(CmsPage,{global:{stubs,directives:{loading:()=>{}}}});await flushPromises();
  expect(wrapper.find('[data-testid="cms-site-select"]').exists()).toBe(true);expect(wrapper.find('[data-testid="cms-locale-select"]').exists()).toBe(true);
  const picker=wrapper.getComponent('[data-testid="cms-site-select"]');
  picker.vm.$emit('update:modelValue','8');await flushPromises();
  expect(http.get).toHaveBeenCalledWith('/plugins/cms/content',expect.objectContaining({site_id:'8',locale:'fr'}));
  expect(wrapper.find('[data-testid="cms-locale-select"]').exists()).toBe(false);
});
