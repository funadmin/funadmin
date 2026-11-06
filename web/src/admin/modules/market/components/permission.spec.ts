import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
const mocks = vi.hoisted(() => ({ allowed: false, confirm: vi.fn(), save: vi.fn(), remove: vi.fn(), categories: vi.fn(async () => []) }));
vi.mock('@/utils/permission', () => ({ hasPermission: () => mocks.allowed }));
vi.mock('element-plus', () => ({ ElMessageBox: { confirm: mocks.confirm } }));
vi.mock('../api', () => ({ marketApi: { categories: mocks.categories, createCategory: mocks.save, deleteCategory: mocks.remove } }));
import CategoriesPanel from './CategoriesPanel.vue';
const mountPanel = () => shallowMount(CategoriesPanel, { props: { canManage: true }, global: { directives: { perm: {}, loading: {} }, stubs: Object.fromEntries(['ElButton', 'ElTable', 'ElTableColumn', 'ElTag', 'ElInput', 'ElFormItem', 'ElInputNumber', 'ElSwitch', 'ElForm', 'ElDialog'].map(name => [name, true])) } });
describe('市场分类提交权限', () => {
  beforeEach(() => { mocks.allowed = false; vi.clearAllMocks(); });
  it('直接调用保存入口也不能越权', async () => {
    const wrapper = mountPanel(); await flushPromises();
    await (wrapper.vm as any).$.setupState.save();
    expect(mocks.save).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('确认期间撤销权限后不发送删除请求', async () => {
    mocks.allowed = true; mocks.confirm.mockImplementation(async () => { mocks.allowed = false; });
    const wrapper = mountPanel(); await flushPromises();
    await (wrapper.vm as any).$.setupState.remove({ id: 1, name: '分类' });
    expect(mocks.remove).not.toHaveBeenCalled(); wrapper.unmount();
  });
});
