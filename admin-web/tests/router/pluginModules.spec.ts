import { describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';
import type { Router } from 'vue-router';
import {
  clearPluginModules,
  syncPluginModules,
  type EnabledPluginModule
} from '@/router/pluginModules';

const descriptor = (code: string): EnabledPluginModule => ({
  code,
  version: '1.0.0',
  components: { Index: 'Index.vue' },
  routes: [{ path: `/plugin/${code}/index`, name: `Plugin_${code}`, component: 'Index', meta: { title: code } }]
});

const modules = (names: string[]): Record<string, Component> => Object.fromEntries(
  names.map((name) => [`../modules/${name}/Index.vue`, {} as Component])
);

function routerStub() {
  const names = new Set<string>();
  return {
    addRoute: vi.fn((route: { name?: string }) => names.add(String(route.name))),
    removeRoute: vi.fn((name: string) => names.delete(name)),
    hasRoute: vi.fn((name: string) => names.has(name))
  } as unknown as Router;
}

describe('pluginModules', () => {
  it('通过构建时组件映射挂载插件路由', async () => {
    const router = routerStub();
    const result = await syncPluginModules(router, [descriptor('example')], {
      modules: modules(['example'])
    });

    expect(result.loaded).toEqual(['example']);
    expect(router.addRoute).toHaveBeenCalledWith(expect.objectContaining({
      name: 'Plugin_example',
      component: expect.any(Object)
    }));
  });

  it('组件未被当前构建发现时挂载受控错误页且不影响其他插件', async () => {
    const router = routerStub();
    const result = await syncPluginModules(router, [descriptor('missing'), descriptor('healthy')], {
      modules: modules(['healthy'])
    });

    expect(result.loaded).toEqual(['healthy']);
    expect(result.errors[0]).toMatchObject({ code: 'missing', stage: 'component' });
    expect(router.addRoute).toHaveBeenCalledTimes(2);
    expect(router.addRoute).toHaveBeenCalledWith(expect.objectContaining({
      name: 'Plugin_missing_Error',
      component: expect.any(Function)
    }));
  });

  it('拒绝越界路由并继续加载其他插件', async () => {
    const router = routerStub();
    const broken = descriptor('broken');
    broken.routes[0].name = 'Outside';
    const result = await syncPluginModules(router, [broken, descriptor('healthy')], {
      modules: modules(['broken', 'healthy'])
    });

    expect(result.loaded).toEqual(['healthy']);
    expect(result.errors[0]).toMatchObject({ code: 'broken', stage: 'route' });
  });

  it('重复同步不重复挂载并移除已禁用插件路由', async () => {
    const router = routerStub();
    const options = { modules: modules(['demo']) };
    await syncPluginModules(router, [descriptor('demo')], options);
    await syncPluginModules(router, [descriptor('demo')], options);
    expect(router.addRoute).toHaveBeenCalledTimes(1);
    await syncPluginModules(router, [], options);
    expect(router.removeRoute).toHaveBeenCalledWith('Plugin_demo');
  });

  it('Router 实例重建后恢复路由', async () => {
    const firstRouter = routerStub();
    const secondRouter = routerStub();
    const item = descriptor('reloadable');
    const options = { modules: modules(['reloadable']) };
    await syncPluginModules(firstRouter, [item], options);
    await syncPluginModules(secondRouter, [item], options);
    expect(secondRouter.addRoute).toHaveBeenCalledOnce();
    clearPluginModules(secondRouter);
  });
});
