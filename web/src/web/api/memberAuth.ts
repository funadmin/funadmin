import axios from 'axios';
import { memberSession } from '@/web/session/memberSession';
import { backendOrigin } from './backendOrigin';
import { i18n } from '@/locales';

const service = axios.create({
  baseURL: `${backendOrigin}/api`,
  timeout: 30_000
});

service.interceptors.request.use((config) => {
  config.headers['X-Locale'] = i18n.global.locale.value;
  const token = memberSession.accessToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

service.interceptors.response.use(
  (response) => {
    const body = response.data as API.Response;
    if (body.code !== 200) return Promise.reject(body);
    return response;
  },
  (error) => {
    if (error?.response?.status === 401) memberSession.clear();
    return Promise.reject(error?.response?.data || error);
  }
);

const PREFIX = '/v3/auth';

const requestData = async <T>(request: Promise<{ data: API.Response<T> }>): Promise<T> => {
  const response = await request;
  const body = response.data;
  if (body.code !== 200) throw body;
  return body.data;
};



export interface MemberAccount {
  id: number;
  username: string;
  nickname: string;
  email?: string;
  mobile?: string;
  avatar?: string;
  motto?: string;
  created_at?: string;
  last_login?: number | string;
}

export interface MemberTokenResult {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  expires_at?: number;
  account: MemberAccount;
}

export interface MemberLoginParams {
  account: string;
  password: string;
}

export interface MemberRegisterParams {
  username: string;
  email: string;
  nickname?: string;
  password: string;
  password_confirm: string;
}

export const memberAuthApi = {
  login: (payload: MemberLoginParams) => requestData(service.post<API.Response<MemberTokenResult>>(`${PREFIX}/login`, payload)),
  register: (payload: MemberRegisterParams) => requestData(service.post<API.Response<MemberTokenResult>>(`${PREFIX}/register`, payload)),
  refresh: (refreshToken: string) => requestData(service.post<API.Response<Omit<MemberTokenResult, 'account'>>>(`${PREFIX}/refresh`, { refresh_token: refreshToken })),
  me: () => requestData(service.post<API.Response<{ account: MemberAccount }>>(`${PREFIX}/me`)),
  updateProfile: (payload: Pick<MemberAccount, 'nickname' | 'avatar' | 'motto'>) => requestData(service.put<API.Response<{ account: MemberAccount }>>(`${PREFIX}/profile`, payload)),
  changePassword: (payload: { current: string; password: string; password_confirm: string }) => requestData(service.post<API.Response<{ logged_out: boolean }>>(`${PREFIX}/password`, payload)),
  logout: () => requestData(service.post<API.Response<{ logged_out: boolean }>>(`${PREFIX}/logout`))
};
