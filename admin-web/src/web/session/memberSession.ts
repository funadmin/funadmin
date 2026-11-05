import { hooks } from '@/shared/plugins/hooks/runtime';
import type { MemberAccount, MemberTokenResult } from '@/web/api/memberAuth';

const ACCESS_KEY = 'WEB_MEMBER_ACCESS_TOKEN';
const REFRESH_KEY = 'WEB_MEMBER_REFRESH_TOKEN';
const ACCOUNT_KEY = 'WEB_MEMBER_ACCOUNT';
const browserStorage = window.sessionStorage;


const read = <T>(key: string): T | null => {
  try {
    const raw = browserStorage.getItem(key);
    return raw ? JSON.parse(raw) as T : null;
  } catch {
    return null;
  }
};

const write = (key: string, value: unknown): void => {
  browserStorage.setItem(key, JSON.stringify(value));
};

export const memberSession = {
  accessToken: (): string => read<string>(ACCESS_KEY) || '',
  refreshToken: (): string => read<string>(REFRESH_KEY) || '',
  account: (): MemberAccount | null => read<MemberAccount>(ACCOUNT_KEY),
  authenticated: (): boolean => Boolean(read<string>(ACCESS_KEY)),
  save: (result: Pick<MemberTokenResult, 'access_token' | 'refresh_token' | 'account'>): void => {
    hooks.clear();
    write(ACCESS_KEY, result.access_token);
    write(REFRESH_KEY, result.refresh_token);
    write(ACCOUNT_KEY, result.account);
    window.dispatchEvent(new Event('plugin-hooks-session'));
  },
  updateAccount: (account: MemberAccount): void => write(ACCOUNT_KEY, account),
  clear: (): void => {
    hooks.clear();
    browserStorage.removeItem(ACCESS_KEY);
    browserStorage.removeItem(REFRESH_KEY);
    browserStorage.removeItem(ACCOUNT_KEY);
    window.dispatchEvent(new Event('plugin-hooks-session'));
  }
};
