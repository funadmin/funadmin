import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, reactive } from 'vue';
import ElementPlus, { ElMessage, ElMessageBox, ElTable } from 'element-plus';
import OperationPage from '@/views/system/log/operation.vue';
import SearchForm from '@/components/SearchForm/index.vue';
import DataTableShell from '@/components/DataTable/DataTableShell.vue';
import DataTableToolbar from '@/components/DataTable/DataTableToolbar.vue';
import { permission } from '@/directives/permission';
import { operationLogApi, type OperationLog } from '@/api/system/log';

const user = vi.hoisted(() => ({ state: null as unknown as { permissions: string[] } }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user.state }));
vi.mock('@/api/system/log', () => ({
  operationLogApi: {
    list: vi.fn(), detail: vi.fn(), remove: vi.fn(), clear: vi.fn(), forceRemove: vi.fn(), clearForce: vi.fn()
  }
}));

const ALL = [
  'system:operation-log:index', 'system:operation-log:detail', 'system:operation-log:delete',
  'system:operation-log:clear', 'system:operation-log:force-delete', 'system:operation-log:clear-force'
];
const row = (id: number, deletedAt = ''): OperationLog => ({
  id, username: 'admin', appName: 'console', sourceType: 'system', sourceName: 'core', controller: 'c', action: 'a',
  name: `op-${id}`, method: 'DELETE', url: '/x', ip: '127.0.0.1', status: 1, responseCode: 200, durationMs: 3,
  requestId: '', createdAt: '2026-11-10 10:00:00', deletedAt
});
const trashed = (id: number) => row(id, '2026-11-10 11:00:00');
const wrappers: VueWrapper[] = [];
function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}
const page = (rows: OperationLog[]) => ({ list: rows, total: rows.length, page: 1, pageSize: 10 });
const tableIds = (wrapper: VueWrapper) => (wrapper.findComponent(ElTable).props('data') as OperationLog[]).map(item => item.id);
/** 表格行内按钮（按文案精确匹配）。 */
const rowButtons = (wrapper: VueWrapper, text: string) =>
  wrapper.findComponent(ElTable).findAll('button').filter(button => button.text().trim() === text);

async function mountPage(permissions = ALL) {
  user.state = reactive({ permissions: [...permissions] });
  const wrapper = mount(OperationPage, {
    attachTo: document.body,
    global: {
      plugins: [ElementPlus],
      directives: { perm: permission },
      components: { SearchForm, DataTableShell, PageWrapper: defineComponent({ setup: (_, { slots }) => () => h('main', slots.default?.()) }) }
    }
  });
  wrappers.push(wrapper);
  await flushPromises();
  return wrapper;
}
/** 工具栏按钮按文案精确匹配（忽略选中数量后缀）。 */
function toolbarButton(wrapper: VueWrapper, text: string) {
  return wrapper.findComponent(DataTableToolbar).findAll('button')
    .find(button => button.text().replace(/\(\d+\)$/, '').trim() === text);
}
const visible = (wrapper: VueWrapper, text: string) => Boolean(toolbarButton(wrapper, text)?.isVisible());
async function switchToTrash(wrapper: VueWrapper) {
  const option = wrapper.findAll('.el-radio-button').find(item => item.text().trim() === '回收站');
  expect(option, '回收站切换项').toBeTruthy();
  await option!.find('input').setValue(true);
  await flushPromises();
}
async function select(wrapper: VueWrapper, rows: OperationLog[]) {
  wrapper.findComponent(ElTable).vm.$emit('selection-change', rows);
  await flushPromises();
}
const lastListParams = () => vi.mocked(operationLogApi.list).mock.calls.at(-1)?.[0];

