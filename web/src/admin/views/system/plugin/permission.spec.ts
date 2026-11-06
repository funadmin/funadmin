import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
const mocks = vi.hoisted(() => ({ allowed: false, confirm: vi.fn(), enable: vi.fn(), installCloud: vi.fn() }));
vi.mock('@/utils/permission', () => ({ hasPermission: () => mocks.allowed }));
vi.mock('element-plus', () => ({ ElMessageBox: { confirm: mocks.confirm } }));
vi.mock('@/router', () => ({ default: { push: vi.fn() } }));
vi.mock('@/router/pluginStartup', () => ({ loadPluginModulesSafely: vi.fn() }));
vi.mock('@/api/plugin', () => ({ pluginApi: { installed: async () => [{ code: 'demo' }], checkUpdates: async () => [], enable: mocks.enable, installCloud: mocks.installCloud } }));
import PluginCenter from './index.vue';
const mountCenter = () => shallowMount(PluginCenter, { global: { directives: { perm: {}, loading: {} }, stubs: Object.fromEntries(['PageWrapper','ElButton','ElAlert','ElTabs','ElTabPane','ElTable','ElTableColumn'].map(name => [name, true])) } });
describe('插件中心操作权限', () => {
  beforeEach(() => { mocks.allowed = false; vi.clearAllMocks(); });
  it('无权限直接调用操作入口不会发请求或弹出确认', async () => {
    const wrapper = mountCenter(); await flushPromises();
    await (wrapper.vm as any).$.setupState.operate({ code: 'demo' }, 'enable');
    expect(mocks.confirm).not.toHaveBeenCalled(); expect(mocks.enable).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('生命周期确认期间撤权后阻止请求', async () => {
    mocks.allowed = true; mocks.confirm.mockImplementation(async () => { mocks.allowed = false; });
    const wrapper = mountCenter(); await flushPromises();
    await (wrapper.vm as any).$.setupState.operate({ code: 'demo' }, 'enable');
    expect(mocks.enable).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('市场安装确认期间撤权后阻止请求', async () => {
    mocks.allowed = true; mocks.confirm.mockImplementation(async () => { mocks.allowed = false; });
    const wrapper = mountCenter(); await flushPromises();
    await (wrapper.vm as any).$.setupState.installMarket({ code: 'demo', versions: [{ version: '1.0', compatible: true }] });
    expect(mocks.installCloud).not.toHaveBeenCalled(); wrapper.unmount();
  });
});
