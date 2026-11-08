import { defineComponent } from 'vue';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';

const mocks = vi.hoisted(() => ({
  permissions: [] as string[],
  query: {} as Record<string, string>,
  replace: vi.fn(),
  push: vi.fn()
}));

vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));
vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/development/business/legacy', query: mocks.query }),
  useRouter: () => ({ replace: mocks.replace, push: mocks.push }),
  onBeforeRouteLeave: vi.fn()
}));
vi.mock('../visual.vue', async () => {
  const { defineComponent: define } = await import('vue');
  return { default: define({ name: 'BusinessVisual', props: ['initialMode'], template: '<main data-visual :data-mode="initialMode">创建业务</main>' }) };
});
vi.mock('./RecordsPanel.vue', async () => {
  const { defineComponent: define } = await import('vue');
  return { default: define({ name: 'RecordsPanel', props: ['moduleId', 'embedded'], emits: ['subtitle-change'], template: '<section data-records-panel>records</section>' }) };
});

import DatabasePage from '../database.vue';
import RecordsPage from '../records.vue';

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const PageWrapper = defineComponent({ props: ['title', 'subtitle'], template: '<div data-page><h1>{{ title }}</h1><slot /></div>' });
const render = (component: typeof DatabasePage) => mount(component, { global: { plugins: [i18n], stubs: { PageWrapper } } });
// database.vue 内嵌的创建页在上面被替换为桩；旧「可视化创建」路由本身直接渲染真实的 visual.vue。
const loadVisualPage = async () => (await vi.importActual<typeof import('../visual.vue')>('../visual.vue')).default as typeof DatabasePage;

enableAutoUnmount(afterEach);

beforeEach(() => {
  vi.clearAllMocks();
  mocks.permissions = [];
  mocks.query = {};
});

describe('旧路由合并到代码生成页', () => {
  it('有 development:business:modules 时，从数据库生成跳到代码生成页并打开装载弹窗（保留 query）', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:inspect-database'];
    mocks.query = { plugin: 'demo' };
    const wrapper = render(DatabasePage); await flushPromises();
    expect(mocks.replace).toHaveBeenCalledOnce();
    expect(mocks.replace).toHaveBeenCalledWith({ path: '/admin/development/business/mine', query: { plugin: 'demo', load: '1' } });
    expect(wrapper.find('[data-visual]').exists()).toBe(false);
  });

  it('有 development:business:modules 时，生成记录跳到代码生成页的记录页签并保留 moduleId', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:generations'];
    mocks.query = { moduleId: '3', status: 'failed' };
    const wrapper = render(RecordsPage); await flushPromises();
    expect(mocks.replace).toHaveBeenCalledWith({ path: '/admin/development/business/mine', query: { moduleId: '3', status: 'failed', tab: 'records' } });
    expect(wrapper.find('[data-records-panel]').exists()).toBe(false);
  });

  it('无 development:business:modules 时，从数据库生成仍渲染原有的已有表创建页', async () => {
    mocks.permissions = ['development:business:inspect-database'];
    const wrapper = render(DatabasePage); await flushPromises();
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(wrapper.get('[data-visual]').attributes('data-mode')).toBe('adopted');
  });

  it('无 development:business:modules 时，生成记录仍渲染原页面', async () => {
    mocks.permissions = ['development:business:generations'];
    mocks.query = { moduleId: '3' };
    const wrapper = render(RecordsPage); await flushPromises();
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(wrapper.get('h1').text()).toBe('生成记录');
    expect(wrapper.find('[data-records-panel]').exists()).toBe(true);
  });

  it('有 development:business:modules 时，可视化创建跳到代码生成页并打开新建表弹窗（保留 query）', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:create-visual'];
    mocks.query = { plugin: 'demo' };
    const wrapper = render(await loadVisualPage()); await flushPromises();
    expect(mocks.replace).toHaveBeenCalledOnce();
    expect(mocks.replace).toHaveBeenCalledWith({ path: '/admin/development/business/mine', query: { plugin: 'demo', create: '1' } });
    expect(wrapper.find('[data-page]').exists()).toBe(false);
  });

  it('可视化创建旧链接带 mode=adopted 时改为打开装载数据表弹窗', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:create-from-database'];
    mocks.query = { mode: 'adopted' };
    render(await loadVisualPage()); await flushPromises();
    expect(mocks.replace).toHaveBeenCalledWith({ path: '/admin/development/business/mine', query: { mode: 'adopted', load: '1' } });
  });

  it('可视化创建：只有 create-from-database 没有 create-visual 时改为打开装载数据表弹窗', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:create-from-database'];
    mocks.query = { plugin: 'demo' };
    render(await loadVisualPage()); await flushPromises();
    expect(mocks.replace).toHaveBeenCalledWith({ path: '/admin/development/business/mine', query: { plugin: 'demo', load: '1' } });
  });

  it('可视化创建 mode=adopted：只有 create-visual 没有 create-from-database 时改为打开新建表弹窗', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:create-visual'];
    mocks.query = { mode: 'adopted' };
    render(await loadVisualPage()); await flushPromises();
    expect(mocks.replace).toHaveBeenCalledWith({ path: '/admin/development/business/mine', query: { mode: 'adopted', create: '1' } });
  });

  it('可视化创建：两种创建权限都没有时不跳转，仍渲染原页面', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:inspect-database'];
    const wrapper = render(await loadVisualPage()); await flushPromises();
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(wrapper.get('h1').text()).toBe('创建业务');
  });

  it('无 development:business:modules 时，可视化创建仍渲染原页面', async () => {
    mocks.permissions = ['development:business:create-visual'];
    const wrapper = render(await loadVisualPage()); await flushPromises();
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(wrapper.get('h1').text()).toBe('创建业务');
  });
});
