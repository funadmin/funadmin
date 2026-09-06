import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PluginAccountDrawer from '@/views/system/plugin/components/PluginAccountDrawer.vue';
import PluginHistoryDrawer from '@/views/system/plugin/components/PluginHistoryDrawer.vue';

const api = vi.hoisted(() => ({
  currentAccount: vi.fn(), accountLogin: vi.fn(), accountRefresh: vi.fn(), accountLogout: vi.fn(),
  operations: vi.fn(), history: vi.fn(), recoveryInfo: vi.fn(), historyDownloadUrl: vi.fn(), redeployHistory: vi.fn()
}));
const dialogs = vi.hoisted(() => ({ confirm: vi.fn() }));
vi.mock('@/api/plugin', () => ({ pluginApi: api }));
vi.mock('element-plus', () => ({ ElMessageBox: dialogs }));

const passthrough = defineComponent({ inheritAttrs: false, setup(_, { attrs, slots }) { return () => h('div', attrs, slots.default?.()); } });
const ElAlert = defineComponent({ inheritAttrs: false, props: { title: String, description: String }, setup(props, { attrs }) { return () => h('div', attrs, [props.title, props.description]); } });
const ElButton = defineComponent({
  inheritAttrs: false,
  props: { disabled: Boolean }, emits: ['click'],
  setup(props, { attrs, slots, emit }) { return () => h('button', { ...attrs, disabled: props.disabled, onClick: () => emit('click') }, slots.default?.()); }
});
const ElTable = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.()); } });
const ElTableColumn = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.({ row: { id: 7, version: '1.0.0', downloadable: true } })); } });
const globals = {
  stubs: {
    ElDrawer: passthrough, ElAlert, ElForm: passthrough, ElFormItem: passthrough,
    ElInput: passthrough, ElButton, ElTable, ElTableColumn
  },
  directives: { perm: (element: HTMLElement, binding: { value: string }) => { element.dataset.permission = binding.value; }, loading: () => undefined }
};

beforeEach(() => {
  vi.clearAllMocks();
  api.currentAccount.mockResolvedValue({ id: 1, username: 'dev', nickname: '开发者', avatar: '' });
  api.accountRefresh.mockResolvedValue({ id: 1, username: 'dev', nickname: '已刷新', avatar: '' });
  api.operations.mockResolvedValue([]);
  api.history.mockResolvedValue([{ id: 7, version: '1.0.0', source: 'cloud', package_hash: 'a', signature_verified: true, downloadable: true, createdAt: '' }]);
  api.recoveryInfo.mockResolvedValue({ available: true, stage: 'migration', message: '请禁用插件后重试迁移' });
  api.historyDownloadUrl.mockReturnValue('/system/plugin/demo/history/7/download');
  api.redeployHistory.mockResolvedValue({});
  dialogs.confirm.mockResolvedValue(undefined);
});

describe('插件抽屉 mount 行为', () => {
  it('账号抽屉以独立权限刷新 token 并更新账号展示', async () => {
    const wrapper = mount(PluginAccountDrawer, { props: { modelValue: true }, global: globals });
    await flushPromises();

    const refresh = wrapper.findAll('button').find((button) => button.text() === '刷新令牌');
    expect(refresh?.attributes('data-permission')).toBe('system:plugin:account-refresh');
    await refresh?.trigger('click');
    await flushPromises();
    expect(api.accountRefresh).toHaveBeenCalledOnce();
    expect(wrapper.text()).toContain('已刷新');
  });

  it('历史抽屉加载版本与恢复指引并提供鉴权下载和重部署', async () => {
    const wrapper = mount(PluginHistoryDrawer, { props: { modelValue: true, name: 'demo', redeployDisabledReason: '' }, global: globals });
    await flushPromises();

    expect(api.history).toHaveBeenCalledWith('demo');
    expect(api.recoveryInfo).toHaveBeenCalledWith('demo');
    expect(wrapper.text()).toContain('请禁用插件后重试迁移');
    expect(wrapper.find('a').attributes('href')).toBe('/system/plugin/demo/history/7/download');
    expect(wrapper.find('a').attributes('data-permission')).toBe('system:plugin:history-download');

    const redeploy = wrapper.findAll('button').find((button) => button.text() === '重部署');
    expect(redeploy?.attributes('data-permission')).toBe('system:plugin:history-redeploy');
    await redeploy?.trigger('click');
    await flushPromises();
    expect(api.redeployHistory).toHaveBeenCalledWith('demo', 7, false);
  });

  it('历史重部署按生命周期门禁禁用并展示原因', async () => {
    const wrapper = mount(PluginHistoryDrawer, {
      props: { modelValue: true, name: 'demo', redeployDisabledReason: '插件正在执行 update（35%）' },
      global: globals
    });
    await flushPromises();

    const redeploy = wrapper.findAll('button').find((button) => button.text() === '重部署');
    expect(redeploy?.attributes('disabled')).toBeDefined();
    expect(redeploy?.attributes('title')).toBe('插件正在执行 update（35%）');
    await redeploy?.trigger('click');
    expect(api.redeployHistory).not.toHaveBeenCalled();
  });
});
