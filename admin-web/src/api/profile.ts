import http from '@/utils/http';

export interface ProfileInfo {
  id: number;
  username: string;
  nickname: string;
  email?: string;
  mobile?: string;
  avatar: string;
  lastLoginIp?: string;
}

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

const PREFIX = '/profile';

export const profileApi = {
  detail: () => http.get<ProfileInfo>(PREFIX),
  update: (data: ProfileUpdate) =>
    http.put<ProfileInfo>(PREFIX, data, {
      requestOptions: { showSuccessMsg: true }
    }),
  changePassword: (data: ChangePasswordParams) =>
    http.post<void>(`${PREFIX}/password`, data, {
      requestOptions: { showSuccessMsg: true }
    })
};
