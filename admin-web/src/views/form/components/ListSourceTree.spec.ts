import { defineComponent } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Tree from './ListSourceTree.vue';
import ElementPlus from 'element-plus';
vi.mock('@/api/formData', () => ({ formDataApi: {} }));
vi.mock('./SchemaRenderer.vue', () => ({ default: defineComponent({ props: ['values'], setup(_, { expose }) { expose({ submit: async () => {} }); }, template: '<input :value="values.title" @input="values.title = $event.target.value" />' }) }));
const button = defineComponent({ props: ['disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' });
const box = defineComponent({ template: '<div><slot /><slot name="footer" /></div>' });
function render(parent = '', grants = true) {
  const api = { leftTree: vi.fn().mockResolvedValue({ nodes: [{ id: 1, value: 1, label: '分类' }], schemaHash: 'source-hash', actions: { create: true, addChild: true, edit: true, delete: true } }), leftTreeForm: vi.fn().mockResolvedValue({ meta: { form: { form_key: 'categories' }, fields: [{ field_name: 'title', type: 'input' }], schema: { nodes: [] }, schemaHash: 'source-hash' }, row: { title: '旧分类' } }), mutateLeftTree: vi.fn().mockResolvedValue({}) };
  const wrapper = mount(Tree, { props: { formKey: 'orders', schemaHash: 'target-hash', config: { enabled: true, source: { type: 'module', module: 'categories' }, mapping: { valueField: 'id', labelField: 'title', targetField: 'category_id', parentField: parent } }, canReadForm: grants, canMutate: grants, api }, global: { plugins: [ElementPlus], stubs: { ElButton: button, ElDialog: box, ElAlert: true, ElTree: defineComponent({ props: ['data'], setup(_, { expose }) { expose({ setCheckedKeys() {}, setCurrentKey() {} }); }, template: '<div><slot v-for="node in data" :data="node" /></div>' }) } } });
  return { wrapper, api };
}
describe('来源分类动作与来源表单', () => {
  it('分类节点上下文仅传身份、空 ids 和双版本，成功效果由宿主处理', async () => {
    const { wrapper, api } = render('parent_id'); await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    expect(state.buttonContext('categoryNode', { id: 1, secret: '不发送' })).toEqual({ formKey: 'orders', schemaHash: 'target-hash', location: 'categoryNode', ids: [], category: { id: 1 }, sourceSchemaHash: 'source-hash', sourceKey: undefined });
    state.clearSelection(); expect(wrapper.emitted('change')!.at(-1)).toEqual([[]]);
    await state.refreshButtonHost(); expect(api.leftTree).toHaveBeenCalledTimes(2); expect(wrapper.emitted('mutated')).toHaveLength(1);
    wrapper.unmount();
  });
  it('弹窗打开后关闭动作开关或撤销按钮权限，保存重新核验', async () => {
    const { wrapper, api } = render(); await flushPromises();
    const state = (wrapper.vm as any).$.setupState;
    await state.open('edit', 1);
    await wrapper.setProps({ config: { ...wrapper.props('config'), actions: { edit: false } } });
    await state.save(); expect(api.mutateLeftTree).not.toHaveBeenCalled();
    await wrapper.setProps({ config: { ...wrapper.props('config'), actions: { edit: true } }, list: { buttons: { categoryNode: [{ id: 'edit', label: '编辑', permission: 'source:edit', action: { type: 'builtin', key: 'edit' } }] } }, permissionCheck: () => false });
    await state.save(); expect(api.mutateLeftTree).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('即使旧响应允许子级，无父级映射也不显示或请求子级动作', async () => { const { wrapper, api } = render(); await flushPromises(); expect(wrapper.text()).not.toContain('加子级'); await (wrapper.vm as any).$.setupState.open('addChild', 1); expect(api.leftTreeForm).not.toHaveBeenCalled(); wrapper.unmount(); });
  it('分类按钮显式空集合隐藏所有管理入口', async () => { const { wrapper } = render('parent_id'); await wrapper.setProps({ list: { buttons: { categoryToolbar: [], categoryNode: [] } } } as never); await flushPromises(); for (const label of ['新增', '加子级', '编辑', '删除']) expect(wrapper.findAll('button').some(button => button.text() === label)).toBe(false); wrapper.unmount(); });
  it('入口无权限时不显示四个写操作', async () => { const { wrapper } = render('parent_id', false); await flushPromises(); for (const label of ['新增', '加子级', '编辑', '删除']) expect(wrapper.findAll('button').some(button => button.text() === label)).toBe(false); wrapper.unmount(); });
  it('来源表单编辑值保存到既有接口并携带双 hash，成功刷新来源和右表', async () => { const { wrapper, api } = render('parent_id'); await flushPromises(); await (wrapper.vm as any).$.setupState.open('edit', 1); await flushPromises(); await wrapper.get('input').setValue('更新分类'); await (wrapper.vm as any).$.setupState.save(); expect(api.mutateLeftTree).toHaveBeenCalledWith('orders', 'edit', 1, { title: '更新分类' }, 'target-hash', 'source-hash'); expect(wrapper.emitted('mutated')).toHaveLength(1); expect(api.leftTree).toHaveBeenCalledTimes(2); wrapper.unmount(); });
});
