import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, KeepAlive, nextTick, reactive, ref } from 'vue';
import ElementPlus from 'element-plus';
import { createPinia, setActivePinia } from 'pinia';
import { i18n } from '@/locales';
import Dashboard from '@/views/dashboard/index.vue';
import { dashboardApi, type DashboardOverview } from '@/api/dashboard';
import { hooks } from '@/shared/plugins/hooks';

const stores = vi.hoisted(() => ({
  user: null as unknown as { permissions: string[]; nickname: string; avatar: string; userInfo: unknown },
  permission: null as unknown as { rawMenus: API.MenuItem[] }
}));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => stores.user }));
vi.mock('@/store/modules/permission', () => ({ usePermissionStore: () => stores.permission }));
vi.mock('@/api/dashboard', () => ({ dashboardApi: { overview: vi.fn() } }));
vi.mock('@/components/Echarts/index.vue', () => ({
  default: defineComponent({ name: 'EchartsStub', props: { option: { type: Object, default: () => ({}) } }, setup: () => () => h('div', { class: 'echarts-stub' }) })
}));

const menu = (id: number, path: string, name: string, permission?: string, extra: Partial<API.MenuItem> = {}): API.MenuItem => ({
  id, parentId: 0, routeName: `Menu${id}`, path, component: 'system/x/index', type: 'C', icon: 'i-ep-user', name, sort: id,
  hidden: false, keepAlive: false, affix: false, permission, ...extra
});
const MENUS: API.MenuItem[] = [
  {
    ...menu(1, '/system', '系统管理', undefined, { type: 'M', component: 'Layout' }),
    children: [
      menu(2, '/system/user', '管理员管理', 'authorization:admin:index'),
      menu(3, '/system/log/operation', '操作日志', 'system:operation-log:index'),
      menu(4, '/system/config', '配置管理', 'system:config:index'),
      menu(5, '/system/hidden', '隐藏页', undefined, { hidden: true }),
      menu(6, '/system/button', '按钮', undefined, { type: 'B' })
    ]
  }
];

const day = (offset: number) => {
  const date = new Date();
  date.setDate(date.getDate() - offset);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
const FULL: DashboardOverview = {
  generatedAt: '2026-11-12 10:00:00',
  admins: { total: 7 },
  members: { total: 1234, today: 5 },
  attachments: { total: 42, size: 3 * 1024 * 1024 },
  logs: {
    today: 9,
    trend: [6, 5, 4, 3, 2, 1, 0].map((offset, index) => ({ date: day(offset), count: index })),
    latest: [{ id: 11, username: 'root', name: '更新角色', action: 'update', method: 'PUT', url: '/admin/system/role/2', ip: '127.0.0.1', status: 1, createdAt: '2026-11-12 09:59:00' }]
  },
  plugins: { installed: 3, enabled: 2 }
};

const wrappers: VueWrapper[] = [];
async function mountDashboard(permissions: string[] = ['*'], menus: API.MenuItem[] = MENUS) {
  stores.user = reactive({ permissions, nickname: 'root', avatar: '', userInfo: { id: 1 } });
  stores.permission = reactive({ rawMenus: menus });
  const pinia = createPinia();
  setActivePinia(pinia);
  const wrapper = mount(Dashboard, {
    global: {
      plugins: [ElementPlus, pinia],
      stubs: { PluginSlot: true },
      mocks: { $router: { push: vi.fn() } }
    }
  });
  wrappers.push(wrapper);
  await flushPromises();
  return wrapper;
}
const cardKeys = (wrapper: VueWrapper) =>
  wrapper.findAll('[data-test^="dashboard-card-"]').map(card => card.attributes('data-test')!.replace('dashboard-card-', ''));
const shortcutTitles = (wrapper: VueWrapper) =>
  wrapper.find('[data-test="dashboard-shortcuts"]').exists()
    ? wrapper.find('[data-test="dashboard-shortcuts"]').findAll('.dashboard-shortcut span').map(item => item.text())
    : [];

beforeEach(() => {
  i18n.global.locale.value = 'zh-CN';
  vi.mocked(dashboardApi.overview).mockResolvedValue(structuredClone(FULL));
});
afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount());
  vi.clearAllMocks();
});

