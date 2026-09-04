import { ADMIN_DEMO_ACCOUNTS, ADMIN_DEMO_USER } from '../data/adminSeed';
import { fail, ok, type MockRoute } from '../types';

const profile = {
  id: 1,
  username: 'admin',
  nickname: '超级管理员',
  email: ADMIN_DEMO_USER.email,
  mobile: ADMIN_DEMO_USER.mobile,
  avatar: 'https://avatars.githubusercontent.com/u/1?v=4',
  lastLoginIp: '127.0.0.1'
};

function profileData() {
  return { ...profile };
}

export const profileMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/profile',
    handler: () => ok(profileData())
  },
  {
    method: 'PUT',
    url: '/profile',
    handler: ({ body }) => {
      const nickname = String(body?.nickname ?? profile.nickname).trim();
      if (!nickname || nickname.length > 50) return fail('昵称不能为空且不能超过 50 个字符');
      Object.assign(profile, {
        nickname,
        email: body?.email ?? profile.email,
        mobile: body?.mobile ?? profile.mobile,
        avatar: body?.avatar ?? profile.avatar
      });
      return ok(profileData(), '资料已更新');
    }
  },
  {
    method: 'POST',
    url: '/profile/password',
    handler: ({ body }) => {
      const oldPassword = String(body?.oldPassword ?? '');
      const newPassword = String(body?.newPassword ?? '');
      if (oldPassword !== ADMIN_DEMO_ACCOUNTS.admin.password) return fail('原密码错误');
      if (newPassword.length < 8) return fail('新密码至少 8 位');
      if (newPassword === ADMIN_DEMO_ACCOUNTS.admin.password) return fail('新密码不能与原密码相同');
      ADMIN_DEMO_ACCOUNTS.admin.password = newPassword;
      return ok(null, '密码已更新，请重新登录');
    }
  }
];
