import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Router, RouteLocationNormalized } from 'vue-router';

const state = vi.hoisted(() => ({
  installed: true,
  loggedIn: false,
  check: vi.fn(),
  restore: vi.fn(),
  mounted: true
}));
vi.mock('@/api/install', () => ({ isSystemInstalled: state.check }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get isLoggedIn() { return state.loggedIn; }, permissions: ['*'], fetchUserInfo: state.restore }) }));
vi.mock('@/store/modules/permission', () => ({ usePermissionStore: () => ({ mounted: state.mounted }) }));
vi.mock('@/web/session/memberSession', () => ({ memberSession: { authenticated: () => false } }));
vi.mock('@/router/pluginStartup', () => ({ loadPluginModulesSafely: vi.fn() }));
vi.mock('nprogress', () => ({ default: { start: vi.fn(), done: vi.fn() } }));

import { setupRouterGuard } from '@/router/guard';

async function navigate(path: string, meta = {}) {
  let guard: any;
  const router = {
    beforeEach: (callback: any) => { guard = callback; },
    afterEach: vi.fn(),
    onError: vi.fn()
  } as unknown as Router;
  setupRouterGuard(router);
  const next = vi.fn();
  // 与真实 vue-router 一致：路由对象带 params / query；守卫按路由名识别登录页。
  const name = path === '/admin/login' ? 'Login' : '';
  await guard({ path, fullPath: path, name, meta, params: {}, query: {} } as unknown as RouteLocationNormalized, {}, next);
  return next;
}

describe('Webman 登录入口安装引导', () => {
  beforeEach(() => {
    vi.stubEnv('VITE_APP_BACKEND', 'webman');
    state.installed = true;
    state.loggedIn = false;
    state.check.mockReset().mockImplementation(async () => state.installed);
    state.restore.mockReset().mockRejectedValue({ code: 401 });
  });
  afterEach(() => vi.unstubAllEnvs());

  it('未安装时访问登录页跳转安装，而不是白名单直接放行', async () => {
    state.installed = false;
    const next = await navigate('/admin/login');
    expect(state.check).toHaveBeenCalledOnce();
    expect(next).toHaveBeenCalledExactlyOnceWith({ path: '/admin/install', replace: true });
  });

  it('未安装时不受残留后台登录状态影响', async () => {
    state.installed = false;
    state.loggedIn = true;
    expect(await navigate('/admin/login')).toHaveBeenCalledWith({ path: '/admin/install', replace: true });
  });

  it('已安装时正常放行登录页', async () => {
    expect(await navigate('/admin/login')).toHaveBeenCalledExactlyOnceWith();
  });

  it('本地标记丢失但 Cookie 有效时恢复身份，不要求重复提交密码', async () => {
    state.restore.mockImplementation(async () => { state.loggedIn = true; });
    expect(await navigate('/admin/login')).toHaveBeenCalledExactlyOnceWith({ path: '/admin/dashboard' });
    expect(state.restore).toHaveBeenCalledExactlyOnceWith(true);
  });

  it('Cookie 无效时安静保留登录页，不能误判为已登录', async () => {
    expect(await navigate('/admin/login')).toHaveBeenCalledExactlyOnceWith();
    expect(state.restore).toHaveBeenCalledExactlyOnceWith(true);
    expect(state.loggedIn).toBe(false);
  });

  it('身份接口暂时异常时仍允许用户访问登录表单', async () => {
    state.restore.mockRejectedValue({ code: 500 });
    expect(await navigate('/admin/login')).toHaveBeenCalledExactlyOnceWith();
    expect(state.loggedIn).toBe(false);
  });

  it('安装页不会再次触发守卫检测，避免重定向循环', async () => {
    expect(await navigate('/admin/install')).toHaveBeenCalledExactlyOnceWith();
    expect(state.check).not.toHaveBeenCalled();
  });

  it('官网和会员登录页面不检查后台安装状态', async () => {
    expect(await navigate('/', { public: true })).toHaveBeenCalledExactlyOnceWith();
    expect(await navigate('/web/login', { public: true, member: true })).toHaveBeenCalledExactlyOnceWith();
    expect(state.check).not.toHaveBeenCalled();
  });

  it('受保护页面先进入登录入口，由登录入口统一判断安装状态', async () => {
    expect(await navigate('/admin/dashboard')).toHaveBeenCalledWith({ path: '/admin/login', query: { redirect: '/admin/dashboard' } });
    expect(state.check).not.toHaveBeenCalled();
  });

  it('其他后端模式保留原登录行为', async () => {
    vi.stubEnv('VITE_APP_BACKEND', '');
    expect(await navigate('/admin/login')).toHaveBeenCalledExactlyOnceWith();
    expect(state.check).not.toHaveBeenCalled();
  });
});
