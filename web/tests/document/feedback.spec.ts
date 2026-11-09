import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

const api = vi.hoisted(() => ({ feedback: vi.fn() }));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/api', () => ({ documentApi: api }));
vi.mock('@/components/TablePagination/index.vue', () => ({ default: { template: '<div />' } }));

import Drawer from '../../../admin/plugins/document/web/src/admin/plugins/document/components/DocumentFeedbackDrawer.vue';
import { documentMessages } from '../../../admin/plugins/document/web/src/admin/plugins/document/locales';

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: documentMessages });
const stubs = {
  'el-drawer': { props: ['modelValue', 'title'], template: '<div v-if="modelValue"><h1>{{ title }}</h1><slot /></div>' },
  'el-radio-group': { template: '<div><slot /></div>' },
  'el-radio-button': { template: '<span><slot /></span>' },
  'el-checkbox': { template: '<label><slot /></label>' },
  'el-tag': { template: '<span class="tag"><slot /></span>' },
  'el-empty': { props: ['description'], template: '<p class="empty">{{ description }}</p>' }
};
const page = {
  list: [{ id: 1, documentId: 7, title: '快速开始', slug: 'quick', revisionNo: 2, helpful: false, reasons: ['outdated', 'broken'], comment: '第三步报错', updatedAt: '2026-11-09 10:00:00' }],
  total: 1, page: 1, pageSize: 20, summary: { helpful: 3, unhelpful: 1 }
};

const mountDrawer = (documentId: number | null) => {
  const wrapper = mount(Drawer, { props: { documentId, documentTitle: '快速开始', modelValue: false }, global: { plugins: [i18n], stubs, directives: { loading: () => {} } } });
  return wrapper;
};

describe('读者反馈抽屉', () => {
  it('打开时按当前文档加载，显示汇总、有帮助率、原因与说明', async () => {
    api.feedback.mockResolvedValue(page);
    const wrapper = mountDrawer(7);
    await wrapper.setProps({ modelValue: true });
    await flushPromises();
    expect(api.feedback).toHaveBeenLastCalledWith({ documentId: 7, helpful: '', withComment: false, page: 1, pageSize: 20 });
    const text = wrapper.text();
    expect(text).toContain('读者反馈 · 快速开始');
    expect(text).toContain('有帮助率 75%');
    expect(text).toContain('内容已过时');
    expect(text).toContain('示例无法运行');
    expect(text).toContain('第三步报错');
    expect(wrapper.find('.fb-doc').exists()).toBe(false);
  });

  it('查看全部反馈时不限定文档，点击文档标题发出打开事件', async () => {
    api.feedback.mockResolvedValue(page);
    const wrapper = mountDrawer(null);
    await wrapper.setProps({ modelValue: true });
    await flushPromises();
    expect(api.feedback.mock.lastCall?.[0]).not.toHaveProperty('documentId');
    await wrapper.find('.fb-doc').trigger('click');
    expect(wrapper.emitted('open')?.[0]).toEqual([7]);
  });

  it('没有反馈时显示空状态', async () => {
    api.feedback.mockResolvedValue({ ...page, list: [], total: 0, summary: { helpful: 0, unhelpful: 0 } });
    const wrapper = mountDrawer(7);
    await wrapper.setProps({ modelValue: true });
    await flushPromises();
    expect(wrapper.find('.empty').text()).toBe('暂无反馈');
    expect(wrapper.text()).not.toContain('有帮助率');
  });
});
