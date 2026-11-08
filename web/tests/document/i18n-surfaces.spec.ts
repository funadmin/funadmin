import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('element-plus', () => ({ ElMessage: { success: vi.fn(), error: vi.fn() }, ElMessageBox: { confirm: vi.fn() } }));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/api', () => ({
  documentApi: {
    revisions: vi.fn().mockResolvedValue({ list: [{ revisionNo: 2, title: 't', changeSummary: '', createdBy: 1, createdAt: '2026-11-08 10:00:00' }], total: 1 }),
    revision: vi.fn().mockResolvedValue({ revisionNo: 2, title: 't', summary: '', changeSummary: '', createdAt: '', content: { type: 'doc', content: [] } })
  }
}));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/editor/DocumentPreview.vue', () => ({ default: { template: '<div />' } }));

import Drawer from '../../../admin/plugins/document/web/src/admin/plugins/document/components/DocumentVersionDrawer.vue';
import MetaDialog from '../../../admin/plugins/document/web/src/admin/plugins/document/components/DocumentMetaDialog.vue';
import { documentMessages } from '../../../admin/plugins/document/web/src/admin/plugins/document/locales';

const en = () => createI18n({ legacy: false, locale: 'en-US', messages: documentMessages });
const passthrough = (tag: string) => ({ props: ['label', 'title', 'description'], template: `<${tag}>{{ title }}{{ label }}{{ description }}<slot /><slot name="footer" /></${tag}>` });
const stubs = {
  'el-drawer': passthrough('section'), 'el-dialog': passthrough('section'), 'el-form': passthrough('form'), 'el-form-item': passthrough('label'),
  'el-button': passthrough('button'), 'el-empty': passthrough('div'), 'el-input': true, 'el-select': true, 'el-option': true
};
const chinese = /[一-龥]/;

describe('英文界面', () => {
  it('版本历史抽屉不出现中文', async () => {
    const wrapper = mount(Drawer, { props: { documentId: 1, canRestore: true, modelValue: true }, global: { stubs, plugins: [en()], directives: { loading: () => {} } } });
    await flushPromises();
    expect(wrapper.text()).toContain('Revision history');
    expect(wrapper.text()).toContain('Revision 2');
    expect(wrapper.text()).not.toMatch(chinese);
  });

  it('文档信息对话框不出现中文', async () => {
    const wrapper = mount(MetaDialog, { props: { modelValue: true, row: null, tree: [], defaultScope: 'framework', defaultCategoryId: null }, global: { stubs, plugins: [en()] } });
    await flushPromises();
    expect(wrapper.text()).toContain('New document');
    expect(wrapper.text()).not.toMatch(chinese);
  });
});
