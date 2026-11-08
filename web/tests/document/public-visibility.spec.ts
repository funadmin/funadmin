import { defineComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Dialog from '../../../admin/plugins/document/web/src/admin/plugins/document/components/DocumentMetaDialog.vue';
import { registerDocumentLocales } from '../../../admin/plugins/document/web/src/admin/plugins/document/locales';

const permissions = vi.hoisted(() => ({ publish: true }));
vi.mock('@/utils/permission', () => ({ hasPermission: () => permissions.publish }));
registerDocumentLocales();
const slot = defineComponent({ template: '<div><slot/><slot name="footer"/></div>' });
const form = defineComponent({ setup(_, { expose }) { expose({ validate: async () => true }); }, template: '<form><slot/></form>' });
const button = defineComponent({ template: '<button><slot/></button>' });
const select = defineComponent({ props: ['modelValue','disabled'], emits: ['update:modelValue'], template: '<select :disabled="disabled" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot/></select>' });
const option = defineComponent({ props: ['value','label'], template: '<option :value="value">{{ label }}</option>' });
const render = async (row: any) => {
  const wrapper = mount(Dialog, { props: { modelValue: false, row, tree: [], defaultScope: '', defaultCategoryId: null }, global: { stubs: { ElDialog: slot, ElForm: form, ElFormItem: slot, ElButton: button, ElInput: true, ElSelect: select, ElOption: option } } });
  await wrapper.setProps({ modelValue: true }); await flushPromises(); return wrapper;
};
describe('文档公开可见性', () => {
  it('新建文档明确默认公开，发布前仍不对外显示', async () => {
    permissions.publish = true;
    const w = await render(null);
    await w.findAll('button').find(b => b.text() === '确定')!.trigger('click'); await flushPromises();
    expect(w.emitted('submit')?.[0]?.[0]).toMatchObject({ visibility: 'public' });
    expect(w.text()).toContain('草稿不会对外展示'); w.unmount();
  });
  it('旧文档保持仅后台，缺少发布权限时不能切换已发布文档的可见性', async () => {
    permissions.publish = false;
    const w = await render({ id:1,title:'内部',slug:'private',summary:'',scope:'framework',type:'guide',pluginCode:'',appliesTo:'',categoryIds:[],status:'published',visibility:'admin' });
    const visibility = w.findAll('select').find(s => s.find('option[value="admin"]').exists())!;
    expect((visibility.element as HTMLSelectElement).value).toBe('admin');
    expect(visibility.attributes('disabled')).toBeDefined();
    await w.findAll('button').find(b => b.text() === '确定')!.trigger('click'); await flushPromises();
    expect(w.emitted('submit')?.[0]?.[0]).toMatchObject({ visibility: 'admin' }); w.unmount();
  });
});
