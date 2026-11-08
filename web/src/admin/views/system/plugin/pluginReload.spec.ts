import { describe, expect, it, vi } from 'vitest';
import { PLUGIN_RELOAD_SETTLE_MS, afterPluginLifecycle, notifyPluginReload } from './pluginReload';

const notifier = () => ({ success: vi.fn(), warning: vi.fn() });

describe('插件生命周期后端重载提示', () => {
  it('后端已请求重载时提示稍后可用，并等待约 1.5 秒再刷新路由与菜单', async () => {
    const messages = notifier();
    const order: string[] = [];
    const wait = vi.fn(async (ms: number) => { order.push(`wait:${ms}`); });
    const refresh = vi.fn(async () => { order.push('refresh'); });
    await afterPluginLifecycle({ reloadRequested: true, reloadReason: null }, refresh, { notifier: messages, wait });
    expect(messages.success).toHaveBeenCalledWith('后端已重新加载，新页面稍后可用');
    expect(messages.warning).not.toHaveBeenCalled();
    expect(PLUGIN_RELOAD_SETTLE_MS).toBe(1500);
    expect(order).toEqual(['wait:1500', 'refresh']);
  });

  it('后端未能重载时提示手动重启并附带原因，不等待直接刷新', async () => {
    const messages = notifier();
    const wait = vi.fn(async () => undefined);
    const refresh = vi.fn(async () => undefined);
    await afterPluginLifecycle({ reloadRequested: false, reloadReason: 'pid_file_missing' }, refresh, { notifier: messages, wait });
    expect(messages.warning).toHaveBeenCalledWith('请重启后端服务使新接口生效（原因：pid_file_missing）');
    expect(messages.success).not.toHaveBeenCalled();
    expect(wait).not.toHaveBeenCalled();
    expect(refresh).toHaveBeenCalledOnce();
  });

  it('响应不含重载字段（迁移等非路由操作）时不提示', () => {
    const messages = notifier();
    expect(notifyPluginReload(true, messages)).toBe(false);
    expect(notifyPluginReload(undefined, messages)).toBe(false);
    expect(messages.success).not.toHaveBeenCalled();
    expect(messages.warning).not.toHaveBeenCalled();
  });
});
