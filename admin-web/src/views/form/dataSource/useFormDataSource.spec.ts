import { effectScope, nextTick, reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clearFormDataSourceCache, useFormDataSource, type FormDataSourceRequest } from './useFormDataSource';

const flush = async () => {
  await vi.runAllTimersAsync();
  await nextTick();
};

const deferred = <T>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
};

describe('useFormDataSource', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    clearFormDataSourceCache();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('防抖请求、取消前次请求且只让最后请求生效', async () => {
    const first = deferred<{ options: Array<{ label: string; value: number }> }>();
    const second = deferred<{ options: Array<{ label: string; value: number }> }>();
    const signals: AbortSignal[] = [];
    const request: FormDataSourceRequest = vi.fn((_key, _field, params, signal) => {
      signals.push(signal);
      return params.keyword === 'new' ? second.promise : first.promise;
    });
    const scope = effectScope();
    const source = scope.run(() => useFormDataSource({
      formKey: 'member', field: 'owner_id', definition: { kind: 'user' }, values: reactive({}), request, debounceMs: 100
    }))!;

    source.search('old');
    await flush();
    source.search('new');
    await flush();
    expect(signals[0]?.aborted).toBe(true);

    second.resolve({ options: [{ label: '新', value: 2 }] });
    await vi.waitFor(() => expect(source.options.value).toEqual([{ label: '新', value: 2 }]));
    first.resolve({ options: [{ label: '旧', value: 1 }] });
    await Promise.resolve();
    expect(source.options.value).toEqual([{ label: '新', value: 2 }]);
    scope.stop();
  });

  it.each([
    ['clear', 9, null],
    ['retain', 9, 9],
    ['revalidate', 2, 2],
    ['revalidate', 9, null]
  ] as const)('依赖字段变化后执行 %s 旧值策略', async (staleValue, initial, expected) => {
    const values = reactive<Record<string, unknown>>({ department_id: 1, owner_id: initial });
    const request: FormDataSourceRequest = vi.fn(async () => ({ options: [{ label: '有效', value: 2 }] }));
    const scope = effectScope();
    scope.run(() => useFormDataSource({
      formKey: 'member', field: 'owner_id',
      definition: { kind: 'user', dependsOn: ['department_id'], staleValue },
      values, request, debounceMs: 0
    }));

    await flush();
    values.department_id = 2;
    await nextTick();
    await flush();
    expect(values.owner_id).toBe(expected);
    scope.stop();
  });

  it('搜索和分页参数进入请求，换搜索词时重置页码', async () => {
    const request: FormDataSourceRequest = vi.fn(async () => ({ options: [], total: 30 }));
    const scope = effectScope();
    const source = scope.run(() => useFormDataSource({
      formKey: 'member', field: 'owner_id',
      definition: { kind: 'user', pagination: { pageSize: 20 } },
      values: reactive({}), request, debounceMs: 0
    }))!;

    await flush();
    source.setPage(2);
    await flush();
    source.search('张');
    await flush();
    expect(request).toHaveBeenLastCalledWith('member', 'owner_id', { keyword: '张', page: 1, pageSize: 20 }, expect.any(AbortSignal));
    expect(source.total.value).toBe(30);
    scope.stop();
  });

  it('TTL 内复用缓存并对相同在途请求去重', async () => {
    const pending = deferred<{ options: Array<{ label: string; value: number }> }>();
    const request: FormDataSourceRequest = vi.fn(() => pending.promise);
    const scopes = [effectScope(), effectScope()];
    const sources = scopes.map((scope) => scope.run(() => useFormDataSource({
      formKey: 'member', field: 'owner_id', definition: { kind: 'user', cacheTtl: 1000 },
      values: reactive({}), request, debounceMs: 0
    }))!);

    await vi.runAllTimersAsync();
    expect(request).toHaveBeenCalledTimes(1);
    pending.resolve({ options: [{ label: '用户', value: 1 }] });
    await nextTick();
    expect(sources[0].options.value).toEqual(sources[1].options.value);

    await sources[0].refresh();
    expect(request).toHaveBeenCalledTimes(1);
    scopes.forEach((scope) => scope.stop());
  });
});
