import { describe, expect, it } from 'vitest';
import { pluginMockHandlers } from '@/mock/modules/plugin';
import type { MockContext, MockMethod, MockRoute } from '@/mock/types';
import { getAdminMenuTreeSeed } from '@/mock/data/adminSeed';

function route(method: MockMethod, pattern: string): MockRoute {
  const item = pluginMockHandlers.find((handler) => handler.method === method && String(handler.url) === pattern);
  if (!item) throw new Error(`Mock route not found: ${method} ${pattern}`);
  return item;
}

const context = (method: MockMethod, params: Record<string, unknown>, pathParams: Record<string, string>): MockContext => ({ url: '', method, params, body: {}, pathParams, headers: {} });

describe('mock/plugin safeguards', () => {
  it('卸载保留数据且独立 purge 拒绝名称二次确认不匹配', async () => {
    const uninstall = await route('DELETE', '/^\\/system\\/plugin\\/([a-z][a-z0-9]*)\\/uninstall$/').handler(context('DELETE', {}, { name: 'demo' }));
    expect(uninstall.code).toBe(200);

    const purge = await route('DELETE', '/^\\/system\\/plugin\\/([a-z][a-z0-9]*)\\/purge$/').handler(context('DELETE', { purgeConfirm: 'other' }, { name: 'demo' }));
    expect(purge.code).toBe(422);
  });

  it('更新检查返回真实 UpdateCheck 数组结构', async () => {
    const response = await route('POST', '/system/plugin/market/check-updates').handler(
      context('POST', {}, {})
    );
    expect(response.data).toEqual([{
      name: 'demo',
      installedVersion: '1.0.0',
      latestVersion: '1.1.0',
      updateAvailable: true
    }]);
  });

  it('提供 enabled modules 空列表避免影响核心路由', async () => {
    const response = await route('GET', '/system/plugin/modules/enabled').handler(context('GET', {}, {}));
    expect(response.data).toEqual([]);
  });

  it('在 mock 菜单中提供可访问的插件中心', () => {
    const system = getAdminMenuTreeSeed().find((item) => item.routeName === 'System');
    expect(system?.children?.some((item) => item.routeName === 'SystemPlugin' && item.component === 'system/plugin/index')).toBe(true);
  });
});
