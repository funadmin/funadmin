import { describe, expect, it } from 'vitest';
import { columnTypeOf, fieldNodes, isRequiredNode, isSensitiveNode, relationTypeOf } from './fieldNodes';
import type { FormSchemaNode } from './types';

const field = (name: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id: `n_${name}`, kind: 'field', type: 'input', field: name, title: name, children: [], ...extra
});
const layout = (id: string, type: string, children: FormSchemaNode[]): FormSchemaNode => ({
  id, kind: 'layout', type, field: null, title: id, children
});

describe('fieldNodes', () => {
  it('按文档顺序展开布局节点，repeatable/subform 只占一项且不展开子节点', () => {
    const nodes = [
      layout('g', 'group', [field('a')]),
      field('b'),
      field('r', { type: 'repeatable', children: [field('c')] })
    ];
    expect(fieldNodes(nodes).map((node) => node.field)).toEqual(['a', 'b', 'r']);
  });

  it('嵌套布局与子表单：深层字段保留，subform 子节点不出现在顶层', () => {
    const nodes = [
      layout('tabs', 'tabs', [layout('basic', 'group', [field('a'), layout('grid', 'grid', [field('b')])])]),
      field('s', { type: 'subform', children: [field('x'), layout('inner', 'group', [field('y')])] }),
      layout('text', 'text', [])
    ];
    expect(fieldNodes(nodes).map((node) => node.field)).toEqual(['a', 'b', 's']);
  });

  it('返回原节点引用', () => {
    const b = field('b');
    expect(fieldNodes([layout('g', 'group', [b])])[0]).toBe(b);
  });
});

describe('isSensitiveNode', () => {
  it('password、props.writeOnly / props.sensitive 为 true|1|"1" 时为敏感字段', () => {
    expect(isSensitiveNode(field('p', { type: 'password' }))).toBe(true);
    expect(isSensitiveNode(field('w', { props: { writeOnly: 1 } }))).toBe(true);
    expect(isSensitiveNode(field('s', { props: { sensitive: '1' } }))).toBe(true);
    expect(isSensitiveNode(field('t', { props: { sensitive: true } }))).toBe(true);
  });

  it('其余情况不是敏感字段', () => {
    expect(isSensitiveNode(field('a'))).toBe(false);
    expect(isSensitiveNode(field('b', { props: { sensitive: false, writeOnly: 0 } }))).toBe(false);
    expect(isSensitiveNode(field('c', { props: { sensitive: 'true', writeOnly: '0' } }))).toBe(false);
    expect(isSensitiveNode(field('d', { props: { sensitive: 2 } }))).toBe(false);
  });
});

describe('节点谓词', () => {
  it('isRequiredNode 按 validation 中是否存在 required 规则判断', () => {
    expect(isRequiredNode(field('a', { validation: [{ type: 'max', value: 3 }, { type: 'required', value: true }] }))).toBe(true);
    expect(isRequiredNode(field('b', { validation: [{ type: 'max', value: 3 }] }))).toBe(false);
    expect(isRequiredNode(field('c'))).toBe(false);
  });

  it('relationTypeOf 缺省为 none', () => {
    expect(relationTypeOf(field('a'))).toBe('none');
    expect(relationTypeOf(field('b', { database: { columnType: 'int' } }))).toBe('none');
    expect(relationTypeOf(field('c', { database: { relation: { type: 'belongs_to', table: 'user' } } }))).toBe('belongs_to');
    expect(relationTypeOf(field('d', { type: 'subform', database: { relation: { type: 'has_many' } } }))).toBe('has_many');
  });

  it('columnTypeOf 缺省为空串', () => {
    expect(columnTypeOf(field('a'))).toBe('');
    expect(columnTypeOf(field('b', { database: { columnType: 'varchar(100)' } }))).toBe('varchar(100)');
  });
});
