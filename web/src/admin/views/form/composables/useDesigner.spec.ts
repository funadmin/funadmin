import { describe, expect, it } from 'vitest';
import type { FormDefinition } from '@/api/form';
import type { FormSchemaNode } from '../schema/types';
import { changeControl, patchLayout, patchList } from '../schema/nodeEdits';
import { useDesigner } from './useDesigner';

const field = (extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id: 'n_a',
  kind: 'field',
  type: 'input',
  field: 'a',
  title: 'A',
  children: [],
  props: { clearable: true },
  list: { show: true, sort: false, filter: '', formatter: '', width: 0 },
  layout: { span: 24, group: '' },
  ...extra
});

const group = (children: FormSchemaNode[] = []): FormSchemaNode => ({
  id: 'n_group', kind: 'layout', type: 'group', field: null, title: '分组', children, props: { title: '分组' }
});

const definition = (nodes: FormSchemaNode[]): FormDefinition => ({
  id: 1, form_key: 'demo', name: '示例', table_name: 'fun_demo', connection: 'mysql', source_type: 'created', status: 1, remark: '', sort_order: 0,
  schema_hash: 'h1',
  schema_document: { schemaVersion: 2, key: 'demo', title: '示例', nodes }
} as FormDefinition);

const loaded = (nodes: FormSchemaNode[]) => {
  const store = useDesigner();
  store.load(definition(nodes));
  return store;
};

