import type { EnabledPluginModule, PluginItem } from '@/api/plugin';
import type { MockRoute } from '../types';
import { fail, ok, page } from '../types';

let account: Record<string, unknown> | null = null;
const initialInstalled = (): PluginItem[] => [{ code: 'demo', name: '演示插件', version: '1.0.0', latestVersion: '1.1.0', dbVersion: '1.0.0', state: 'disabled', dependencies: {}, migrationPending: false, lastError: '', source: 'installed', needsReinstall: false, operation: '', progress: 0 }];
const installed: PluginItem[] = initialInstalled();

export function resetPluginMockState(): void {
  account = null;
  installed.splice(0, installed.length, ...initialInstalled());
}
function installedPlugin(code: string): PluginItem | undefined {
  return installed.find((item) => item.code === code);
}

function enabledModules(): EnabledPluginModule[] {
  return installed.filter((item) => item.state === 'enabled').map((item) => ({
    code: item.code,
    version: item.version,
    components: { Index: 'Index.vue' },
    routes: [{ path: `/plugin/${item.code}/index`, name: `Plugin_${item.code}`, component: 'Index', meta: { title: item.name } }]
  }));
}
const local = [{ code: 'localdemo', name: '本地示例', version: '1.0.0', latestVersion: '', dbVersion: '', state: 'discovered', dependencies: {}, migrationPending: false, lastError: '', source: 'local' }];
const market = [{ id: 1, code: 'marketdemo', name: '市场示例', description: '云市场插件', author: 'FunAdmin', versions: [
  { id: 1, pluginCode: 'marketdemo', version: '1.0.0', changelog: '初始版本', compatible: true, requires: {}, compatibleRange: '^1.0', publishedAt: '2026-01-01T00:00:00Z', sha256: 'a'.repeat(64), signature: null, signatureAlgorithm: null, size: 1024 },
  { id: 2, pluginCode: 'marketdemo', version: '1.1.0', changelog: '功能更新', compatible: true, requires: {}, compatibleRange: '^1.0', publishedAt: '2026-02-01T00:00:00Z', sha256: 'b'.repeat(64), signature: null, signatureAlgorithm: null, size: 2048 }
] }];

export const pluginMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/system/plugin/account/current', handler: () => ok(account) },
  { method: 'POST', url: '/system/plugin/account/login', handler: ({ body }) => { account = { id: 1, username: body.account, nickname: body.account, avatar: '' }; return ok(account); } },
  { method: 'POST', url: '/system/plugin/account/refresh', handler: () => account ? ok(account) : fail('请先登录云账号', 401) },
  { method: 'POST', url: '/system/plugin/account/logout', handler: () => { account = null; return ok({ authenticated: false }); } },
  { method: 'GET', url: '/system/plugin/market/categories', handler: () => ok([{ id: 1, name: '工具' }]) },
  { method: 'GET', url: '/system/plugin/market/search', handler: () => ok(page(market, market.length, 1, 20)) },
  { method: 'GET', url: /^\/system\/plugin\/market\/([a-z][a-z0-9]*)\/versions$/, paramNames: ['code'], handler: () => ok(market[0].versions) },
  { method: 'GET', url: /^\/system\/plugin\/market\/([a-z][a-z0-9]*)$/, paramNames: ['code'], handler: ({ pathParams }) => ok(market.find((item) => item.code === pathParams.code) || market[0]) },
  {
    method: 'POST',
    url: '/system/plugin/market/check-updates',
    handler: ({ body }) => ok(((Array.isArray(body.installed) && body.installed.length > 0) ? body.installed : installed).map((item: PluginItem) => {
      const latestVersion = '1.1.0';
      return {
        code: item.code,
        installedVersion: item.version,
        latestVersion,
        updateAvailable: item.version !== latestVersion
      };
    }))
  },
  { method: 'GET', url: '/system/plugin/local/discovered', handler: () => ok(local) },
  { method: 'GET', url: '/system/plugin/local/installed', handler: () => ok(installed) },
  { method: 'POST', url: '/system/plugin/local/install', handler: () => ok({ installed: true }) },
  { method: 'POST', url: /^\/system\/plugin\/local\/([a-z][a-z0-9]*)\/install$/, paramNames: ['code'], handler: () => ok({ installed: true }) },
  { method: 'POST', url: /^\/system\/plugin\/local\/([a-z][a-z0-9]*)\/update$/, paramNames: ['code'], handler: () => ok({ updated: true }) },
  { method: 'GET', url: '/system/plugin/modules/enabled', handler: () => ok(enabledModules()) },
  { method: 'GET', url: /^\/system\/plugin\/local\/([a-z][a-z0-9]*)$/, paramNames: ['code'], handler: () => ok(local[0]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/config$/, paramNames: ['code'], handler: () => ok({ enabled: { title: '启用功能', type: 'switch', value: true } }) },
  { method: 'PUT', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/config$/, paramNames: ['code'], handler: () => ok(true) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/history$/, paramNames: ['code'], handler: ({ pathParams }) => ok([{ id: 1, plugin_code: pathParams.code, version: '1.0.0', source: 'cloud', package_hash: 'a'.repeat(64), signature_verified: true, downloadable: true, createdAt: '2026-01-01T00:00:00Z' }]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/operations$/, paramNames: ['code'], handler: () => ok([]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/recovery$/, paramNames: ['code'], handler: () => ok({ available: false, stage: '', message: '当前没有待处理的插件恢复记录。' }) },
  { method: 'POST', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/history\/(\d+)\/redeploy$/, paramNames: ['code', 'id'], handler: () => ok(true) },
  {
    method: 'POST',
    url: /^\/system\/plugin\/cloud\/([a-z][a-z0-9]*)\/install$/,
    paramNames: ['code'],
    handler: ({ body, pathParams }) => {
      if (!body.version) return fail('version 必填', 422);
      if (!account) return fail('请先登录云账号', 401);
      if (installedPlugin(pathParams.code)) return fail('插件已安装', 409);
      const marketplace = market.find((item) => item.code === pathParams.code);
      if (!marketplace) return fail('市场插件不存在', 404);
      installed.push({
        code: marketplace.code,
        name: marketplace.name,
        version: body.version,
        latestVersion: marketplace.versions[0]?.version || body.version,
        dbVersion: body.version,
        state: 'disabled',
        dependencies: {},
        migrationPending: false,
        lastError: '',
        source: 'cloud',
        needsReinstall: false,
        operation: '',
        progress: 0
      });
      return ok(true);
    }
  },
  {
    method: 'POST',
    url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/(update|migrate|enable|disable)$/,
    paramNames: ['code', 'action'],
    handler: ({ body, pathParams }) => {
      const plugin = installedPlugin(pathParams.code);
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
    paramNames: ['code'],
    handler: ({ pathParams }) => {
      const index = installed.findIndex((item) => item.code === pathParams.code);
      if (index < 0) return fail('插件尚未安装', 404);
      if (installed[index].state !== 'disabled') return fail('请先禁用插件', 409);
      installed.splice(index, 1);
      return ok(true);
    }
  },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/purge$/, paramNames: ['code'], handler: ({ params, pathParams }) => params.purgeConfirm !== pathParams.code ? fail('二次确认失败', 422) : ok(true) },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/package$/, paramNames: ['code'], handler: () => ok(true) }
];
