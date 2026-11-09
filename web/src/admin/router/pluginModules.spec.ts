import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { createMemoryHistory, createWebHashHistory, createRouter } from 'vue-router';
import type { EnabledPluginModule } from '@/api/system/plugin';
import { clearPluginModules, syncPluginModules } from './pluginModules';
import Layout from '@/layout/index.vue';
import { generateRoutes } from './dynamic';

vi.mock('@/layout/index.vue', () => ({ default: { template: '<router-view />' } }));

const descriptor = (): EnabledPluginModule => ({
  code: 'example', version: '1.0.0', components: { Index: 'Index.vue' },
  routes: [{ path: '/plugins/example/items', name: 'Plugin_example_Items', component: 'Index', meta: { title: '示例', permission: 'example:item:list' } }]
});
const router = () => createRouter({ history: createMemoryHistory(), routes: [] });
const modules = { '../plugins/example/Index.vue': defineComponent({ template: '<div />' }) };
let activeRouter = router();

afterEach(() => { clearPluginModules(activeRouter); vi.unstubAllEnvs(); });

describe('Webman plugin module routing', () => {
  it.each([
    ['/', '/#/admin/plugins/example/items'],
    ['/console/', '/console/#/admin/plugins/example/items']
  ])('uses deployment base %s only in the browser href', async (base, href) => {
    const previousUrl = window.location.href;
    const previousState = window.history.state;
    window.history.replaceState({}, '', base);
    const history = createWebHashHistory(base);
    activeRouter = createRouter({ history, routes: [] });
    try {
      await syncPluginModules(activeRouter, [descriptor()], { modules });
      const resolved = activeRouter.resolve('/admin/plugins/example/items');
      const browserUrl = new URL(resolved.href, window.location.href);
      expect(browserUrl.pathname + browserUrl.hash).toBe(href);
      expect(resolved.path).toBe('/admin/plugins/example/items');
      expect(resolved.meta.activeMenu).toBe('/admin/plugins/example/items');
    } finally {
      history.destroy();
      window.history.replaceState(previousState, '', previousUrl);
    }
  });

  it('loads enabled plugin modules in Webman mode', async () => {
    vi.stubEnv('VITE_APP_BACKEND', 'webman');
    activeRouter = router();
    await syncPluginModules(activeRouter, [descriptor()], { modules });
    expect(activeRouter.resolve('/admin/plugins/example/items').name).toBe('Plugin_example_Items');
  });

  it('replaces the menu placeholder and removes all routes when disabled', async () => {
    activeRouter = router();
    activeRouter.addRoute({ path: '/admin/plugins/example/items', name: 'Menu_99', component: Layout });
    await syncPluginModules(activeRouter, [descriptor()], { modules });
    expect(activeRouter.hasRoute('Menu_99')).toBe(false);
    expect(activeRouter.resolve('/admin/plugins/example/items').matched.map(route => route.name)).toEqual(['Plugin_example_Items_Layout', 'Plugin_example_Items']);
    expect(activeRouter.resolve('/admin/plugins/example/items').meta.activeMenu).toBe('/admin/plugins/example/items');
    await syncPluginModules(activeRouter, [descriptor()], { modules });
    expect(activeRouter.getRoutes()).toHaveLength(2);
    await syncPluginModules(activeRouter, [], { modules });
    expect(activeRouter.getRoutes()).toHaveLength(0);
  });

  it('a plugin menu without an enabled descriptor never mounts the admin layout twice', async () => {
    activeRouter = router();
    generateRoutes([{
      id: 70, parentId: 0, path: '/plugins/document', routeName: 'DocumentMenu',
      name: '技术文档', type: 'M', component: 'Layout', children: [{
        id: 71, parentId: 70, path: '/plugins/document/docs', routeName: 'DocumentMenuPage',
        name: '文档中心', type: 'C', component: 'Layout'
      }]
    }] as API.MenuItem[]).forEach(route => activeRouter.addRoute(route));
    const result = activeRouter.resolve('/admin/plugins/document/docs');
    expect(result.matched.filter(record => record.components?.default === Layout)).toHaveLength(1);
  });

  it('the enabled document route replaces the nested menu placeholder', async () => {
    activeRouter = router();
    generateRoutes([{
      id: 70, parentId: 0, path: '/plugins/document', routeName: 'DocumentMenu',
      name: '技术文档', type: 'M', component: 'Layout', children: [{
        id: 71, parentId: 70, path: '/plugins/document/docs', routeName: 'DocumentMenuPage',
        name: '文档中心', type: 'C', component: 'Layout'
      }]
    }] as API.MenuItem[]).forEach(route => activeRouter.addRoute(route));
    const document = { code: 'document', version: '1.0.0', components: { Index: 'Index.vue' }, routes: [{
      path: '/plugins/document/docs', name: 'Plugin_document_Docs', component: 'Index', meta: { title: '文档中心' }
    }] };
    const page = defineComponent({ template: '<article>文档中心</article>' });
    const synced = await syncPluginModules(activeRouter, [document], { modules: { '../plugins/document/Index.vue': page } });
    expect(synced.errors).toEqual([]);
    const result = activeRouter.resolve('/admin/plugins/document/docs');
    expect(result.name).toBe('Plugin_document_Docs');
    expect(result.matched.filter(record => record.components?.default === Layout)).toHaveLength(1);
    expect(result.matched.at(-1)?.components?.default).toBe(page);
  });

  it('resolves real plugin pages from the build-time src/admin/plugins glob', async () => {
    activeRouter = router();
    const qa: EnabledPluginModule = { code: 'qa', version: '1.0.0', components: { Moderation: 'pages/Moderation.vue' }, routes: [{
      path: '/plugins/qa/moderation', name: 'Plugin_qa_Moderation', component: 'Moderation', meta: { title: '问答审核' }
    }] };
    const synced = await syncPluginModules(activeRouter, [descriptor(), qa]);
    expect(synced).toEqual({ loaded: ['example', 'qa'], errors: [] });
    for (const path of ['/admin/plugins/example/items', '/admin/plugins/qa/moderation']) {
      expect(typeof activeRouter.resolve(path).matched.at(-1)?.components?.default).toBe('function');
    }
  });

  it('mounts a diagnostic route for missing build assets and clears it on logout', async () => {
    activeRouter = router();
    const result = await syncPluginModules(activeRouter, [descriptor()], { modules: {} });
    expect(result.errors[0]?.stage).toBe('component');
    expect(activeRouter.resolve('/admin/plugins/example/error').name).toBe('Plugin_example_Error');
    clearPluginModules(activeRouter);
    expect(activeRouter.getRoutes()).toHaveLength(0);
  });

  it('does not load the removed local market operations module from build assets', async () => {
    activeRouter = router();
    const result = await syncPluginModules(activeRouter, [{
      code: 'market', version: '1.0.0', components: { Index: 'Index.vue' },
      routes: [{ path: '/plugins/market/index', name: 'Plugin_market_Index', component: 'Index', meta: { title: '市场运营' } }]
    }]);
    expect(result.loaded).toEqual([]);
    expect(result.errors).toEqual([expect.objectContaining({ code: 'market', stage: 'component' })]);
    expect(activeRouter.hasRoute('Plugin_market_Index')).toBe(false);
    expect(activeRouter.getRoutes().map(route => route.name)).toEqual(['Plugin_market_Error']);
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
