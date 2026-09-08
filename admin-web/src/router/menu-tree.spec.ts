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
  it('Mock 表单菜单生成可直达且设计器不出现在侧栏', () => {
    const menuSeed = getAdminMenuTreeSeed();
    const developmentMenu = menuSeed.find((menu) => menu.routeName === 'Development');
    const formList = developmentMenu?.children?.find((menu) => menu.routeName === 'FormList');
    const formDesigner = developmentMenu?.children?.find((menu) => menu.routeName === 'FormDesigner');

    expect(formList).toMatchObject({
      id: 202,
      parentId: 200,
      path: 'form/list',
      sort: 40,
      hidden: false,
      keepAlive: false,
      permission: 'console/formdesigner:index'
    });
    expect(formDesigner).toMatchObject({
      id: 203,
      parentId: 200,
      path: 'form/designer',
      component: 'form/designer/index',
      sort: 1,
      hidden: true,
      keepAlive: false,
      permission: 'console/formdesigner:index'
    });

    const routes = generateRoutes(menuSeed);
    const router = createRouter({ history: createMemoryHistory(), routes });
    const resolved = router.resolve('/development/form/designer?id=1');
    const developmentRoute = routes.find((route) => route.name === 'Development');
    const visibleMenuNames = getVisibleMenuChildren(developmentRoute!).map((route) => route.name);

    expect(resolved.name).toBe('FormDesigner');
    expect(resolved.query).toEqual({ id: '1' });
    expect(visibleMenuNames).toContain('FormList');
    expect(visibleMenuNames).not.toContain('FormDesigner');
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