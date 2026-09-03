import { CACHE_KEYS } from '@/config';
import { local } from './storage';

const SESSION_FLAG = 'authenticated';
const CSRF_KEY = 'ADMIN_CSRF_TOKEN';

/** Session Cookie 由浏览器管理，本地只记录是否需要尝试恢复会话。 */
export const hasSession = (): boolean => local.get<string>(CACHE_KEYS.TOKEN) === SESSION_FLAG;
export const markSession = (): void => local.set(CACHE_KEYS.TOKEN, SESSION_FLAG);
export const getCsrfToken = (): string => local.get<string>(CSRF_KEY) || '';
export const setCsrfToken = (token: string): void => local.set(CSRF_KEY, token);

export const clearAuth = (): void => {
  local.remove(CACHE_KEYS.TOKEN);
  local.remove(CSRF_KEY);
  local.remove(CACHE_KEYS.USER_INFO);
};
