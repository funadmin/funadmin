import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const read = (path: string) => readFileSync(resolve(process.cwd(), path), 'utf8');

describe('Webman 独立迁移模式', () => {
  it('使用独立端口与代理，保留原默认启动方式', () => {
    const mode = read('.env.webman');
    expect(mode).toContain('VITE_APP_PROXY_TARGET=http://127.0.0.1:8787');
    expect(mode).toContain('VITE_APP_PORT=5174');
    expect(mode).toContain('VITE_APP_BACKEND=webman');
    expect(read('package.json')).toContain('vite --mode webman');
  });

  it('移除四合一原型页面和 API，保留正式系统管理页面', () => {
    for (const path of ['src/admin/views/webman/management.vue', 'src/admin/api/webman/management.ts']) {
      expect(existsSync(resolve(process.cwd(), path))).toBe(false);
    }
    for (const resource of ['user', 'role', 'permission', 'menu']) {
      expect(existsSync(resolve(process.cwd(), `src/admin/views/system/${resource}/index.vue`))).toBe(true);
      expect(read(`src/admin/api/system/${resource}.ts`)).not.toContain('/management');
    }
  });
});
