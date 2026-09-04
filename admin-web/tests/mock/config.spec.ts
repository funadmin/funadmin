import { describe, expect, it } from 'vitest';
import { configMockHandlers } from '@/mock/modules/config';
import type { MockContext, MockMethod, MockRoute } from '@/mock/types';

function route(method: MockMethod, pattern: string): MockRoute {
  const item = configMockHandlers.find((handler) => handler.method === method && String(handler.url) === pattern);
  if (!item) throw new Error(`Mock route not found: ${method} ${pattern}`);
  return item;
}

function context(method: MockMethod, body: Record<string, unknown> = {}, pathParams: Record<string, string> = {}): MockContext {
  return { url: '', method, params: {}, body, pathParams, headers: {} };
}

describe('mock/config safeguards', () => {
  it('拒绝删除系统配置', async () => {
    const response = await route('DELETE', '/system/config').handler(context('DELETE', { ids: [1] }));
    expect(response.code).toBe(422);
    expect(response.msg).toContain('系统配置');
  });

  it('拒绝保存选项范围外的值', async () => {
    const response = await route('PUT', '/^\\/system\\/config\\/(\\d+)\\/value$/').handler(context('PUT', { value: 'invalid' }, { id: '3' }));
    expect(response.code).toBe(422);
    expect(response.msg).toContain('允许的选项范围');
  });

  it('拒绝删除仍被配置项引用的分组', async () => {
    const response = await route('DELETE', '/^\\/system\\/config-group\\/(\\d+)$/').handler(context('DELETE', {}, { id: '1' }));
    expect(response.code).toBe(422);
    expect(response.msg).toContain('仍被配置项引用');
  });
});
