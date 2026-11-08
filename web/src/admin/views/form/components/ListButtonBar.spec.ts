import { listButtonAdapterKey } from '../runtime/listButtonHost';
import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ElementPlus, { ElMessage, ElMessageBox } from 'element-plus';
import { formDataApi } from '@/api/formData';
import Bar from './ListButtonBar.vue';
import Interaction from './ListButtonInteraction.vue';
import type { FormListButton } from '../schema/types';
import PageActions from '@/components/DataTable/PageActions.vue';
import type { PageAction, PageContext } from '@/components/DataTable/pageSchema';
import { afterEach } from 'vitest';
const provide = { [listButtonAdapterKey as symbol]: { api: formDataApi, declaration: { catalogPermission: 'form:data:list-actions', executePermission: 'form:data:list-action' } } };
const edit: FormListButton = { id: 'edit', label: '修改名称', action: { type: 'builtin', key: 'edit' } };
describe('PageActions 本地确认真实挂载反证', () => {
  afterEach(() => vi.restoreAllMocks());

  function renderLocal() {
    const writes: number[] = [];
    let finish!: () => void;
    const run = vi.fn((row: { id: number }) => {
      writes.push(row.id);
      return new Promise<void>(resolve => { finish = resolve; });
    });
    const context: PageContext = {
      values: { selectionCount: 1, selectedIds: [7] }, permissions: ['member:delete'], row: { id: 7 },
      handlers: { remove: { version: 'v1', permission: 'member:delete', interaction: { type: 'confirm', title: '确认删除会员', message: '删除后不可恢复' }, run } }
    };
    const actions: PageAction[] = [{ id: 'remove', label: '删除会员', action: { type: 'registered', key: 'remove', capabilityVersion: 'v1' } }];
    const catalog = vi.spyOn(formDataApi, 'listActions').mockRejectedValue(Error('本地禁止调用服务端目录'));
    const server = vi.spyOn(formDataApi, 'listAction').mockRejectedValue(Error('本地禁止降级服务端'));
    const serverConfirm = vi.spyOn(ElMessageBox, 'confirm');
    const lock = { busy: false };
    const wrapper = mount(PageActions, { props: { actions, context, lock }, global: { plugins: [ElementPlus], provide } });
    return { wrapper, context, writes, run, lock, catalog, server, serverConfirm, finish: () => finish() };
  }

  function dialogButton(label: string) {
    const button = Array.from(document.body.querySelectorAll<HTMLButtonElement>('.el-dialog button')).find(item => item.textContent?.trim() === label);
    expect(button, `真实确认弹窗必须存在 ${label} 按钮`).toBeDefined();
    return button!;
  }

  it('confirm 等待及取消零写入；确定一次才写入，异步完成前防重入且绝不降级 server', async () => {
    const test = renderLocal();
    try {
      expect(test.wrapper.findComponent(Bar).exists()).toBe(true);
      expect(test.wrapper.findComponent(Interaction).exists()).toBe(true);
      await test.wrapper.get('button').trigger('click'); await flushPromises();
      expect(test.writes, '确认前不得写入').toEqual([]);
      expect(document.body.textContent).toContain('删除后不可恢复');
      expect(document.body.querySelectorAll('.el-dialog')).toHaveLength(1);
      expect(test.writes).toEqual([]); expect(test.lock.busy).toBe(true);
      await test.wrapper.get('button').trigger('click'); await flushPromises();
      expect(test.writes).toEqual([]);
      dialogButton('取消').click(); await flushPromises();
      expect(test.writes).toEqual([]); expect(test.lock.busy).toBe(false);
      await test.wrapper.get('button').trigger('click'); await flushPromises();
      expect(test.writes).toEqual([]);
      dialogButton('确定').click(); await flushPromises();
      expect(test.writes).toEqual([7]); expect(test.run).toHaveBeenCalledOnce();
      expect(test.lock.busy).toBe(true);
      await test.wrapper.get('button').trigger('click'); await flushPromises();
      expect(test.run).toHaveBeenCalledOnce();
      test.finish(); await flushPromises();
      expect(test.lock.busy).toBe(false);
      expect(test.catalog).not.toHaveBeenCalled(); expect(test.server).not.toHaveBeenCalled();
      expect(test.serverConfirm).not.toHaveBeenCalled();
    } finally { test.wrapper.unmount(); }
  });

  it.each(['权限撤销', '同数量选择变化', '记录变化'] as const)('确认等待时%s，迟到确定仍零写入且不降级', async change => {
    const test = renderLocal();
    try {
      await test.wrapper.get('button').trigger('click'); await flushPromises();
      expect(test.writes, '确认前不得写入').toEqual([]);
      const staleConfirm = dialogButton('确定');
      expect(test.writes).toEqual([]);
      const context = { ...test.context };
      if (change === '权限撤销') context.permissions = [];
      if (change === '同数量选择变化') context.values = { selectionCount: 1, selectedIds: [8] };
      if (change === '记录变化') context.row = { id: 8 };
      await test.wrapper.setProps({ context });
      staleConfirm.click(); await flushPromises();
      expect(test.writes).toEqual([]); expect(test.run).not.toHaveBeenCalled();
      expect(test.lock.busy).toBe(false);
      expect(test.catalog).not.toHaveBeenCalled(); expect(test.server).not.toHaveBeenCalled();
      expect(test.serverConfirm).not.toHaveBeenCalled();
    } finally { test.wrapper.unmount(); }
  });
});

