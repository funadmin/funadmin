import { useRouter, type Router } from 'vue-router';
import { usePermissionStore } from '@/store/modules/permission';

export function useBusinessMenuRefresh(router: Router = useRouter()) {
  const permissionStore = usePermissionStore();

  async function refreshBusinessMenu() {
    permissionStore.reset();
    const routes = await permissionStore.fetchMenus();
    routes.forEach((route) => router.addRoute(route));
    permissionStore.setMounted(true);
    return routes;
  }

  return { refreshBusinessMenu };
}
