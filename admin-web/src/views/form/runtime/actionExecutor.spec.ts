import { describe, expect, it, vi } from 'vitest';
import { ACTION_TYPES, executeActionChain, type ActionHandlers } from './actionExecutor';

const createHandlers = (): ActionHandlers => ({
  setValue: vi.fn(), copyValue: vi.fn(), clearValue: vi.fn(), show: vi.fn(), hide: vi.fn(),
  enable: vi.fn(), disable: vi.fn(), setRequired: vi.fn(), validate: vi.fn(), request: vi.fn(),
  notify: vi.fn(), openDialog: vi.fn(), navigate: vi.fn(), submit: vi.fn(), reset: vi.fn()
});

describe('FormSchema v2 动作执行器', () => {
  it('动作白名单完整且按声明顺序等待 handlers', async () => {
    expect(ACTION_TYPES).toEqual([
      'setValue', 'copyValue', 'clearValue', 'show', 'hide', 'enable', 'disable', 'setRequired',
      'validate', 'request', 'notify', 'openDialog', 'navigate', 'submit', 'reset'
    ]);
    const calls: string[] = [];
    const handlers = createHandlers();
    handlers.setValue = vi.fn(async () => { calls.push('setValue'); });
    handlers.notify = vi.fn(() => { calls.push('notify'); });

    await executeActionChain([
      { type: 'setValue', target: 'name', value: 'Alice' },
      { type: 'notify', message: '已更新' }
    ], { values: {} }, handlers);

    expect(calls).toEqual(['setValue', 'notify']);
  });

  it('将动作与只读上下文交给注入 handler', async () => {
    const handlers = createHandlers();
    const context = { values: { source: 'Alice' }, event: { type: 'change' } };
    const action = { type: 'copyValue' as const, source: 'source', target: 'name' };

    await executeActionChain([action], context, handlers);

    expect(handlers.copyValue).toHaveBeenCalledWith(action, context);
  });

  it('拒绝未知动作且不执行任何 handler', async () => {
    const handlers = createHandlers();
    await expect(executeActionChain([{ type: 'javascript' } as never], { values: {} }, handlers))
      .rejects.toThrow('动作未注册：javascript');
    expect(Object.values(handlers).every((handler) => !vi.mocked(handler).mock.calls.length)).toBe(true);
  });

  it('在执行前拒绝超过最大步数的动作链', async () => {
    const handlers = createHandlers();
    const actions = Array.from({ length: 4 }, () => ({ type: 'reset' as const }));
    await expect(executeActionChain(actions, { values: {} }, handlers, { maxSteps: 3 }))
      .rejects.toThrow('动作链超过最大步数：3');
    expect(handlers.reset).not.toHaveBeenCalled();
  });

  it.each([
    { type: 'request', concurrency: 'latest' },
    { type: 'request', key: 'profile.load' },
    { type: 'request', key: 'profile.load', concurrency: 'unknown' }
  ])('拒绝未注册 key 或无效并发策略的 request：$type', async (action) => {
    const handlers = createHandlers();
    await expect(executeActionChain([action as never], { values: {} }, handlers, {
      requestKeys: ['profile.load']
    })).rejects.toThrow();
    expect(handlers.request).not.toHaveBeenCalled();
  });

  it.each(['parallel', 'latest', 'queue', 'drop'] as const)('允许 request 使用已注册 key 和 %s 并发策略', async (concurrency) => {
    const handlers = createHandlers();
    const action = { type: 'request' as const, key: 'profile.load', concurrency };
    await executeActionChain([action], { values: {} }, handlers, { requestKeys: ['profile.load'] });
    expect(handlers.request).toHaveBeenCalledWith(action, expect.objectContaining({ values: {} }));
  });

  it('drop 丢弃同 key 的并发请求，latest 取消前序请求', async () => {
    const handlers = createHandlers();
    let release: () => void = () => {};
    handlers.request = vi.fn((_action, context) => new Promise<void>((resolve) => {
      release = () => resolve();
      context.signal && (context.signal as AbortSignal).addEventListener('abort', () => resolve(), { once: true });
    }));
    const drop = { type: 'request' as const, key: 'profile.load', concurrency: 'drop' as const };
    const first = executeActionChain([drop], { values: {} }, handlers, { requestKeys: ['profile.load'] });
    await Promise.resolve();
    await executeActionChain([drop], { values: {} }, handlers, { requestKeys: ['profile.load'] });
    expect(handlers.request).toHaveBeenCalledTimes(1);
    release();
    await first;

    const latest = { ...drop, concurrency: 'latest' as const };
    const previous = executeActionChain([latest], { values: {} }, handlers, { requestKeys: ['profile.load'] });
    await Promise.resolve();
    const current = executeActionChain([latest], { values: {} }, handlers, { requestKeys: ['profile.load'] });
    await Promise.resolve();
    expect(handlers.request).toHaveBeenCalledTimes(3);
    release();
    await Promise.all([previous, current]);
  });
});
