import type { RouteRecordRaw, RouteComponent } from 'vue-router';
import Layout from '@/layout/index.vue';

/** 异步加载所有业务页面：约定页面位于 src/views 和 src/modules/<module>/views 下 */
const modules = {
  ...import.meta.glob('@/views/**/*.vue'),
  ...import.meta.glob('@/modules/**/views/**/*.vue')
};

/** 占位空白布局（用于多级嵌套菜单） */
const Blank: RouteComponent = () => import('@/layout/blank.vue');

/**
 * 解析后端 component 字段：
 * - "Layout"           => 主布局
 * - "Blank"            => 空白布局（用于多级菜单）
 * - "system/user/index" => src/views/system/user/index.vue
 * - "modules/example/views/index" => src/modules/example/views/index.vue
 * - 完整路径 "/views/system/user/index.vue" 也兼容
 */
function resolveComponent(component?: string): RouteComponent {
  if (!component || component === 'Layout') return Layout;
  if (component === 'Blank') return Blank;

  const normalized = component
    .replace(/^\/+/, '')
    .replace(/^views\//, '');
  const suffix = normalized.endsWith('.vue') ? '' : '.vue';
  const candidates = normalized.startsWith('modules/')
    ? [`/src/${normalized}${suffix}`]
    : [`/src/views/${normalized}${suffix}`];
  const target = candidates.find((key) => key in modules);
  const loader = target ? modules[target as keyof typeof modules] : undefined;
  if (!loader) {
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
      path: ensureLeadingSlash(menu.path),
      name: menu.routeName || `Menu_${menu.id}`,
      component: Layout,
      redirect: menu.redirect,
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
      const parentPath = ensureLeadingSlash(menu.path);
      (route as any).children = (menu.children as API.MenuItem[])
        .filter((c) => c.type !== 'B')
        .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
        .map((child) => transformChild(child, parentPath));
    }
    return route;
  }

  // 顶层就是单页面（type: 'C'）：自动包一层 Layout，把自己作为空路径子路由
  // 例：path=/foo => /foo 命中 Layout，下面空路径子路由命中页面
  const path = ensureLeadingSlash(menu.path);
  return {
    path,
    component: Layout,
    meta: {
      title: menu.name,
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
          activeMenu: path
        }
      } as RouteRecordRaw
    ]
  } as RouteRecordRaw;
}

/**
 * 递归子路由；parentAbsolutePath 为父级完整路径（如 /system），用于子 path 拼接与中间目录 redirect。
 */
function transformChild(menu: API.MenuItem, parentAbsolutePath: string): RouteRecordRaw {
  const seg = menu.path.replace(/^\//, '');
  const fullPath = `${parentAbsolutePath.replace(/\/$/, '')}/${seg}`.replace(/\/+/g, '/');
  const hasChildren = Array.isArray(menu.children) && menu.children.length > 0;
  const route: RouteRecordRaw = {
    path: seg,
    name: menu.routeName || `Menu_${menu.id}`,
    component: hasChildren ? Blank : resolveComponent(menu.component),
    redirect: menu.redirect,
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
    (route as any).children = (menu.children as API.MenuItem[])
      .filter((c) => c.type !== 'B')
      .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
      .map((child) => transformChild(child, fullPath));
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
  const seg = first.path.replace(/^\//, '');
  const nextBase = `${menuFullPath.replace(/\/$/, '')}/${seg}`.replace(/\/+/g, '/');
  const sub = (first.children || []).filter((c) => c.type !== 'B');
  if (!sub.length) return nextBase;
  return findFirstLeafRedirectPath(first, nextBase);
}

function ensureLeadingSlash(path: string): string {
  if (!path) return '/';
  return path.startsWith('/') ? path : `/${path}`;
}

/** 收集所有按钮权限标识（用于 v-perm 全量校验） */
export function collectPermissions(menus: API.MenuItem[]): string[] {
  const list: string[] = [];
  const walk = (items: API.MenuItem[]) => {
    items.forEach((m) => {
      if (m.permission) list.push(m.permission);
      if (m.children?.length) walk(m.children);
    });
  };
  walk(menus);
  return list;
}
