import type { RouteRecordRaw, RouteComponent } from 'vue-router';
import Layout from '@/layout/index.vue';
import { resolveMenuPath, adminRoutePath } from '@/utils/route';

/** 异步加载核心业务页面；插件页面由 pluginModules 基于 Vite 构建期源码清单独立挂载。 */
const modules = import.meta.glob('@/views/**/*.vue');

/** 占位空白布局（用于多级嵌套菜单） */
const Blank: RouteComponent = () => import('@/layout/blank.vue');

/**
 * 解析后端 component 字段：
 * - "Layout"           => 主布局
 * - "Blank"            => 空白布局（用于多级菜单）
 * - "system/user/index" => src/views/system/user/index.vue
 * - "business/{entity}/index" => 生成业务页 src/views/business/{entity}/index.vue（与代码生成器所在的 views/development/business 是不同路径）
 * - 完整路径 "/views/system/user/index.vue" 也兼容
 */
function resolveComponent(component?: string): RouteComponent {
  // 主布局只在顶层挂载；目录或待加载插件的子节点使用空白 router-view。
  if (!component || component === 'Layout' || component === 'Blank') return Blank;

  const normalized = component
    .replace(/^\/+/, '')
    .replace(/^views\//, '');
  const suffix = normalized.endsWith('.vue') ? '' : '.vue';
  const candidates = [
    `/src/admin/views/${normalized}${suffix}`,
    `/src/admin/views/${normalized}/index${suffix}`
  ];
  const target = candidates.find((key) => key in modules);
  const loader = target ? modules[target as keyof typeof modules] : undefined;
  if (!loader) {
    if (normalized.startsWith('business/')) {
      console.info(`[router] 生成源码尚未进入当前构建，使用运行时发布宿主: ${normalized}`);
      return () => import('@/views/form/published.vue');
    }
    console.warn(`[router] 未找到组件: ${candidates.join(' | ')}，请检查后端菜单 component 字段`);
    return () => import('@/views/error/404.vue');
  }
  return loader as RouteComponent;
}

/** 后端菜单转 vue-router 路由 */
export function generateRoutes(menus: API.MenuItem[]): RouteRecordRaw[] {
  const sorted = [...menus].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));

  return sorted
    .filter((menu) => menu.type !== 'B') // 过滤按钮
    .map((menu) => transformMenu(menu));
}

function transformMenu(menu: API.MenuItem): RouteRecordRaw {
  const hasChildren = Array.isArray(menu.children) && menu.children.length > 0;

  // 目录或多级菜单：顶层挂 Layout
  if (hasChildren || menu.type === 'M' || !menu.component || menu.component === 'Layout') {
    const route: RouteRecordRaw = {
      path: adminRoutePath(menu.path),
      name: menu.routeName || `Menu_${menu.id}`,
      component: Layout,
      redirect: menu.redirect ? resolveAdminTarget(adminRoutePath(menu.path), menu.redirect) : undefined,
      meta: {
        title: menu.name,
        icon: menu.icon,
        hidden: Boolean(menu.hidden),
        keepAlive: Boolean(menu.keepAlive),
        permission: menu.permission,
        rank: menu.sort
      }
    } as RouteRecordRaw;

    if (hasChildren) {
      const parentPath = adminRoutePath(menu.path);
      (route as any).children = (menu.children as API.MenuItem[])
        .filter((c) => c.type !== 'B')
        .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
        .flatMap((child) => transformChildRoutes(child, parentPath));
    }
    return route;
  }

  // 顶层就是单页面（type: 'C'）：自动包一层 Layout，把自己作为空路径子路由
  // 例：path=/foo => /foo 命中 Layout，下面空路径子路由命中页面
  const path = adminRoutePath(menu.path);
  return {
    path,
    component: Layout,
    meta: {
      title: menu.name,
      menuKey: menu.routeName || `Menu_${menu.id}`,
      icon: menu.icon,
      hidden: Boolean(menu.hidden),
      rank: menu.sort
    },
    children: [
      {
        path: '',
        name: menu.routeName || `Menu_${menu.id}`,
        component: resolveComponent(menu.component),
        meta: {
          title: menu.name,
          icon: menu.icon,
          hidden: false,
          keepAlive: Boolean(menu.keepAlive),
          permission: menu.permission,
          rank: menu.sort,
          activeMenu: path,
          formKey: menu.formKey
        }
      } as RouteRecordRaw
    ]
  } as RouteRecordRaw;
}

