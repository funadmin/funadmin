import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { staticRoutes } from './routes';
import { ADMIN_HOME_PATH, ADMIN_LOGIN_PATH } from '@/config';

vi.mock('@/layout/index.vue', () => ({ default: { template: '<div />' } }));

describe('公开展示站点路由', () => {
  it('提供官网首页、产品能力和快速开始页面', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });

    expect(router.resolve('/').name).toBe('PublicHome');
    expect(router.resolve('/features').name).toBe('PublicFeatures');
    expect(router.resolve('/quick-start').name).toBe('PublicQuickStart');
  });
  it('官网页面不挂载后台 Layout', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });
    const home = router.getRoutes().find((route) => route.name === 'PublicHome');

    expect(home?.meta.public).toBe(true);
    expect(home?.meta.hidden).toBe(true);
  });

  it('后台登录和首页使用 admin 路由前缀', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });

    expect(router.resolve(ADMIN_LOGIN_PATH).name).toBe('Login');
    expect(router.resolve(ADMIN_HOME_PATH).name).toBe('Dashboard');
    expect(router.resolve('/login').name).not.toBe('Login');
    expect(router.resolve('/dashboard').name).not.toBe('Dashboard');
  });
});
