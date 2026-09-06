import { beforeEach, describe, expect, it } from 'vitest';
import { pluginMockHandlers, resetPluginMockState } from '@/mock/modules/plugin';
import type { MockContext, MockMethod, MockRoute } from '@/mock/types';
import { getAdminMenuTreeSeed } from '@/mock/data/adminSeed';

function route(method: MockMethod, pattern: string): MockRoute {
  const item = pluginMockHandlers.find((handler) => handler.method === method && String(handler.url) === pattern);
  if (!item) throw new Error(`Mock route not found: ${method} ${pattern}`);
  return item;
}

const context = (
  method: MockMethod,
  params: Record<string, unknown>,
  pathParams: Record<string, string>,
  body: Record<string, unknown> = {}
): MockContext => ({ url: '', method, params, body, pathParams, headers: {} });

describe('mock/plugin safeguards', () => {
  beforeEach(resetPluginMockState);

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

  it('完成云账号登录、安装、启用、模块发布、更新、禁用与卸载状态流', async () => {
    const login = await route('POST', '/system/plugin/account/login').handler(
      context('POST', {}, {}, { account: 'developer', password: 'secret' })
    );
    expect(login.data.username).toBe('developer');

    const install = route('POST', '/^\\/system\\/plugin\\/cloud\\/([a-z][a-z0-9]*)\\/install$/');
    expect((await install.handler(context('POST', {}, { name: 'marketdemo' }, { version: '1.0.0' }))).code).toBe(200);

    const installed = route('GET', '/system/plugin/local/installed');
    expect((await installed.handler(context('GET', {}, {}))).data).toEqual(
      expect.arrayContaining([expect.objectContaining({ name: 'marketdemo', version: '1.0.0', state: 'disabled', source: 'cloud' })])
    );
    const updates = await route('POST', '/system/plugin/market/check-updates').handler(
      context('POST', {}, {}, { installed: [{ name: 'marketdemo', version: '1.0.0' }] })
    );
    expect(updates.data).toContainEqual({
      name: 'marketdemo',
      installedVersion: '1.0.0',
      latestVersion: '1.1.0',
      updateAvailable: true
    });

    const lifecycle = route('POST', '/^\\/system\\/plugin\\/([a-z][a-z0-9]*)\\/(update|migrate|enable|disable)$/');
    await lifecycle.handler(context('POST', {}, { name: 'marketdemo', action: 'enable' }));
    const modules = await route('GET', '/system/plugin/modules/enabled').handler(context('GET', {}, {}));
    expect(modules.data).toEqual([
      expect.objectContaining({
        name: 'marketdemo',
        version: '1.0.0',
        hash: expect.stringMatching(/^[a-f0-9]{64}$/),
        entryUrl: expect.stringMatching(/^\/plugin-assets\/marketdemo\/entry\.js\?v=[a-f0-9]{64}$/),
        routes: [expect.objectContaining({ path: '/plugin/marketdemo/index', name: 'Plugin_marketdemo' })]
      })
    ]);

    await lifecycle.handler(context('POST', {}, { name: 'marketdemo', action: 'disable' }));
    await lifecycle.handler(context('POST', {}, { name: 'marketdemo', action: 'update' }, { version: '1.1.0', migrate: true }));
    expect((await installed.handler(context('GET', {}, {}))).data).toEqual(
      expect.arrayContaining([expect.objectContaining({ name: 'marketdemo', version: '1.1.0', dbVersion: '1.1.0', state: 'disabled' })])
    );

    await lifecycle.handler(context('POST', {}, { name: 'marketdemo', action: 'enable' }));
    await lifecycle.handler(context('POST', {}, { name: 'marketdemo', action: 'disable' }));
    const uninstall = route('DELETE', '/^\\/system\\/plugin\\/([a-z][a-z0-9]*)\\/uninstall$/');
    await uninstall.handler(context('DELETE', {}, { name: 'marketdemo' }));
    expect((await installed.handler(context('GET', {}, {}))).data.some((item: { name: string }) => item.name === 'marketdemo')).toBe(false);
    expect((await route('GET', '/system/plugin/modules/enabled').handler(context('GET', {}, {}))).data).toEqual([]);
  });

  it('enabled module 仅返回动态 ESM 入口、哈希与路由描述', async () => {
    await route('POST', '/^\\/system\\/plugin\\/([a-z][a-z0-9]*)\\/(update|migrate|enable|disable)$/').handler(
      context('POST', {}, { name: 'demo', action: 'enable' })
    );
    const modules = await route('GET', '/system/plugin/modules/enabled').handler(context('GET', {}, {}));
    expect(modules.data[0]).toMatchObject({
      entryUrl: `/plugin-assets/demo/entry.js?v=${'a'.repeat(64)}`,
      hash: 'a'.repeat(64),
      routes: [expect.objectContaining({ path: '/plugin/demo/index', component: 'Index' })]
    });
  });

  it('在 mock 菜单中提供可访问的插件中心', () => {
    const system = getAdminMenuTreeSeed().find((item) => item.routeName === 'System');
    expect(system?.children?.some((item) => item.routeName === 'SystemPlugin' && item.component === 'system/plugin/index')).toBe(true);
  });
});
