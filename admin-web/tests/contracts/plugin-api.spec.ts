import { describe, expect, it, vi } from 'vitest';
import http from '@/utils/http';
import { pluginApi } from '@/api/plugin';

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

  it('purge 卸载发送独立二次确认字段', async () => {
    await pluginApi.uninstall('demo', true, 'demo');
    expect(http.delete).toHaveBeenCalledWith(
      '/system/plugin/demo/uninstall',
      { purge: true, purgeConfirm: 'demo' },
      expect.any(Object)
    );
  });
});
