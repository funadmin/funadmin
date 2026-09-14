import { describe, expect, it, vi } from 'vitest';
import { createListButtonExecutor, registeredListButtonAvailable, listButtonRequest } from './listButtonExecutor';
import type { FormListButton } from '../schema/types';

const button: FormListButton = { id: 'reload', label: '随意改名', action: { type: 'refresh' }, success: { refresh: true } };
describe('列表执行生命周期', () => {
  it('跨执行器共用外部锁，并释放实际取得的对象', async () => {
    const original = { busy: false }; let lock = original;
    let cancel!: (value: null) => void;
    const host = { lock: () => lock, check: async () => true, interact: () => new Promise<null>(resolve => { cancel = resolve; }), invoke: vi.fn() };
    const first = createListButtonExecutor(host).execute(button);
    await Promise.resolve();
    expect(original.busy).toBe(true);
    expect(await createListButtonExecutor(host).execute(button)).toEqual({ status: 'busy' });
    lock = { busy: true }; cancel(null); await first;
    expect(original.busy).toBe(false); expect(lock.busy).toBe(true);
  });
  it('上下文变化使交互失效并清空草稿', async () => {
    let context = 'a';
    const invoke = vi.fn();
    const executor = createListButtonExecutor({ context: () => context, check: async () => true, interact: async () => { context = 'b'; return { reason: '旧输入' }; }, invoke });
    await expect(executor.execute(button)).rejects.toThrow('FORM_LIST_CONTEXT_CHANGED');
    expect(invoke).not.toHaveBeenCalled();
    expect(executor.input(button.id)).toEqual({});
  });
  it('一个成功效果失败不阻断其他宿主效果', async () => {
    const refresh = vi.fn(); const close = vi.fn();
    const executor = createListButtonExecutor({ check: async () => true, interact: async () => ({}), invoke: async () => ({ status: 'success' }), clearSelection: () => { throw Error('清空失败'); }, close, refresh });
    expect(await executor.execute({ ...button, success: { clearSelection: true, close: true, refresh: true } })).toMatchObject({ status: 'success', effectError: expect.any(Error) });
    expect(close).toHaveBeenCalledOnce(); expect(refresh).toHaveBeenCalledOnce();
  });
  it('目录必须匹配版本、权限、位置和批量能力，请求仅发送协议字段', () => {
    const action: FormListButton = { id: 'approve', label: '批准', permission: 'orders:approve', action: { type: 'registered', key: 'approve', capabilityVersion: 'v1' } };
    const context = { formKey: 'orders', schemaHash: 'hash', location: 'toolbar' as const, ids: [1, 2] };
    const catalog = { schemaHash: 'hash', actions: { approve: { permission: 'orders:approve', capabilityVersion: 'v1', locations: ['toolbar'], targets: ['selection'], batch: true, effect: 'write', resultContract: 'json' } } };
    expect(registeredListButtonAvailable(action, context, catalog, () => true)).toBe(true);
    expect(registeredListButtonAvailable(action, { ...context, ids: [] }, catalog, () => true)).toBe(false);
    expect(registeredListButtonAvailable(action, context, { ...catalog, schemaHash: 'old' }, () => true)).toBe(false);
    expect(registeredListButtonAvailable(action, context, catalog, () => false)).toBe(false);
    expect(listButtonRequest(action, context, {}, 'request', 'token')).toEqual({ buttonId: 'approve', location: 'toolbar', schemaHash: 'hash', ids: [1, 2], idempotencyKey: 'request', confirmation: 'token' });
    expect(listButtonRequest(action, { ...context, location: 'categoryNode', ids: [], category: { id: 3 }, sourceSchemaHash: 'source' }, {}, 'request')).toMatchObject({ ids: [], category: { id: 3 }, sourceSchemaHash: 'source' });
  });
  it('锁覆盖交互全过程，取消无副作用', async () => {
    let cancel!: (value: null) => void;
    const invoke = vi.fn();
    const executor = createListButtonExecutor({ check: async () => true, interact: () => new Promise((resolve) => { cancel = resolve; }), invoke });
    const first = executor.execute(button);
    await Promise.resolve();
    expect(await executor.execute(button)).toEqual({ status: 'busy' });
    cancel(null);
    expect(await first).toEqual({ status: 'cancelled' });
    expect(invoke).not.toHaveBeenCalled();
    expect(executor.busy()).toBe(false);
  });
  it('交互后重新检查权限，撤销时不发送请求', async () => {
    const check = vi.fn().mockResolvedValueOnce(true).mockResolvedValueOnce(false);
    const invoke = vi.fn();
    const executor = createListButtonExecutor({ check, interact: async () => ({}), invoke });
    await expect(executor.execute(button)).rejects.toThrow('FORM_LIST_BUTTON_FORBIDDEN');
    expect(invoke).not.toHaveBeenCalled();
  });
  it('成功后刷新失败单独返回，不重发动作', async () => {
    const invoke = vi.fn().mockResolvedValue({ status: 'success' });
    const executor = createListButtonExecutor({ check: async () => true, interact: async () => ({}), invoke, refresh: async () => { throw new Error('离线'); } });
    expect(await executor.execute(button)).toMatchObject({ status: 'success', effectError: expect.any(Error) });
    expect(invoke).toHaveBeenCalledTimes(1);
  });
  it('服务端确认使用同一请求键，取消不执行确认请求', async () => {
    const invoke = vi.fn().mockResolvedValue({ status: 'confirmation_required', confirmation: 'token' });
    const executor = createListButtonExecutor({ check: async () => true, interact: async () => ({}), invoke, confirm: async () => false });
    expect(await executor.execute(button)).toEqual({ status: 'cancelled' });
    expect(invoke).toHaveBeenCalledTimes(1);
  });
  it('失败保留输入，不自动重试', async () => {
    const invoke = vi.fn().mockRejectedValue(new Error('FORM_ACTION_TIMEOUT'));
    const executor = createListButtonExecutor({ check: async () => true, interact: async () => ({ reason: '保留' }), invoke });
    await expect(executor.execute(button)).rejects.toThrow('FORM_ACTION_TIMEOUT');
    expect(executor.input('reload')).toEqual({ reason: '保留' });
    expect(invoke).toHaveBeenCalledTimes(1);
    expect(executor.busy()).toBe(false);
  });
});
