import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
const mocks = vi.hoisted(() => ({ saveModel: vi.fn(), attachments: vi.fn(async () => []), context: vi.fn(async () => ({ items: [], is_super: false })) }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: [] }) }));
vi.mock('../../../admin/plugins/cms/web/src/admin/plugins/cms/api', () => ({ cmsApi: { context: mocks.context, saveModel: mocks.saveModel, attachments: mocks.attachments } }));
import CmsPage from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/CmsPage.vue';
import ModelPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ModelPanel.vue';
import AttachmentField from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/AttachmentField.vue';
const global = { directives: { loading: {}, perm: {} }, stubs: Object.fromEntries(['PageWrapper','ElEmpty','ElAlert','ElButton','ElDialog','ElForm','ElFormItem','ElInput','ElInputNumber','ElSelect','ElOption','ElSwitch','ElCheckbox','ElTable','ElTableColumn','ElTag'].map(name => [name, true])) };
const scope = { site_id: 'a', locale: 'zh-cn' };
describe('CMS源插件公共权限保持站点上下文', () => {
  beforeEach(() => { vi.clearAllMocks(); vi.stubGlobal('sessionStorage', { getItem: () => null, setItem: () => {} }); });
  it('站点来源使用公共通配符语义并保持切换站点隔离', async () => {
    const wrapper = shallowMount(CmsPage, { props: { page: 'content' }, global }); await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    state.sites = [{ id: 'a', permissions: ['*'], locales: [] }, { id: 'b', permissions: ['cms:content:list'], locales: [] }];
    state.siteId = 'a'; expect(state.can('cms:content:edit')).toBe(true);
    state.siteId = 'b'; expect(state.can('cms:content:edit')).toBe(false); expect(state.can('cms:content:list')).toBe(true);
    wrapper.unmount();
  });
  it('超级管理员保存模型服从传入权限上下文，不被旧全局save码误拒绝', async () => {
    const wrapper = shallowMount(ModelPanel, { props: { scope, settings: { models: [] }, isSuper: true, can: (code: string) => code === 'cms:models:save' }, global }); await flushPromises();
    await (wrapper.vm as any).$.setupState.save(); expect(mocks.saveModel).toHaveBeenCalledOnce(); wrapper.unmount();
  });
  it('仅站点create权限即可查询附件，不要求全局旧save码', async () => {
    const wrapper = shallowMount(AttachmentField, { props: { scope, can: (code: string) => code === 'cms:content:create' }, global }); await flushPromises();
    expect(mocks.attachments).toHaveBeenCalledWith({ ...scope, q: '' }); wrapper.unmount();
  });
  it('站点没有create/edit权限时附件查询仍被拒绝', async () => {
    const wrapper = shallowMount(AttachmentField, { props: { scope, can: () => false }, global }); await flushPromises();
    expect(mocks.attachments).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('附件查询在站点权限撤销后使用最新上下文', async () => {
    let allowed = true;
    const wrapper = shallowMount(AttachmentField, { props: { scope, can: () => allowed }, global }); await flushPromises();
    expect(mocks.attachments).toHaveBeenCalledOnce(); allowed = false;
    await (wrapper.vm as any).$.setupState.search('image'); expect(mocks.attachments).toHaveBeenCalledOnce(); wrapper.unmount();
  });
  it('模型定义写入保留超级管理员限制', async () => {
    const wrapper = shallowMount(ModelPanel, { props: { scope, settings: { models: [] }, isSuper: false, can: (code: string) => code === 'cms:models:save' }, global }); await flushPromises();
    await (wrapper.vm as any).$.setupState.save(); expect(mocks.saveModel).not.toHaveBeenCalled(); wrapper.unmount();
  });

});
