import { describe, expect, it } from 'vitest';
import {
  ADMIN_LOGIN_PATH,
  DEFAULT_ADMIN_LOGIN_ENTRY,
  adminAuthPrefix,
  adminLoginPath,
  normalizeAdminLoginEntry
} from '@/config';

describe('动态后台登录入口', () => {
  it('默认入口保持原路径，自定义入口同时改变页面和认证前缀', () => {
    expect(DEFAULT_ADMIN_LOGIN_ENTRY).toBe('admin');
    expect(ADMIN_LOGIN_PATH).toBe('/admin/login');
    expect(adminLoginPath()).toBe('/admin/login');
    expect(adminAuthPrefix()).toBe('/admin/auth');
    expect(adminLoginPath('secure-login')).toBe('/admin/secure-login/login');
    expect(adminAuthPrefix('secure-login')).toBe('/admin/secure-login/auth');
  });

  it('拒绝保留词和不安全入口', () => {
    expect(() => normalizeAdminLoginEntry('auth')).toThrow();
    expect(() => normalizeAdminLoginEntry('abc')).toThrow();
    expect(normalizeAdminLoginEntry(' Secure-Login ')).toBe('secure-login');
  });
});
