import { createRouter, createWebHashHistory, type Router } from 'vue-router';
import { staticRoutes } from './routes';
import { setupRouterGuard } from './guard';

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
