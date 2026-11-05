import { defineStore } from 'pinia';
import { markRaw } from 'vue';
import type { RouteRecordRaw } from 'vue-router';
import { authApi } from '@/api/auth';
import { generateRoutes } from '@/router/dynamic';
import { staticRoutes } from '@/router/routes';
import router from '@/router';
import { clearPluginModules } from '@/router/pluginModules';

interface PermissionState {
  /** 后端原始菜单 */
  rawMenus: API.MenuItem[];
  /** 转换后的动态路由 */
  dynamicRoutes: RouteRecordRaw[];
  /** 已挂载（避免重复 addRoute） */
  mounted: boolean;
}

export const usePermissionStore = defineStore('permission', {
  state: (): PermissionState => ({
    rawMenus: [],
    dynamicRoutes: [],
    mounted: false
  }),

  getters: {
    /** 完整菜单（含静态默认菜单） */
    menus: (state) => [...staticRoutes.filter((r) => !r.meta?.hidden), ...state.dynamicRoutes]
  },

  actions: {
    async fetchMenus() {
      const menus = await authApi.menus();
      this.rawMenus = menus;
      this.dynamicRoutes = markRaw(generateRoutes(menus));
      return this.dynamicRoutes;
    },

    setMounted(mounted: boolean) {
      this.mounted = mounted;
    },

    /** 重置权限：从 router 中卸载已挂载的动态路由，避免账号切换后菜单残留 */
    reset() {
      // 卸载顶层动态路由（子路由会随之被卸载）
      this.dynamicRoutes.forEach((route) => {
        const name = route.name as string | undefined;
        if (name && router.hasRoute(name)) {
          router.removeRoute(name);
        }
      });
      clearPluginModules(router);
      // 同时卸载正式 NotFound，并恢复启动占位通配供下次深链导航使用
      if (router.hasRoute('NotFound')) router.removeRoute('NotFound');
      if (!router.hasRoute('BootstrapNotFound')) {
        const bootstrapNotFound = staticRoutes.find((route) => route.name === 'BootstrapNotFound');
        if (bootstrapNotFound) router.addRoute(bootstrapNotFound);
      }
      this.rawMenus = [];
      this.dynamicRoutes = [];
      this.mounted = false;
    }
  }
});
