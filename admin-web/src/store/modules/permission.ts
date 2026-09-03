import { defineStore } from 'pinia';
import type { RouteRecordRaw } from 'vue-router';
import { authApi } from '@/api/auth';
import { generateRoutes } from '@/router/dynamic';
import { staticRoutes } from '@/router/routes';
import router from '@/router';

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
      this.dynamicRoutes = generateRoutes(menus);
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
      // 同时卸载 NotFound 通配，下次登录会重新注册到动态路由之后
      if (router.hasRoute('NotFound')) {
        router.removeRoute('NotFound');
      }
      this.rawMenus = [];
      this.dynamicRoutes = [];
      this.mounted = false;
    }
  }
});