describe('useDesigner 直接编辑 FormSchema 节点', () => {
  it('update keeps events, access, attrs, info and valueType', () => {
    const store = loaded([field({
      events: { change: [{ type: 'x' }] },
      access: { write: ['a'] },
      attrs: { id: 'k' },
      info: 'i',
      valueType: 'string'
    })]);
    store.selectNode('n_a');
    const node = store.selectedNode.value!;
    store.updateNode(patchList(node, { show: false }));
    const after = store.findNode('n_a')!;
    expect(after.list?.show).toBe(false);
    expect(after.events).toEqual({ change: [{ type: 'x' }] });
    expect(after.access).toEqual({ write: ['a'] });
    expect(after.attrs).toEqual({ id: 'k' });
    expect(after.info).toBe('i');
    expect(after.valueType).toBe('string');
  });

  it('layout span edit writes layout.span', () => {
    const store = loaded([group([field()])]);
    store.selectNode('n_group');
    const node = store.selectedNode.value!;
    store.updateNode(patchLayout(node, { span: 12 }));
    const after = store.findNode('n_group')!;
    expect(after.layout?.span).toBe(12);
    expect(after.props?.form_span).toBeUndefined();
    expect(after.children.map((child) => child.id)).toEqual(['n_a']);
  });

  it('field group edit writes layout.group', () => {
    const store = loaded([field()]);
    store.selectNode('n_a');
    store.updateNode(patchLayout(store.selectedNode.value!, { group: '基础' }));
    const after = store.findNode('n_a')!;
    expect(after.layout).toEqual({ span: 24, group: '基础' });
    expect(after.props?.form_group).toBeUndefined();
  });

  it('a patch value of undefined removes the key (changeControl drops valueType)', () => {
    const store = loaded([field({ valueType: 'string' })]);
    store.selectNode('n_a');
    const node = store.selectedNode.value!;
    const next = changeControl(node, 'textarea', { resetColumnType: true });
    store.updateNode({ type: next.type, props: next.props, dataSource: next.dataSource, database: next.database, valueType: undefined });
    const after = store.findNode('n_a')!;
    expect(after.type).toBe('textarea');
    expect(after).not.toHaveProperty('valueType');
    expect(after.database?.columnType).toBe('text');
  });

  it('updateNode never replaces id or children', () => {
    const store = loaded([group([field()])]);
    store.selectNode('n_group');
    const children = store.findNode('n_group')!.children;
    store.updateNode({ ...store.selectedNode.value!, id: 'other', children: [], title: '新分组' });
    const after = store.findNode('n_group')!;
    expect(after.title).toBe('新分组');
    expect(after.children).toBe(children);
    expect(after.children).toHaveLength(1);
  });

  it('load rejects non-v2', () => {
    const store = useDesigner();
    const form = definition([field()]);
    expect(() => store.load({ ...form, schema_document: null })).toThrow();
    expect(() => store.load({ ...form, schema_document: { ...form.schema_document!, schemaVersion: 1 as 2 } })).toThrow();
  });

  it('duplicate gives unique field names', () => {
    const store = loaded([field()]);
    const first = store.duplicateNode('n_a')!;
    const second = store.duplicateNode('n_a')!;
    expect(first.field).toBe('a_copy');
    expect(second.field).toBe('a_copy_2');
    expect(first.title).toBe('A(副本)');
    expect(store.nodes.value.map((node) => node.field)).toEqual(['a', 'a_copy_2', 'a_copy']);
    expect(new Set(store.flattenedNodes.value.map(({ node }) => node.id)).size).toBe(3);
  });

  it('duplicating a layout renames every nested field', () => {
    const store = loaded([group([field()])]);
    const copy = store.duplicateNode('n_group')!;
    expect(copy.field).toBeNull();
    expect(copy.title).toBe('分组');
    expect(copy.children[0].field).toBe('a_copy');
    expect(store.fieldNodes.value.map((node) => node.field)).toEqual(['a', 'a_copy']);
  });

  it('addNode gives new fields a unique field_<n> across nested field nodes', () => {
    const store = loaded([group([field({ field: 'field_2' })])]);
    const added = store.addNode('input');
    expect(added.field).toBe('field_3');
    expect(added).toMatchObject({ kind: 'field', type: 'input', title: '单行输入', props: { clearable: true }, events: {} });
    const layout = store.addNode('group');
    expect(layout).toMatchObject({ kind: 'layout', field: null });
    expect(store.addNode('input').field).toBe('field_4');
  });

  it('addNode keeps field names unique against repeatable/subform children', () => {
    const items = field({ id: 'n_items', type: 'repeatable', field: 'items', children: [] });
    const store = loaded([items]);
    const first = store.addNode('input', 'n_items');
    const second = store.addNode('input', 'n_items');
    const top = store.addNode('input');
    const names = [first.field, second.field, top.field, 'items'];
    expect(new Set(names).size).toBe(4);
    expect(store.findNode('n_items')!.children.map((child) => child.field)).toEqual([first.field, second.field]);
  });

  it('duplicating a repeatable/subform child twice gives unique names', () => {
    const items = field({ id: 'n_items', type: 'subform', field: 'items', children: [field()] });
    const store = loaded([items]);
    const first = store.duplicateNode('n_a')!;
    const second = store.duplicateNode('n_a')!;
    expect(first.field).toBe('a_copy');
    expect(second.field).toBe('a_copy_2');
    expect(store.findNode('n_items')!.children.map((child) => child.field)).toEqual(['a', 'a_copy_2', 'a_copy']);
  });

  it('duplicating a container renames its children too', () => {
    const items = field({ id: 'n_items', type: 'repeatable', field: 'items', children: [field()] });
    const store = loaded([items]);
    const copy = store.duplicateNode('n_items')!;
    expect(copy.field).toBe('items_copy');
    expect(copy.children[0].field).toBe('a_copy');
    const all = store.flattenedNodes.value.map(({ node }) => node.field);
    expect(new Set(all).size).toBe(all.length);
  });

  it('undo restores nodes after rename', () => {
    const store = loaded([field()]);
    store.selectNode('n_a');
    store.updateNode({ field: 'b', title: 'B' });
    expect(store.findNode('n_a')).toMatchObject({ field: 'b', title: 'B' });
    store.undo();
    expect(store.findNode('n_a')).toMatchObject({ field: 'a', title: 'A' });
    store.redo();
    expect(store.findNode('n_a')).toMatchObject({ field: 'b', title: 'B' });
  });

  it('snapshots hold only nodes and form; unchanged patches add no history', () => {
    const store = loaded([field()]);
    store.selectNode('n_a');
    store.updateNode({ title: 'A' });
    expect(store.canUndo.value).toBe(false);
    expect(store.dirty.value).toBe(false);
    expect(store).not.toHaveProperty('fields');
    expect(store).not.toHaveProperty('selected');
    expect(store).not.toHaveProperty('updateField');
  });

  it('markSaved reconciles server nodes in place', () => {
    const store = loaded([field()]);
    store.selectNode('n_a');
    store.updateNode({ title: 'B' });
    const node = store.findNode('n_a');
    store.markSaved(definition([field({ title: 'B', list: { show: true, sort: false, filter: '', formatter: '', width: 0 } })]));
    expect(store.findNode('n_a')).toBe(node);
    expect(store.dirty.value).toBe(false);
    expect(store.selectedNode.value?.id).toBe('n_a');
  });
});
