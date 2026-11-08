import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { ElTabs, ElTabPane } from 'element-plus';
import { beforeEach, expect, it, vi } from 'vitest';
import SitePanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/SitePanel.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@/utils/http', () => ({ default: http }));

const pass = defineComponent({ setup(_, { slots }) { return () => h('div', slots.default?.()); } });
const button = defineComponent({ emits: ['click'], setup(_, { slots, emit }) { return () => h('button', { onClick: () => emit('click') }, slots.default?.()); } });
const input = defineComponent({ props: ['modelValue'], emits: ['update:modelValue'], setup(props, { emit }) { return () => h('input', { value: props.modelValue, onInput: (event: any) => emit('update:modelValue', event.target.value) }); } });
const stubs = {
  ...Object.fromEntries(['ElAlert', 'ElDescriptions', 'ElDescriptionsItem', 'ElForm', 'ElFormItem', 'ElSelect', 'ElOption', 'ElTag', 'ElEmpty', 'ElSwitch', 'ElInputNumber'].map(name => [name, pass])),
  ElButton: button, ElInput: input, ElDialog: true, ElPopconfirm: true, ElTable: true, ElTableColumn: true,
};
const fields = [
  { key: 'headline', label: '首页标题', type: 'text', group: '首页展示', localized: true, default: '' },
  { key: 'phone', label: '联系电话', type: 'text', group: '联系信息', localized: false, default: '' },
];
const configuration = { fields, values: { headline: '原标题', phone: '123' }, overrides: {}, effective: {}, locale: '' };
const settings: any = { site: { id: '7', name: '测试站点', default_locale: 'zh-cn', theme: 'default', status: 1 }, locales: [], domains: [], models: [] };
beforeEach(() => { vi.clearAllMocks(); http.get.mockResolvedValue(configuration); http.post.mockResolvedValue(configuration); });

it('separates site details, website configuration, languages and domains into actual selectable tabs', async () => {
  const wrapper = mount(SitePanel, { props: { settings, can: () => true, isSuper: true }, global: { components: { ElTabs, ElTabPane }, stubs, directives: { loading: () => {} } } });
  await flushPromises();
  for (const title of ['基本信息', '网站配置', '语言设置', '域名绑定']) expect(wrapper.findAll('[role="tab"]').some(tab => tab.text() === title)).toBe(true);
  expect(wrapper.get('#pane-site').isVisible()).toBe(true);
  await wrapper.get('#tab-domains').trigger('click');
  await flushPromises();
  expect(wrapper.get('#pane-domains').isVisible()).toBe(true);
  expect(wrapper.get('#pane-site').attributes('aria-hidden')).toBe('true');
  expect((wrapper.get('#pane-site').element as HTMLElement).style.display).toBe('none');
  expect(wrapper.get('#pane-domains').attributes('aria-hidden')).toBe('false');
  wrapper.unmount();
});

it('keeps edits across group and main tab changes and saves values from every group together', async () => {
  const wrapper = mount(SitePanel, { props: { settings, can: () => true, isSuper: true }, global: { components: { ElTabs, ElTabPane }, stubs, directives: { loading: () => {} } } });
  await flushPromises();
  await wrapper.get('#tab-configuration').trigger('click');
  await flushPromises();
  expect(wrapper.get('.configuration-fields').text()).toContain('首页标题');
  expect(wrapper.get('.configuration-fields').text()).not.toContain('联系电话');
  await wrapper.get('.configuration-fields input').setValue('编辑后的标题');
  await wrapper.findAll('[role="tab"]').find(tab => tab.text() === '联系信息')!.trigger('click');
  await flushPromises();
  await wrapper.get('.configuration-fields input').setValue('456');
  await wrapper.get('#tab-locales').trigger('click');
  await flushPromises();
  await wrapper.get('#tab-configuration').trigger('click');
  await flushPromises();
  expect((wrapper.get('.configuration-fields input').element as HTMLInputElement).value).toBe('456');
  await wrapper.findAll('button').find(node => node.text() === '保存站点配置')!.trigger('click');
  await flushPromises();
  expect(http.post).toHaveBeenCalledWith('/plugins/cms/configuration/save', { site_id: '7', values: { headline: '编辑后的标题', phone: '456' } });
  expect(http.get).toHaveBeenCalledTimes(1);
  wrapper.unmount();
});
