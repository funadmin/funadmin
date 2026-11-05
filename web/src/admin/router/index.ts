import { nextTick } from 'vue';
import { createRouter, createWebHashHistory, type Router } from 'vue-router';
import { staticRoutes } from './routes';
import { setupRouterGuard } from './guard';

export const router: Router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: staticRoutes,
  async scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (['/', '/features', '/quick-start'].includes(to.path)) {
      const sections = ['features', 'showcase', 'architecture', 'quick-start'];
      const anchor = to.hash ? to.hash.slice(1) : to.path.slice(1);
      if (sections.includes(anchor)) {
        await nextTick();
        const el = document.getElementById(anchor);
        if (el) {
          const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
          return { el, behavior: reducedMotion ? 'auto' : 'smooth' };
        }
      }
    }
    return { top: 0 };
  }
});

export function setupRouter(app: import('vue').App) {
  setupRouterGuard(router);
  app.use(router);
}

export default router;
