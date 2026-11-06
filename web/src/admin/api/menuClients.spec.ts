import { beforeEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const calls = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));
vi.mock('@/utils/http', () => ({ default: calls }));
import { memberApi } from '@/api/system/member';
import { attachmentApi } from '@/api/system/attachment';
import { blacklistApi } from '@/api/system/blacklist';

beforeEach(() => vi.clearAllMocks());

describe('菜单页面遵循后端查询协议', () => {
  it('会员列表与导出直接发送分页筛选字段', () => {
    const query = { page: 3, pageSize: 10, keyword: 'reader', groupId: 2, recycled: 1 as const };
    memberApi.list(query);
    expect(calls.get).toHaveBeenLastCalledWith('/system/member', query);
    memberApi.exportRows({ groupId: 2, recycled: 1 });
    expect(calls.get).toHaveBeenLastCalledWith('/system/member/export', { groupId: 2, recycled: 1 });
  });

  it('附件列表直接发送分组与文件类型', () => {
    const query = { page: 2, pageSize: 10, groupId: 4, mimeType: 'image' as const };
    attachmentApi.list(query);
    expect(calls.get).toHaveBeenLastCalledWith('/system/attachment', query);
  });

  it('黑名单列表与导出直接发送 IP 和状态', () => {
    const query = { page: 2, pageSize: 10, ip: '127.0.0.1', status: 0 as const };
    blacklistApi.list(query);
    expect(calls.get).toHaveBeenLastCalledWith('/system/blacklist', query);
    blacklistApi.exportRows({ ip: '127.0.0.1', status: 0 });
    expect(calls.get).toHaveBeenLastCalledWith('/system/blacklist/export', { ip: '127.0.0.1', status: 0 });
  });

  it('默认启动同样连接当前 Webman 后端而不是已退役的 8000 服务', () => {
    const env = readFileSync(resolve(process.cwd(), '.env.example'), 'utf8');
    expect(env).toContain('VITE_APP_PROXY_TARGET=http://127.0.0.1:8787');
    expect(env).toContain('VITE_APP_BACKEND=webman');
  });
});
