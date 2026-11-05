import { describe, expect, it } from 'vitest';
import type { FormSchemaDocument } from './types';
import { assertFieldComponents, assertRuntimeComponents, findUnknownComponents } from './runtimeGuard';

const schema = (type: string): FormSchemaDocument => ({
  schemaVersion: 2,
  key: 'demo',
  title: '演示',
  nodes: [{ id: 'field', kind: 'field', type, field: 'field', title: '字段', children: [] }]
});

describe('生产表单未知组件阻断', () => {
  it('设计态可定位未知组件并提供节点错误', () => {
    expect(findUnknownComponents(schema('missing-control'))).toEqual([
      { id: 'field', type: 'missing-control', path: '/nodes/0/type' }
    ]);
  });

  it('生产态 validate 和 submit 前抛出明确阻断错误', () => {
    expect(() => assertRuntimeComponents(schema('missing-control')))
      .toThrow('未注册的表单组件：missing-control');
    expect(() => assertRuntimeComponents(schema('input'))).not.toThrow();
  });

  it('旧版生产表单同样拒绝未知字段组件', () => {
    expect(() => assertFieldComponents([{ type: 'missing-control' }]))
      .toThrow('未注册的表单组件：missing-control');
    expect(() => assertFieldComponents([{ type: 'input' }])).not.toThrow();
  });
});
