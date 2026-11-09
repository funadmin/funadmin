import { describe, expect, it } from 'vitest';
import type { FormSchemaDatabase, FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import { useDesigner } from '@/views/form/composables/useDesigner';
import { changeControl, patchRelation } from '@/views/form/schema/nodeEdits';
import { applyRelationRows, schemaToRelationRows, type RelationRow } from './relationTable';

const field = (id: string, type: string, name: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id, kind: 'field', type, field: name, title: name, children: [], validation: [], dataSource: null, events: {}, conditions: [],
  database: { columnType: 'bigint', nullable: true, comment: '' },
  list: { show: true, sort: false, filter: '', width: 0, formatter: '' },
  ...extra
});

const belongsTo = (table: string, labelField: string): FormSchemaDatabase => ({
  columnType: 'bigint', nullable: true, comment: '',
  relation: { type: 'belongs_to', table, labelField, valueField: 'id', multiple: false, onDelete: 'restrict' }
});

const makeDoc = (): FormSchemaDocument => ({
  schemaVersion: 2,
  key: 'goods',
  title: '商品',
  nodes: [
    field('n_admin', 'relation', 'admin_id', {
      database: belongsTo('fun_admin', 'username'),
      dataSource: { kind: 'relation', options: [] },
      props: { clearable: true, filterable: true }
    }),
    field('n_name', 'input', 'name', { database: { columnType: 'varchar(100)', nullable: true, comment: '' }, props: { clearable: true } }),
    { id: 'n_group', kind: 'layout', type: 'group', field: null, title: '分类', children: [
      field('n_category', 'relation', 'category_id', {
        database: belongsTo('goods_category', 'title'),
        dataSource: { kind: 'dictionary', dictionary: 'goods_category' }
      })
    ] },
    field('n_brand', 'select', 'brand_id', {
      database: belongsTo('brand', 'name'),
      dataSource: { kind: 'static', options: [{ label: 'A', value: 1 }] }
    }),
    field('n_items', 'subform', 'items', {
      database: { relation: { type: 'has_many', table: 'goods_sku', valueField: 'goods_id' } },
      children: [field('n_sku_admin', 'relation', 'sku_admin_id', { database: belongsTo('fun_admin', 'username') })]
    })
  ],
  relations: [{ type: 'hasOne', name: 'profile', table: 'goods_profile', localField: 'id', foreignField: 'goods_id' }]
});

const modelRow = (row: Partial<RelationRow>): RelationRow => ({
  origin: 'model', type: 'hasOne', name: '', table: '', localField: 'id', foreignField: '', editable: true, ...row
});

describe('schemaToRelationRows', () => {
  it('belongsTo 来自字段 relation，子表 hasMany 只读，顶层 relations 为模型关联', () => {
    expect(schemaToRelationRows(makeDoc())).toEqual([
      { origin: 'field', type: 'belongsTo', name: 'admin', table: 'fun_admin', localField: 'admin_id', foreignField: 'id', labelField: 'username', path: '/nodes/0', editable: true },
      { origin: 'field', type: 'belongsTo', name: 'category', table: 'goods_category', localField: 'category_id', foreignField: 'id', labelField: 'title', path: '/nodes/2/children/0', editable: true },
      { origin: 'field', type: 'belongsTo', name: 'brand', table: 'brand', localField: 'brand_id', foreignField: 'id', labelField: 'name', path: '/nodes/3', editable: true },
      { origin: 'subform', type: 'hasMany', name: 'items', table: 'goods_sku', localField: 'id', foreignField: 'goods_id', path: '/nodes/4', editable: false },
      { origin: 'model', type: 'hasOne', name: 'profile', table: 'goods_profile', localField: 'id', foreignField: 'goods_id', editable: true }
    ]);
  });
});

