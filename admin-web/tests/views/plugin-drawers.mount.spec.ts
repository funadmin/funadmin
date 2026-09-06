import { defineComponent, h, nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PluginAccountDrawer from '@/views/system/plugin/components/PluginAccountDrawer.vue';
import PluginHistoryDrawer from '@/views/system/plugin/components/PluginHistoryDrawer.vue';

const api = vi.hoisted(() => ({
  currentAccount: vi.fn(), accountLogin: vi.fn(), accountRefresh: vi.fn(), accountLogout: vi.fn(),
  operations: vi.fn(), history: vi.fn(), recoveryInfo: vi.fn(), historyDownloadUrl: vi.fn(), redeployHistory: vi.fn()
}));
const dialogs = vi.hoisted(() => ({ confirm: vi.fn() }));
const messages = vi.hoisted(() => ({ success: vi.fn() }));
vi.mock('@/api/plugin', () => ({ pluginApi: api }));
vi.mock('element-plus', () => ({ ElMessage: messages, ElMessageBox: dialogs }));

const passthrough = defineComponent({ inheritAttrs: false, setup(_, { attrs, slots }) { return () => h('div', attrs, slots.default?.()); } });
const ElDrawer = defineComponent({
  inheritAttrs: false,
  props: { modelValue: Boolean, size: String },
  emits: ['update:modelValue', 'closed'],
  setup(props, { attrs, slots }) {
    return () => props.modelValue ? h('aside', { ...attrs, 'data-size': props.size }, [slots.header?.(), slots.default?.()]) : null;
  }
});
const ElAlert = defineComponent({ inheritAttrs: false, props: { title: String, description: String }, setup(props, { attrs }) { return () => h('div', attrs, [props.title, props.description]); } });
const ElForm = defineComponent({
  inheritAttrs: false,
  emits: ['submit'],
  setup(_, { attrs, slots, emit }) { return () => h('form', { ...attrs, onSubmit: (event: Event) => { event.preventDefault(); emit('submit', event); } }, slots.default?.()); }
});
const ElInput = defineComponent({
  inheritAttrs: false,
  props: { modelValue: String, type: String, placeholder: String, autocomplete: String },
  emits: ['update:modelValue'],
  setup(props, { attrs, emit, slots }) {
    return () => h('label', [slots.prefix?.(), h('input', {
      ...attrs,
      value: props.modelValue,
      type: props.type || 'text',
      placeholder: props.placeholder,
      autocomplete: props.autocomplete,
      onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value)
    })]);
  }
});
const ElButton = defineComponent({
  inheritAttrs: false,
  props: { disabled: Boolean, loading: Boolean }, emits: ['click'],
  setup(props, { attrs, slots, emit }) { return () => h('button', { ...attrs, disabled: props.disabled, 'data-loading': String(props.loading), onClick: () => emit('click') }, slots.default?.()); }
});
const ElTable = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.()); } });
const ElTableColumn = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.({ row: { id: 7, version: '1.0.0', downloadable: true } })); } });
const globals = {
  stubs: {
    ElDrawer, ElAlert, ElForm, ElFormItem: passthrough, ElInput, ElButton,
    ElCard: passthrough, ElAvatar: passthrough, ElTag: passthrough, ElSkeleton: passthrough,
    ElIcon: passthrough, ElDivider: passthrough, ElTable, ElTableColumn
  },
  directives: { perm: (element: HTMLElement, binding: { value: string }) => { element.dataset.permission = binding.value; }, loading: () => undefined }
};

beforeEach(() => {
  vi.clearAllMocks();
  dialogs.confirm.mockReset().mockResolvedValue(undefined);
  messages.success.mockReset();
  api.currentAccount.mockReset().mockResolvedValue({ id: 1, username: 'dev', nickname: '开发者', avatar: '' });
  api.accountLogin.mockReset();
  api.accountRefresh.mockReset().mockResolvedValue({ id: 1, username: 'dev', nickname: '已刷新', avatar: '' });
  api.accountLogout.mockReset().mockResolvedValue({ authenticated: false });
  api.operations.mockResolvedValue([]);
  api.history.mockResolvedValue([{ id: 7, version: '1.0.0', source: 'cloud', package_hash: 'a', signature_verified: true, downloadable: true, createdAt: '' }]);
  api.recoveryInfo.mockResolvedValue({ available: true, stage: 'migration', message: '请禁用插件后重试迁移' });
  api.historyDownloadUrl.mockReturnValue('/system/plugin/demo/history/7/download');
  api.redeployHistory.mockResolvedValue({});
});

function accountDrawer(modelValue = true) {
  return mount(PluginAccountDrawer, { props: { modelValue }, global: globals });
}

function input(wrapper: ReturnType<typeof accountDrawer>, name: 'account' | 'password') {
  return wrapper.get(`[data-testid="account-${name}"]`);
}