describe('仪表盘系统概览', () => {
  it('请求概览接口并渲染真实数据卡片、趋势图与最近日志', async () => {
    const wrapper = await mountDashboard();
    expect(dashboardApi.overview).toHaveBeenCalledTimes(1);
    expect(cardKeys(wrapper)).toEqual(['admins', 'members', 'attachments', 'logs', 'plugins']);
    const text = wrapper.text();
    expect(text).toContain('1,234');
    expect(text).toContain('今日新增 5');
    expect(text).toContain('占用 3.0 MB');
    expect(text).toContain('近 7 天 21');
    expect(text).toContain('已启用 2');
    expect(text).toContain('数据更新于 2026-11-12 10:00:00');
    const chart = wrapper.find('[data-test="dashboard-trend"]').findComponent({ name: 'EchartsStub' });
    const option = chart.props('option') as { xAxis: { data: string[] }; series: { data: number[] }[] };
    expect(option.series[0].data).toEqual([0, 1, 2, 3, 4, 5, 6]);
    expect(option.xAxis.data).toHaveLength(7);
    const latest = wrapper.find('[data-test="dashboard-latest"]');
    expect(latest.text()).toContain('更新角色');
    expect(latest.text()).toContain('PUT');
    // 不再出现写死的示例数据。
    expect(text).not.toMatch(/12,648|销售金额|热门商品|团队成员|张伟/);
  });

  it('接口省略的块不显示', async () => {
    vi.mocked(dashboardApi.overview).mockResolvedValue({ generatedAt: '2026-11-12 10:00:00', members: { total: 1, today: 0 } });
    const wrapper = await mountDashboard(['system:member:index'], []);
    expect(cardKeys(wrapper)).toEqual(['members']);
    expect(wrapper.find('[data-test="dashboard-trend"]').exists()).toBe(false);
    expect(wrapper.find('[data-test="dashboard-latest"]').exists()).toBe(false);
  });

  it('没有任何可见块与入口时显示空状态而不是空白页', async () => {
    vi.mocked(dashboardApi.overview).mockResolvedValue({ generatedAt: '2026-11-12 10:00:00' });
    const wrapper = await mountDashboard([], []);
    expect(cardKeys(wrapper)).toEqual([]);
    expect(wrapper.find('[data-test="dashboard-empty"]').exists()).toBe(true);
  });

  it('插件注册了概览卡片时不显示空状态', async () => {
    vi.mocked(dashboardApi.overview).mockResolvedValue({ generatedAt: '2026-11-12 10:00:00' });
    const components = vi.spyOn(hooks, 'components').mockImplementation(point => (point === 'dashboard.cards' ? [{}] : []) as ReturnType<typeof hooks.components>);
    try {
      const wrapper = await mountDashboard([], []);
      expect(cardKeys(wrapper)).toEqual([]);
      expect(wrapper.find('[data-test="dashboard-empty"]').exists()).toBe(false);
      expect(components).toHaveBeenCalledWith('dashboard.cards');
    } finally {
      components.mockRestore();
    }
  });

  it('数字按当前界面语言格式化', async () => {
    vi.mocked(dashboardApi.overview).mockResolvedValue({ ...structuredClone(FULL), members: { total: 1234567, today: 12345 } });
    i18n.global.locale.value = 'de-DE';
    const wrapper = await mountDashboard();
    expect(wrapper.find('[data-test="dashboard-card-members"]').text()).toContain('1.234.567');
    expect(wrapper.find('[data-test="dashboard-card-members"]').text()).toContain('12.345');
    expect(wrapper.find('[data-test="dashboard-card-attachments"]').text()).toContain('3,0 MB');
    i18n.global.locale.value = 'en-US';
    await nextTick();
    expect(wrapper.find('[data-test="dashboard-card-members"]').text()).toContain('1,234,567');
    expect(wrapper.find('[data-test="dashboard-card-attachments"]').text()).toContain('3.0 MB');
  });

  it('keep-alive 重新激活时刷新概览，首次挂载只请求一次', async () => {
    stores.user = reactive({ permissions: ['*'], nickname: 'root', avatar: '', userInfo: { id: 1 } });
    stores.permission = reactive({ rawMenus: MENUS });
    const pinia = createPinia();
    setActivePinia(pinia);
    const showDashboard = ref(true);
    const Other = defineComponent({ name: 'Other', setup: () => () => h('div', 'other') });
    const Host = defineComponent({ setup: () => () => h(KeepAlive, null, [showDashboard.value ? h(Dashboard) : h(Other)]) });
    const wrapper = mount(Host, { global: { plugins: [ElementPlus, pinia], stubs: { PluginSlot: true }, mocks: { $router: { push: vi.fn() } } } });
    wrappers.push(wrapper);
    await flushPromises();
    expect(dashboardApi.overview).toHaveBeenCalledTimes(1);
    showDashboard.value = false; await flushPromises();
    expect(dashboardApi.overview).toHaveBeenCalledTimes(1);
    vi.mocked(dashboardApi.overview).mockResolvedValue({ ...structuredClone(FULL), members: { total: 99, today: 1 } });
    showDashboard.value = true; await flushPromises();
    expect(dashboardApi.overview).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-test="dashboard-card-members"]').text()).toContain('99');
  });

  it('加载中显示骨架屏', async () => {
    let resolve!: (value: DashboardOverview) => void;
    vi.mocked(dashboardApi.overview).mockReturnValue(new Promise(done => { resolve = done; }));
    const wrapper = await mountDashboard();
    expect(wrapper.find('[data-test="dashboard-skeleton"]').exists()).toBe(true);
    expect(cardKeys(wrapper)).toEqual([]);
    resolve(structuredClone(FULL));
    await flushPromises();
    expect(wrapper.find('[data-test="dashboard-skeleton"]').exists()).toBe(false);
    expect(cardKeys(wrapper)).toHaveLength(5);
  });

  it('接口失败时提示错误并可重试', async () => {
    vi.mocked(dashboardApi.overview).mockRejectedValueOnce({ code: 500, msg: '服务器开小差了' });
    const wrapper = await mountDashboard();
    const alert = wrapper.find('[data-test="dashboard-error"]');
    expect(alert.exists()).toBe(true);
    expect(alert.text()).toContain('系统概览加载失败');
    expect(alert.text()).toContain('服务器开小差了');
    // 失败时页面仍保留欢迎区与快捷入口。
    expect(wrapper.find('.dashboard-hero').exists()).toBe(true);
    expect(shortcutTitles(wrapper).length).toBeGreaterThan(0);

    await alert.findAll('button').find(button => button.text() === '重试')!.trigger('click');
    await flushPromises();
    expect(dashboardApi.overview).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-test="dashboard-error"]').exists()).toBe(false);
    expect(cardKeys(wrapper)).toHaveLength(5);
  });

  it('快捷入口来自菜单，并按当前账号权限过滤', async () => {
    const all = await mountDashboard(['*']);
    expect(shortcutTitles(all)).toEqual(['管理员管理', '操作日志', '配置管理']);

    const limited = await mountDashboard(['system:operation-log:index']);
    expect(shortcutTitles(limited)).toEqual(['操作日志']);
  });

  it('英文界面使用英文文案', async () => {
    i18n.global.locale.value = 'en-US';
    const wrapper = await mountDashboard();
    expect(wrapper.text()).toContain('{count} new today'.replace('{count}', '5'));
    expect(wrapper.text()).toContain('Recent Operations');
    expect(wrapper.text()).toContain('Shortcuts');
  });
});
