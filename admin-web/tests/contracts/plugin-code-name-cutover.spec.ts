import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../../..');
const read = (relative: string) => fs.readFileSync(path.join(root, relative), 'utf8');

describe('插件 code/name 字段硬切契约', () => {
  it('前端插件业务类型只暴露 code/name', () => {
    const api = read('admin-web/src/api/system/plugin.ts');

    expect(api).toMatch(/interface PluginItem\s*\{[\s\S]*?code: string;[\s\S]*?name: string;/);
    expect(api).toMatch(/interface MarketplacePlugin\s*\{[\s\S]*?code: string;[\s\S]*?name: string;/);
    expect(api).toMatch(/interface MarketplaceVersion\s*\{[\s\S]*?pluginCode: string;/);
    expect(api).toMatch(/interface EnabledPluginModule\s*\{[\s\S]*?code: string;/);
    expect(api).not.toMatch(/interface PluginItem\s*\{[\s\S]*?\btitle:/);
    expect(api).not.toMatch(/interface MarketplacePlugin\s*\{[\s\S]*?\btitle:/);
    expect(api).not.toContain('pluginName: string');
  });

  it('API 标识参数与更新检查 payload 使用 code', () => {
    const api = read('admin-web/src/api/system/plugin.ts');

    expect(api).toContain("marketDetail: (code: string)");
    expect(api).toMatch(/interface UpdateCheckRequest\s*\{[\s\S]*?code: string;[\s\S]*?code_version: string;[\s\S]*?db_version: string;[\s\S]*?modified: boolean;/);
    expect(api).toContain("checkUpdates: (installed: UpdateCheckRequest[])");
    expect(api).toContain("detail: (code: string)");
    expect(api).not.toMatch(/\((?:name|pluginName): string/);
  });

  it('插件中心表格与操作统一使用 row.code', () => {
    const page = read('admin-web/src/views/system/plugin/index.vue');

    expect(page).toContain('prop="code" label="插件标识"');
    expect(page).toContain('prop="name" label="名称"');
    expect(page).not.toContain('prop="title"');
    expect(page).not.toContain('row.name');
  });

  it('动态模块 descriptor 的插件标识使用 code，Vue route name 保持不变', () => {
    const api = read('admin-web/src/api/system/plugin.ts');
    const modules = read('admin-web/src/router/pluginModules.ts');

    expect(api).toMatch(/interface PluginRouteDto\s*\{[\s\S]*?name: string;/);
    expect(modules).toContain('descriptor.code');
    expect(modules).not.toContain('descriptor.name');
    expect(modules).toContain('dto.name');
  });
});
