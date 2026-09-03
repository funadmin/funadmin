import http from '@/utils/http';

export interface ProfileUpdate {
  nickname?: string;
  email?: string;
  mobile?: string;
  avatar?: string;
}

export interface ChangePasswordParams {
  oldPassword: string;
  newPassword: string;
}

export interface BindingItem {
  type: 'wechat' | 'qq' | 'github' | 'dingtalk';
  bound: boolean;
  account?: string;
  boundAt?: string;
}

const PREFIX = '/profile';

export const profileApi = {
  /** 更新基本信息 */
  update: (data: ProfileUpdate) =>
    http.put<API.UserInfo>(`${PREFIX}`, data, {
      requestOptions: { showSuccessMsg: true }
    }),

  /** 修改密码 */
  changePassword: (data: ChangePasswordParams) =>
    http.post<void>(`${PREFIX}/password`, data, {
      requestOptions: { showSuccessMsg: true }
    }),

  /** 第三方绑定列表 */
  bindings: () => http.get<BindingItem[]>(`${PREFIX}/bindings`),

  /** 绑定 / 解绑 */
  toggleBinding: (type: BindingItem['type'], bound: boolean) =>
    http.post<void>(`${PREFIX}/bindings/${type}`, { bound }, {
      requestOptions: { showSuccessMsg: true }
    })
};
