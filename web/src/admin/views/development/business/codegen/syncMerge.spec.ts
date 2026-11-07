import { describe, expect, it } from 'vitest';
import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import { mergeDatabaseSync, type DatabaseSyncDiff } from './syncMerge';

const field = (id: string, type: string, name: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id, kind: 'field', type, field: name, title: name, children: [], validation: [], dataSource: null,
  database: { columnType: 'varchar(100)', nullable: false, comment: '旧注释', unsigned: false, index: 'none' },
  list: { show: true, sort: false, filter: '', width: 0, formatter: '' },
  ...extra
});

const makeDoc = (): FormSchemaDocument => ({
  schemaVersion: 2,
  key: 'goods',
  title: '商品',
  nodes: [
    field('n_title', 'textarea', 'title', {
      title: '商品标题（用户改过）',
      list: { show: false, sort: true, filter: 'like', width: 220, formatter: '' },
      dataSource: { kind: 'static', options: [{ label: 'A', value: 1 }] }
    }),
    field('n_old', 'input', 'old_col'),
    { id: 'n_group', kind: 'layout', type: 'group', field: null, title: '价格', children: [field('n_price', 'number', 'price')] },
    field('n_items', 'subform', 'items', { children: [field('n_sku', 'input', 'sku')] })
  ]
});

const diff: DatabaseSyncDiff = {
  inspectionHash: 'abc',
  added: [field('n_remark', 'input', 'remark'), field('n_extra', 'input', 'extra')],
  removed: ['old_col', 'price'],
  changed: [
    { field: 'title', from: { columnType: 'varchar(100)', comment: '旧注释' }, to: { columnType: 'varchar(255)', comment: '新注释' } },
    { field: 'price', from: { nullable: false }, to: { nullable: true } }
  ]
};

describe('mergeDatabaseSync', () => {
  it('只合并勾选项，输入不变', () => {
    const doc = makeDoc();
    const snapshot = structuredClone(doc);
    const diffSnapshot = structuredClone(diff);
    const next = mergeDatabaseSync(doc, diff, { added: ['remark'], removed: ['old_col'], changed: ['title'] });
    expect(doc).toStrictEqual(snapshot);
    expect(diff).toStrictEqual(diffSnapshot);
    expect(next.nodes.map((node) => node.id)).toEqual(['n_title', 'n_group', 'n_items', 'n_remark']);
    expect(next.nodes[1]).toStrictEqual(snapshot.nodes[2]);
    expect(next.nodes[2]).toStrictEqual(snapshot.nodes[3]);
  });

  it('changed 只改 database 物理属性，保留用户改过的 title/type/list/dataSource', () => {
    const doc = makeDoc();
    const next = mergeDatabaseSync(doc, diff, { added: [], removed: [], changed: ['title'] });
    expect(next.nodes[0]).toStrictEqual({
      ...doc.nodes[0],
      database: { columnType: 'varchar(255)', nullable: false, comment: '新注释', unsigned: false, index: 'none' }
    });
  });

  it('嵌套字段同样可删除或更新物理属性', () => {
    const updated = mergeDatabaseSync(makeDoc(), diff, { added: [], removed: [], changed: ['price'] });
    expect(updated.nodes[2].children[0].database).toMatchObject({ nullable: true, columnType: 'varchar(100)' });
    const removed = mergeDatabaseSync(makeDoc(), diff, { added: [], removed: ['price'], changed: [] });
    expect(removed.nodes[2].children).toEqual([]);
  });

  it('added 追加到根节点末尾且按差异顺序、为独立副本', () => {
    const next = mergeDatabaseSync(makeDoc(), diff, { added: ['extra', 'remark'], removed: [], changed: [] });
    expect(next.nodes.slice(-2).map((node) => node.field)).toEqual(['remark', 'extra']);
    expect(next.nodes.at(-1)).toStrictEqual(diff.added[1]);
    expect(next.nodes.at(-1)).not.toBe(diff.added[1]);
  });

  it('不在差异中的勾选项被忽略，未勾选时文档不变', () => {
    const doc = makeDoc();
    expect(mergeDatabaseSync(doc, diff, { added: ['nope'], removed: ['title'], changed: ['sku'] })).toStrictEqual(doc);
  });
});