beforeEach(() => {
  vi.mocked(operationLogApi.list).mockImplementation(async (params) => ({
    list: params.trashed === 1 ? [row(3, '2026-11-10 11:00:00'), row(5, '2026-11-10 12:00:00')] : [row(1), row(2)],
    total: 2, page: 1, pageSize: 10
  }));
  vi.mocked(operationLogApi.detail).mockImplementation(async (id) => row(id, '2026-11-10 11:00:00'));
  vi.mocked(operationLogApi.remove).mockResolvedValue({ removed: 2 });
  vi.mocked(operationLogApi.clear).mockResolvedValue({ removed: 2 });
  vi.mocked(operationLogApi.forceRemove).mockResolvedValue({ removed: 2 });
  vi.mocked(operationLogApi.clearForce).mockResolvedValue({ removed: 2 });
});
afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount());
  vi.clearAllMocks();
  vi.restoreAllMocks();
});

describe('操作日志回收站与彻底删除', () => {
  it('普通视图请求未删除日志，只显示软删除按钮，不渲染彻底删除按钮', async () => {
    const wrapper = await mountPage();
    expect(lastListParams()?.trashed).not.toBe(1);
    expect(visible(wrapper, '批量删除')).toBe(true);
    expect(visible(wrapper, '清空')).toBe(true);
    expect(toolbarButton(wrapper, '彻底删除')).toBeUndefined();
    expect(toolbarButton(wrapper, '清空回收站')).toBeUndefined();
    expect(wrapper.text()).not.toContain('删除时间');
  });

  it('切换到回收站请求 trashed=1，显示删除时间列与彻底删除按钮，隐藏软删除按钮', async () => {
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    expect(lastListParams()).toMatchObject({ trashed: 1, page: 1 });
    expect(wrapper.text()).toContain('删除时间');
    expect(visible(wrapper, '彻底删除')).toBe(true);
    expect(visible(wrapper, '清空回收站')).toBe(true);
    expect(toolbarButton(wrapper, '批量删除')).toBeUndefined();
    expect(toolbarButton(wrapper, '清空')).toBeUndefined();
  });

  it('回收站中重置查询条件后仍停留在回收站', async () => {
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    const reset = wrapper.findComponent(SearchForm).findAll('button').find(button => button.text().includes('重置'));
    await reset!.trigger('click');
    await flushPromises();
    expect(lastListParams()?.trashed).toBe(1);
  });

  it('彻底删除确认后以选中 ID 调用 forceRemove，确认文案说明不可恢复', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    await select(wrapper, [trashed(3), trashed(5)]);
    const listCalls = vi.mocked(operationLogApi.list).mock.calls.length;
    await toolbarButton(wrapper, '彻底删除')!.trigger('click');
    await flushPromises();
    expect(String(confirm.mock.calls[0][0])).toContain('不可恢复');
    expect(operationLogApi.forceRemove).toHaveBeenCalledWith([3, 5]);
    expect(operationLogApi.remove).not.toHaveBeenCalled();
    expect(vi.mocked(operationLogApi.list).mock.calls.length).toBe(listCalls + 1);
    expect(lastListParams()?.trashed).toBe(1);
  });

  it('取消彻底删除确认时不调用 forceRemove', async () => {
    vi.spyOn(ElMessageBox, 'confirm').mockRejectedValue('cancel');
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    await select(wrapper, [trashed(3)]);
    await toolbarButton(wrapper, '彻底删除')!.trigger('click');
    await flushPromises();
    expect(operationLogApi.forceRemove).not.toHaveBeenCalled();
  });

  it('未选择日志时彻底删除按钮不可用', async () => {
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    expect(toolbarButton(wrapper, '彻底删除')!.attributes('disabled')).toBeDefined();
  });

  it('清空回收站确认后调用 clearForce，确认文案说明不可恢复；取消时不调用', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockRejectedValueOnce('cancel').mockResolvedValueOnce('confirm' as never);
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    await toolbarButton(wrapper, '清空回收站')!.trigger('click');
    await flushPromises();
    expect(operationLogApi.clearForce).not.toHaveBeenCalled();
    await toolbarButton(wrapper, '清空回收站')!.trigger('click');
    await flushPromises();
    expect(String(confirm.mock.calls[1][0])).toContain('不可恢复');
    expect(operationLogApi.clearForce).toHaveBeenCalledTimes(1);
    expect(operationLogApi.clear).not.toHaveBeenCalled();
  });

  it('普通视图批量删除仍调用软删除 remove，确认文案说明移入回收站', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const wrapper = await mountPage();
    await select(wrapper, [row(1), row(2)]);
    await toolbarButton(wrapper, '批量删除')!.trigger('click');
    await flushPromises();
    expect(String(confirm.mock.calls[0][0])).toContain('回收站');
    expect(operationLogApi.remove).toHaveBeenCalledWith([1, 2]);
    expect(operationLogApi.forceRemove).not.toHaveBeenCalled();
  });

  it('普通视图清空调用软清空 clear，确认文案说明移入回收站', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const wrapper = await mountPage();
    await toolbarButton(wrapper, '清空')!.trigger('click');
    await flushPromises();
    expect(String(confirm.mock.calls[0][0])).toContain('回收站');
    expect(operationLogApi.clear).toHaveBeenCalledTimes(1);
    expect(operationLogApi.clearForce).not.toHaveBeenCalled();
  });

  it('缺少彻底删除与清空回收站权限时隐藏对应按钮', async () => {
    const wrapper = await mountPage(['system:operation-log:index', 'system:operation-log:delete', 'system:operation-log:clear']);
    await switchToTrash(wrapper);
    expect(visible(wrapper, '彻底删除')).toBe(false);
    expect(visible(wrapper, '清空回收站')).toBe(false);
  });

  it('缺少删除与清空权限时隐藏普通视图的软删除按钮', async () => {
    const wrapper = await mountPage(['system:operation-log:index', 'system:operation-log:force-delete', 'system:operation-log:clear-force']);
    expect(visible(wrapper, '批量删除')).toBe(false);
    expect(visible(wrapper, '清空')).toBe(false);
  });

  it('没有清空回收站权限时即使触发处理器也不会调用 clearForce', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const wrapper = await mountPage(['system:operation-log:index']);
    await switchToTrash(wrapper);
    await toolbarButton(wrapper, '清空回收站')!.trigger('click');
    await flushPromises();
    expect(confirm).not.toHaveBeenCalled();
    expect(operationLogApi.clearForce).not.toHaveBeenCalled();
  });

  it('回收站中可查看日志详情', async () => {
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    const detail = wrapper.findComponent(ElTable).findAll('button').find(button => button.text().trim() === '详情');
    expect(detail, '回收站行内详情按钮').toBeTruthy();
    await detail!.trigger('click');
    await flushPromises();
    expect(operationLogApi.detail).toHaveBeenCalledWith(3);
  });

  it('切换视图前发出的普通列表慢响应不会覆盖回收站列表', async () => {
    const slow = deferred<ReturnType<typeof page>>();
    vi.mocked(operationLogApi.list).mockImplementationOnce(() => slow.promise as never);
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    expect(tableIds(wrapper)).toEqual([3, 5]);
    slow.resolve(page([row(1), row(2)]));
    await flushPromises();
    expect(tableIds(wrapper)).toEqual([3, 5]);
    expect(wrapper.findComponent(ElTable).text()).not.toContain('op-1');
  });

  it('普通视图选中后切换到回收站会清空选择，彻底删除按钮不可用', async () => {
    const wrapper = await mountPage();
    await select(wrapper, [row(1), row(2)]);
    expect(toolbarButton(wrapper, '批量删除')!.text()).toContain('(2)');
    await switchToTrash(wrapper);
    const force = toolbarButton(wrapper, '彻底删除')!;
    expect(force.text()).not.toContain('(');
    expect(force.attributes('disabled')).toBeDefined();
    await force.trigger('click');
    await flushPromises();
    expect(operationLogApi.forceRemove).not.toHaveBeenCalled();
  });

  it('回收站行内彻底删除确认后以该行 ID 调用 forceRemove', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    const buttons = rowButtons(wrapper, '彻底删除');
    expect(buttons).toHaveLength(2);
    expect(rowButtons(wrapper, '删除')).toHaveLength(0);
    await buttons[1].trigger('click');
    await flushPromises();
    expect(String(confirm.mock.calls[0][0])).toContain('不可恢复');
    expect(operationLogApi.forceRemove).toHaveBeenCalledWith([5]);
  });

  it('缺少彻底删除权限时隐藏行内彻底删除按钮', async () => {
    const wrapper = await mountPage(['system:operation-log:index', 'system:operation-log:detail']);
    await switchToTrash(wrapper);
    const buttons = rowButtons(wrapper, '彻底删除');
    expect(buttons).toHaveLength(2);
    expect(buttons.every(button => !button.isVisible())).toBe(true);
    expect(rowButtons(wrapper, '详情').every(button => button.isVisible())).toBe(true);
  });

  it('没有彻底删除权限时直接调用处理器既不确认也不请求', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const wrapper = await mountPage(['system:operation-log:index']);
    await switchToTrash(wrapper);
    await (wrapper.vm as unknown as { onForceDelete: (rows: OperationLog[]) => Promise<void> }).onForceDelete([trashed(3)]);
    await select(wrapper, [trashed(3)]);
    await toolbarButton(wrapper, '彻底删除')!.trigger('click');
    await rowButtons(wrapper, '彻底删除')[0].trigger('click');
    await flushPromises();
    expect(confirm).not.toHaveBeenCalled();
    expect(operationLogApi.forceRemove).not.toHaveBeenCalled();
  });

  it('选中行包含未删除日志时提示并拒绝彻底删除', async () => {
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    const warning = vi.spyOn(ElMessage, 'warning').mockImplementation((() => undefined) as never);
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    await select(wrapper, [trashed(3), row(1)]);
    await toolbarButton(wrapper, '彻底删除')!.trigger('click');
    await flushPromises();
    expect(warning).toHaveBeenCalledTimes(1);
    expect(confirm).not.toHaveBeenCalled();
    expect(operationLogApi.forceRemove).not.toHaveBeenCalled();
  });

  it('彻底删除被后端拒绝（403/422）时吞掉错误、清空选择并刷新列表', async () => {
    vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    vi.mocked(operationLogApi.forceRemove).mockRejectedValueOnce({ code: 403, msg: '只能彻底删除回收站中的日志' });
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    await select(wrapper, [trashed(3), trashed(5)]);
    const listCalls = vi.mocked(operationLogApi.list).mock.calls.length;
    await toolbarButton(wrapper, '彻底删除')!.trigger('click');
    await flushPromises();
    expect(operationLogApi.forceRemove).toHaveBeenCalledWith([3, 5]);
    expect(vi.mocked(operationLogApi.list).mock.calls.length).toBe(listCalls + 1);
    expect(lastListParams()?.trashed).toBe(1);
    const force = toolbarButton(wrapper, '彻底删除')!;
    expect(force.text()).not.toContain('(');
    expect(force.attributes('disabled')).toBeDefined();
  });

  it('清空回收站被后端拒绝时吞掉错误并刷新列表', async () => {
    vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as never);
    vi.mocked(operationLogApi.clearForce).mockRejectedValueOnce({ code: 403, msg: '没有访问权限' });
    const wrapper = await mountPage();
    await switchToTrash(wrapper);
    const listCalls = vi.mocked(operationLogApi.list).mock.calls.length;
    await toolbarButton(wrapper, '清空回收站')!.trigger('click');
    await flushPromises();
    expect(operationLogApi.clearForce).toHaveBeenCalledTimes(1);
    expect(vi.mocked(operationLogApi.list).mock.calls.length).toBe(listCalls + 1);
    expect(lastListParams()?.trashed).toBe(1);
  });
});
