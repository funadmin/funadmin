import { describe, expect, it, vi } from 'vitest';
import http from '@/utils/http';
import { pluginApi, type UpdateCheck } from '@/api/plugin';

vi.mock('@/utils/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn()
  }
}));

describe('pluginApi', () => {
  it('本地 ZIP 使用 multipart 上传且云安装指定版本', async () => {
    const file = new File(['zip'], 'demo.zip', { type: 'application/zip' });
    await pluginApi.installLocal(file);
    const form = vi.mocked(http.upload).mock.calls[0][1];
    expect(form.get('file')).toBe(file);

    await pluginApi.installCloud('demo', '1.2.3');
    expect(http.post).toHaveBeenCalledWith('/system/plugin/cloud/demo/install', { version: '1.2.3' }, expect.any(Object));
  });

  it('更新检查使用 UpdateCheck 数组契约', async () => {
    const checks: UpdateCheck[] = [{
      name: 'demo',
      installedVersion: '1.0.0',
      latestVersion: '1.1.0',
      updateAvailable: true
    }];
    vi.mocked(http.post).mockResolvedValueOnce(checks);

    await expect(pluginApi.checkUpdates([{ name: 'demo', version: '1.0.0' }])).resolves.toEqual(checks);
    expect(http.post).toHaveBeenCalledWith(
      '/system/plugin/market/check-updates',
      { installed: [{ name: 'demo', version: '1.0.0' }] }
    );
  });

  it('刷新、发现安装、本地更新与历史动作使用独立契约', async () => {
    await pluginApi.accountRefresh();
    expect(http.post).toHaveBeenCalledWith('/system/plugin/account/refresh', undefined, expect.any(Object));
    await pluginApi.installDiscovered('demo');
    expect(http.post).toHaveBeenCalledWith('/system/plugin/local/demo/install', undefined, expect.any(Object));
    const file = new File(['zip'], 'demo.zip', { type: 'application/zip' });
    await pluginApi.updateLocal('demo', file, false);
    expect(http.upload).toHaveBeenCalledWith('/system/plugin/local/demo/update', expect.any(FormData), expect.any(Object));
    await pluginApi.redeployHistory('demo', 3);
    expect(http.post).toHaveBeenCalledWith('/system/plugin/demo/history/3/redeploy', { migrate: false }, expect.any(Object));
    expect(pluginApi.historyDownloadUrl('demo', 3)).toBe('/system/plugin/demo/history/3/download');
    await pluginApi.recoveryInfo('demo');
    expect(http.get).toHaveBeenCalledWith('/system/plugin/demo/recovery');
  });

  it('卸载与 purge 使用独立端点和请求契约', async () => {
    await pluginApi.uninstall('demo');
    expect(http.delete).toHaveBeenCalledWith(
      '/system/plugin/demo/uninstall',
      undefined,
      expect.any(Object)
    );

    await pluginApi.purge('demo', 'demo');
    expect(http.delete).toHaveBeenCalledWith(
      '/system/plugin/demo/purge',
      { purgeConfirm: 'demo' },
      expect.any(Object)
    );
  });
});