describe('真实共享按钮组件', () => {
  it('数量不足或超限显示明确禁用原因，不调用宿主；范围合法仍不能扩大能力权限', async () => {
    const invoke = vi.fn();
    const context = { formKey: 'orders', schemaHash: 'hash', location: 'toolbar' as const, ids: [1] };
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'batch', label: '批量', action: { type: 'builtin', key: 'batchDelete' }, selection: { min: 2, max: 3 } }], handlers: { batchDelete: invoke }, allowed: () => true, context }, global: { plugins: [ElementPlus] } });
    expect(wrapper.get('button').attributes('disabled')).toBeDefined(); expect(wrapper.html()).toContain('至少选择 2 条');
    await wrapper.get('button').trigger('click'); expect(invoke).not.toHaveBeenCalled();
    await wrapper.setProps({ context: { ...context, ids: [1, 2, 3, 4] } }); expect(wrapper.html()).toContain('最多选择 3 条');
    await wrapper.setProps({ context: { ...context, ids: [1, 2] } }); await wrapper.get('button').trigger('click'); await flushPromises(); expect(invoke).toHaveBeenCalledOnce();
    await wrapper.setProps({ handlers: {} }); expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    await wrapper.setProps({ allowed: () => false }); expect(wrapper.find('button').exists()).toBe(false); wrapper.unmount();
  });
  it('实际组件按类型执行纯文本复制和已有授权导出而非任意下载', async () => {
    vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: {}, resources: {}, resourceHash: 'r1' });
    const invoke = vi.spyOn(formDataApi, 'listAction').mockResolvedValue({ status: 'success', result: { type: 'copy', text: '安全文本' } });
    const copy = vi.fn(); Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: copy } });
    const download = vi.fn();
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'copy', label: '复制', action: { type: 'copy' }, params: { text: { source: 'row', field: 'title' } } }], handlers: { export: download }, allowed: () => true, permissionCheck: () => true, context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
    await flushPromises(); await wrapper.get('button').trigger('click'); await flushPromises(); expect(copy).toHaveBeenCalledWith('安全文本');
    invoke.mockResolvedValue({ status: 'success', result: { type: 'download', key: 'export' } });
    await wrapper.setProps({ buttons: [{ id: 'download', label: '下载', action: { type: 'download', key: 'export' } }], context: { formKey: 'orders', schemaHash: 'hash', location: 'toolbar', ids: [] } });
    await flushPromises(); await wrapper.get('button').trigger('click'); await flushPromises(); expect(download).toHaveBeenCalledOnce();
    wrapper.unmount(); vi.restoreAllMocks();
  });
  it('复制响应不得换成下载指令调用其他宿主能力', async () => {
    vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: {}, resources: {}, resourceHash: 'r1' });
    vi.spyOn(formDataApi, 'listAction').mockResolvedValue({ status: 'success', result: { type: 'download', key: 'export' } });
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: vi.fn() } });
    const download = vi.fn();
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'copy', label: '复制', action: { type: 'copy' }, params: { text: { source: 'row', field: 'title' } } }], handlers: { export: download }, allowed: () => true, permissionCheck: () => true, context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
    await flushPromises(); await wrapper.get('button').trigger('click'); await flushPromises();
    expect(download).not.toHaveBeenCalled();
    wrapper.unmount(); vi.restoreAllMocks();
  });
  it('复制经过实际后端宿主且切换记录后的迟到结果不产生副作用', async () => {
    vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: {}, resources: {}, resourceHash: 'r1' });
    let finish!: (reply: any) => void;
    vi.spyOn(formDataApi, 'listAction').mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const copy = vi.fn(); Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: copy } });
    const context = { formKey: 'orders', schemaHash: 'hash', location: 'row' as const, ids: [1] };
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'copy', label: '复制', action: { type: 'copy' }, params: { text: { source: 'row', field: 'title' } } }], handlers: {}, allowed: () => true, permissionCheck: () => true, context }, global: { plugins: [ElementPlus], provide } });
    await flushPromises(); await wrapper.get('button').trigger('click'); await flushPromises();
    await wrapper.setProps({ context: { ...context, ids: [2] } }); await flushPromises();
    finish({ status: 'success', result: { type: 'copy', text: '旧记录' } }); await flushPromises();
    expect(copy).not.toHaveBeenCalled(); wrapper.unmount(); vi.restoreAllMocks();
  });
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
    const wrapper = mount(Bar, { props: { buttons: [{ id: 'approve', label: '批准', permission: 'approve', interaction: { type: 'confirm' }, action: { type: 'registered', key: 'approve', capabilityVersion: 'v1' } }], handlers: {}, allowed: () => true, permissionCheck: () => true, context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
    await flushPromises();
    await wrapper.get('button').trigger('click'); await wrapper.get('button').trigger('click'); await flushPromises();
    expect(catalog).toHaveBeenCalled(); expect(invoke).toHaveBeenCalledTimes(2);
    expect(invoke.mock.calls[1]![1]).toEqual({ ...invoke.mock.calls[0]![1], confirmation: 'token' });
    expect(confirm).toHaveBeenCalledOnce();
    wrapper.unmount(); vi.restoreAllMocks();
  });
  describe('注册动作结果提示', () => {
    const approve = { permission: 'approve', capabilityVersion: 'v1', locations: ['row'], targets: ['record'], batch: false, effect: 'write', resultContract: 'json' };
    const button = (extra: Partial<FormListButton> = {}): FormListButton => ({ id: 'approve', label: '审核', permission: 'approve', action: { type: 'registered', key: 'approve', capabilityVersion: 'v1' }, ...extra });
    async function run(reply: () => Promise<unknown>, extra: Partial<FormListButton> = {}) {
      vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: { approve } });
      vi.spyOn(formDataApi, 'listAction').mockImplementation(reply as never);
      const success = vi.spyOn(ElMessage, 'success').mockImplementation((() => undefined) as never);
      const failure = vi.spyOn(ElMessage, 'error').mockImplementation((() => undefined) as never);
      const wrapper = mount(Bar, { props: { buttons: [button(extra)], handlers: {}, allowed: () => true, permissionCheck: () => true, context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
      await flushPromises();
      await wrapper.get('button').trigger('click'); await flushPromises();
      wrapper.unmount(); vi.restoreAllMocks();
      return { success: success.mock.calls.map(call => call[0]), failure: failure.mock.calls.map(call => call[0]) };
    }
    it('未实现与处理器主动拒绝显示后端消息，其他错误仍为通用提示', async () => {
      expect((await run(() => Promise.reject({ code: 422, msg: '未实现：审核', data: { code: 'FORM_ACTION_NOT_IMPLEMENTED' } }))).failure).toEqual(['未实现：审核']);
      expect((await run(() => Promise.reject({ code: 422, msg: '订单已关闭', data: { code: 'FORM_ACTION_USER_ERROR' } }))).failure).toEqual(['订单已关闭']);
      expect((await run(() => Promise.reject({ code: 500, msg: '服务器内部错误', data: null }))).failure).toEqual(['动作执行失败']);
    });
    it('按钮未配置成功提示时以处理器返回的 message 作为成功提示；已配置时以按钮为准', async () => {
      expect((await run(() => Promise.resolve({ status: 'success', result: { message: '已审核 1 条' } }))).success).toEqual(['已审核 1 条']);
      expect((await run(() => Promise.resolve({ status: 'success', result: { message: '已审核 1 条' } }), { success: { message: '操作完成' } })).success).toEqual(['操作完成']);
      expect((await run(() => Promise.resolve({ status: 'success', result: { message: 3 } }))).success).toEqual([]);
    });
  });
  it('动态发布时尚未生成的自定义动作（business.* 不在运行时目录）显示为禁用并提示「正式生成后可用」，不隐藏、不执行', async () => {
    vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: {}, pendingKeys: ['business.orders.approve'] });
    const invoke = vi.spyOn(formDataApi, 'listAction');
    const pending: FormListButton = { id: 'approve', label: '审批', permission: 'business:orders:action-approve', action: { type: 'registered', key: 'business.orders.approve', capabilityVersion: '1' } };
    const missing: FormListButton = { id: 'other', label: '其他', permission: 'other', action: { type: 'registered', key: 'other', capabilityVersion: '1' } };
    // 权限码在生成前并不存在：宿主权限判断为 false 时待生成按钮仍应可见（禁用）。
    const wrapper = mount(Bar, { props: { buttons: [pending, missing], handlers: {}, allowed: button => button.id !== 'other' && !button.permission?.startsWith('business:'), permissionCheck: code => code.startsWith('form:'), context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
    await flushPromises();
    const button = wrapper.findAll('button').find(item => item.text() === '审批')!;
    expect(button.attributes('disabled')).toBeDefined();
    expect(button.element.parentElement!.getAttribute('title')).toBe('正式生成后可用');
    expect(wrapper.findAll('button').some(item => item.text() === '其他')).toBe(false);
    await button.trigger('click'); await flushPromises();
    expect(invoke).not.toHaveBeenCalled();
    wrapper.unmount(); vi.restoreAllMocks();
  });
  it('已正式生成但当前用户无权限的自定义动作（不在 pendingKeys）仍按原规则隐藏，不提示「正式生成后可用」', async () => {
    // 运行时目录按权限过滤：已生成的 business.* 键缺席只代表无权限。
    vi.spyOn(formDataApi, 'listActions').mockResolvedValue({ schemaHash: 'hash', actions: {}, pendingKeys: [] });
    const generated: FormListButton = { id: 'approve', label: '审批', permission: 'business:orders:action-approve', action: { type: 'registered', key: 'business.orders.approve', capabilityVersion: '1' } };
    const wrapper = mount(Bar, { props: { buttons: [generated], handlers: {}, allowed: button => !button.permission?.startsWith('business:'), permissionCheck: code => code.startsWith('form:'), context: { formKey: 'orders', schemaHash: 'hash', location: 'row', ids: [1] } }, global: { plugins: [ElementPlus], provide } });
    await flushPromises();
    expect(wrapper.findAll('button').some(item => item.text() === '审批')).toBe(false);
    expect(wrapper.html()).not.toContain('正式生成后可用');
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

describe('表格操作栏样式契约', () => {
  it('行级(link)按钮只渲染文字，工具栏按钮保留图标', () => {
    const withIcon: FormListButton = { ...edit, icon: 'edit' };
    const rowBar = mount(Bar, { props: { buttons: [withIcon], handlers: { edit: vi.fn() }, allowed: () => true, link: true }, global: { plugins: [ElementPlus], provide } });
    expect(rowBar.find('.el-icon').exists()).toBe(false);
    expect(rowBar.text()).toContain('修改名称');
    rowBar.unmount();
    const toolbarBar = mount(Bar, { props: { buttons: [withIcon], handlers: { edit: vi.fn() }, allowed: () => true }, global: { plugins: [ElementPlus], provide } });
    expect(toolbarBar.find('.el-icon').exists()).toBe(true);
    toolbarBar.unmount();
  });
});
