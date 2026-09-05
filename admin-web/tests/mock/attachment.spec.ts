import { describe, expect, it } from 'vitest';
import { attachmentMockHandlers } from '@/mock/modules/attachment';
import type { MockContext, MockMethod, MockRoute } from '@/mock/types';

function findRoute(method: MockMethod, pattern: string): MockRoute {
  const route = attachmentMockHandlers.find((item) => item.method === method && String(item.url) === pattern);
  if (!route) throw new Error(`Mock route not found: ${method} ${pattern}`);
  return route;
}

function context(pathParams: Record<string, string>, body: Record<string, unknown> = {}): MockContext {
  return { url: '', method: 'PUT', params: {}, body, pathParams, headers: {} };
}

describe('mock/attachment group safeguards', () => {
  it('拒绝把父分组移动到自己的下级', async () => {
    const route = findRoute('PUT', '/^\\/system\\/attachment-group\\/(\\d+)$/');
    const response = await route.handler(context({ id: '1' }, { parentId: 2, name: '默认' }));

    expect(response.code).toBe(422);
    expect(response.msg).toContain('自身或下级');
  });

  it('拒绝删除默认附件分组', async () => {
    const route = findRoute('DELETE', '/^\\/system\\/attachment-group\\/(\\d+)$/');
    const response = await route.handler({ ...context({ id: '1' }), method: 'DELETE' });

    expect(response.code).toBe(422);
    expect(response.msg).toContain('默认附件分组');
  });
});
