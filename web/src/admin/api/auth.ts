import http from '@/utils/http';
import { ADMIN_ROUTE_PREFIX, adminAuthPrefix, getAdminLoginEntry } from '@/config';

const prefix = (entry?: string) => {
  const path = adminAuthPrefix(entry ?? getAdminLoginEntry());
  return path.startsWith(ADMIN_ROUTE_PREFIX) ? path.slice(ADMIN_ROUTE_PREFIX.length) : path;
};

export interface CsrfResult {
  csrfToken: string;
  captchaEnabled: boolean;
}

export const authApi = {
  csrf: (entry?: string) => http.get<CsrfResult>(`${prefix(entry)}/csrf`),
  login: (params: API.LoginParams, entry?: string) =>
    http.post<API.LoginResult>(`${prefix(entry)}/login`, params, {
      requestOptions: { showSuccessMsg: true, ignoreUnauthorized: true }
    }),
  logout: (entry?: string) => http.post<void>(`${prefix(entry)}/logout`),
  me: (silent = false, entry?: string) => http.get<API.UserInfo>(`${prefix(entry)}/me`, undefined, silent
    ? { requestOptions: { ignoreUnauthorized: true, showErrorMsg: false } }
    : undefined),
  menus: (entry?: string) => http.get<API.MenuItem[]>(`${prefix(entry)}/menus`)
};
