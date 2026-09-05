import { describe, expect, it } from 'vitest';
import { pluginMockHandlers } from '@/mock/modules/plugin';
import type { MockContext, MockMethod, MockRoute } from '@/mock/types';

function route(method: MockMethod, pattern: string): MockRoute {
  const item = pluginMockHandlers.find((handler) => handler.method === method && String(handler.url) === pattern);
  if (!item) throw new Error(`Mock route not found: ${method} ${pattern}`);
  return item;
}

const context = (method: MockMethod, params: Record<string, unknown>, pathParams: Record<string, string>): MockContext => ({ url: '', method, params, body: {}, pathParams, headers: {} });

describe('mock/plugin safeguards', () => {
  it('拒绝 purge 名称二次确认不匹配', async () => {
    const response = await route('DELETE', '/^\\/system\\/plugin\\/([a-z][a-z0-9]*)\\/uninstall$/').handler(context('DELETE', { purge: true, purgeConfirm: 'other' }, { name: 'demo' }));
    expect(response.code).toBe(422);
  });

  it('提供 enabled modules 空列表避免影响核心路由', async () => {
    const response = await route('GET', '/system/plugin/modules/enabled').handler(context('GET', {}, {}));
    expect(response.data).toEqual([]);
  });
});
