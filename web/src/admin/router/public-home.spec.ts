import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { staticRoutes } from './routes';
import { ADMIN_HOME_PATH, ADMIN_LOGIN_PATH, MEMBER_LOGIN_PATH, MEMBER_REGISTER_PATH, MEMBER_PROFILE_PATH } from '@/config';

vi.mock('@/layout/index.vue', () => ({ default: { template: '<div />' } }));

describe('公开展示站点路由', () => {
  it('问答列表和详情使用公开会员入口，不挂载后台布局', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });
    for (const path of ['/web/questions', '/web/questions/9007199254740993']) {
      const route = router.resolve(path);
      expect(route.meta.public).toBe(true);
      expect(route.meta.member).toBe(true);
      expect(route.matched).toHaveLength(1);
    }
  });
  it('移除 CMS 投稿入口，保留独立问答与通用会员入口', () => {
    expect(staticRoutes.some(route => route.path === '/web/community' || route.name === 'MemberCommunity')).toBe(false);
    expect(staticRoutes.find(route => route.name === 'CommunityQuestions')?.component?.toString()).toContain('QuestionsPortal.vue');
  });
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

  it('提供前台会员登录、注册和个人中心路由', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });

    expect(router.resolve(MEMBER_LOGIN_PATH).name).toBe('MemberLogin');
    expect(router.resolve(MEMBER_REGISTER_PATH).name).toBe('MemberRegister');
    expect(router.resolve(MEMBER_PROFILE_PATH).name).toBe('MemberProfile');
    expect(router.getRoutes().find((route) => route.name === 'MemberProfile')?.meta.member).toBe(true);
  });
  it('后台登录和首页使用 admin 路由前缀', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });

    expect(router.resolve(ADMIN_LOGIN_PATH).name).toBe('Login');
    expect(router.resolve(ADMIN_HOME_PATH).name).toBe('Dashboard');
    expect(router.resolve('/login').name).not.toBe('Login');
    expect(router.resolve('/dashboard').name).not.toBe('Dashboard');
  });
});
