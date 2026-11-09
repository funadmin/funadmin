import { describe, expect, it } from 'vitest';
import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import { useDesigner } from '@/views/form/composables/useDesigner';
import { changeControl, patchList, withRequired } from '@/views/form/schema/nodeEdits';
import { applyFieldPatches, moveFieldWithinParent, schemaToFieldRows } from './fieldTable';

const field = (id: string, type: string, name: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id,
  kind: 'field',
  type,
  field: name,
  title: name.toUpperCase(),
  children: [],
  validation: [],
  dataSource: null,
  hidden: false,
  events: {},
  conditions: [],
  database: { columnType: 'varchar(255)', nullable: true, comment: name },
  list: { show: true, sort: false, filter: '', width: 0, formatter: '' },
  ...extra
});

const layout = (id: string, type: string, title: string, children: FormSchemaNode[]): FormSchemaNode => ({
  id, kind: 'layout', type, field: null, title, children
});

const makeDoc = (): FormSchemaDocument => ({
  schemaVersion: 2,
  key: 'goods',
  title: '商品',
  nodes: [
    field('n_title', 'input', 'title', { list: { show: true, sort: true, filter: 'like', width: 160, formatter: '' } }),
    layout('n_tabs', 'tabs', '标签页', [
      layout('n_basic', 'group', '基础信息', [
        field('n_price', 'number', 'price', { database: { columnType: 'decimal(10,2)', nullable: false, comment: '价格' } }),
        field('n_status', 'radio', 'status', {
          validation: [{ type: 'required', value: true }],
          dataSource: { kind: 'static', options: [{ label: '启用', value: 1 }] }
        })
      ]),
      layout('n_more', 'collapse', '更多', [field('n_content', 'richtext', 'content', { hidden: true })])
    ]),
    field('n_items', 'subform', 'items', {
      database: { relation: { type: 'has_many', table: 'goods_sku', valueField: 'goods_id' } },
      list: { show: false },
      children: [field('n_sku', 'input', 'sku')]
    }),
    layout('n_grid', 'grid', '栅格', [field('n_cover', 'image', 'cover', { dataSource: { kind: 'dictionary', dictionary: 'cover_kind' } })])
  ]
});

const at = (doc: FormSchemaDocument, path: string): FormSchemaNode => path.split('/').filter(Boolean).slice(1)
  .reduce<unknown>((current, segment) => (current as Record<string, unknown>)[segment], doc.nodes) as FormSchemaNode;

describe('schemaToFieldRows', () => {
  it('列出分组/标签页内字段并给出分组名，子表容器一行且不展开', () => {
    const rows = schemaToFieldRows(makeDoc());
    expect(rows.map((row) => [row.field, row.path, row.parentPath, row.groupTitle, row.container])).toEqual([
      ['title', '/nodes/0', '/nodes', '', false],
      ['price', '/nodes/1/children/0/children/0', '/nodes/1/children/0/children', '基础信息', false],
      ['status', '/nodes/1/children/0/children/1', '/nodes/1/children/0/children', '基础信息', false],
      ['content', '/nodes/1/children/1/children/0', '/nodes/1/children/1/children', '更多', false],
      ['items', '/nodes/2', '/nodes', '', true],
      ['cover', '/nodes/3/children/0', '/nodes/3/children', '', false]
    ]);
  });

  it('按节点属性映射各列', () => {
    const rows = schemaToFieldRows(makeDoc());
    expect(rows[0]).toEqual({
      path: '/nodes/0', parentPath: '/nodes', field: 'title', title: 'TITLE', columnType: 'varchar(255)',
      required: false, inForm: true, inList: true, filter: 'like', sortable: true, component: 'input',
      dictionary: '', listWidth: 160, groupTitle: '', container: false
    });
    expect(rows[1]).toMatchObject({ columnType: 'decimal(10,2)' });
    expect(rows[2]).toMatchObject({ required: true });
    expect(rows[3]).toMatchObject({ inForm: false, component: 'richtext' });
    expect(rows[5]).toMatchObject({ dictionary: 'cover_kind' });
  });
});

