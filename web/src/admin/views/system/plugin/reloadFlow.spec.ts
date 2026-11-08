import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
const mocks = vi.hoisted(() => ({ confirm: vi.fn(), success: vi.fn(), warning: vi.fn(), enable: vi.fn(), uninstall: vi.fn(), migrate: vi.fn(), syncRoutes: vi.fn(), installed: vi.fn() }));
vi.mock('@/utils/permission', () => ({ hasPermission: () => true }));
vi.mock('element-plus', () => ({ ElMessageBox: { confirm: mocks.confirm }, ElMessage: { success: mocks.success, warning: mocks.warning } }));
vi.mock('@/router', () => ({ default: { push: vi.fn() } }));
vi.mock('@/router/pluginStartup', () => ({ loadPluginModulesSafely: mocks.syncRoutes }));
vi.mock('@/api/plugin', () => ({ pluginApi: { installed: mocks.installed, checkUpdates: async () => [], enable: mocks.enable, uninstall: mocks.uninstall, migrate: mocks.migrate } }));
import PluginCenter from './index.vue';
const mountCenter = () => shallowMount(PluginCenter, { global: { directives: { perm: {}, loading: {} }, stubs: Object.fromEntries(['PageWrapper','ElButton','ElAlert','ElTabs','ElTabPane','ElTable','ElTableColumn'].map(name => [name, true])) } });

describe('插件中心生命周期后的后端重载', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.confirm.mockResolvedValue(undefined);
    mocks.installed.mockResolvedValue([{ code: 'demo' }]);
  });
  afterEach(() => { vi.useRealTimers(); });

  it('启用后后端已请求重载：提示稍后可用，约 1.5 秒后才刷新插件路由', async () => {
    const wrapper = mountCenter(); await flushPromises();
    vi.useFakeTimers();
    mocks.enable.mockResolvedValue({ enabled: true, reloadRequested: true, reloadReason: null });
    const done = (wrapper.vm as any).$.setupState.operate({ code: 'demo' }, 'enable');
    await vi.advanceTimersByTimeAsync(0);
    expect(mocks.success).toHaveBeenCalledWith('后端已重新加载，新页面稍后可用');
    expect(mocks.syncRoutes).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(1499);
    expect(mocks.syncRoutes).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(1);
    await done;
    expect(mocks.syncRoutes).toHaveBeenCalledOnce();
    wrapper.unmount();
  });

  it('卸载后后端未能重载：提示手动重启并立即刷新', async () => {
    const wrapper = mountCenter(); await flushPromises();
    mocks.uninstall.mockResolvedValue({ uninstalled: true, reloadRequested: false, reloadReason: 'pid_file_missing' });
    await (wrapper.vm as any).$.setupState.uninstall({ code: 'demo' });
    expect(mocks.warning).toHaveBeenCalledWith('请重启后端服务使新接口生效（原因：pid_file_missing）');
    expect(mocks.success).not.toHaveBeenCalled();
    expect(mocks.syncRoutes).toHaveBeenCalledOnce();
    wrapper.unmount();
  });

  it('迁移等不改变路由的操作不提示重载', async () => {
    const wrapper = mountCenter(); await flushPromises();
    mocks.migrate.mockResolvedValue({ version: '2' });
    await (wrapper.vm as any).$.setupState.operate({ code: 'demo' }, 'migrate');
    expect(mocks.success).not.toHaveBeenCalled();
    expect(mocks.warning).not.toHaveBeenCalled();
    expect(mocks.syncRoutes).toHaveBeenCalledOnce();
    wrapper.unmount();
  });
});
