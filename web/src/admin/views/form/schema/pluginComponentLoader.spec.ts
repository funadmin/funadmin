import { describe, expect, it, vi } from 'vitest';
import { createFormComponentRegistry } from './componentRegistry';
import { registerPluginFormComponents } from './pluginComponentLoader';
import type { PluginCatalogRegistration } from '../designer/pluginCatalog';

const catalog = {
  schemaVersion: 2 as const,
  components: [{
    type: 'demo:rating',
    namespace: 'demo',
    component: 'Rating',
    valueType: 'number',
    defaultValue: 0,
    defaultProps: { max: 5 },
    propertySchema: { type: 'object', properties: { max: { type: 'integer' } } },
    codec: 'demo:rating',
    allowedAttrs: ['aria-label'],
    allowedEvents: ['change']
  }]
};

describe('受信插件表单组件加载', () => {
  it('仅从已安装插件构建期 glob 白名单注册异步 renderer', async () => {
    const registry = createFormComponentRegistry();
    const loader = vi.fn(async () => ({ default: { name: 'Rating' } }));

    const result = registerPluginFormComponents(catalog, registry, {
      '../../../plugins/demo/Rating.vue': loader
    });

    expect(result).toEqual<PluginCatalogRegistration>({ registered: ['demo:rating'], diagnostics: [] });
    const definition = registry.resolve('demo:rating');
    expect(definition?.namespace).toBe('demo');
    expect(definition?.allowedProps).toEqual(['max']);
    expect(await definition?.renderer()).toMatchObject({ name: 'Rating' });
    expect(loader).toHaveBeenCalledOnce();
  });

  it('默认从 src/admin/plugins 构建期 glob 解析插件组件', () => {
    const result = registerPluginFormComponents({
      ...catalog,
      components: [{ ...catalog.components[0], type: 'document:index', namespace: 'document', component: 'Index' }]
    }, createFormComponentRegistry());
    expect(result).toEqual<PluginCatalogRegistration>({ registered: ['document:index'], diagnostics: [] });
  });

  it('把 catalog 命名空间越界和未进入 glob 白名单记录为诊断', () => {
    const namespaceResult = registerPluginFormComponents({
      ...catalog,
      components: [{ ...catalog.components[0], namespace: 'other' }]
    }, createFormComponentRegistry(), {
      '../../../plugins/demo/Rating.vue': async () => ({ default: {} })
    });
    expect(namespaceResult.registered).toEqual([]);
    expect(namespaceResult.diagnostics[0]).toMatchObject({ code: 'namespace-mismatch', type: 'demo:rating' });

    const missingResult = registerPluginFormComponents(catalog, createFormComponentRegistry(), {});
    expect(missingResult.registered).toEqual([]);
    expect(missingResult.diagnostics[0]).toMatchObject({ code: 'missing-plugin', type: 'demo:rating' });
  });
});
