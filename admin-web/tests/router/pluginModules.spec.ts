import { describe, expect, it, vi } from 'vitest';
import type { Router } from 'vue-router';
import {
  isAllowedPluginModuleUrl,
  syncPluginModules,
  type EnabledPluginModule,
  type PluginEsmModule
} from '@/router/pluginModules';

const descriptor = (name: string, entryUrl: string): EnabledPluginModule => ({
  name,
  version: '1.0.0',
  hash: 'a'.repeat(64),
  entryUrl,
  routes: [{ path: `/plugin/${name}/index`, name: `Plugin_${name}`, component: 'Index', meta: { title: name } }]
});

function routerStub() {
  const names = new Set<string>();
  return {
    addRoute: vi.fn((route: { name?: string }) => names.add(String(route.name))),
    removeRoute: vi.fn((name: string) => names.delete(name)),
    hasRoute: vi.fn((name: string) => names.has(name))
  } as unknown as Router;
}

describe('pluginModules', () => {
  it('只接受当前 origin 的 plugin-assets 前缀', () => {
    expect(isAllowedPluginModuleUrl('/plugin-assets/demo/index.js', 'https://admin.example.com')).toBe(true);
    expect(isAllowedPluginModuleUrl('/admin-web/plugin-assets/demo/index.js', 'https://admin.example.com', '/admin-web/')).toBe(true);
    expect(isAllowedPluginModuleUrl('/other/plugin-assets/demo/index.js', 'https://admin.example.com', '/admin-web/')).toBe(false);
    expect(isAllowedPluginModuleUrl('https://admin.example.com/plugin-assets/demo/index.js', 'https://admin.example.com')).toBe(true);
    expect(isAllowedPluginModuleUrl('https://evil.example/plugin-assets/demo/index.js', 'https://admin.example.com')).toBe(false);
    expect(isAllowedPluginModuleUrl('/static/demo/index.js', 'https://admin.example.com')).toBe(false);
    expect(isAllowedPluginModuleUrl('/plugin-assets/../admin/index.js', 'https://admin.example.com')).toBe(false);
  });

  it('加载标准 fixture 发布根下的 entry.js', async () => {
    const router = routerStub();
    const importer = vi.fn(async (): Promise<PluginEsmModule> => ({ register: () => ({ components: { Index: {} } }) }));
    const result = await syncPluginModules(router, [
      descriptor('example', '/plugin-assets/example/entry.js?v=' + 'a'.repeat(64))
    ], { origin: 'https://admin.example.com', importer });

    expect(result.loaded).toEqual(['example']);
    expect(importer).toHaveBeenCalledWith(`https://admin.example.com/plugin-assets/example/entry.js?v=${'a'.repeat(64)}`);
  });

  it('隔离单个插件加载失败并挂载其余插件', async () => {
    const router = routerStub();
    const importer = vi.fn(async (url: string): Promise<PluginEsmModule> => {
      if (url.includes('broken')) throw new Error('load failed');
      return { register: () => ({ components: { Index: {} } }) };
    });
    const result = await syncPluginModules(router, [
      descriptor('broken', '/plugin-assets/broken/index.js'),
      descriptor('healthy', '/plugin-assets/healthy/index.js')
    ], { origin: 'https://admin.example.com', importer });

    expect(result.loaded).toEqual(['healthy']);
    expect(result.errors).toHaveLength(1);
    expect(router.addRoute).toHaveBeenCalledTimes(1);
  });

  it('重复同步不重复挂载并移除已禁用插件路由', async () => {
    const router = routerStub();
    const importer = vi.fn(async (): Promise<PluginEsmModule> => ({ register: () => ({ components: { Index: {} } }) }));
    await syncPluginModules(router, [descriptor('demo', '/plugin-assets/demo/index.js')], { origin: 'https://admin.example.com', importer });
    await syncPluginModules(router, [descriptor('demo', '/plugin-assets/demo/index.js')], { origin: 'https://admin.example.com', importer });
    expect(router.addRoute).toHaveBeenCalledTimes(1);

    await syncPluginModules(router, [], { origin: 'https://admin.example.com', importer });
    expect(router.removeRoute).toHaveBeenCalledWith('Plugin_demo');
  });

  it('Router 实例重建后不因旧 mounted 签名跳过路由恢复', async () => {
    const firstRouter = routerStub();
    const secondRouter = routerStub();
    const importer = vi.fn(async (): Promise<PluginEsmModule> => ({ register: () => ({ components: { Index: {} } }) }));
    const item = descriptor('reloadable', '/plugin-assets/reloadable/index.js');

    await syncPluginModules(firstRouter, [item], { origin: 'https://admin.example.com', importer });
    await syncPluginModules(secondRouter, [item], { origin: 'https://admin.example.com', importer });

    expect(secondRouter.addRoute).toHaveBeenCalledOnce();
  });
});
