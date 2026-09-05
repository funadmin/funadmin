import { describe, expect, it, vi } from 'vitest';
import { confirmAction } from '@/views/system/plugin/pluginActions';

describe('插件操作确认', () => {
  it.each(['cancel', 'close'])('显式吞掉 %s 并返回 false', async (reason) => {
    const action = vi.fn();

    await expect(confirmAction(() => Promise.reject(reason), action)).resolves.toBe(false);
    expect(action).not.toHaveBeenCalled();
  });

  it('确认后执行操作并返回 true', async () => {
    const action = vi.fn().mockResolvedValue(undefined);

    await expect(confirmAction(() => Promise.resolve(), action)).resolves.toBe(true);
    expect(action).toHaveBeenCalledOnce();
  });

  it('非取消错误继续向上传递', async () => {
    const error = new Error('network');

    await expect(confirmAction(() => Promise.reject(error), vi.fn())).rejects.toBe(error);
  });
});
