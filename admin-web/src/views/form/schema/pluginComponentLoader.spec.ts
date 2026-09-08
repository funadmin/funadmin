import { describe, expect, it, vi } from 'vitest';
import { createFormComponentRegistry } from './componentRegistry';
import { registerPluginFormComponents } from './pluginComponentLoader';

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

    registerPluginFormComponents(catalog, registry, {
      '../../../modules/demo/Rating.vue': loader
    });

    const definition = registry.resolve('demo:rating');
    expect(definition?.namespace).toBe('demo');
    expect(definition?.allowedProps).toEqual(['max']);
    expect(await definition?.renderer()).toMatchObject({ name: 'Rating' });
    expect(loader).toHaveBeenCalledOnce();
  });

  it('拒绝 catalog 命名空间越界和未进入 glob 白名单的组件', () => {
    expect(() => registerPluginFormComponents({
      ...catalog,
      components: [{ ...catalog.components[0], namespace: 'other' }]
    }, createFormComponentRegistry(), {
      '../../../modules/demo/Rating.vue': async () => ({ default: {} })
    })).toThrow('命名空间');

    expect(() => registerPluginFormComponents(catalog, createFormComponentRegistry(), {}))
      .toThrow('未包含在当前构建');
  });
});
