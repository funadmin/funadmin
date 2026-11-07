import { describe, expect, it, vi } from 'vitest';
import { runSequential } from './batch';

describe('runSequential', () => {
  it('逐个执行；第 2 项抛错时第 3 项仍执行，结果顺序与输入一致', async () => {
    const calls: number[] = [];
    let running = 0;
    const progress = vi.fn();
    const results = await runSequential([1, 2, 3], async (item) => {
      running++;
      expect(running).toBe(1);
      calls.push(item);
      await Promise.resolve();
      running--;
      if (item === 2) throw new Error('第二项失败');
      return item * 10;
    }, progress);
    expect(calls).toEqual([1, 2, 3]);
    expect(results).toEqual([
      { item: 1, ok: true, value: 10 },
      { item: 2, ok: false, error: '第二项失败' },
      { item: 3, ok: true, value: 30 }
    ]);
    expect(progress.mock.calls.map(([done]) => done)).toEqual([1, 2, 3]);
  });

  it('把接口错误响应、字符串和未知异常转为可读的错误文本', async () => {
    const apiError = { code: 422, msg: '系统核心表，不可装载', data: { error: { code: 'X', requestId: 'r1', retryable: false, details: {} } } };
    const results = await runSequential(['a', 'b', 'c', 'd'], async (item) => {
      if (item === 'a') throw apiError;
      if (item === 'b') throw '字符串错误';
      if (item === 'c') throw { msg: '普通响应' };
      throw null;
    });
    expect(results.map((result) => (result.ok ? '' : result.error))).toEqual(['系统核心表，不可装载', '字符串错误', '普通响应', '请求失败']);
  });

  it('空列表直接返回空结果', async () => {
    const worker = vi.fn();
    expect(await runSequential([], worker)).toEqual([]);
    expect(worker).not.toHaveBeenCalled();
  });
});
