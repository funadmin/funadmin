import { computed, defineComponent, h, inject, provide, type PropType } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PluginPage from '@/views/system/plugin/index.vue';

const api = vi.hoisted(() => ({
  installed: vi.fn(), discovered: vi.fn(), checkUpdates: vi.fn(), marketSearch: vi.fn(),
  installDiscovered: vi.fn(), installLocal: vi.fn(), updateLocal: vi.fn(), update: vi.fn(),
  migrate: vi.fn(), enable: vi.fn(), disable: vi.fn(), uninstall: vi.fn(), purge: vi.fn(),
  deletePackage: vi.fn(), installCloud: vi.fn()
}));
const dialogs = vi.hoisted(() => ({ confirm: vi.fn(), prompt: vi.fn(), alert: vi.fn() }));

vi.mock('@/api/plugin', () => ({ pluginApi: api }));
vi.mock('@/router', () => ({ default: {} }));
vi.mock('@/router/pluginStartup', () => ({ loadPluginModulesSafely: vi.fn() }));
vi.mock('element-plus', () => ({ ElMessageBox: dialogs }));

type Row = Record<string, unknown>;
const rowsKey = Symbol('rows');
const PageWrapper = defineComponent({ setup(_, { slots }) { return () => h('main', slots.default?.()); } });
const ElTable = defineComponent({
  props: { data: { type: Array as PropType<Row[]>, default: () => [] } },
  setup(props, { slots }) {
    provide(rowsKey, computed(() => props.data));
    return () => h('div', { class: 'table' }, slots.default?.());
  }
});
const ElTableColumn = defineComponent({
  props: { prop: String, label: String },
  setup(props, { slots }) {
    const rows = inject<{ value: Row[] }>(rowsKey, { value: [] });
    return () => h('div', { class: 'column', 'data-label': props.label }, rows.value.map((row) =>
      h('div', { class: 'cell', 'data-row': row.name }, slots.default?.({ row }) ?? String(row[props.prop || ''] ?? ''))
    ));
  }
});
const ElButton = defineComponent({
  inheritAttrs: false,
  props: { disabled: Boolean },
  emits: ['click'],
  setup(props, { attrs, slots, emit }) {
    return () => h('button', { ...attrs, disabled: props.disabled, onClick: () => emit('click') }, slots.default?.());
  }
});
const ElTabs = defineComponent({
  props: { modelValue: String }, emits: ['update:modelValue', 'tab-change'],
  setup(_, { slots, emit }) { return () => h('nav', [slots.default?.(), h('button', { 'data-tab': 'local', onClick: () => { emit('update:modelValue', 'local'); emit('tab-change', 'local'); } }, '本地包')]); }
});
const passthrough = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.()); } });
const ElAlert = defineComponent({ props: { title: String }, setup(props) { return () => h('div', { role: 'alert' }, props.title); } });

const plugin = (overrides: Partial<Row> = {}) => ({
  name: 'demo', title: 'Demo', version: '1.0.0', latestVersion: '', dbVersion: '001',
  state: 'disabled', dependencies: {}, migrationPending: false, lastError: '', source: 'installed',
  needsReinstall: false, operation: '', progress: 0, disabledReason: '', ...overrides
});

const mountPage = (permissions: string[] = []) => mount(PluginPage, {
  global: {
    stubs: {
      PageWrapper, ElTable, ElTableColumn, ElButton, ElTabs,
      ElTabPane: passthrough, ElTag: passthrough, ElInput: passthrough, ElAlert,
      Account: true, Market: true, ConfigDialog: true, LifecycleDrawer: true, InstallDialog: true,
      ElUpload: passthrough, ElDialog: passthrough
    },
    directives: {
      perm: (element, binding) => { if (!permissions.includes(binding.value)) element.style.display = 'none'; },
      loading: () => undefined
    }
  }
});

const visibleButton = (wrapper: ReturnType<typeof mount>, text: string) =>
  wrapper.findAll('button').find((button) => button.text() === text && button.attributes('style') !== 'display: none;');

beforeEach(() => {
  vi.clearAllMocks();
  api.installed.mockResolvedValue([plugin()]);
  api.discovered.mockResolvedValue([plugin({ source: 'local', state: 'discovered' })]);
  api.checkUpdates.mockResolvedValue([]);
  api.marketSearch.mockResolvedValue({ list: [], total: 0 });
  dialogs.confirm.mockResolvedValue(undefined);
  dialogs.prompt.mockResolvedValue({ value: 'demo' });
});

describe('插件中心页面 mount 行为', () => {
  it('发现插件确认后调用真实安装 API，取消时不调用', async () => {
    const wrapper = mountPage(['system:plugin:discovered-install']);
    await flushPromises();
    await wrapper.get('[data-tab="local"]').trigger('click');
    await flushPromises();

    dialogs.confirm.mockRejectedValueOnce('cancel');
    await visibleButton(wrapper, '安装')?.trigger('click');
    await flushPromises();
    expect(api.installDiscovered).not.toHaveBeenCalled();

    dialogs.confirm.mockResolvedValueOnce(undefined);
    await visibleButton(wrapper, '安装')?.trigger('click');
    await flushPromises();
    expect(api.installDiscovered).toHaveBeenCalledWith('demo');
  });

  it('清除数据要求精确插件名并与卸载保持独立', async () => {
    const wrapper = mountPage(['system:plugin:purge', 'system:plugin:uninstall']);
    await flushPromises();

    dialogs.prompt.mockResolvedValueOnce({ value: 'other' });
    await visibleButton(wrapper, '清除数据')?.trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('彻底清理数据时必须输入插件名称 demo');
    expect(api.purge).not.toHaveBeenCalled();

    dialogs.prompt.mockResolvedValueOnce({ value: 'demo' });
    await visibleButton(wrapper, '清除数据')?.trigger('click');
    await flushPromises();
    expect(api.purge).toHaveBeenCalledWith('demo', 'demo');
    expect(api.uninstall).not.toHaveBeenCalled();
  });

  it('按权限隐藏写操作，并按生命周期与进行中操作禁用按钮且展示原因', async () => {
    api.installed.mockResolvedValue([plugin({ operation: 'update', progress: 45, disabledReason: '插件正在执行 update（45%）' })]);
    api.checkUpdates.mockResolvedValue([{ name: 'demo', installedVersion: '1.0.0', latestVersion: '2.0.0', updateAvailable: true }]);
    const wrapper = mountPage(['system:plugin:update']);
    await flushPromises();

    const update = visibleButton(wrapper, '更新');
    expect(update?.attributes('disabled')).toBeDefined();
    expect(update?.attributes('title')).toContain('45%');
    expect(visibleButton(wrapper, '卸载')).toBeUndefined();
  });

  it('刷新按钮重新加载列表并展示后端错误', async () => {
    const wrapper = mountPage();
    await flushPromises();
    expect(api.installed).toHaveBeenCalledTimes(1);

    api.installed.mockRejectedValueOnce(new Error('加载失败'));
    await visibleButton(wrapper, '刷新')?.trigger('click');
    await flushPromises();
    expect(api.installed).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).toContain('加载失败');
  });
});
