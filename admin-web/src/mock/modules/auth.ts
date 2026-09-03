/** Session 认证 Mock，仅用于 VITE_APP_MOCK=true 的前端演示。 */
import { ADMIN_DEMO_USER, getAdminMenuTreeSeed } from '../data/adminSeed';
import { fail, ok, type MockRoute } from '../types';

interface MockAccount {
  username: string;
  password: string;
  user: API.UserInfo;
}

const accounts: MockAccount[] = [
  {
    username: 'admin',
    password: '123456',
    user: {
      id: 1,
      username: 'admin',
      nickname: '超级管理员',
      avatar: 'https://avatars.githubusercontent.com/u/1?v=4',
      email: ADMIN_DEMO_USER.email,
      mobile: ADMIN_DEMO_USER.mobile,
      roles: ['role:1'],
      permissions: ['*', 'system:dict:list', 'system:dict:add', 'system:dict:edit', 'system:dict:delete']
    }
  },
  {
    username: 'guest',
    password: '123456',
    user: {
      id: 2,
      username: 'guest',
      nickname: '游客',
      avatar: 'https://avatars.githubusercontent.com/u/2?v=4',
      email: 'guest@demo.local',
      mobile: '13900000000',
      roles: ['role:2'],
      permissions: ['system:dict:list']
    }
  }
];

let currentAccount = accounts[0];
const menus: API.MenuItem[] = getAdminMenuTreeSeed();

export const authMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/auth/csrf',
    handler: () => ok({ csrfToken: 'mock-csrf-token', captchaEnabled: false })
  },
  {
    method: 'POST',
    url: '/auth/login',
    handler: ({ body }) => {
      const account = accounts.find((item) => item.username === body?.username);
      if (!account || account.password !== body?.password) return fail('账号或密码错误');
      currentAccount = account;
      return ok<API.LoginResult>({ authenticated: true }, '登录成功');
    }
  },
  {
    method: 'POST',
    url: '/auth/logout',
    handler: () => ok(null, '已退出登录')
  },
  {
    method: 'GET',
    url: '/auth/me',
    handler: () => ok(currentAccount.user)
  },
  {
    method: 'GET',
    url: '/auth/menus',
    handler: () => ok(menus)
  }
];
