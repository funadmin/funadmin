import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const guardSource = readFileSync(resolve(process.cwd(), 'src/router/guard.ts'), 'utf8');
const loginSource = readFileSync(resolve(process.cwd(), 'src/views/login/index.vue'), 'utf8');

describe('登录与安装流程隔离', () => {
  it('匿名访问受保护页面直接进入登录页，不请求安装接口', () => {
    expect(guardSource).not.toContain("from '@/api/install'");
    expect(guardSource).not.toContain('isSystemInstalled()');
    expect(guardSource).not.toContain('anonymousTarget(');
    expect(guardSource).toContain("next({ path: LOGIN_PATH, query: { redirect: to.fullPath } })");
  });

  it('登录页面加载时不检测安装状态', () => {
    expect(loginSource).not.toContain("from '@/api/install'");
    expect(loginSource).not.toContain('isSystemInstalled()');
  });
});
