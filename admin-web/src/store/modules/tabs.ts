import { defineStore } from 'pinia';
import type { RouteLocationNormalized } from 'vue-router';
import { CACHE_KEYS } from '@/config';

export interface TabItem {
  path: string;
  name: string;
  title: string;
  icon?: string;
  affix: boolean;
  keepAlive: boolean;
  query?: Record<string, any>;
  params?: Record<string, any>;
}

interface TabsState {
  tabs: TabItem[];
  activePath: string;
}

const HOME_TAB: TabItem = {
  path: '/dashboard',
  name: 'Dashboard',
  title: '仪表盘',
  icon: 'i-ep-monitor',
  affix: true,
  keepAlive: true
};

export const useTabsStore = defineStore('tabs', {
  state: (): TabsState => ({
    tabs: [HOME_TAB],
    activePath: HOME_TAB.path
  }),

  getters: {
    cachedNames: (state) => state.tabs.filter((t) => t.keepAlive).map((t) => t.name)
  },

  actions: {
    addTab(route: RouteLocationNormalized) {
      // 隐藏路由 / 重定向中转 不入 tab
      if (route.meta?.hidden) {
        // 仍同步当前路径，否则 Tab 高亮会留在上一页，易误以为「页面空白」或状态错乱
        this.activePath = route.path;
        return;
      }
      if (route.path.startsWith('/redirect')) return;
      if (!route.meta?.title) return;

      const tab: TabItem = {
        path: route.path,
        name: route.name ? String(route.name) : route.path,
        title: route.meta.title as string,
        icon: route.meta?.icon as string,
        affix: Boolean(route.meta?.affix),
        keepAlive: Boolean(route.meta?.keepAlive),
        query: route.query as Recordable,
        params: route.params as Recordable
      };
      const existed = this.tabs.find((t) => t.path === tab.path);
      if (!existed) this.tabs.push(tab);
      this.activePath = tab.path;
    },

    /** 关闭指定标签，返回应跳转的下一个标签（无则返回 undefined） */
    closeTab(path: string): TabItem | undefined {
      const idx = this.tabs.findIndex((t) => t.path === path);
      if (idx === -1) return undefined;
      const tab = this.tabs[idx];
      if (tab.affix) return undefined;
      this.tabs.splice(idx, 1);
      if (this.activePath !== path) return undefined;
      const next = this.tabs[idx] || this.tabs[idx - 1];
      if (next) this.activePath = next.path;
      return next;
    },

    closeOthers(path: string) {
      this.tabs = this.tabs.filter((t) => t.affix || t.path === path);
      this.activePath = path;
    },

    closeLeft(path: string) {
      const idx = this.tabs.findIndex((t) => t.path === path);
      if (idx === -1) return;
      this.tabs = this.tabs.filter((t, i) => i >= idx || t.affix);
    },

    closeRight(path: string) {
      const idx = this.tabs.findIndex((t) => t.path === path);
      if (idx === -1) return;
      this.tabs = this.tabs.filter((t, i) => i <= idx || t.affix);
    },

    closeAll() {
      this.tabs = this.tabs.filter((t) => t.affix);
      const home = this.tabs[0];
      if (home) this.activePath = home.path;
    },

    setActive(path: string) {
      this.activePath = path;
    },

    reset() {
      this.tabs = [HOME_TAB];
      this.activePath = HOME_TAB.path;
    }
  },

  persist: {
    key: CACHE_KEYS.TABS,
    pick: ['tabs', 'activePath']
  }
});
