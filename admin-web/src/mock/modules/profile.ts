/**
 * 个人中心 Mock：基本信息更新、修改密码、第三方绑定
 * 与 auth mock 共用账号定义
 */
import { ADMIN_DEMO_USER } from '../data/adminSeed';
import { fail, ok, type MockRoute } from '../types';

interface ProfileState {
  nickname: string;
  email: string;
  mobile: string;
  avatar: string;
  password: string;
}

const profile: ProfileState = {
  nickname: '超级管理员',
  email: ADMIN_DEMO_USER.email,
  mobile: ADMIN_DEMO_USER.mobile,
  avatar: 'https://avatars.githubusercontent.com/u/1?v=4',
  password: '123456'
};

interface BindingItem {
  type: 'wechat' | 'qq' | 'github' | 'dingtalk';
  bound: boolean;
  account?: string;
  boundAt?: string;
}

const bindings: BindingItem[] = [
  { type: 'wechat', bound: true, account: 'wx_admin', boundAt: '2024-08-12 10:32:00' },
  { type: 'github', bound: true, account: '@admin', boundAt: '2024-09-01 09:08:42' },
  { type: 'qq', bound: false },
  { type: 'dingtalk', bound: false }
];

export const profileMockHandlers: MockRoute[] = [
  {
    method: 'PUT',
    url: '/profile',
    handler: ({ body }) => {
      Object.assign(profile, {
        nickname: body?.nickname ?? profile.nickname,
        email: body?.email ?? profile.email,
        mobile: body?.mobile ?? profile.mobile,
        avatar: body?.avatar ?? profile.avatar
      });
      return ok(
        {
          id: 1,
          username: 'admin',
          nickname: profile.nickname,
          avatar: profile.avatar,
          email: profile.email,
          mobile: profile.mobile,
          roles: ['admin'],
          permissions: ['*:*:*']
        },
        '保存成功'
      );
    }
  },
  {
    method: 'POST',
    url: '/profile/password',
    handler: ({ body }) => {
      const { oldPassword, newPassword } = body || {};
      if (!oldPassword || !newPassword) return fail('参数不完整');
      if (oldPassword !== profile.password) return fail('原密码不正确');
      if (String(newPassword).length < 6) return fail('新密码至少 6 位');
      profile.password = newPassword;
      return ok(null, '密码已更新');
    }
  },
  {
    method: 'GET',
    url: '/profile/bindings',
    handler: () => ok(bindings)
  },
  {
    method: 'POST',
    url: /^\/profile\/bindings\/(wechat|qq|github|dingtalk)$/,
    paramNames: ['type'],
    handler: ({ pathParams, body }) => {
      const type = pathParams.type as BindingItem['type'];
      const item = bindings.find((b) => b.type === type);
      if (!item) return fail('未知的绑定类型');
      const targetBound = Boolean(body?.bound);
      item.bound = targetBound;
      if (targetBound) {
        item.account = item.account || `${type}_admin`;
        item.boundAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
      } else {
        item.account = undefined;
        item.boundAt = undefined;
      }
      return ok(null, targetBound ? '绑定成功' : '已解除绑定');
    }
  }
];