describe('插件抽屉 mount 行为', () => {
  it('未登录时展示云市场说明、安全提示和完整表单，并在输入无效时不调用登录 API', async () => {
    api.currentAccount.mockResolvedValue(null);
    const wrapper = accountDrawer();
    await flushPromises();

    expect(wrapper.get('aside').attributes('data-size')).toBe('min(520px, 100vw)');
    expect(wrapper.text()).toContain('连接云市场');
    expect(wrapper.text()).toContain('同步授权、版本信息与插件下载权限');
    expect(wrapper.text()).toContain('凭据仅用于换取令牌，不在浏览器长期保存');
    expect(input(wrapper, 'account').attributes('placeholder')).toBeTruthy();
    expect(input(wrapper, 'password').attributes('placeholder')).toBeTruthy();
    expect(input(wrapper, 'password').element.getAttribute('value')).toBe('');

    await input(wrapper, 'account').setValue('   ');
    await input(wrapper, 'password').setValue('12345');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(api.accountLogin).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('请输入云市场账号');
    expect(wrapper.text()).toContain('密码至少 6 位');
    expect(wrapper.get('[data-testid="account-submit"]').attributes('disabled')).toBeDefined();
  });

  it('登录前清理账号空白，成功后清除密码并通知包装层', async () => {
    api.currentAccount.mockResolvedValue(null);
    api.accountLogin.mockResolvedValue({ id: 2, username: 'market-dev', nickname: '市场开发者', avatar: '' });
    const wrapper = accountDrawer();
    await flushPromises();

    await input(wrapper, 'account').setValue('  market-dev  ');
    await input(wrapper, 'password').setValue('secret1');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(api.accountLogin).toHaveBeenCalledWith('market-dev', 'secret1');
    expect(wrapper.text()).toContain('市场开发者');
    expect(wrapper.emitted('changed')).toHaveLength(1);
    expect(messages.success).toHaveBeenCalledWith('云市场账号登录成功');
  });

  it('账号加载失败时结束骨架并展示错误而非空白内容', async () => {
    let rejectLoad!: (reason: Error) => void;
    api.currentAccount.mockReturnValue(new Promise((_, reject) => { rejectLoad = reject; }));
    const wrapper = accountDrawer();
    await nextTick();
    expect(wrapper.find('[data-testid="account-skeleton"]').exists()).toBe(true);

    rejectLoad(new Error('市场服务暂不可用'));
    await flushPromises();

    expect(wrapper.find('[data-testid="account-skeleton"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('市场服务暂不可用');
    expect(wrapper.text()).toContain('重新加载');
  });

  it('已登录时展示摘要和功能状态，以独立权限刷新令牌', async () => {
    const wrapper = accountDrawer();
    await flushPromises();

    expect(wrapper.text()).toContain('开发者');
    expect(wrapper.text()).toContain('@dev');
    expect(wrapper.text()).toContain('已连接');
    expect(wrapper.text()).toContain('版本检查');
    const refresh = wrapper.findAll('button').find((button) => button.text() === '刷新令牌');
    expect(refresh?.attributes('data-permission')).toBe('system:plugin:account-refresh');
    await refresh?.trigger('click');
    await flushPromises();
    expect(api.accountRefresh).toHaveBeenCalledOnce();
    expect(wrapper.text()).toContain('已刷新');
    expect(messages.success).toHaveBeenCalledWith('令牌刷新成功');
  });

  it('退出账号需要二次确认，取消不调用 API，确认后退出', async () => {
    dialogs.confirm.mockRejectedValueOnce('cancel');
    const wrapper = accountDrawer();
    await flushPromises();
    const logout = wrapper.findAll('button').find((button) => button.text() === '退出市场账号');

    await logout?.trigger('click');
    await flushPromises();
    expect(api.accountLogout).not.toHaveBeenCalled();

    dialogs.confirm.mockResolvedValueOnce(undefined);
    await logout?.trigger('click');
    await flushPromises();
    expect(api.accountLogout).toHaveBeenCalledOnce();
    expect(messages.success).toHaveBeenCalledWith('已退出云市场账号');
    expect(wrapper.text()).toContain('连接云市场');
  });

  it('刷新与退出使用独立 loading，且操作失败展示 alert', async () => {
    let rejectRefresh!: (reason: Error) => void;
    api.accountRefresh.mockReturnValue(new Promise((_, reject) => { rejectRefresh = reject; }));
    const wrapper = accountDrawer();
    await flushPromises();
    const refresh = wrapper.findAll('button').find((button) => button.text() === '刷新令牌')!;
    const logout = wrapper.findAll('button').find((button) => button.text() === '退出市场账号')!;

    await refresh.trigger('click');
    await nextTick();
    expect(refresh.attributes('data-loading')).toBe('true');
    expect(logout.attributes('data-loading')).toBe('false');
    expect(logout.attributes('disabled')).toBeUndefined();

    rejectRefresh(new Error('令牌已失效'));
    await flushPromises();
    expect(wrapper.text()).toContain('令牌已失效');
  });

  it('关闭抽屉会清除密码和错误信息', async () => {
    api.currentAccount.mockResolvedValue(null);
    api.accountLogin.mockRejectedValue(new Error('账号或密码错误'));
    const wrapper = accountDrawer();
    await flushPromises();
    await input(wrapper, 'account').setValue('dev');
    await input(wrapper, 'password').setValue('secret1');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(wrapper.text()).toContain('账号或密码错误');

    await wrapper.setProps({ modelValue: false });
    await nextTick();
    await wrapper.setProps({ modelValue: true });
    await flushPromises();

    expect(input(wrapper, 'password').element.getAttribute('value')).toBe('');
    expect(wrapper.text()).not.toContain('账号或密码错误');
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
