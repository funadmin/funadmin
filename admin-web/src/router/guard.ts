import type { Router, RouteLocationNormalized } from 'vue-router';
import NProgress from 'nprogress';
import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/modules/user';
import { usePermissionStore } from '@/store/modules/permission';
import { APP_CONFIG, ROUTE_WHITELIST } from '@/config';
import { i18n } from '@/locales';
import { isSystemInstalled } from '@/api/install';
import { anonymousTarget } from '@/views/install/install';
import { pluginApi } from '@/api/plugin';
import { syncPluginModules } from '@/router/pluginModules';

const LOGIN_PATH = '/login';
const HOME_PATH = '/dashboard';

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

    if (!userStore.isLoggedIn) {
      if (ROUTE_WHITELIST.includes(to.path)) return next();
      // 未安装时匿名访问统一引导到安装向导，而不是登录页
      const installed = await isSystemInstalled();
      const target = anonymousTarget(installed);
      return next({ path: target, query: target === LOGIN_PATH ? { redirect: to.fullPath } : {} });
    }
    if (to.path === LOGIN_PATH) return next({ path: HOME_PATH });
    if (permissionStore.mounted) return next();

    try {
      const [, dynamicRoutes] = await Promise.all([
        userStore.userInfo ? Promise.resolve() : userStore.fetchUserInfo(),
        permissionStore.fetchMenus()
      ]);
      dynamicRoutes.forEach((route) => router.addRoute(route));
      const moduleResult = await syncPluginModules(router, await pluginApi.enabledModules());
      moduleResult.errors.forEach((error) => console.error(`[plugin:${error.name}]`, error.message));
      if (!router.hasRoute('NotFound')) {
        router.addRoute({
          path: '/:pathMatch(.*)*',
          name: 'NotFound',
          component: () => import('@/views/error/404.vue'),
          meta: { title: '页面不存在', hidden: true }
        });
      }
      permissionStore.setMounted(true);
      next({ path: to.fullPath, replace: true });
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
