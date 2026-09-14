import { expect, it, vi } from 'vitest';
import { executeListResource } from './listResourceHost';
it('纯文本复制不执行 HTML，下载只调用授权导出', async () => {
  const copy = vi.fn(); const download = vi.fn();
  const host = { permission: () => true, copy, download, refresh: vi.fn() };
  await executeListResource({ type: 'copy', text: '<script>alert(1)</script>' }, host);
  expect(copy).toHaveBeenCalledWith('<script>alert(1)</script>');
  await executeListResource({ type: 'download', key: 'export' }, host);
  expect(download).toHaveBeenCalledOnce();
  await expect(executeListResource({ type: 'download', key: '/etc/passwd' }, host)).rejects.toThrow();
});
it('禁止未授权、重定向路由和外链地址篡改', async () => {
  const push = vi.fn(); const open = vi.fn();
  const router = { hasRoute: () => true, resolve: () => ({ matched: [{ redirect: '/evil' }] }), push };
  const host = { permission: () => true, router, open, resource: { type: 'external', permission: 'read', origin: 'https://example.org', path: '/help', query: [] } };
  await expect(executeListResource({ type: 'navigate', route: 'orders', permission: 'read', params: {}, query: {} }, host as any)).rejects.toThrow();
  for (const url of ['javascript:alert(1)', 'https://evil.org/help', 'https://example.org/redirect', 'https://example.org/help?url=https://evil.org']) await expect(executeListResource({ type: 'external', url, permission: 'read' }, host as any)).rejects.toThrow();
  await executeListResource({ type: 'external', url: 'https://example.org/help', permission: 'read' }, host as any);
  expect(open).toHaveBeenCalledWith('https://example.org/help', '_blank', 'noopener,noreferrer');
  expect(push).not.toHaveBeenCalled();
});
