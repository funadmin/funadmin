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
  if (token && !config.headers.Authorization) config.headers.Authorization = `Bearer ${token}`;
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

const PREFIX = '/v2';

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

export interface MemberTokenPair {
  access_token: string;
  refresh_token: string;
  expires_in: number;
}

export interface MemberTokenResult extends MemberTokenPair {
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

const profile = (accessToken?: string) => requestData(service.get<API.Response<{ user: MemberAccount }>>(
  `${PREFIX}/member/profile`,
  accessToken ? { headers: { Authorization: `Bearer ${accessToken}` } } : undefined
)).then(({ user }) => ({ account: user }));

export const memberAuthApi = {
  /** v2 登录只签发令牌；随后用新 access token 读取本人资料，组成会员会话。 */
  login: async ({ account, password }: MemberLoginParams): Promise<MemberTokenResult> => {
    const tokens = await requestData(service.post<API.Response<MemberTokenPair>>(`${PREFIX}/token`, { username: account, password }));
    return { ...tokens, account: (await profile(tokens.access_token)).account };
  },
  register: async (payload: MemberRegisterParams): Promise<MemberTokenResult> => {
    const { user, ...tokens } = await requestData(service.post<API.Response<MemberTokenPair & { user: MemberAccount }>>(`${PREFIX}/member`, payload));
    return { ...tokens, account: user };
  },
  refresh: (refreshToken: string) => requestData(service.post<API.Response<MemberTokenPair>>(`${PREFIX}/token/refresh`, { refresh_token: refreshToken })),
  me: () => profile(),
  updateProfile: (payload: Pick<MemberAccount, 'nickname' | 'avatar' | 'motto'>) => requestData(service.put<API.Response<{ user: MemberAccount }>>(`${PREFIX}/member/profile`, payload)).then(({ user }) => ({ account: user })),
  changePassword: (payload: { current: string; password: string; password_confirm: string }) => requestData(service.put<API.Response<null>>(`${PREFIX}/member/password`, payload)),
  logout: () => requestData(service.post<API.Response<null>>(`${PREFIX}/logout`))
};