describe('applyRelationRows', () => {
  it('编辑 belongsTo 行写回字段节点，其他节点不变，输入不变', () => {
    const doc = makeDoc();
    const snapshot = structuredClone(doc);
    const rows = schemaToRelationRows(doc).map((row) => row.localField === 'category_id'
      ? { ...row, table: 'shop_category', labelField: 'name', foreignField: 'code' }
      : row);
    const next = applyRelationRows(doc, rows);
    expect(doc).toStrictEqual(snapshot);
    const original = snapshot.nodes[2].children[0];
    // 只改 database.relation 的关联键，节点其余属性原样
    expect(next.nodes[2].children[0]).toStrictEqual({
      ...original,
      database: { ...original.database, relation: { ...(original.database?.relation as object), table: 'shop_category', labelField: 'name', valueField: 'code' } }
    });
    expect(next.nodes[0]).toStrictEqual(snapshot.nodes[0]);
    expect(next.nodes[1]).toStrictEqual(snapshot.nodes[1]);
    expect(next.nodes[3]).toStrictEqual(snapshot.nodes[3]);
    expect(next.nodes[4]).toStrictEqual(snapshot.nodes[4]);
    expect(next.relations).toStrictEqual(snapshot.relations);
  });

  it('新增 belongsTo 在控件、选项、属性、关联上与设计器一致（物理类型不变）', () => {
    const rows = schemaToRelationRows(makeDoc());
    rows.push({ origin: 'field', type: 'belongsTo', name: 'name', table: 'fun_brand', localField: 'name', foreignField: 'id', labelField: 'title', editable: true });
    const next = applyRelationRows(makeDoc(), rows);

    const store = useDesigner();
    expect(store.replaceSchema(makeDoc() as never).ok).toBe(true);
    store.selectNode('n_name');
    // PropsPanel.onTypeChange('relation') 在采纳表上的产出（列类型不变），随后写关联
    store.updateNode(changeControl(store.selectedNode.value!, 'relation', { resetColumnType: false }));
    store.updateNode(patchRelation(store.selectedNode.value!, { type: 'belongs_to', table: 'fun_brand', labelField: 'title', valueField: 'id' }));

    const theirs = store.nodes.value[1];
    expect([next.nodes[1].type, next.nodes[1].dataSource, next.nodes[1].props])
      .toStrictEqual([theirs.type, theirs.dataSource, theirs.props]);
    // 设计器只写被编辑的关联键；关联表另补 multiple/onDelete 的缺省值（与后端 FormFieldView 缺省一致）。
    expect(next.nodes[1].database?.relation).toMatchObject(theirs.database!.relation!);
    expect(theirs.database?.columnType).toBe('varchar(100)');
    expect(next.nodes[1]).toMatchObject({ type: 'relation', dataSource: { kind: 'relation', options: [] }, props: { clearable: true, filterable: true } });
    expect(next.nodes[1].database).toMatchObject({ columnType: 'varchar(100)', relation: { type: 'belongs_to', table: 'fun_brand', labelField: 'title', valueField: 'id' } });
  });

  it('删除 belongsTo：关联控件改为 select（静态/字典选项）或 input，其他控件类型不变', () => {
    const doc = makeDoc();
    const rows = schemaToRelationRows(doc).filter((row) => row.origin !== 'field');
    const next = applyRelationRows(doc, rows);

    expect(next.nodes[0]).toMatchObject({ type: 'input', dataSource: null, props: { clearable: true } });
    expect(next.nodes[0].props).not.toHaveProperty('filterable');
    expect(next.nodes[0].database).toMatchObject({ columnType: 'bigint', relation: { type: 'none' } });

    const category = next.nodes[2].children[0];
    expect(category).toMatchObject({ type: 'select', dataSource: { kind: 'dictionary', dictionary: 'goods_category' }, props: { clearable: true } });
    expect(category.database?.relation).toMatchObject({ type: 'none' });

    expect(next.nodes[3]).toMatchObject({ type: 'select', dataSource: { kind: 'static', options: [{ label: 'A', value: 1 }] } });
    expect(next.nodes[3].database?.relation).toMatchObject({ type: 'none' });
    // 非关联控件只改关联类型
    expect(next.nodes[3]).toStrictEqual({ ...doc.nodes[3], database: { ...doc.nodes[3].database, relation: { ...(doc.nodes[3].database?.relation as object), type: 'none' } } });
  });

  it('hasOne / belongsToMany 写入顶层 relations', () => {
    const rows = [
      ...schemaToRelationRows(makeDoc()),
      modelRow({ type: 'belongsToMany', name: 'tags', table: 'tag', localField: 'id', foreignField: 'id', pivot: { table: 'goods_tag', localKey: 'goods_id', foreignKey: 'tag_id' } })
    ];
    const next = applyRelationRows(makeDoc(), rows);
    expect(next.relations).toStrictEqual([
      { type: 'hasOne', name: 'profile', table: 'goods_profile', localField: 'id', foreignField: 'goods_id' },
      { type: 'belongsToMany', name: 'tags', table: 'tag', localField: 'id', foreignField: 'id', pivot: { table: 'goods_tag', localKey: 'goods_id', foreignKey: 'tag_id' } }
    ]);
    expect(next.nodes).toStrictEqual(makeDoc().nodes);
  });

  it('删除全部模型关联后文档没有 relations 键', () => {
    const next = applyRelationRows(makeDoc(), schemaToRelationRows(makeDoc()).filter((row) => row.origin !== 'model'));
    expect('relations' in next).toBe(false);
  });

  it('子表行只读：改动与删除都不影响子表节点', () => {
    const doc = makeDoc();
    const rows = schemaToRelationRows(doc)
      .filter((row) => row.origin !== 'subform')
      .concat({ origin: 'subform', type: 'hasMany', name: 'items', table: 'changed', localField: 'id', foreignField: 'x', path: '/nodes/4', editable: false });
    expect(applyRelationRows(doc, rows).nodes[4]).toStrictEqual(doc.nodes[4]);
  });

  it('非法行抛错', () => {
    const base = schemaToRelationRows(makeDoc());
    expect(() => applyRelationRows(makeDoc(), [...base, modelRow({ type: 'belongsTo', name: 'x', table: 't', foreignField: 'id' })])).toThrow();
    expect(() => applyRelationRows(makeDoc(), [...base, { origin: 'field', type: 'hasOne', name: 'x', table: 't', localField: 'name', foreignField: 'id', editable: true }])).toThrow();
    expect(() => applyRelationRows(makeDoc(), [...base, { origin: 'field', type: 'belongsTo', name: 'x', table: 't', localField: 'missing', foreignField: 'id', editable: true }])).toThrow();
  });
});
