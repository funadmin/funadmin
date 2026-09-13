import { describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
const mocks = vi.hoisted(() => ({ push: vi.fn() }));
vi.mock('@/router', () => ({ default: { push: mocks.push } }));
vi.mock('@/router/pluginStartup', () => ({ loadPluginModulesSafely: vi.fn() }));
vi.mock('@/api/plugin', () => ({ pluginApi: { installed: async () => [], discovered: async () => [] } }));
import PluginCenter from './index.vue';

describe('插件中心业务开发入口', () => {
  it('复用统一创建页并通过 query 预选插件，生命周期忙碌时阻止入口', async () => {
    const wrapper = shallowMount(PluginCenter, { global: { directives: { perm: {}, loading: {} }, stubs: Object.fromEntries(['PageWrapper', 'ElButton', 'ElAlert', 'ElTabs', 'ElTabPane', 'ElTable', 'ElTableColumn'].map((name) => [name, true])) } });
    await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    expect(typeof state.developBusiness).toBe('function');
    state.developBusiness({ code: 'demo', state: 'discovered' });
    expect(mocks.push).toHaveBeenCalledWith({ path: '/development/business/visual', query: { plugin: 'demo' } });
    mocks.push.mockClear();
    state.developBusiness({ code: 'demo', state: 'recovering' });
    state.developBusiness({ code: 'demo', state: 'enabled', operation: 'update' });
    expect(mocks.push).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});
