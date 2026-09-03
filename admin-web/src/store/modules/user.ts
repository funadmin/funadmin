import { defineStore } from 'pinia';
import { authApi } from '@/api/auth';
import { clearAuth, hasSession, markSession, setCsrfToken } from '@/utils/auth';

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
      const result = await authApi.csrf();
      setCsrfToken(result.csrfToken);
      return result;
    },

    async login(params: API.LoginParams) {
      await this.ensureCsrf();
      const result = await authApi.login(params);
      markSession();
      this.authenticated = true;
      return result;
    },

    async fetchUserInfo() {
      const info = await authApi.me();
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
      try {
        await authApi.logout();
      } catch {
        // 服务端会话失效时仍需清理本地状态。
      }
      this.resetState();
    },

    resetState() {
      clearAuth();
      this.authenticated = false;
      this.userInfo = null;
      this.roles = [];
      this.permissions = [];
    }
  }
});
