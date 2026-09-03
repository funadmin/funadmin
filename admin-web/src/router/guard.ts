import type { Router, RouteLocationNormalized } from 'vue-router';
import NProgress from 'nprogress';
import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/modules/user';
import { usePermissionStore } from '@/store/modules/permission';
import { APP_CONFIG, ROUTE_WHITELIST } from '@/config';
import { i18n } from '@/locales';

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
      return next({ path: LOGIN_PATH, query: { redirect: to.fullPath } });
    }
    if (to.path === LOGIN_PATH) return next({ path: HOME_PATH });
    if (permissionStore.mounted) return next();

    try {
      if (!userStore.userInfo) await userStore.fetchUserInfo();
      const dynamicRoutes = await permissionStore.fetchMenus();
      dynamicRoutes.forEach((route) => router.addRoute(route));
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
