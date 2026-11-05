import http from '@/utils/http';

const PREFIX = '/auth';

export interface CsrfResult {
  csrfToken: string;
  captchaEnabled: boolean;
}

export const authApi = {
  csrf: () => http.get<CsrfResult>(`${PREFIX}/csrf`),
  login: (params: API.LoginParams) =>
    http.post<API.LoginResult>(`${PREFIX}/login`, params, {
      requestOptions: { showSuccessMsg: true, ignoreUnauthorized: true }
    }),
  logout: () => http.post<void>(`${PREFIX}/logout`),
  me: (silent = false) => http.get<API.UserInfo>(`${PREFIX}/me`, undefined, silent
    ? { requestOptions: { ignoreUnauthorized: true, showErrorMsg: false } }
    : undefined),
  menus: () => http.get<API.MenuItem[]>(`${PREFIX}/menus`)
};
