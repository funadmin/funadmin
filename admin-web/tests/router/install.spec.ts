import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import { anonymousTarget, canContinueInstallation, validateInstallForm } from '@/views/install/install';

describe('安装向导', () => {
  it('切换数据库时只替换默认端口，保留自定义端口', async () => {
    const { databasePort } = await import('@/views/install/install');
    expect(databasePort('mysql', 'pgsql', '3306')).toBe('5432');
    expect(databasePort('pgsql', 'mysql', '5432')).toBe('3306');
    expect(databasePort('mysql', 'pgsql', '15432')).toBe('15432');
  });

  it('未安装时匿名访问默认引导到安装页', () => {
    expect(anonymousTarget(false)).toBe('/admin/install');
    expect(anonymousTarget(true)).toBe('/admin/login');
  });

  it('使用视口固定容器将内容稳定居中', () => {
    const source = readFileSync(resolve(process.cwd(), 'src/admin/views/install/index.vue'), 'utf8');

    expect(source).toMatch(/\.install-page\s*\{[^}]*position:\s*fixed[^}]*inset:\s*0/s);
    expect(source).toMatch(/\.install-page\s*\{[^}]*display:\s*grid[^}]*justify-items:\s*center/s);
    expect(source).toMatch(/\.install-shell\s*\{[^}]*width:\s*100%[^}]*max-width:\s*960px/s);
  });

  it('仅在检测项存在且所有必需项通过时允许继续', () => {
    expect(canContinueInstallation([])).toBe(false);
    expect(canContinueInstallation([
      { key: 'php', required: true, passed: true },
      { key: 'zip', required: false, passed: false }
    ])).toBe(true);
    expect(canContinueInstallation([
      { key: 'php', required: true, passed: false }
    ])).toBe(false);
  });

  it('Webman 安装密码与认证长度要求一致', () => {
    vi.stubEnv('VITE_APP_BACKEND', 'webman');
    const base = { hostname: 'localhost', port: '3306', database: 'funadmin', prefix: '', username: 'root', password: '', adminUserName: 'admin', email: 'admin@example.com', appDebug: false };
    expect(validateInstallForm({ ...base, adminPassword: 'abc123', rePassword: 'abc123' })).not.toBe('');
    expect(validateInstallForm({ ...base, adminPassword: 'safe-password-123', rePassword: 'safe-password-123' })).toBe('');
    vi.unstubAllEnvs();
  });

  it('校验管理员密码一致且包含字母和数字', () => {
    const base = {
      hostname: '127.0.0.1', port: '3306', database: 'funadmin', prefix: 'fun_',
      username: 'root', password: '', adminUserName: 'admin', email: 'admin@example.com', appDebug: false
    };

    expect(validateInstallForm({ ...base, adminPassword: 'abcdef', rePassword: 'abcdef' })).toBe('管理员密码必须同时包含字母和数字');
    expect(validateInstallForm({ ...base, adminPassword: 'abc123', rePassword: 'abc124' })).toBe('两次输入密码不一致');
    expect(validateInstallForm({ ...base, adminPassword: 'abc123', rePassword: 'abc123' })).toBe('');
  });
});
