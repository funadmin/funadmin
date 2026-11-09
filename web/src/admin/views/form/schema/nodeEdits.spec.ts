import { describe, expect, it } from 'vitest';
import { controlMeta } from '@/views/form/registry';
import { changeControl, patchDatabase, patchLayout, patchList, patchRelation, withRequired } from './nodeEdits';
import type { FormSchemaNode } from './types';

const makeNode = (extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id: 'n_title',
  kind: 'field',
  type: 'input',
  field: 'title',
  title: '标题',
  children: [],
  props: { clearable: true, maxlength: 20 },
  attrs: { 'aria-label': '标题' },
  events: { change: [{ action: 'setValue', target: 'price', value: 1 }] },
  access: { write: ['goods.edit'] },
  valueType: 'string',
  info: '提示',
  validation: [{ type: 'max', value: 10, message: '太长' }],
  dataSource: null,
  layout: { span: 24, group: 'base' },
  database: { columnType: 'varchar(255)', nullable: true, comment: '标题', relation: { type: 'none' } },
  list: { show: true, sort: false, filter: '', width: 0, formatter: '' },
  ...extra
});

describe('patchLayout', () => {
  it('写入 layout.span，不触碰 props', () => {
    const node = makeNode();
    const snapshot = structuredClone(node);
    const next = patchLayout(node, { span: 12 });
    expect(next.layout).toEqual({ span: 12, group: 'base' });
    expect(next.props).toBe(node.props);
    expect(next.props).not.toHaveProperty('form_span');
    expect(node).toStrictEqual(snapshot);
  });

  it('写入 layout.group；无 layout 的节点也能写入', () => {
    expect(patchLayout(makeNode(), { group: 'more' }).layout).toEqual({ span: 24, group: 'more' });
    const bare = makeNode();
    delete bare.layout;
    expect(patchLayout(bare, { span: 8 }).layout).toEqual({ span: 8 });
  });

  it('值未变化时返回原引用', () => {
    const node = makeNode();
    expect(patchLayout(node, { span: 24 })).toBe(node);
    expect(patchLayout(node, { span: 24, group: 'base' })).toBe(node);
    expect(patchLayout(node, {})).toBe(node);
  });
});

describe('patchList / patchDatabase / patchRelation', () => {
  it('patchList 合并 list 切片', () => {
    const node = makeNode();
    const next = patchList(node, { show: false, width: 120 });
    expect(next.list).toEqual({ show: false, sort: false, filter: '', width: 120, formatter: '' });
    expect(next.database).toBe(node.database);
    expect(patchList(node, { show: true, width: 0 })).toBe(node);
  });

  it('patchDatabase 合并 database 切片并保留 relation', () => {
    const node = makeNode();
    const next = patchDatabase(node, { columnType: 'varchar(100)', nullable: false });
    expect(next.database).toEqual({ columnType: 'varchar(100)', nullable: false, comment: '标题', relation: { type: 'none' } });
    expect(next.database?.relation).toBe(node.database?.relation);
    expect(patchDatabase(node, { columnType: 'varchar(255)' })).toBe(node);
  });

  it('patchRelation 合并进 database.relation，保留 columnType', () => {
    const node = makeNode({ database: { columnType: 'bigint', relation: { type: 'belongs_to', table: 'user', onDelete: 'cascade' } } });
    const next = patchRelation(node, { table: 'member', labelField: 'nickname' });
    expect(next.database).toEqual({
      columnType: 'bigint',
      relation: { type: 'belongs_to', table: 'member', onDelete: 'cascade', labelField: 'nickname' }
    });
    expect(patchRelation(node, { table: 'user' })).toBe(node);
  });

  it('patchRelation 在无 database 的节点上建立 relation', () => {
    const node = makeNode();
    delete node.database;
    expect(patchRelation(node, { type: 'belongs_to', table: 'user' }).database).toEqual({ relation: { type: 'belongs_to', table: 'user' } });
  });
});

describe('withRequired', () => {
  it('true 时把 required 规则放在最前', () => {
    const node = makeNode();
    const next = withRequired(node, true);
    expect(next.validation).toEqual([{ type: 'required', value: true }, { type: 'max', value: 10, message: '太长' }]);
    expect(node.validation).toHaveLength(1);
  });

  it('false 时移除 required 规则', () => {
    const node = makeNode({ validation: [{ type: 'required', value: true, message: '必填' }, { type: 'max', value: 10 }] });
    expect(withRequired(node, false).validation).toEqual([{ type: 'max', value: 10 }]);
  });

  it('未变化时返回原引用', () => {
    const required = makeNode({ validation: [{ type: 'max', value: 10 }, { type: 'required', value: true }] });
    expect(withRequired(required, true)).toBe(required);
    const optional = makeNode();
    expect(withRequired(optional, false)).toBe(optional);
    const bare = makeNode();
    delete bare.validation;
    expect(withRequired(bare, false)).toBe(bare);
  });
});

describe('changeControl', () => {
  it('resetColumnType 为 true 时列类型改为控件默认列类型', () => {
    const next = changeControl(makeNode(), 'select', { resetColumnType: true });
    expect(next.type).toBe('select');
    expect(next.database?.columnType).toBe('varchar(100)');
    expect(next.database?.comment).toBe('标题');
  });

  it('resetColumnType 为 false 时保留原列类型', () => {
    const node = makeNode();
    const next = changeControl(node, 'select', { resetColumnType: false });
    expect(next.type).toBe('select');
    expect(next.database).toBe(node.database);
  });

  it('两种模式都把 props / dataSource 重置为注册表默认值，其余节点数据保留', () => {
    const meta = controlMeta('select');
    for (const resetColumnType of [true, false]) {
      const node = makeNode();
      const next = changeControl(node, 'select', { resetColumnType });
      expect(next.props).toEqual(meta.defaultProps);
      expect(next.dataSource).toEqual(meta.defaultOptions);
      expect(next.props).not.toBe(meta.defaultProps);
      expect(next.dataSource).not.toBe(meta.defaultOptions);
      for (const key of ['id', 'field', 'title', 'events', 'access', 'attrs', 'info', 'validation', 'layout', 'list'] as const) {
        expect(next[key]).toBe(node[key]);
      }
    }
    expect(changeControl(makeNode({ type: 'select' }), 'input', { resetColumnType: false }).dataSource).toBeNull();
  });

  it('valueType 取自控件元信息，未声明则移除', () => {
    expect(changeControl(makeNode(), 'select', { resetColumnType: false })).not.toHaveProperty('valueType');
  });

  it('未知控件或容器控件抛错', () => {
    expect(() => changeControl(makeNode(), 'no-such-control', { resetColumnType: true })).toThrow();
    expect(() => changeControl(makeNode(), 'group', { resetColumnType: true })).toThrow();
    expect(() => changeControl(makeNode(), 'subform', { resetColumnType: true })).toThrow();
  });

  it('切换结果与原节点一致时返回原引用', () => {
    const node = makeNode({ type: 'select', props: { clearable: true }, dataSource: structuredClone(controlMeta('select').defaultOptions) });
    delete node.valueType;
    expect(changeControl(node, 'select', { resetColumnType: false })).toBe(node);
    const columnNode = patchDatabase(node, { columnType: 'varchar(100)' });
    expect(changeControl(columnNode, 'select', { resetColumnType: true })).toBe(columnNode);
  });
});
