import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clearAsyncValidatorCache, createAsyncValidatorRegistry, mapFieldErrors } from './asyncValidatorRegistry';

const deferred = <T>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
};

describe('异步 validator 治理', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    clearAsyncValidatorCache();
  });
  afterEach(() => vi.useRealTimers());

  it('只允许注册 key，拒绝未知 validator', async () => {
    const registry = createAsyncValidatorRegistry({ unique: async () => ({ valid: true }) });
    expect(() => registry.rule('missing', {})).toThrow('FORM_ASYNC_VALIDATOR_NOT_REGISTERED');
  });

  it('防抖阶段被替换的验证会正常结束且不发请求', async () => {
    const handler = vi.fn(async () => ({ valid: true }));
    const registry = createAsyncValidatorRegistry({ unique: handler });
    const validator = registry.rule('unique', { debounce: 100 });
    const oldValidation = validator({}, 'old');
    const newValidation = validator({}, 'new');
    await vi.advanceTimersByTimeAsync(100);
    await expect(oldValidation).resolves.toBeUndefined();
    await expect(newValidation).resolves.toBeUndefined();
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('防抖、取消旧验证并只接受最后结果', async () => {
    const oldResult = deferred<{ valid: boolean; message?: string }>();
    const newResult = deferred<{ valid: boolean; message?: string }>();
    const signals: AbortSignal[] = [];
    const registry = createAsyncValidatorRegistry({
      unique: ({ value, signal }) => {
        signals.push(signal);
        return value === 'new' ? newResult.promise : oldResult.promise;
      }
    });
    const validator = registry.rule('unique', { debounce: 100 });
    const oldValidation = validator({}, 'old').catch((error) => error);
    await vi.advanceTimersByTimeAsync(100);
    const newValidation = validator({}, 'new');
    await vi.advanceTimersByTimeAsync(100);
    expect(signals[0]?.aborted).toBe(true);
    newResult.resolve({ valid: true });
    await expect(newValidation).resolves.toBeUndefined();
    oldResult.resolve({ valid: false, message: '旧错误' });
    await expect(oldValidation).resolves.toBeUndefined();
  });

  it('支持超时和 TTL 缓存', async () => {
    const handler = vi.fn(async () => ({ valid: false, message: '已存在' }));
    const registry = createAsyncValidatorRegistry({ unique: handler });
    const validator = registry.rule('unique', { debounce: 0, timeout: 1000, cacheTtl: 5000 });
    const first = expect(validator({}, 'same')).rejects.toThrow('已存在');
    await vi.advanceTimersByTimeAsync(0);
    await first;
    const second = expect(validator({}, 'same')).rejects.toThrow('已存在');
    await vi.advanceTimersByTimeAsync(0);
    await second;
    expect(handler).toHaveBeenCalledTimes(1);

    const timeoutRegistry = createAsyncValidatorRegistry({ timeout: () => new Promise(() => {}) });
    const timed = timeoutRegistry.rule('timeout', { debounce: 0, timeout: 50 });
    const result = expect(timed({}, 'value')).rejects.toThrow('FORM_ASYNC_VALIDATOR_TIMEOUT');
    await vi.advanceTimersByTimeAsync(50);
    await result;
  });

  it('将 fieldErrors path/message 映射为 Element Plus 字段错误', () => {
    expect(mapFieldErrors([
      { path: 'profile.email', message: '邮箱已存在' },
      { path: '/items/0/name', message: '名称重复' }
    ])).toEqual({ 'profile.email': '邮箱已存在', 'items.0.name': '名称重复' });
  });
});
