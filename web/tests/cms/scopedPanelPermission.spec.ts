import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
const mocks = vi.hoisted(() => ({ allowed: false, permissions: [] as string[], saveSite: vi.fn(), saveCategory: vi.fn(), saveModel: vi.fn(), saveSiteModel: vi.fn() }));
vi.mock('@/utils/permission', () => ({ hasPermission: (code: string) => mocks.permissions.includes(code) }));
vi.mock('../../../admin/plugins/cms/web/src/admin/plugins/cms/api', () => ({ cmsApi: { sites: async () => [], categories: async () => [], models: async () => [], saveSite: mocks.saveSite, saveCategory: mocks.saveCategory, saveModel: mocks.saveModel, saveSiteModel: mocks.saveSiteModel } }));
import SitePanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/SitePanel.vue';
import CategoryPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/CategoryPanel.vue';
import ModelPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/ModelPanel.vue';
const global = { directives: { perm: {}, loading: {} }, stubs: Object.fromEntries(['ElAlert','ElButton','ElDialog','ElForm','ElFormItem','ElInput','ElInputNumber','ElSelect','ElOption','ElSwitch','ElCheckbox','ElTable','ElTableColumn','ElTag','ElDescriptions','ElDescriptionsItem'].map(name => [name, true])) };
const scope = { site_id: 'site-a', locale: 'zh-cn' };
describe('CMS功能权限与站点授权', () => {
  beforeEach(() => { mocks.permissions = []; mocks.allowed = false; vi.clearAllMocks(); });
  it('未授予本站新建/编辑权限不能保存栏目', async () => {
    const wrapper = shallowMount(CategoryPanel, { props: { scope, can: () => mocks.allowed }, global }); await flushPromises();
    await (wrapper.vm as any).$.setupState.save();
    expect(mocks.saveCategory).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('编辑器打开后撤销权限，提交被阻止', async () => {
    mocks.permissions = ['cms:category:save']; mocks.allowed = true;
    const wrapper = shallowMount(CategoryPanel, { props: { scope, can: () => mocks.allowed }, global }); await flushPromises();
    const state = (wrapper.vm as any).$.setupState; state.open(); mocks.permissions = []; mocks.allowed = false;
    await state.save(); expect(mocks.saveCategory).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('新建站点仍需超级管理员，站点成员只通过角色分配管理', async () => {
    mocks.permissions = ['cms:site:save'];
    const wrapper = shallowMount(SitePanel, { props: { can: () => mocks.allowed, isSuper: false }, global });
    const state = (wrapper.vm as any).$.setupState; state.dialog = 'site'; state.form = {}; await state.save();
    expect(mocks.saveSite).not.toHaveBeenCalled(); wrapper.unmount();
    const source = readFileSync(resolve(process.cwd(), '../admin/plugins/cms/web/src/admin/plugins/cms/api.ts'), 'utf8');
    expect(source).not.toContain('/sites/grant');
  });
  it('模型管理保留超级管理员约束，本站开关需要本站权限', async () => {
    mocks.permissions = ['cms:model:save'];
    const wrapper = shallowMount(ModelPanel, { props: { scope, settings: { models: [] } as any, can: () => mocks.allowed, isSuper: false }, global }); await flushPromises();
    const state = (wrapper.vm as any).$.setupState; await state.save(); await state.toggle({ id: 'm' }, true);
    expect(mocks.saveModel).not.toHaveBeenCalled(); expect(mocks.saveSiteModel).not.toHaveBeenCalled(); wrapper.unmount();
  });
});
