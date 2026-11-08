import { describe, expect, it, vi, beforeEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

const api = vi.hoisted(() => ({ createCategory: vi.fn(), updateCategory: vi.fn(), deleteCategory: vi.fn() }));
const msg = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), confirm: vi.fn() }));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/api', () => ({ documentApi: api }));
vi.mock('element-plus', () => ({ ElMessage: { success: msg.success, error: msg.error }, ElMessageBox: { confirm: msg.confirm } }));

import Dialog from '../../../admin/plugins/document/web/src/admin/plugins/document/components/DocumentCategoryDialog.vue';
import { documentMessages } from '../../../admin/plugins/document/web/src/admin/plugins/document/locales';
import type { DocumentCategory } from '../../../admin/plugins/document/web/src/admin/plugins/document/types';

const tree: DocumentCategory[] = [
  { id: 1, scope: 'framework', parentId: 0, name: '主框架', slug: 'framework', sortOrder: 10, children: [{ id: 2, scope: 'framework', parentId: 1, name: '架构', slug: 'arch', sortOrder: 10, children: [] }] }
] as DocumentCategory[];
const stubs = {
  'el-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
  'el-button': { emits: ['click'], template: '<button @click="$emit(\'click\', $event)"><slot /></button>' },
  'el-input': { props: ['modelValue'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
  'el-input-number': true, 'el-select': true, 'el-option': true, 'el-tree-select': true, 'el-form': { template: '<form><slot /></form>' }, 'el-form-item': { template: '<label><slot /></label>' },
  'el-tree': { props: ['data'], template: '<ul><li v-for="n in data" :key="n.id"><slot :data="n" /><ul><li v-for="c in n.children" :key="c.id"><slot :data="c" /></li></ul></li></ul>' }
};
const mountDialog = () => mount(Dialog, { props: { modelValue: true, tree }, global: { stubs, plugins: [createI18n({ legacy: false, locale: 'zh-CN', messages: documentMessages })] } });

describe('目录管理', () => {
  beforeEach(() => Object.values({ ...api, ...msg }).forEach((fn) => fn.mockReset()));

  it('在选中的目录下新建子目录，成功后通知页面刷新', async () => {
    api.createCategory.mockResolvedValue({ id: 9 });
    const wrapper = mountDialog();
    await wrapper.find('[data-category-add="1"]').trigger('click');
    const inputs = wrapper.findAll('input');
    await inputs[0].setValue('数据库与迁移');
    await inputs[1].setValue('framework-database');
    await wrapper.find('[data-testid="category-save"]').trigger('click');
    await flushPromises();
    expect(api.createCategory).toHaveBeenCalledWith(expect.objectContaining({ scope: 'framework', parentId: 1, name: '数据库与迁移', slug: 'framework-database' }));
    expect(wrapper.emitted('changed')).toBeTruthy();
  });

  it('删除非空目录时显示服务端原因，不通知刷新', async () => {
    msg.confirm.mockResolvedValue(true);
    api.deleteCategory.mockRejectedValue({ code: 409, msg: '目录下还有子目录或文档，请先移走后再删除' });
    const wrapper = mountDialog();
    await wrapper.find('[data-category-delete="1"]').trigger('click');
    await flushPromises();
    expect(msg.error).toHaveBeenCalledWith('目录下还有子目录或文档，请先移走后再删除');
    expect(wrapper.emitted('changed')).toBeFalsy();
  });

  it('编辑目录时提交改名', async () => {
    api.updateCategory.mockResolvedValue({ id: 2 });
    const wrapper = mountDialog();
    await wrapper.find('[data-category-edit="2"]').trigger('click');
    await wrapper.findAll('input')[0].setValue('架构设计');
    await wrapper.find('[data-testid="category-save"]').trigger('click');
    await flushPromises();
    expect(api.updateCategory).toHaveBeenCalledWith(2, expect.objectContaining({ name: '架构设计' }));
  });
});
