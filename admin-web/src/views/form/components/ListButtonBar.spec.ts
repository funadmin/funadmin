import { listButtonAdapterKey } from '../runtime/listButtonHost';
import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ElementPlus, { ElMessageBox } from 'element-plus';
import { formDataApi } from '@/api/formData';
import Bar from './ListButtonBar.vue';
import Interaction from './ListButtonInteraction.vue';
import type { FormListButton } from '../schema/types';
const provide = { [listButtonAdapterKey as symbol]: { api: formDataApi, declaration: { catalogPermission: 'console/form.data:listactions', executePermission: 'console/form.data:listaction' } } };
const edit: FormListButton = { id: 'edit', label: '修改名称', action: { type: 'builtin', key: 'edit' } };
describe('真实共享按钮组件', () => {
  it('异步执行期间替换外部锁，只释放实际取得的锁', async () => {
    let finish!: () => void;
    const firstLock = { busy: false };
    const replacementLock = { busy: true };
    const invoke = vi.fn(() => new Promise<void>(resolve => { finish = resolve; }));
    const wrapper = mount(Bar, { props: { buttons: [edit], handlers: { edit: invoke }, allowed: () => true, lock: firstLock }, global: { plugins: [ElementPlus], provide } });
    await wrapper.get('button').trigger('click'); await flushPromises();
    expect(firstLock.busy).toBe(true);
    await wrapper.setProps({ lock: replacementLock });
    finish(); await flushPromises();
    expect(firstLock.busy).toBe(false);
    expect(replacementLock.busy).toBe(true);
    wrapper.unmount();
  });
  it('注册动作加载真实目录、确认同键重发并阻止重入', async () => {
    const catalog = vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: { approve: { permission: 'approve', capabilityVersion: 'v1', locations: ['row'], targets: ['record'], batch: false, effect: 'write', resultContract: 'json' } } });
    const invoke = vi.spyOn(formDataApi, 'listAction').mockResolvedValueOnce({ status: 'confirmation_required', confirmation: 'token' }).mockResolvedValue({ status: 'success' });
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockImplementation(async () => 'confirm' as Awaited<ReturnType<typeof ElMessageBox.confirm>>);
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'approve', label: '批准', permission: 'approve', action: { type: 'registered', key: 'approve', capabilityVersion: 'v1' } }], handlers: {}, allowed: () => true, permissionCheck: () => true, context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
    await flushPromises();
    await wrapper.get('button').trigger('click'); await wrapper.get('button').trigger('click'); await flushPromises();
    expect(catalog).toHaveBeenCalled(); expect(invoke).toHaveBeenCalledTimes(2);
    expect(invoke.mock.calls[1]![1]).toEqual({ ...invoke.mock.calls[0]![1], confirmation: 'token' });
    expect(confirm).toHaveBeenCalledOnce();
    wrapper.unmount(); vi.restoreAllMocks();
  });
  it('目录失败关闭执行入口，重试后恢复；确认期间换记录不重发', async () => {
    const catalog = vi.spyOn(formDataApi, 'listActions').mockRejectedValueOnce(Error('离线')).mockResolvedValue({ schemaHash: 'hash', actions: { approve: { permission: 'approve', capabilityVersion: 'v1', locations: ['row'], targets: ['record'], batch: false, effect: 'write', resultContract: 'json' } } });
    const invoke = vi.spyOn(formDataApi, 'listAction').mockResolvedValue({ status: 'confirmation_required', confirmation: 'token' });
    let confirm!: () => void;
    vi.spyOn(ElMessageBox, 'confirm').mockImplementation(() => new Promise(resolve => { confirm = () => resolve('confirm' as Awaited<ReturnType<typeof ElMessageBox.confirm>>); }));
    const context = { formKey: 'orders', schemaHash: 'hash', location: 'row' as const, ids: [1] };
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'approve', label: '批准', permission: 'approve', action: { type: 'registered', key: 'approve', capabilityVersion: 'v1' } }], handlers: {}, allowed: () => true, permissionCheck: () => true, context }, global: { plugins: [ElementPlus], provide } });
    await flushPromises(); expect(wrapper.findAll('button').find(button => button.text() === '批准')!.attributes('disabled')).toBeDefined();
    await wrapper.findAll('button').find(button => button.text() === '重试动作目录')!.trigger('click'); await flushPromises();
    await wrapper.get('button').trigger('click'); await flushPromises();
    await wrapper.setProps({ context: { ...context, ids: [2] } }); confirm(); await flushPromises();
    expect(invoke).toHaveBeenCalledOnce(); expect(catalog).toHaveBeenCalled();
    wrapper.unmount(); vi.restoreAllMocks();
  });
  it('输入失败保持弹窗和输入，明确再次提交才重试', async () => {
    const invoke = vi.fn().mockRejectedValueOnce(Error('输入不符合规则')).mockResolvedValue('ok');
    const wrapper = mount(Bar, { props: { buttons: [{ ...edit, interaction: { type: 'input', fields: [{ name: 'reason', label: '原因', type: 'input' }] } }], handlers: { edit: invoke }, allowed: () => true }, global: { plugins: [ElementPlus], provide } });
    await wrapper.get('button').trigger('click'); await flushPromises();
    const input = document.body.querySelector('.el-dialog input') as HTMLInputElement;
    input.value = '保留内容'; input.dispatchEvent(new Event('input', { bubbles: true }));
    (Array.from(document.body.querySelectorAll('.el-dialog button')).find(button => button.textContent === '确定') as HTMLButtonElement).click();
    await flushPromises();
    expect(invoke).toHaveBeenCalledOnce();
    expect((document.body.querySelector('.el-dialog input') as HTMLInputElement).value).toBe('保留内容');
    expect(document.body.textContent).toContain('输入不符合规则');
    (Array.from(document.body.querySelectorAll('.el-dialog button')).find(button => button.textContent === '确定') as HTMLButtonElement).click();
    await flushPromises(); expect(invoke).toHaveBeenCalledTimes(2);
    wrapper.unmount();
  });
  it('空集合不显示默认按钮，改名仍执行 edit，权限撤销隐藏', async () => {
    const invoke = vi.fn();
    const wrapper = mount(Bar, { props: { buttons: [], handlers: { edit: invoke }, allowed: () => true }, global: { plugins: [ElementPlus], provide } });
    expect(wrapper.findAll('button')).toHaveLength(0);
    await wrapper.setProps({ buttons: [edit] });
    await wrapper.get('button').trigger('click'); await flushPromises();
    expect(invoke).toHaveBeenCalledOnce();
    await wrapper.setProps({ allowed: () => false }); expect(wrapper.findAll('button')).toHaveLength(0);
    wrapper.unmount();
  });
  it('未接通动作显示原因且不执行', async () => {
    const wrapper = mount(Bar, { props: { buttons: [{ ...edit, action: { type: 'copy' } }], handlers: {}, allowed: () => true }, global: { plugins: [ElementPlus], provide } });
    expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    expect(wrapper.html()).toContain('尚未接通'); wrapper.unmount();
  });
  it('成功效果调用宿主，预览不触发宿主副作用', async () => {
    const clearSelection = vi.fn(); const close = vi.fn(); const refresh = vi.fn(); const invoke = vi.fn();
    const wrapper = mount(Bar, { props: { buttons: [{ ...edit, success: { clearSelection: true, close: true, refresh: true } }], handlers: { edit: invoke }, allowed: () => true, clearSelection, close, refresh }, global: { plugins: [ElementPlus], provide } });
    await wrapper.get('button').trigger('click'); await flushPromises();
    expect(clearSelection).toHaveBeenCalledOnce(); expect(close).toHaveBeenCalledOnce(); expect(refresh).toHaveBeenCalledOnce();
    await wrapper.setProps({ preview: true });
    await wrapper.get('button').trigger('click'); await flushPromises();
    expect(invoke).toHaveBeenCalledOnce(); expect(clearSelection).toHaveBeenCalledOnce(); expect(close).toHaveBeenCalledOnce(); expect(refresh).toHaveBeenCalledOnce();
    wrapper.unmount();
  });
  it('抽屉输入必填校验、取消返回 null', async () => {
    const wrapper = mount(Interaction, { global: { plugins: [ElementPlus], provide } });
    const pending = wrapper.vm.open({ ...edit, interaction: { type: 'input', presentation: 'drawer', fields: [{ name: 'reason', label: '原因', type: 'input', required: true }] } }, {});
    await flushPromises();
    expect(document.body.querySelector('.el-drawer')).not.toBeNull();
    wrapper.vm.cancel(); expect(await pending).toBeNull(); wrapper.unmount();
  });
});
