import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router';
import { generateRoutes } from './dynamic';
import { setupRouterGuard } from './guard';
import { staticRoutes } from './routes';
import zh from '@/locales/zh-CN';
import en from '@/locales/en-US';
import { ADMIN_ROLE_ROWS, getAdminMenuTreeSeed } from '@/mock/data/adminSeed';
import { ADMIN_ROUTE_PREFIX } from '@/config';
import { getFirstLeafRouteFullPath, getMenuActiveRootPath, getVisibleMenuChildren, resolveMenuPath } from '@/utils/route';

const guardStores = vi.hoisted(() => ({
  user: { isLoggedIn: true, userInfo: {}, permissions: ['development:business:create-visual', 'development:business:create-from-database', 'development:business:validate-schema', 'development:business:save-schema', 'development:business:compile-schema', 'development:business:rollback-schema', 'development:business:adopt-resolved-baseline', 'development:business:save'], fetchUserInfo: vi.fn(), resetState: vi.fn() },
  permission: { mounted: false, fetchMenus: vi.fn(), setMounted: vi.fn(), reset: vi.fn() }
}));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => guardStores.user }));
vi.mock('@/store/modules/permission', () => ({ usePermissionStore: () => guardStores.permission }));
vi.mock('@/router/pluginStartup', () => ({ loadPluginModulesSafely: vi.fn().mockResolvedValue(undefined) }));
vi.mock('nprogress', () => ({ default: { start: vi.fn(), done: vi.fn() } }));

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
  it.each([
    ['Plugins', '/plugins', '/plugin', 'SystemPlugin'],
    ['UserManagement', '/users', '/system/member', 'SystemMember']
  ])('跨 URL 模块 %s 根据路由归属保留二级菜单', (name, path, childPath, childName) => {
    const routes = generateRoutes([{
      id: 900, parentId: 0, routeName: name, path, type: 'M', component: 'Layout', name, sort: 1,
      hidden: false, keepAlive: false, affix: false,
      children: [{ id: 901, parentId: 900, routeName: childName, path: childPath,
        type: 'C', component: 'system/user/index', name: childName, sort: 1,
        hidden: false, keepAlive: false, affix: false }]
    }]);
    const router = createRouter({ history: createMemoryHistory(), routes });
    const current = router.resolve(`${ADMIN_ROUTE_PREFIX}${childPath}`);
    const rootPath = getMenuActiveRootPath(current, routes);
    expect(rootPath).toBe(`${ADMIN_ROUTE_PREFIX}${path}`);
    expect(getVisibleMenuChildren(routes.find((item) => item.path === rootPath)!)).toHaveLength(1);
  });

  it('静态隐藏辅助页使用 activeMenu 定位所属模块', () => {
    const routes = generateRoutes(menus);
    const current = { path: `${ADMIN_ROUTE_PREFIX}/designer/42`, matched: [],
      meta: { activeMenu: `${ADMIN_ROUTE_PREFIX}/development/business/mine` } };
    expect(getMenuActiveRootPath(current, routes)).toBe(`${ADMIN_ROUTE_PREFIX}/development`);
  });

  it('没有匹配记录时仍按完整菜单路径而不是 URL 首段定位模块', () => {
    const routes = [{ path: `${ADMIN_ROUTE_PREFIX}/plugins`, children: [
      { path: `${ADMIN_ROUTE_PREFIX}/plugin`, name: 'SystemPlugin' }
    ] }] as RouteRecordRaw[];
    const current = { path: `${ADMIN_ROUTE_PREFIX}/plugin`, matched: [], meta: {} };
    expect(getMenuActiveRootPath(current, routes)).toBe(`${ADMIN_ROUTE_PREFIX}/plugins`);
  });

  it.each([
    `${ADMIN_ROUTE_PREFIX}/development/business/designer?moduleId=42`,
    `${ADMIN_ROUTE_PREFIX}/development/business/designer?moduleId=42&tag=a&tag=b&empty=&flag&keyword=%E4%B8%AD%E6%96%87#schema`
  ])('首次动态路由加载后保留深链的 path、query 和 hash：%s', async (fullPath) => {
    guardStores.permission.mounted = false;
    guardStores.permission.fetchMenus.mockResolvedValue(generateRoutes(getAdminMenuTreeSeed()));
    guardStores.permission.setMounted.mockImplementation((mounted: boolean) => {
      guardStores.permission.mounted = mounted;
    });
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });
    const expected = router.resolve(fullPath);
    setupRouterGuard(router);

    await router.push(fullPath);

    expect(router.currentRoute.value.name).toBe('BusinessDesigner');
    expect(router.currentRoute.value.path).toBe(expected.path);
    expect(router.currentRoute.value.query).toEqual(expected.query);
    expect(router.currentRoute.value.hash).toBe(expected.hash);
    expect(router.currentRoute.value.fullPath).toBe(expected.fullPath);
    expect(router.hasRoute('BootstrapNotFound')).toBe(false);
    expect(guardStores.permission.mounted).toBe(true);
  });

  it('已挂载路由拒绝缺少 meta.permission 的直接深链访问', async () => {
    guardStores.permission.mounted = true;
    guardStores.user.permissions = [];
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });
    setupRouterGuard(router);

    await router.push(`${ADMIN_ROUTE_PREFIX}/development/business/designer?moduleId=42`);

    expect(router.currentRoute.value.name).toBe('Forbidden');
    // 设计器对任一业务设计相关方法开放：只有"保存 Schema"或"按数据库创建"的角色也能进入。
    for (const permission of ['development:business:save-schema', 'development:business:create-from-database']) {
      guardStores.user.permissions = [permission];
      await router.push(`${ADMIN_ROUTE_PREFIX}/development/business/designer?moduleId=${permission.length}`);
      expect(router.currentRoute.value.name).toBe('BusinessDesigner');
    }
  });

  it('首次深链启动前由 bootstrap catch-all 消除未匹配，并在动态路由加载后落到正式路由', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const router = createRouter({ history: createMemoryHistory(), routes: staticRoutes });

    await router.push(`${ADMIN_ROUTE_PREFIX}/development/ai`);
    expect(router.currentRoute.value.matched).toHaveLength(1);
    expect(router.currentRoute.value.name).toBe('BootstrapNotFound');
    expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('No match found'));

    generateRoutes(getAdminMenuTreeSeed()).forEach((route) => router.addRoute(route));
    router.removeRoute('BootstrapNotFound');
    router.addRoute({ path: `${ADMIN_ROUTE_PREFIX}/:pathMatch(.*)*`, name: 'NotFound', component: { template: '<div />' } });
    await router.replace(`${ADMIN_ROUTE_PREFIX}/development/ai`);

    expect(router.currentRoute.value.name).toBe('AiDevelopment');
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/not-permitted`).name).toBe('NotFound');
    warn.mockRestore();
  });

  it('Mock 业务开发直接展示列表并保留 AI', () => {
    const menuSeed = getAdminMenuTreeSeed();
    const developmentMenu = menuSeed.find((menu) => menu.routeName === 'Development');
    const business = developmentMenu?.children?.find((menu) => menu.routeName === 'BusinessDevelopment');
    expect(business).toMatchObject({ path: 'business/mine', component: 'development/business/mine', type: 'C', permission: 'development:business:modules' });
    expect(business?.redirect).toBeUndefined();
    expect(business?.children ?? []).toEqual([]);

    const routes = generateRoutes(menuSeed);
    const router = createRouter({ history: createMemoryHistory(), routes });
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/mine`).name).toBe('BusinessDevelopment');
    const developmentRoute = routes.find((route) => route.name === 'Development');
    expect(getVisibleMenuChildren(developmentRoute!).map((route) => route.name)).toEqual(['BusinessDevelopment', 'AiDevelopment']);
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/ai`).name).toBe('AiDevelopment');
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/designer?moduleId=1`).name).toBe('BusinessDesigner');
    expect(ADMIN_ROLE_ROWS[0].menuIds).toEqual(expect.arrayContaining([200, 201, 207]));
  });

  it('隐藏辅助入口保留权限、组件及 active 菜单，父子不重复注册路径', () => {
    const seed = getAdminMenuTreeSeed();
    const business = seed.find((menu) => menu.routeName === 'Development')!.children!
      .find((menu) => menu.routeName === 'BusinessDevelopment')!;
    expect(business.type).toBe('C');
    const routes = generateRoutes(seed);
    const router = createRouter({ history: createMemoryHistory(), routes });
    const businessRoute = routes.find((route) => route.name === 'Development')!.children!
      .find((route) => route.name === 'BusinessDevelopment')!;
    expect(getVisibleMenuChildren(businessRoute).map((route) => route.name))
      .toEqual([]);
    expect(getFirstLeafRouteFullPath(businessRoute, `${ADMIN_ROUTE_PREFIX}/development`)).toBe(`${ADMIN_ROUTE_PREFIX}/development/business/mine`);
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/mine`).matched.at(-1)?.components?.default).toBeTypeOf('function');
    const paths = router.getRoutes().map((route) => route.path);
    expect(new Set(paths).size).toBe(paths.length);
    for (const path of ['visual', 'database', 'records']) {
      expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/${path}`).matched, path).toEqual([]);
    }
    for (const [path, name, permission] of [
      ['designer', 'BusinessDesigner', 'save-schema']
    ]) {
      const resolved = router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/${path}`);
      expect(resolved.name).toBe(name);
      expect(resolved.meta.hidden).toBe(true);
      expect(resolved.meta.activeMenu).toBe(`${ADMIN_ROUTE_PREFIX}/development/business/mine`);
      expect(resolved.meta.permission).toBe(`development:business:${permission}`);
      expect(resolved.matched.at(-1)?.components?.default).toBeTypeOf('function');
    }
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/mine`).name).toBe('BusinessDevelopment');
    expect(router.resolve(`${ADMIN_ROUTE_PREFIX}/development/business/mine`).meta.permission).toBe('development:business:modules');
  });

  it('业务列表菜单中英文标题统一', () => {
    expect(zh.menu).toMatchObject({ BusinessDevelopment: '代码生成' });
    expect(en.menu).toMatchObject({ BusinessDevelopment: 'Code Generator' });
    const designer = staticRoutes.flatMap((route) => route.children || []).find((route) => route.name === 'BusinessDesigner');
    expect(designer?.meta?.activeMenu).toBe(`${ADMIN_ROUTE_PREFIX}/development/business/mine`);
  });


  it('TopMenu 首叶跳转与 Sidebar 路径解析一致', () => {
    const routes = generateRoutes(menus);

    expect(getFirstLeafRouteFullPath(routes[0].children![0], routes[0].path)).toBe(`${ADMIN_ROUTE_PREFIX}/system/user`);
    expect(resolveMenuPath(routes[0].path, routes[0].children![0].path)).toBe(`${ADMIN_ROUTE_PREFIX}/system/user`);
    expect(getFirstLeafRouteFullPath(routes[1].children![0], routes[1].path)).toBe(`${ADMIN_ROUTE_PREFIX}/development/business/mine`);
    expect(resolveMenuPath(routes[1].path, routes[1].children![0].path)).toBe(`${ADMIN_ROUTE_PREFIX}/development/business/mine`);
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

  it('component 只接受相对 views 的路径：前导 /、views/ 前缀与 .vue 后缀不再被规范化', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const page = (id: number, component: string): API.MenuItem => ({ id, parentId: 0, routeName: `Path${id}`, path: `/path-${id}`, component, type: 'C', name: component, sort: id, hidden: false, keepAlive: false, affix: false });
    try {
      generateRoutes([page(1, 'system/user/index'), page(2, '/views/system/user/index.vue'), page(3, 'views/system/user/index'), page(4, 'system/user/index.vue')]);
      expect(warn).toHaveBeenCalledTimes(3);
      expect(warn.mock.calls.map((call) => String(call[0]))).toEqual([
        expect.stringContaining('/src/admin/views//views/system/user/index.vue.vue'),
        expect.stringContaining('/src/admin/views/views/system/user/index.vue'),
        expect.stringContaining('/src/admin/views/system/user/index.vue.vue')
      ]);
    } finally {
      warn.mockRestore();
    }
  });

  it('生成业务组件只按 business/ 前缀解析到 views/business，未构建时落到发布宿主；generated/ 不再识别', () => {
    const info = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const page = (id: number, component: string): API.MenuItem => ({ id, parentId: 0, routeName: `Biz${id}`, path: `/biz-${id}`, component, type: 'C', name: component, sort: id, hidden: false, keepAlive: false, affix: false });
    const leaf = (route: RouteRecordRaw) => route.children![0]!.component;
    try {
      const [built, pending, legacy, codegen] = generateRoutes([
        page(1, 'business/order/index'),
        page(2, 'business/not-built-yet/index'),
        page(3, 'generated/not-built-yet/index'),
        page(4, 'development/business/mine')
      ]);
      expect(leaf(built!)).toBeTypeOf('function');
      expect(info).toHaveBeenCalledTimes(1);
      expect(info.mock.calls[0]![0]).toContain('business/not-built-yet/index');
      expect(leaf(pending!)).toBeTypeOf('function');
      expect(warn).toHaveBeenCalledTimes(1);
      expect(warn.mock.calls[0]![0]).toContain('/src/admin/views/generated/not-built-yet/index.vue');
      expect(leaf(legacy!)).toBeTypeOf('function');
      expect(leaf(codegen!)).toBeTypeOf('function');
    } finally {
      info.mockRestore();
      warn.mockRestore();
    }
  });
});
