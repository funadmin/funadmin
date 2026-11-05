import type { Router, RouteLocationNormalized } from 'vue-router';
import NProgress from 'nprogress';
import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/modules/user';
import { usePermissionStore } from '@/store/modules/permission';
import { memberSession } from '@/web/session/memberSession';
import { APP_CONFIG, ADMIN_HOME_PATH, ADMIN_LOGIN_PATH, ADMIN_ROUTE_PREFIX, MEMBER_LOGIN_PATH, ROUTE_WHITELIST, isWebmanBackend } from '@/config';
import { isSystemInstalled } from '@/api/install';
import { i18n } from '@/locales';
import { loadPluginModulesSafely } from '@/router/pluginStartup';

const LOGIN_PATH = ADMIN_LOGIN_PATH;
const HOME_PATH = ADMIN_HOME_PATH;

function translateRouteTitle(to: RouteLocationNormalized): string {
  const name = to.name != null ? String(to.name) : '';
  const key = `menu.${name}`;
  if (name && i18n.global.te(key)) return i18n.global.t(key) as string;
  return (to.meta?.title as string) || '';
}

export function setupRouterGuard(router: Router) {
  router.beforeEach(async (to, _from, next) => {
    NProgress.start();
    const pageTitle = translateRouteTitle(to);
    document.title = pageTitle ? `${pageTitle} - ${APP_CONFIG.title}` : APP_CONFIG.title;

    const userStore = useUserStore();
    const permissionStore = usePermissionStore();

    if (to.meta.member) {
      if (to.meta.public) return next();
      if (memberSession.authenticated()) return next();
      return next({ path: MEMBER_LOGIN_PATH, query: { redirect: to.fullPath } });
    }
    if (to.meta.public) return next();
    if (to.path === LOGIN_PATH && isWebmanBackend() && !(await isSystemInstalled())) {
      return next({ path: `${ADMIN_ROUTE_PREFIX}/install`, replace: true });
    }
    if (to.path === LOGIN_PATH && isWebmanBackend() && !userStore.isLoggedIn) {
      try {
        await userStore.fetchUserInfo(true);
      } catch {
        // Cookie 不可用或服务暂时异常时保留登录表单。
      }
    }
    if (!userStore.isLoggedIn) {
      if (ROUTE_WHITELIST.includes(to.path)) return next();
      return next({ path: LOGIN_PATH, query: { redirect: to.fullPath } });
    }
    if (to.path === LOGIN_PATH) return next({ path: HOME_PATH });
    const hasRoutePermission = (permission: unknown): boolean => {
      if (!permission) return true;
      const required = Array.isArray(permission) ? permission.map(String) : [String(permission)];
      const granted = userStore.permissions || [];
      return granted.some((code) => code === '*' || code === '*:*:*') || required.some((code) => granted.includes(code));
    };
    if (permissionStore.mounted) {
      return hasRoutePermission(to.meta?.permission) ? next() : next({ path: `${ADMIN_ROUTE_PREFIX}/403`, replace: true });
    }

    try {
      const [, dynamicRoutes] = await Promise.all([
        userStore.userInfo ? Promise.resolve() : userStore.fetchUserInfo(),
        permissionStore.fetchMenus()
      ]);
      dynamicRoutes.forEach((route) => router.addRoute(route));
      await loadPluginModulesSafely(router);
      if (router.hasRoute('BootstrapNotFound')) router.removeRoute('BootstrapNotFound');
      if (!router.hasRoute('NotFound')) {
        router.addRoute({
          path: `${ADMIN_ROUTE_PREFIX}/:pathMatch(.*)*`,
          name: 'NotFound',
          component: () => import('@/views/error/404.vue'),
          meta: { title: '页面不存在', hidden: true }
        });
      }
      permissionStore.setMounted(true);
      const resolved = router.resolve({ path: to.path, query: to.query, hash: to.hash });
      next(hasRoutePermission(resolved.meta?.permission)
        ? { path: to.path, query: to.query, hash: to.hash, replace: true }
        : { path: `${ADMIN_ROUTE_PREFIX}/403`, replace: true });
    } catch (e: any) {
      ElMessage.error(e?.message || (i18n.global.t('common.fetchPermissionFailed') as string));
      userStore.resetState();
      permissionStore.reset();
      next({ path: LOGIN_PATH, query: { redirect: to.fullPath } });
    }
  });

  router.afterEach(() => NProgress.done());
  router.onError((error) => {
    NProgress.done();
    console.error('[router error]', error);
  });
}
