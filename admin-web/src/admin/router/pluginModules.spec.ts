import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { createMemoryHistory, createWebHashHistory, createRouter } from 'vue-router';
import type { EnabledPluginModule } from '@/api/system/plugin';
import { clearPluginModules, syncPluginModules } from './pluginModules';
import { loadPluginModulesSafely } from './pluginStartup';
import Layout from '@/layout/index.vue';

vi.mock('@/layout/index.vue', () => ({ default: { template: '<router-view />' } }));

const descriptor = (): EnabledPluginModule => ({
  code: 'example', version: '1.0.0', components: { Index: 'Index.vue' },
  routes: [{ path: '/plugin/example/items', name: 'Plugin_example_Items', component: 'Index', meta: { title: '示例', permission: 'example:item:list' } }]
});
const router = () => createRouter({ history: createMemoryHistory(), routes: [] });
const modules = { '../modules/example/Index.vue': defineComponent({ template: '<div />' }) };
let activeRouter = router();

afterEach(() => { clearPluginModules(activeRouter); vi.unstubAllEnvs(); });

describe('Webman plugin module routing', () => {
  it.each([
    ['/', '/#/admin/plugin/example/items'],
    ['/console/', '/console/#/admin/plugin/example/items']
  ])('uses deployment base %s only in the browser href', async (base, href) => {
    const previousUrl = window.location.href;
    const previousState = window.history.state;
    window.history.replaceState({}, '', base);
    const history = createWebHashHistory(base);
    activeRouter = createRouter({ history, routes: [] });
    try {
      await syncPluginModules(activeRouter, [descriptor()], { modules });
      const resolved = activeRouter.resolve('/admin/plugin/example/items');
      const browserUrl = new URL(resolved.href, window.location.href);
      expect(browserUrl.pathname + browserUrl.hash).toBe(href);
      expect(resolved.path).toBe('/admin/plugin/example/items');
      expect(resolved.meta.activeMenu).toBe('/admin/plugin/example/items');
    } finally {
      history.destroy();
      window.history.replaceState(previousState, '', previousUrl);
    }
  });

  it('loads enabled plugin modules in Webman mode', async () => {
    vi.stubEnv('VITE_APP_BACKEND', 'webman');
    activeRouter = router();
    const fetch = vi.fn(async () => [descriptor()]);
    expect((await loadPluginModulesSafely(activeRouter, fetch)).loaded).toEqual(['example']);
    expect(fetch).toHaveBeenCalledOnce();
    expect(activeRouter.resolve('/admin/plugin/example/items').name).toBe('Plugin_example_Items');
  });

  it('replaces the menu placeholder and removes all routes when disabled', async () => {
    activeRouter = router();
    activeRouter.addRoute({ path: '/admin/plugin/example/items', name: 'Menu_99', component: Layout });
    await syncPluginModules(activeRouter, [descriptor()], { modules });
    expect(activeRouter.hasRoute('Menu_99')).toBe(false);
    expect(activeRouter.resolve('/admin/plugin/example/items').matched.map(route => route.name)).toEqual(['Plugin_example_Items_Layout', 'Plugin_example_Items']);
    expect(activeRouter.resolve('/admin/plugin/example/items').meta.activeMenu).toBe('/admin/plugin/example/items');
    await syncPluginModules(activeRouter, [descriptor()], { modules });
    expect(activeRouter.getRoutes()).toHaveLength(2);
    await syncPluginModules(activeRouter, [], { modules });
    expect(activeRouter.getRoutes()).toHaveLength(0);
  });

  it('mounts a diagnostic route for missing build assets and clears it on logout', async () => {
    activeRouter = router();
    const result = await syncPluginModules(activeRouter, [descriptor()], { modules: {} });
    expect(result.errors[0]?.stage).toBe('component');
    expect(activeRouter.resolve('/admin/plugin/example/error').name).toBe('Plugin_example_Error');
    clearPluginModules(activeRouter);
    expect(activeRouter.getRoutes()).toHaveLength(0);
  });

  it('rejects route names belonging to another plugin', async () => {
    activeRouter = router();
    const value = descriptor();
    value.routes[0]!.name = 'Plugin_exampleother_Items';
    const result = await syncPluginModules(activeRouter, [value], { modules });
    expect(result.errors[0]?.stage).toBe('route');
    expect(activeRouter.hasRoute('Plugin_exampleother_Items')).toBe(false);
  });
});
