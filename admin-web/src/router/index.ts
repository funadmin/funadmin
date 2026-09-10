import { createRouter, createWebHashHistory, type Router } from 'vue-router';
import { staticRoutes } from './routes';
import { setupRouterGuard } from './guard';

const legacyPathRedirects: Record<string, string> = {
  '/development/crud': '/development/business/database',
  '/development/form/list': '/development/business/mine',
  '/development/form/designer': '/development/business/mine'
};
const legacyTarget = legacyPathRedirects[location.pathname.replace(/^\/admin-web/, '')];
if (legacyTarget && !location.hash) location.replace(`${import.meta.env.BASE_URL}#${legacyTarget}`);

export const router: Router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: staticRoutes,
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition || { top: 0 };
  }
});

export function setupRouter(app: import('vue').App) {
  setupRouterGuard(router);
  app.use(router);
}

export default router;
