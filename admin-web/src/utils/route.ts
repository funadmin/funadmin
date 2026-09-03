import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';

/**
 * 当前 URL 对应的一级菜单根 path（取 path 首段并加前导 /）。
 * 与侧栏/顶栏菜单项的 path（如 /dashboard、/system）对齐，避免 matched 子记录为相对段（如 dashboard）时匹配失败。
 */
export function getMenuActiveRootPath(route: RouteLocationNormalizedLoaded): string {
  const path = route.path || '/';
  const parts = path.split('/').filter(Boolean);
  if (!parts.length) return '/dashboard';
  return `/${parts[0]}`;
}

/** 拼接父路径与路由段，得到完整 path */
export function joinRoutePath(parentAbsolutePath: string, segment: string): string {
  const base = parentAbsolutePath.replace(/\/$/, '');
  const seg = segment.replace(/^\//, '');
  return seg ? `${base}/${seg}`.replace(/\/+/g, '/') : base;
}

/**
 * 从某级路由起向下找到第一个可见叶子路由的完整 path（用于双列/混合顶栏点到一级时跳转）。
 */
export function getFirstLeafRouteFullPath(route: RouteRecordRaw, parentAbsolutePath: string): string {
  const full = joinRoutePath(parentAbsolutePath, route.path);
  const visible = (route.children || []).filter((c) => !c.meta?.hidden);
  if (!visible.length) return full;
  return getFirstLeafRouteFullPath(visible[0], full);
}
