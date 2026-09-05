import type { MockRoute } from '../types';
import { fail, ok, page } from '../types';

let account: Record<string, unknown> | null = null;
const installed = [{ name: 'demo', title: '演示插件', version: '1.0.0', latestVersion: '1.1.0', dbVersion: '1.0.0', state: 'disabled', dependencies: {}, migrationPending: false, lastError: '', source: 'installed' }];
const local = [{ name: 'localdemo', title: '本地示例', version: '1.0.0', latestVersion: '', dbVersion: '', state: 'discovered', dependencies: {}, migrationPending: false, lastError: '', source: 'local' }];
const market = [{ id: 1, name: 'marketdemo', title: '市场示例', description: '云市场插件', author: 'FunAdmin', versions: [{ id: 1, pluginName: 'marketdemo', version: '1.0.0', changelog: '初始版本', compatible: true }] }];

export const pluginMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/system/plugin/account/current', handler: () => ok(account) },
  { method: 'POST', url: '/system/plugin/account/login', handler: ({ body }) => { account = { id: 1, username: body.account, nickname: body.account, avatar: '' }; return ok(account); } },
  { method: 'POST', url: '/system/plugin/account/logout', handler: () => { account = null; return ok({ authenticated: false }); } },
  { method: 'GET', url: '/system/plugin/market/categories', handler: () => ok([{ id: 1, name: '工具' }]) },
  { method: 'GET', url: '/system/plugin/market/search', handler: () => ok(page(market, market.length, 1, 20)) },
  { method: 'GET', url: /^\/system\/plugin\/market\/([a-z][a-z0-9]*)\/versions$/, paramNames: ['name'], handler: () => ok(market[0].versions) },
  { method: 'GET', url: /^\/system\/plugin\/market\/([a-z][a-z0-9]*)$/, paramNames: ['name'], handler: ({ pathParams }) => ok(market.find((item) => item.name === pathParams.name) || market[0]) },
  { method: 'POST', url: '/system/plugin/market/check-updates', handler: () => ok([{ name: 'demo', installedVersion: '1.0.0', latestVersion: '1.1.0', updateAvailable: true }]) },
  { method: 'GET', url: '/system/plugin/local/discovered', handler: () => ok(local) },
  { method: 'GET', url: '/system/plugin/local/installed', handler: () => ok(installed) },
  { method: 'POST', url: '/system/plugin/local/install', handler: () => ok({ installed: true }) },
  { method: 'GET', url: '/system/plugin/modules/enabled', handler: () => ok([]) },
  { method: 'GET', url: /^\/system\/plugin\/local\/([a-z][a-z0-9]*)$/, paramNames: ['name'], handler: () => ok(local[0]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/config$/, paramNames: ['name'], handler: () => ok({ enabled: { title: '启用功能', type: 'switch', value: true } }) },
  { method: 'PUT', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/config$/, paramNames: ['name'], handler: () => ok(true) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/history$/, paramNames: ['name'], handler: () => ok([]) },
  { method: 'GET', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/operations$/, paramNames: ['name'], handler: () => ok([]) },
  { method: 'POST', url: /^\/system\/plugin\/cloud\/([a-z][a-z0-9]*)\/install$/, paramNames: ['name'], handler: ({ body }) => body.version ? ok(true) : fail('version 必填', 422) },
  { method: 'POST', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/(update|migrate|enable|disable)$/, paramNames: ['name', 'action'], handler: () => ok(true) },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/uninstall$/, paramNames: ['name'], handler: () => ok(true) },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/purge$/, paramNames: ['name'], handler: ({ params, pathParams }) => params.purgeConfirm !== pathParams.name ? fail('二次确认失败', 422) : ok(true) },
  { method: 'DELETE', url: /^\/system\/plugin\/([a-z][a-z0-9]*)\/package$/, paramNames: ['name'], handler: () => ok(true) }
];
