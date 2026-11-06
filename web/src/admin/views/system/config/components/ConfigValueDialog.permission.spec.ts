import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import ConfigValueDialog from './ConfigValueDialog.vue';

const api = vi.hoisted(() => ({ updateValue: vi.fn() }));
const user = reactive({ permissions: [] as string[] });
vi.mock('@/api/system/config', () => ({ configApi: api }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));
vi.mock('./ConfigValueEditor.vue', () => ({ default: { template: '<div />' } }));
beforeEach(() => { vi.clearAllMocks(); user.permissions = []; api.updateValue.mockResolvedValue(undefined); });
function render() {
  return mount(ConfigValueDialog, { props: { modelValue: true, row: { id: 2, code: 'site', value: 'old' } as any }, global: { stubs: { ElDialog: { template: '<div><slot /><slot name="footer" /></div>' }, ElForm: { template: '<div><slot /></div>' }, ElFormItem: { template: '<div><slot /></div>' }, ElButton: { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' } }, directives: { perm: {} } } });
}
describe('配置值保存权限', () => {
  it('查看或编辑定义权限不能提交配置值', async () => {
    user.permissions = ['system:config:list', 'system:config:edit'];
    const wrapper = render(); await wrapper.findAll('button')[1]!.trigger('click'); await flushPromises();
    expect(api.updateValue).not.toHaveBeenCalled(); expect(wrapper.emitted('success')).toBeUndefined(); wrapper.unmount();
  });
  it.each(['system:config:value', '*', '*:*:*'])('%s 可以提交配置值', async permission => {
    user.permissions = [permission]; const wrapper = render(); await wrapper.findAll('button')[1]!.trigger('click'); await flushPromises();
    expect(api.updateValue).toHaveBeenCalledExactlyOnceWith(2, 'old'); wrapper.unmount();
  });
  it('弹窗打开后撤销权限会阻止保存', async () => {
    user.permissions = ['system:config:value']; const wrapper = render(); user.permissions = [];
    await wrapper.findAll('button')[1]!.trigger('click'); await flushPromises(); expect(api.updateValue).not.toHaveBeenCalled(); wrapper.unmount();
  });
});
