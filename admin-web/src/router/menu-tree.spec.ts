import { describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router';
import { generateRoutes } from './dynamic';
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
    redirect: '/development/crud',
    type: 'M',
    name: '开发工具',
    sort: 20,
    children: [
      { id: 38, parentId: 37, routeName: 'DevelopmentCrud', path: 'crud', component: 'development/crud/index', type: 'C', name: 'CRUD生成器', sort: 10 },
      { id: 41, parentId: 37, routeName: 'FormList', path: '/form/list', component: 'form/list', type: 'C', name: '表单管理', sort: 20 }
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
  it('Mock 菜单只暴露统一业务开发四入口', () => {
    const menuSeed = getAdminMenuTreeSeed();
    const developmentMenu = menuSeed.find((menu) => menu.routeName === 'Development');
    const business = developmentMenu?.children?.find((menu) => menu.routeName === 'BusinessDevelopment');
    expect(business).toMatchObject({ path: 'business', redirect: '/development/business/mine', permission: 'development:business:view' });
    expect(business?.children?.map((item) => item.routeName)).toEqual(['BusinessMine', 'BusinessVisual', 'BusinessDatabase', 'BusinessRecords']);

    const routes = generateRoutes(menuSeed);
    const router = createRouter({ history: createMemoryHistory(), routes });
    expect(router.resolve('/development/business/mine').name).toBe('BusinessMine');
    const developmentRoute = routes.find((route) => route.name === 'Development');
    expect(getVisibleMenuChildren(developmentRoute!).map((route) => route.name)).toEqual(['BusinessDevelopment']);
    expect(router.resolve('/development/business/designer?moduleId=1').name).toBe('BusinessDesigner');
    expect(ADMIN_ROLE_ROWS[0].menuIds).toEqual(expect.arrayContaining([200, 201, 202, 203]));
  });

  it('TopMenu 首叶跳转与 Sidebar 路径解析一致', () => {
    const routes = generateRoutes(menus);

    expect(getFirstLeafRouteFullPath(routes[0].children![0], routes[0].path)).toBe('/system/user');
    expect(resolveMenuPath(routes[0].path, routes[0].children![0].path)).toBe('/system/user');
    expect(getFirstLeafRouteFullPath(routes[1].children![1], routes[1].path)).toBe('/form/list');
    expect(resolveMenuPath(routes[1].path, routes[1].children![1].path)).toBe('/form/list');
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