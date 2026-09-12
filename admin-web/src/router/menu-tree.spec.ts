import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router';
import { generateRoutes } from './dynamic';
import { staticRoutes } from './routes';
import { ADMIN_ROLE_ROWS, getAdminMenuTreeSeed } from '@/mock/data/adminSeed';
import { getFirstLeafRouteFullPath, getVisibleMenuChildren, resolveMenuPath } from '@/utils/route';

const menus: API.MenuItem[] = [
  {
    id: 34,
    parentId: 0,
    routeName: 'SystemManagement',
    path: '/system',
    component: 'Layout',
    redirect: '/system/user',
    type: 'M',
    name: '系统管理',
    sort: 10,
    children: [
      { id: 23, parentId: 34, routeName: 'SystemUser', path: '/system/user', component: 'system/user/index', type: 'C', name: '管理员管理', sort: 10 },
      { id: 24, parentId: 34, routeName: 'SystemMenu', path: 'menu', component: 'system/menu/index', type: 'C', name: '菜单管理', sort: 20 }
    ]
  },
  {
    id: 37,
    parentId: 0,
    routeName: 'Development',
    path: '/development',
    component: 'Layout',
    redirect: '/development/business/mine',
    type: 'M',
    name: '开发工具',
    sort: 20,
    children: [
      { id: 200, parentId: 37, routeName: 'BusinessMine', path: 'business/mine', component: 'development/business/mine', type: 'C', name: '我的业务', sort: 10 }
    ]
  }
] as API.MenuItem[];

const visibleLeafPaths = (routes: RouteRecordRaw[], parentPath = ''): string[] => routes.flatMap((route) => {
  if (route.meta?.hidden) return [];
  const fullPath = resolveMenuPath(parentPath, route.path);
  const children = getVisibleMenuChildren(route);
  return children.length ? visibleLeafPaths(children, fullPath) : [fullPath];
});

describe('混合布局菜单全树路由', () => {
  it('首次深链启动前由 bootstrap catch-all 消除未匹配，并在动态路由加载后落到正式路由', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });

    await router.push('/development/ai');
    expect(router.currentRoute.value.matched).toHaveLength(1);
    expect(router.currentRoute.value.name).toBe('BootstrapNotFound');
    expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('No match found'));

    generateRoutes(getAdminMenuTreeSeed()).forEach((route) => router.addRoute(route));
    router.removeRoute('BootstrapNotFound');
    router.addRoute({ path: '/:pathMatch(.*)*', name: 'NotFound', component: { template: '<div />' } });
    await router.replace('/development/ai');

    expect(router.currentRoute.value.name).toBe('AiDevelopment');
    expect(router.resolve('/not-permitted').name).toBe('NotFound');
    warn.mockRestore();
  });

  it('Mock 菜单暴露业务开发四入口与 AI 开发助手', () => {
    const menuSeed = getAdminMenuTreeSeed();
    const developmentMenu = menuSeed.find((menu) => menu.routeName === 'Development');
    const business = developmentMenu?.children?.find((menu) => menu.routeName === 'BusinessDevelopment');
    expect(business).toMatchObject({ path: 'business', redirect: '/development/business/mine', permission: 'development:business:view' });
    expect(business?.children?.map((item) => item.routeName)).toEqual(['BusinessMine', 'BusinessVisual', 'BusinessDatabase', 'BusinessRecords']);

    const routes = generateRoutes(menuSeed);
    const router = createRouter({ history: createMemoryHistory(), routes });
    expect(router.resolve('/development/business/mine').name).toBe('BusinessMine');
    const developmentRoute = routes.find((route) => route.name === 'Development');
    expect(getVisibleMenuChildren(developmentRoute!).map((route) => route.name)).toEqual(['BusinessDevelopment', 'AiDevelopment']);
    expect(router.resolve('/development/ai').name).toBe('AiDevelopment');
    expect(router.resolve('/development/business/designer?moduleId=1').name).toBe('BusinessDesigner');
    expect(ADMIN_ROLE_ROWS[0].menuIds).toEqual(expect.arrayContaining([200, 201, 202, 203, 207]));
  });

  it('TopMenu 首叶跳转与 Sidebar 路径解析一致', () => {
    const routes = generateRoutes(menus);

    expect(getFirstLeafRouteFullPath(routes[0].children![0], routes[0].path)).toBe('/system/user');
    expect(resolveMenuPath(routes[0].path, routes[0].children![0].path)).toBe('/system/user');
    expect(getFirstLeafRouteFullPath(routes[1].children![0], routes[1].path)).toBe('/development/business/mine');
    expect(resolveMenuPath(routes[1].path, routes[1].children![0].path)).toBe('/development/business/mine');
  });

  it('每个可见叶子均由业务路由命中而不是 NotFound', () => {
    const routes = generateRoutes(menus);
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        ...routes,
        { path: '/:pathMatch(.*)*', name: 'NotFound', component: { template: '<div />' } }
      ]
    });

    for (const path of visibleLeafPaths(routes)) {
      const resolved = router.resolve(path);
      expect(resolved.name, `${path} 不应命中 NotFound`).not.toBe('NotFound');
      expect(resolved.matched.length, `${path} 必须命中动态路由`).toBeGreaterThan(0);
    }
  });
});