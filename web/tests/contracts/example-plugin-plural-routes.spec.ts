import { beforeEach, describe, expect, it, vi } from 'vitest';

const request = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));
vi.mock('@/utils/http', () => ({ default: request }));

import { testApi } from '@/plugins/example/test/api';

describe('example 插件 test 资源 API 使用复数插件路由', () => {
  beforeEach(() => vi.clearAllMocks());

  it('所有端点都使用复数 /plugins/ 路由前缀', async () => {
    await testApi.list({ page: 1, pageSize: 20 });
    await testApi.detail(7);
    await testApi.create({ field1: 'a' });
    await testApi.update(7, { field1: 'b' });
    await testApi.remove(7);
    await testApi.restore(7);
    await testApi.forceDelete(7);
    await testApi.removeMany([7]);
    await testApi.restoreMany([7]);
    await testApi.forceDeleteMany([7]);
    await testApi.importRows([{ field1: 'c' }]);
    await testApi.exportRows({});

    const urls = [request.get, request.post, request.put, request.delete].flatMap(fn => fn.mock.calls.map(call => String(call[0])));
    expect(urls).toHaveLength(12);
    expect(urls.every(url => url.startsWith('/plugins/example/test'))).toBe(true);
    expect(request.get).toHaveBeenCalledWith('/plugins/example/test/7');
    expect(request.put).toHaveBeenCalledWith('/plugins/example/test/7', { field1: 'b' });
    expect(request.delete).toHaveBeenCalledWith('/plugins/example/test/7');
    expect(request.post).toHaveBeenCalledWith('/plugins/example/test/7/restore');
    expect(request.delete).toHaveBeenCalledWith('/plugins/example/test/7/destroy');
  });
});
