/**
 * 前端 Mock 适配器
 * 通过替换 axios 的 adapter，在不发起真实网络请求的情况下返回模拟数据。
 * 启用方式：.env 中设置 VITE_APP_MOCK=true（默认 dev 已开启）。
 */
import type {
  AxiosAdapter,
  AxiosResponse,
  InternalAxiosRequestConfig
} from 'axios';
import qs from 'qs';
import { service } from '@/utils/http';
import { APP_CONFIG, RESP_CODE } from '@/config';
import { authMockHandlers } from './modules/auth';
import { systemMockHandlers } from './modules/system';
import { profileMockHandlers } from './modules/profile';
import { dictMockHandlers } from './modules/dict';
import { deptMockHandlers } from './modules/dept';
import { uploadMockHandlers } from './modules/upload';
import { logMockHandlers } from './modules/log';
import { permissionMockHandlers } from './modules/permission';
import { blacklistMockHandlers } from './modules/blacklist';
import { languageMockHandlers } from './modules/language';
import { memberGroupMockHandlers } from './modules/memberGroup';
import { memberLevelMockHandlers } from './modules/memberLevel';
import { memberMockHandlers } from './modules/member';
import { attachmentMockHandlers } from './modules/attachment';
import { configMockHandlers } from './modules/config';
import { pluginMockHandlers } from './modules/plugin';
import { upgradeMockHandlers } from './modules/upgrade';
import type { MockHandler, MockRoute, MockMethod } from './types';

const routes: MockRoute[] = [
  ...authMockHandlers,
  ...systemMockHandlers,
  ...profileMockHandlers,
  ...dictMockHandlers,
  ...deptMockHandlers,
  ...uploadMockHandlers,
  ...logMockHandlers,
  ...permissionMockHandlers,
  ...blacklistMockHandlers,
  ...languageMockHandlers,
  ...memberGroupMockHandlers,
  ...memberLevelMockHandlers,
  ...memberMockHandlers,
  ...attachmentMockHandlers,
  ...configMockHandlers,
  ...pluginMockHandlers,
  ...upgradeMockHandlers
];

function stripBase(url: string): string {
  let u = url || '/';
  const base = APP_CONFIG.baseApi;
  if (base && u.startsWith(base)) u = u.slice(base.length);
  // 去查询串
  const i = u.indexOf('?');
  if (i >= 0) u = u.slice(0, i);
  return u || '/';
}

function matchRoute(method: MockMethod, url: string): { route: MockRoute; params: Record<string, string> } | null {
  for (const r of routes) {
    if (r.method !== method) continue;
    if (typeof r.url === 'string') {
      if (r.url === url) return { route: r, params: {} };
    } else {
      const m = url.match(r.url);
      if (m) {
        const params: Record<string, string> = {};
        if (r.paramNames) r.paramNames.forEach((n, i) => (params[n] = m[i + 1]));
        return { route: r, params };
      }
    }
  }
  return null;
}

function parseBody(data: unknown): any {
  if (!data) return {};
  if (typeof data === 'string') {
    try {
      return JSON.parse(data);
    } catch {
      return qs.parse(data);
    }
  }
  return data;
}

const delay = (ms = 120) => new Promise((r) => setTimeout(r, ms));

const mockAdapter: AxiosAdapter = async (config) => {
  const url = stripBase(config.url || '/');
  const method = (config.method || 'get').toUpperCase() as MockMethod;
  const matched = matchRoute(method, url);

  await delay();

  const respond = <T>(data: T): AxiosResponse =>
    ({
      data,
      status: 200,
      statusText: 'OK',
      headers: {},
      config: config as InternalAxiosRequestConfig,
      request: {}
    }) as AxiosResponse;

  if (!matched) {
    console.warn(`[mock] 未命中路由：${method} ${url}`);
    return respond({
      code: RESP_CODE.FAIL,
      msg: `Mock 未实现：${method} ${url}`,
      data: null,
      time: Date.now()
    });
  }

  const ctx: Parameters<MockHandler>[0] = {
    url,
    method,
    params: config.params || {},
    body: parseBody(config.data),
    pathParams: matched.params,
    headers: config.headers as any
  };

  try {
    const resp = await matched.route.handler(ctx);
    return respond(resp);
  } catch (err: any) {
    console.error('[mock] handler error:', err);
    return respond({
      code: RESP_CODE.FAIL,
      msg: err?.message || 'Mock 处理异常',
      data: null,
      time: Date.now()
    });
  }
};

if (import.meta.env.VITE_APP_MOCK === 'true') {
  service.defaults.adapter = mockAdapter;
   
  console.info(
    `%c[mock] 已启用前端 Mock，命中 ${routes.length} 条路由（admin / 123456 登录）`,
    'color:#7c3aed;font-weight:bold'
  );
}
