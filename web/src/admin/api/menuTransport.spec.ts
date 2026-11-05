import axios, { type InternalAxiosRequestConfig } from 'axios';
import { afterEach, describe, expect, it } from 'vitest';
import { service } from '@/utils/http';
import { memberApi } from '@/api/system/member';
import { attachmentApi } from '@/api/system/attachment';
import { blacklistApi } from '@/api/system/blacklist';

const originalAdapter = service.defaults.adapter;
afterEach(() => { service.defaults.adapter = originalAdapter; });

describe('菜单 API 实际 HTTP 序列化', () => {
  it('共享 Axios 客户端发送顶层筛选字段，保留零状态与回收站参数', async () => {
    const requests: InternalAxiosRequestConfig[] = [];
    service.defaults.adapter = async config => {
      requests.push(config);
      return { data: { code: 200, data: { list: [], total: 0, page: 2, pageSize: 10 } }, status: 200, statusText: 'OK', headers: {}, config };
    };
    await memberApi.list({ page: 2, pageSize: 10, groupId: 4, status: 0, recycled: 1 });
    await attachmentApi.list({ page: 2, pageSize: 10, groupId: 4, mimeType: 'image' });
    await blacklistApi.list({ page: 2, pageSize: 10, status: 0, ip: '203.0.113' });
    const urls = requests.map(config => new URL(axios.getUri(config), 'http://localhost'));
    expect(urls.map(url => url.pathname)).toEqual(['/admin/system/member', '/admin/system/attachment', '/admin/system/blacklist']);
    for (const url of urls) {
      expect(url.searchParams.get('page')).toBe('2');
      expect(url.searchParams.get('pageSize')).toBe('10');
      expect([...url.searchParams.keys()].some(key => key.startsWith('params['))).toBe(false);
    }
    expect(urls[0].searchParams.get('status')).toBe('0');
    expect(urls[0].searchParams.get('recycled')).toBe('1');
    expect(urls[1].searchParams.get('mimeType')).toBe('image');
    expect(urls[2].searchParams.get('ip')).toBe('203.0.113');
  });
});
