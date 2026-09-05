import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { anonymousTarget, canContinueInstallation, validateInstallForm } from '@/views/install/install';

describe('安装向导', () => {
  it('未安装时匿名访问默认引导到安装页', () => {
    expect(anonymousTarget(false)).toBe('/install');
    expect(anonymousTarget(true)).toBe('/login');
  });

  it('使用视口固定容器将内容稳定居中', () => {
    const source = readFileSync(resolve(process.cwd(), 'src/views/install/index.vue'), 'utf8');

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
