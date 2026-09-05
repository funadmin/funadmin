import type { EnabledPluginModule, PluginItem } from '@/api/plugin';
import type { MockRoute } from '../types';
import { fail, ok, page } from '../types';

let account: Record<string, unknown> | null = null;
const initialInstalled = (): PluginItem[] => [{ name: 'demo', title: '演示插件', version: '1.0.0', latestVersion: '1.1.0', dbVersion: '1.0.0', state: 'disabled', dependencies: {}, migrationPending: false, lastError: '', source: 'installed' }];
const installed: PluginItem[] = initialInstalled();

export function resetPluginMockState(): void {
  account = null;
  installed.splice(0, installed.length, ...initialInstalled());
}
const moduleHash = 'a'.repeat(64);

function installedPlugin(name: string): PluginItem | undefined {
  return installed.find((item) => item.name === name);
}

function enabledModules(): EnabledPluginModule[] {
  return installed.filter((item) => item.state === 'enabled').map((item) => ({
    name: item.name,
    version: item.version,
    hash: moduleHash,
    entryUrl: `/plugin-assets/${item.name}/entry.js?v=${moduleHash}`,
    routes: [{ path: `/plugin/${item.name}/index`, name: `Plugin_${item.name}`, component: 'Index', meta: { title: item.title } }]
  }));
}
const local = [{ name: 'localdemo', title: '本地示例', version: '1.0.0', latestVersion: '', dbVersion: '', state: 'discovered', dependencies: {}, migrationPending: false, lastError: '', source: 'local' }];
const market = [{ id: 1, name: 'marketdemo', title: '市场示例', description: '云市场插件', author: 'FunAdmin', versions: [
  { id: 1, pluginName: 'marketdemo', version: '1.0.0', changelog: '初始版本', compatible: true },
  { id: 2, pluginName: 'marketdemo', version: '1.1.0', changelog: '功能更新', compatible: true }
] }];

export const pluginMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/system/plugin/account/current', handler: () => ok(account) },
  { method: 'POST', url: '/system/plugin/account/login', handler: ({ body }) => { account = { id: 1, username: body.account, nickname: body.account, avatar: '' }; return ok(account); } },
  { method: 'POST', url: '/system/plugin/account/logout', handler: () => { account = null; return ok({ authenticated: false }); } },
  { method: 'GET', url: '/system/plugin/market/categories', handler: () => ok([{ id: 1, name: '工具' }]) },
  { method: 'GET', url: '/system/plugin/market/search', handler: () => ok(page(market, market.length, 1, 20)) },
  { method: 'GET', url: /^\/system\/plugin\/market\/([a-z][a-z0-9]*)\/versions$/, paramNames: ['name'], handler: () => ok(market[0].versions) },
  { method: 'GET', url: /^\/system\/plugin\/market\/([a-z][a-z0-9]*)$/, paramNames: ['name'], handler: ({ pathParams }) => ok(market.find((item) => item.name === pathParams.name) || market[0]) },
  {
    method: 'POST',
    url: '/system/plugin/market/check-updates',
    handler: ({ body }) => ok(((Array.isArray(body.installed) && body.installed.length > 0) ? body.installed : installed).map((item: PluginItem) => {
      const latestVersion = '1.1.0';
      return {
        name: item.name,
        installedVersion: item.version,
        latestVersion,
        updateAvailable: item.version !== latestVersion
      };
    }))
  },
  { method: 'GET', url: '/system/plugin/local/discovered', handler: () => ok(local) },
  { method: 'GET', url: '/system/plugin/local/installed', handler: () => ok(installed) },
  { method: 'POST', url: '/system/plugin/local/install', handler: () => ok({ installed: true }) },
  { method: 'GET', url: '/system/plugin/modules/enabled', handler: () => ok(enabledModules()) },
  { method: 'GET', url: /^\/system\/plugin\/local\/([a-z][a-z0-9]*)$/, paramNames: ['name'], handler: () => ok(local[0]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/config$/, paramNames: ['name'], handler: () => ok({ enabled: { title: '启用功能', type: 'switch', value: true } }) },
  { method: 'PUT', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/config$/, paramNames: ['name'], handler: () => ok(true) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/history$/, paramNames: ['name'], handler: () => ok([]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/operations$/, paramNames: ['name'], handler: () => ok([]) },
  {
    method: 'POST',
    url: /^\/system\/plugin\/cloud\/([a-z][a-z0-9]*)\/install$/,
    paramNames: ['name'],
    handler: ({ body, pathParams }) => {
      if (!body.version) return fail('version 必填', 422);
      if (!account) return fail('请先登录云账号', 401);
      if (installedPlugin(pathParams.name)) return fail('插件已安装', 409);
      const marketplace = market.find((item) => item.name === pathParams.name);
      if (!marketplace) return fail('市场插件不存在', 404);
      installed.push({
        name: marketplace.name,
        title: marketplace.title,
        version: body.version,
        latestVersion: marketplace.versions[0]?.version || body.version,
        dbVersion: body.version,
        state: 'disabled',
        dependencies: {},
        migrationPending: false,
        lastError: '',
        source: 'cloud'
      });
      return ok(true);
    }
  },
  {
    method: 'POST',
    url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/(update|migrate|enable|disable)$/,
    paramNames: ['name', 'action'],
    handler: ({ body, pathParams }) => {
      const plugin = installedPlugin(pathParams.name);
      if (!plugin) return fail('插件尚未安装', 404);
      if (pathParams.action === 'enable') plugin.state = 'enabled';
      if (pathParams.action === 'disable') plugin.state = 'disabled';
      if (pathParams.action === 'migrate') {
        plugin.dbVersion = plugin.version;
        plugin.migrationPending = false;
      }
      if (pathParams.action === 'update') {
        if (!body.version) return fail('version 必填', 422);
        if (plugin.state !== 'disabled') return fail('请先禁用插件', 409);
        plugin.version = body.version;
        if (body.migrate !== false) plugin.dbVersion = body.version;
        plugin.migrationPending = body.migrate === false;
      }
      return ok(true);
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/uninstall$/,
    paramNames: ['name'],
    handler: ({ pathParams }) => {
      const index = installed.findIndex((item) => item.name === pathParams.name);
      if (index < 0) return fail('插件尚未安装', 404);
      if (installed[index].state !== 'disabled') return fail('请先禁用插件', 409);
      installed.splice(index, 1);
      return ok(true);
    }
  },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/purge$/, paramNames: ['name'], handler: ({ params, pathParams }) => params.purgeConfirm !== pathParams.name ? fail('二次确认失败', 422) : ok(true) },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/package$/, paramNames: ['name'], handler: () => ok(true) }
];
