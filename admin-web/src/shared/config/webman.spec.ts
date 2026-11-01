import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const read = (path: string) => readFileSync(resolve(process.cwd(), 'src', path), 'utf8');

describe('Webman 独立迁移模式', () => {
  it('使用独立端口与代理，保留原默认启动方式', () => {
    const mode = read('../.env.webman');
    expect(mode).toContain('VITE_APP_PROXY_TARGET=http://127.0.0.1:8787');
    expect(mode).toContain('VITE_APP_PORT=5174');
    expect(mode).toContain('VITE_APP_BACKEND=webman');
    expect(read('../package.json')).toContain('vite --mode webman');
  });

  it('Webman 启动不请求尚未迁移的插件与远程语言包', () => {
    expect(read('router/pluginStartup.ts')).toContain('isWebmanBackend');
    expect(read('locales/remotePack.ts')).toContain('isWebmanBackend');
  });

  it('后台页面提供四类资源管理，不使用旧角色继承协议', () => {
    const page = read('views/webman/management.vue');
    for (const key of ['users', 'roles', 'permissions', 'menus']) expect(page).toContain(key);
    expect(page).toContain('managementApi');
    expect(page).not.toContain('roleApi.authorization');
  });
});