/**
 * 递归子路由；parentAbsolutePath 为父级完整路径（如 /system），用于子 path 拼接与中间目录 redirect。
 */
// 页面节点的隐藏辅助页提升为同级路由：页面无需嵌套 router-view，也不会重复注册列表路径。
function transformChildRoutes(menu: API.MenuItem, parentPath: string): RouteRecordRaw[] {
  if (menu.type === 'C' && menu.component && !['Layout', 'Blank'].includes(menu.component) && menu.children?.length) {
    const activeMenu = resolveMenuPath(parentPath, menu.path.startsWith('/') ? adminRoutePath(menu.path) : menu.path);
    const page = transformChild({ ...menu, children: undefined }, parentPath);
    const auxiliary = menu.children.filter((child) => child.type !== 'B')
      .flatMap((child) => transformChildRoutes(child, activeMenu));
    auxiliary.forEach((route) => { route.meta = { ...route.meta, activeMenu }; });
    return [page, ...auxiliary];
  }
  return [transformChild(menu, parentPath)];
}

function transformChild(menu: API.MenuItem, parentAbsolutePath: string): RouteRecordRaw {
  const absolutePath = menu.path.startsWith('/') ? adminRoutePath(menu.path) : menu.path;
  const absolute = absolutePath.startsWith('/');
  const seg = absolute ? absolutePath : absolutePath.replace(/^\//, '');
  const fullPath = resolveMenuPath(parentAbsolutePath, absolutePath);
  const hasChildren = Array.isArray(menu.children) && menu.children.length > 0;
  const route: RouteRecordRaw = {
    path: seg,
    name: menu.routeName || `Menu_${menu.id}`,
    component: hasChildren ? Blank : resolveComponent(menu.component),
    redirect: menu.redirect ? resolveAdminTarget(parentAbsolutePath, menu.redirect) : undefined,
    meta: {
      title: menu.name,
      icon: menu.icon,
      hidden: Boolean(menu.hidden),
      keepAlive: Boolean(menu.keepAlive),
      permission: menu.permission,
      rank: menu.sort,
      formKey: menu.formKey,
      ...(!hasChildren && fullPath.startsWith('/admin/plugins/') && (!menu.component || menu.component === 'Layout')
        ? { pluginPlaceholder: true } : {}),
      ...(menu.routeName === 'BusinessDesigner' ? { activeMenu: adminRoutePath('/development/business/mine') } : {})
    }
  } as RouteRecordRaw;

  if (hasChildren) {
    (route as any).children = (menu.children as API.MenuItem[])
      .filter((c) => c.type !== 'B')
      .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
      .flatMap((child) => transformChildRoutes(child, fullPath));
    if (!menu.redirect) {
      const auto = findFirstLeafRedirectPath(menu, fullPath);
      if (auto) (route as RouteRecordRaw).redirect = auto;
    }
  }
  return route;
}

/** 中间目录（Blank）未设置 redirect 时，跳到第一个可见后代叶子路由的完整 path */
function findFirstLeafRedirectPath(menu: API.MenuItem, menuFullPath: string): string | undefined {
  const vis = (menu.children || [])
    .filter((c) => c.type !== 'B')
    .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));
  if (!vis.length) return undefined;
  const first = vis[0];
  const nextBase = resolveMenuPath(menuFullPath, first.path.startsWith('/') ? adminRoutePath(first.path) : first.path);
  const sub = (first.children || []).filter((c) => c.type !== 'B');
  if (!sub.length) return nextBase;
  return findFirstLeafRedirectPath(first, nextBase);
}

function resolveAdminTarget(parentPath: string, target: string): string {
  return target.startsWith('/')
    ? adminRoutePath(target)
    : resolveMenuPath(parentPath, target);
}