describe('applyFieldPatches', () => {
  it('只改一行：其他节点保持原样，输入文档不被修改', () => {
    const doc = makeDoc();
    const snapshot = structuredClone(doc);
    const next = applyFieldPatches(doc, { '/nodes/1/children/0/children/0': { filter: 'like' } });
    expect(doc).toStrictEqual(snapshot);
    expect(at(next, '/nodes/1/children/0/children/0').list?.filter).toBe('like');
    expect(next.nodes[0]).toStrictEqual(snapshot.nodes[0]);
    expect(next.nodes[2]).toStrictEqual(snapshot.nodes[2]);
    expect(next.nodes[3]).toStrictEqual(snapshot.nodes[3]);
    expect(at(next, '/nodes/1/children/0/children/1')).toStrictEqual(at(snapshot, '/nodes/1/children/0/children/1'));
    expect(at(next, '/nodes/1/children/1')).toStrictEqual(at(snapshot, '/nodes/1/children/1'));
    const parent = at(next, '/nodes/1/children/0');
    expect({ ...parent, children: [] }).toStrictEqual({ ...at(snapshot, '/nodes/1/children/0'), children: [] });
  });

  it('required 切换增删 required 规则', () => {
    const next = applyFieldPatches(makeDoc(), {
      '/nodes/1/children/0/children/0': { required: true },
      '/nodes/1/children/0/children/1': { required: false }
    });
    expect(at(next, '/nodes/1/children/0/children/0').validation?.some((rule) => rule.type === 'required')).toBe(true);
    expect(at(next, '/nodes/1/children/0/children/1').validation?.some((rule) => rule.type === 'required')).toBe(false);
  });

  it('其他列写回对应节点属性', () => {
    const next = applyFieldPatches(makeDoc(), {
      '/nodes/0': { title: '名称', inForm: false, inList: false, sortable: false, filter: '', listWidth: 200, component: 'textarea' },
      '/nodes/1/children/0/children/1': { dictionary: 'goods_status' }
    });
    const rows = schemaToFieldRows(next);
    expect(rows[0]).toMatchObject({ title: '名称', inForm: false, inList: false, sortable: false, filter: '', listWidth: 200, component: 'textarea' });
    expect(rows[2].dictionary).toBe('goods_status');
    expect(at(next, '/nodes/1/children/0/children/1').dataSource).toMatchObject({ kind: 'dictionary', dictionary: 'goods_status' });
  });

  it('与设计器 updateNode 对被改动的属性产出一致', () => {
    const store = useDesigner();
    expect(store.replaceSchema(makeDoc() as never).ok).toBe(true);
    store.selectNode('n_price');
    const price = store.selectedNode.value!;
    store.updateNode(patchList(withRequired({ ...price, title: '售价', hidden: true }, true), { filter: 'gte', width: 90, sort: true }));
    store.selectNode('n_status');
    // 属性面板 updateDictionary 的产出：保留原数据源其余键，kind 收敛为 dictionary
    store.updateNode({ dataSource: { ...store.selectedNode.value!.dataSource, kind: 'dictionary', dictionary: 'goods_status' } });

    const next = applyFieldPatches(makeDoc(), {
      '/nodes/1/children/0/children/0': { filter: 'gte', required: true, title: '售价', listWidth: 90, inForm: false, sortable: true },
      '/nodes/1/children/0/children/1': { dictionary: 'goods_status' }
    });
    const ours = at(next, '/nodes/1/children/0/children/0');
    const theirs = store.nodes.value[1].children[0].children[0];
    expect(ours.title).toBe(theirs.title);
    expect(ours.validation).toStrictEqual(theirs.validation);
    expect(ours.hidden).toBe(theirs.hidden);
    for (const key of ['filter', 'width', 'sort']) expect(ours.list?.[key]).toStrictEqual(theirs.list?.[key]);
    expect(at(next, '/nodes/1/children/0/children/1').dataSource).toStrictEqual(store.nodes.value[1].children[0].children[1].dataSource);
  });

  it('只改被改动的属性：校验 message/trigger/when、响应式 span、数值默认值与未知键原样保留', () => {
    const doc = makeDoc();
    const rich: FormSchemaNode = {
      ...doc.nodes[0],
      defaultValue: 0,
      layout: { span: { md: 12, xs: 24 }, group: 'g' },
      validation: [
        { type: 'required', value: true, message: '必填' },
        { type: 'max', value: 10, message: '太长', trigger: ['blur'] },
        { type: 'max', value: 20, severity: 'warning' },
        { type: 'async', validator: { key: 'unique', debounce: 300 }, when: { field: 'price', op: 'gt', value: 0 } }
      ],
      events: { change: [{ action: 'setValue', target: 'price', value: 1 }] },
      conditions: [{ effect: 'hidden', when: { field: 'price', op: 'eq', value: 0 } }],
      attrs: { 'aria-label': '标题' },
      className: 'title-field',
      style: { width: '100%' },
      info: '提示',
      slot: 'prefix',
      database: { columnType: 'varchar(255)', nullable: true, comment: 'title', extraKey: 'k', relation: { type: 'none', custom: 1 } },
      list: { show: true, sort: true, filter: 'like', width: 160, formatter: '', align: 'center' }
    };
    doc.nodes[0] = rich;
    const snapshot = structuredClone(rich);

    expect(applyFieldPatches(doc, { '/nodes/0': { title: '名称' } }).nodes[0]).toStrictEqual({ ...snapshot, title: '名称' });
    expect(applyFieldPatches(doc, { '/nodes/0': { filter: 'eq', listWidth: 80 } }).nodes[0])
      .toStrictEqual({ ...snapshot, list: { ...snapshot.list, filter: 'eq', width: 80 } });
    expect(applyFieldPatches(doc, { '/nodes/0': { required: false } }).nodes[0])
      .toStrictEqual({ ...snapshot, validation: snapshot.validation?.slice(1) });
    // 已是目标值的改动不触碰节点
    expect(applyFieldPatches(doc, { '/nodes/0': { required: true, inForm: true, component: 'input' } }).nodes[0]).toBe(rich);
    expect(doc.nodes[0]).toStrictEqual(snapshot);
  });

  it('切换控件按设计器 onTypeChange 重置选项与属性，物理类型不变', () => {
    const doc = makeDoc();
    doc.nodes[0] = { ...doc.nodes[0], type: 'select', props: { clearable: true, filterable: true }, dataSource: { kind: 'static', options: [{ label: 'A', value: 1 }] } };
    const next = applyFieldPatches(doc, { '/nodes/0': { component: 'input' } }).nodes[0];
    expect(next).toStrictEqual({ ...doc.nodes[0], type: 'input', props: { clearable: true }, dataSource: null });

    const store = useDesigner();
    expect(store.replaceSchema(doc as never).ok).toBe(true);
    store.selectNode('n_title');
    // PropsPanel.onTypeChange('input') 在采纳表上的产出（列类型不变）
    store.updateNode(changeControl(store.selectedNode.value!, 'input', { resetColumnType: false }));
    const theirs = store.nodes.value[0];
    expect([next.type, next.props, next.dataSource]).toStrictEqual([theirs.type, theirs.props, theirs.dataSource]);

    const withDictionary = applyFieldPatches(doc, { '/nodes/0': { component: 'dictionary', dictionary: 'sex' } }).nodes[0];
    expect(withDictionary).toMatchObject({ type: 'dictionary', dataSource: { kind: 'dictionary', dictionary: 'sex', options: [] }, props: { clearable: true } });
    expect(() => applyFieldPatches(doc, { '/nodes/0': { component: 'group' } })).toThrow();
    expect(() => applyFieldPatches(doc, { '/nodes/0': { component: 'subform' } })).toThrow();
  });

  it('未知路径或子表容器行抛错', () => {
    expect(() => applyFieldPatches(makeDoc(), { '/nodes/9': { title: 'x' } })).toThrow();
    expect(() => applyFieldPatches(makeDoc(), { '/nodes/2': { title: 'x' } })).toThrow();
  });
});

describe('moveFieldWithinParent', () => {
  it('同一父容器内按行序号调整顺序', () => {
    const doc = makeDoc();
    const snapshot = structuredClone(doc);
    const next = moveFieldWithinParent(doc, '/nodes/1/children/0/children/1', 1);
    expect(doc).toStrictEqual(snapshot);
    expect(schemaToFieldRows(next).map((row) => row.field)).toEqual(['title', 'status', 'price', 'content', 'items', 'cover']);
    expect(next.nodes[0]).toStrictEqual(snapshot.nodes[0]);

    const root = moveFieldWithinParent(doc, '/nodes/0', 4);
    expect(root.nodes.map((node) => node.id)).toEqual(['n_tabs', 'n_items', 'n_title', 'n_grid']);
  });

  it('跨父容器移动抛错', () => {
    expect(() => moveFieldWithinParent(makeDoc(), '/nodes/1/children/0/children/0', 3)).toThrow();
    expect(() => moveFieldWithinParent(makeDoc(), '/nodes/0', 1)).toThrow();
    expect(() => moveFieldWithinParent(makeDoc(), '/nodes/0', 99)).toThrow();
  });
});
