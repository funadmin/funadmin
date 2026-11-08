import { hooks } from '@/shared/plugins/hooks/runtime';
import { defineStore } from 'pinia';
import { authApi } from '@/api/auth';
import { applyRemotePack } from '@/locales/remotePack';
import { useAppStore } from '@/store/modules/app';
import { clearAuth, hasSession, markSession, setCsrfToken } from '@/utils/auth';
import { getAdminLoginEntry, setAdminLoginEntry } from '@/config';

interface UserState {
  authenticated: boolean;
  userInfo: API.UserInfo | null;
  roles: string[];
  permissions: string[];
}

export const useUserStore = defineStore('user', {
  state: (): UserState => ({
    authenticated: hasSession(),
    userInfo: null,
    roles: [],
    permissions: []
  }),

  getters: {
    isLoggedIn: (state) => state.authenticated,
    avatar: (state) => state.userInfo?.avatar || '',
    nickname: (state) => state.userInfo?.nickname || state.userInfo?.username || ''
  },

  actions: {
    async ensureCsrf() {
      const result = await authApi.csrf(getAdminLoginEntry());
      setCsrfToken(result.csrfToken);
      return result;
    },

    async login(params: API.LoginParams, entry?: string) {
      if (entry) setAdminLoginEntry(entry);
      const selectedEntry = getAdminLoginEntry();
      await this.ensureCsrf();
      const result = await authApi.login(params, selectedEntry);
      markSession();
      this.authenticated = true;
      try {
        await this.fetchUserInfo(false, selectedEntry);
      } catch (error) {
        this.resetState();
        throw error;
      }
      void applyRemotePack(useAppStore().locale);
      return result;
    },

    async fetchUserInfo(silent = false, entry?: string) {
      await this.ensureCsrf();
      const info = await authApi.me(silent, entry ?? getAdminLoginEntry());
      this.userInfo = info;
      this.roles = info.roles || [];
      this.permissions = info.permissions || [];
      this.authenticated = true;
      markSession();
      return info;
    },

    updateUserInfo(patch: Partial<API.UserInfo>) {
      if (!this.userInfo) return;
      this.userInfo = { ...this.userInfo, ...patch } as API.UserInfo;
    },

    async logout() {
      this.authenticated = false;
      hooks.clear();
      try {
        await this.ensureCsrf();
        await authApi.logout(getAdminLoginEntry());
      } catch {
        // 服务端会话失效时仍需清理本地状态。
      }
      this.resetState();
    },

    resetState() {
      hooks.clear();
      clearAuth();
      this.authenticated = false;
      this.userInfo = null;
      this.roles = [];
      this.permissions = [];
    }
  }
});
