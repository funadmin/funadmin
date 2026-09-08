import { describe, expect, it } from 'vitest';
import {
  componentRegistry,
  createFormComponentRegistry,
  sanitizeComponentBindings
} from './componentRegistry';

describe('FormSchema 组件注册表', () => {
  it('每个核心组件声明完整能力契约和独立 props 白名单', () => {
    const input = componentRegistry.resolve('input');
    const number = componentRegistry.resolve('number');
    const richtext = componentRegistry.resolve('richtext');

    for (const definition of [input, number, richtext]) {
      expect(definition).toMatchObject({
        defaultProps: expect.any(Object),
        propertySchema: expect.any(Object),
        codec: expect.any(Object),
        allowedAttrs: expect.any(Array),
        allowedEvents: expect.any(Array),
        renderer: expect.any(Function)
      });
      expect(definition).toHaveProperty('defaultValue');
    }
    expect(input?.allowedProps).not.toBe(number?.allowedProps);
    expect(input?.allowedProps).toContain('maxlength');
    expect(number?.allowedProps).toContain('min');
    expect(number?.allowedProps).not.toContain('maxlength');
  });

  it('注册时拒绝命名空间不一致的插件组件', () => {
    const registry = createFormComponentRegistry();
    expect(() => registry.register({
      type: 'other:rating',
      namespace: 'demo',
      kind: 'field',
      componentKey: 'Rating',
      valueType: 'number',
      defaultValue: 0,
      defaultProps: {},
      propertySchema: { type: 'object', properties: {} },
      codec: { encode: (value) => value, decode: (value) => value },
      allowedProps: [],
      allowedAttrs: [],
      allowedEvents: [],
      renderer: async () => ({})
    })).toThrow('插件组件必须使用插件命名空间');
  });

  it('在 v-bind 前按 props 和 attrs 白名单净化绑定', () => {
    const definition = componentRegistry.resolve('input');
    expect(definition).toBeDefined();
    expect(sanitizeComponentBindings(definition!, {
      props: { placeholder: '请输入', maxlength: 20, innerHTML: '<img>' },
      attrs: { autocomplete: 'off', onclick: 'attack()' }
    })).toEqual({
      placeholder: '请输入',
      maxlength: 20,
      autocomplete: 'off'
    });
  });
});
