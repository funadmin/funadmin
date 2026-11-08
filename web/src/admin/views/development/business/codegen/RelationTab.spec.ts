import { defineComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';

const mocks = vi.hoisted(() => ({ databaseTables: vi.fn(), databaseTableSchema: vi.fn() }));
vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return { ...actual, businessDevelopmentApi: { ...actual.businessDevelopmentApi, databaseTables: mocks.databaseTables, databaseTableSchema: mocks.databaseTableSchema } };
});

import RelationTab from './tabs/RelationTab.vue';
import { createRelationCatalog } from './relationCatalog';

const node = (id: string, type: string, name: string, extra: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id, kind: 'field', type, field: name, title: name, props: {}, children: [], validation: [], dataSource: null,
  conditions: [], events: {}, hidden: false,
  database: { columnType: 'bigint', nullable: true, comment: name, relation: { type: 'none' } },
  list: { show: false, filter: '', sort: false, width: 0 },
  ...extra
});

const makeDoc = (relations: FormSchemaDocument['relations'] = [{ type: 'hasOne', name: 'detail', table: 'shop_goods_ext', localField: 'id', foreignField: 'goods_id' }]): FormSchemaDocument => ({
  schemaVersion: 2, key: 'shop_goods', title: '商品', list: {},
  nodes: [
    node('n_title', 'input', 'title'),
    node('n_category', 'relation', 'category_id', {
      database: { columnType: 'bigint', relation: { type: 'belongs_to', table: 'shop_category', valueField: 'id', labelField: 'name' } }
    })
  ],
  relations
});

const columns: Record<string, Array<{ name: string; type: string; comment: string; primary: boolean }>> = {
  shop_goods_ext: [{ name: 'id', type: 'bigint', comment: '', primary: true }, { name: 'goods_id', type: 'bigint', comment: '商品', primary: false }],
  shop_profile: [{ name: 'id', type: 'bigint', comment: '', primary: true }, { name: 'member_id', type: 'bigint', comment: '会员', primary: false }],
  shop_profile2: [{ name: 'pid', type: 'int', comment: '', primary: true }, { name: 'goods_id', type: 'bigint', comment: '', primary: false }],
  shop_category: [{ name: 'id', type: 'bigint', comment: '', primary: true }, { name: 'name', type: 'varchar(60)', comment: '名称', primary: false }],
  shop_goods_tag: [{ name: 'goods_id', type: 'bigint', comment: '', primary: false }, { name: 'tag_id', type: 'bigint', comment: '', primary: false }],
  shop_tag: [{ name: 'id', type: 'bigint', comment: '', primary: true }]
};
const tables = [
  { name: 'shop_goods_ext', comment: '商品扩展' }, { name: 'shop_profile', comment: '' }, { name: 'shop_profile2', comment: '' },
  { name: 'shop_category', comment: '分类' }, { name: 'shop_goods_tag', comment: '' }, { name: 'shop_tag', comment: '标签' }
];

const stubs = {
  ElButton: defineComponent({ inheritAttrs: false, props: ['disabled', 'loading', 'type', 'link', 'plain'], emits: ['click'], template: '<button v-bind="$attrs" type="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' }),
  ElInput: defineComponent({ inheritAttrs: false, props: ['modelValue'], emits: ['update:modelValue'], template: '<input v-bind="$attrs" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' }),
  ElSelect: defineComponent({
    inheritAttrs: false,
    props: ['modelValue', 'disabled', 'loading', 'allowCreate'],
    emits: ['update:modelValue', 'change', 'visible-change'],
    template: '<select v-bind="$attrs" :value="modelValue" :data-loading="loading ? \'true\' : \'false\'" :data-allow-create="allowCreate ? \'true\' : \'false\'" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'change\', $event.target.value)"><slot /></select>'
  }),
  ElOption: defineComponent({ props: ['value', 'label'], template: '<option :value="value">{{ label }}</option>' }),
  ElTag: defineComponent({ inheritAttrs: false, props: ['type'], template: '<span v-bind="$attrs" data-tag :data-type="type"><slot /></span>' }),
  ElAlert: defineComponent({ inheritAttrs: false, props: ['title', 'type'], template: '<div v-bind="$attrs" role="alert">{{ title }}<slot /></div>' })
};

const mountTab = async (doc = makeDoc(), catalog = createRelationCatalog(() => 'mysql')) => {
  const wrapper = mount(RelationTab, { props: { doc, catalog }, global: { stubs } });
  await flushPromises();
  return wrapper;
};
type Wrapper = Awaited<ReturnType<typeof mountTab>>;
const cell = (wrapper: Wrapper, row: string, col: string) => wrapper.find(`[data-relation-row="${row}"] [data-col="${col}"]`);
const optionLabels = (wrapper: Wrapper, selector: string) => wrapper.find(selector).findAll('option').map((item) => item.text());
const lastDoc = (wrapper: Wrapper): FormSchemaDocument => wrapper.emitted('update:doc')!.at(-1)![0] as FormSchemaDocument;

beforeEach(() => {
  vi.clearAllMocks();
  mocks.databaseTables.mockResolvedValue(tables);
  mocks.databaseTableSchema.mockImplementation(async (_connection: string, table: string) => ({ table, columns: columns[table] ?? [] }));
});

describe('关联页签：关联表与字段可选', () => {
  it('关联表从当前连接的数据表加载，标签为「表名 — 注释」；已选表的字段作为对方字段选项；同一目录只加载一次', async () => {
    const catalog = createRelationCatalog(() => 'mysql');
    const wrapper = await mountTab(makeDoc(), catalog);
    expect(mocks.databaseTables).toHaveBeenCalledTimes(1);
    expect(mocks.databaseTables).toHaveBeenCalledWith('mysql');
    const tableSelect = cell(wrapper, 'detail', 'table').find('select');
    expect(tableSelect.exists()).toBe(true);
    expect(tableSelect.attributes('data-allow-create')).toBe('false');
    expect(optionLabels(wrapper, '[data-relation-row="detail"] [data-col="table"] select')).toEqual(expect.arrayContaining(['shop_goods_ext — 商品扩展', 'shop_profile']));
    expect(mocks.databaseTableSchema).toHaveBeenCalledWith('mysql', 'shop_goods_ext');
    expect(optionLabels(wrapper, '[data-relation-row="detail"] [data-col="foreignField"] select')).toEqual(['id · bigint', 'goods_id — 商品 · bigint']);
    expect((cell(wrapper, 'detail', 'foreignField').find('select').element as HTMLSelectElement).value).toBe('goods_id');
    // 本表字段仍是本文档字段（含 id）
    expect(optionLabels(wrapper, '[data-relation-row="detail"] [data-col="localField"] select')).toEqual(['id', 'title', 'category_id']);
    // 同一目录：再次挂载（如保存后重建页签）不重复请求
    const again = await mountTab(makeDoc(), catalog);
    expect(again.find('[data-relation-row="detail"] [data-col="table"] select').exists()).toBe(true);
    expect(mocks.databaseTables).toHaveBeenCalledTimes(1);
    expect(mocks.databaseTableSchema.mock.calls.filter(([, table]) => table === 'shop_goods_ext')).toHaveLength(1);
  });

  it('选择关联表后加载该表字段；换表时清掉新表中不存在的对方字段，存在的保留', async () => {
    const wrapper = await mountTab();
    await cell(wrapper, 'detail', 'table').find('select').setValue('shop_profile');
    await flushPromises();
    expect(mocks.databaseTableSchema).toHaveBeenCalledWith('mysql', 'shop_profile');
    expect(optionLabels(wrapper, '[data-relation-row="detail"] [data-col="foreignField"] select')).toEqual(['id · bigint', 'member_id — 会员 · bigint']);
    expect(lastDoc(wrapper).relations).toEqual([{ type: 'hasOne', name: 'detail', table: 'shop_profile', localField: 'id', foreignField: '' }]);
    await cell(wrapper, 'detail', 'foreignField').find('select').setValue('member_id');
    expect(lastDoc(wrapper).relations?.[0].foreignField).toBe('member_id');
    // 新表也有该字段时保留
    const keep = await mountTab();
    await cell(keep, 'detail', 'table').find('select').setValue('shop_profile2');
    await flushPromises();
    expect(lastDoc(keep).relations?.[0]).toMatchObject({ table: 'shop_profile2', foreignField: 'goods_id' });
  });

  it('已保存的表不在数据库中时保留原值并标记「表不存在」，不请求其字段；字段不存在时标记「字段不存在」', async () => {
    const wrapper = await mountTab(makeDoc([
      { type: 'hasOne', name: 'legacy', table: 'shop_legacy', localField: 'id', foreignField: 'goods_id' },
      { type: 'hasMany', name: 'ext', table: 'shop_goods_ext', localField: 'id', foreignField: 'old_goods_id' }
    ]));
    const table = cell(wrapper, 'legacy', 'table');
    expect((table.find('select').element as HTMLSelectElement).value).toBe('shop_legacy');
    expect(table.find('[data-missing]').text()).toBe('表不存在');
    expect(mocks.databaseTableSchema).not.toHaveBeenCalledWith('mysql', 'shop_legacy');
    expect((cell(wrapper, 'legacy', 'foreignField').find('select').element as HTMLSelectElement).value).toBe('goods_id');
    const field = cell(wrapper, 'ext', 'foreignField');
    expect((field.find('select').element as HTMLSelectElement).value).toBe('old_goods_id');
    expect(field.find('[data-missing]').text()).toBe('字段不存在');
    expect(cell(wrapper, 'ext', 'table').find('[data-missing]').exists()).toBe(false);
    // 未改动时不写回文档
    expect(wrapper.emitted('update:doc')).toBeUndefined();
  });

  it('belongsTo 字段关联：关联表、值字段、显示字段都从数据库选择', async () => {
    const wrapper = await mountTab();
    expect(optionLabels(wrapper, '[data-relation-row="category"] [data-col="table"] select')).toContain('shop_category — 分类');
    expect(optionLabels(wrapper, '[data-relation-row="category"] [data-col="foreignField"] select')).toEqual(['id · bigint', 'name — 名称 · varchar(60)']);
    expect(optionLabels(wrapper, '[data-relation-row="category"] [data-col="labelField"] select')).toEqual(['id · bigint', 'name — 名称 · varchar(60)']);
    await cell(wrapper, 'category', 'table').find('select').setValue('shop_profile');
    await flushPromises();
    const relation = (lastDoc(wrapper).nodes[1].database as { relation: Record<string, unknown> }).relation;
    expect(relation).toMatchObject({ table: 'shop_profile', valueField: 'id', labelField: '' });
    await cell(wrapper, 'category', 'labelField').find('select').setValue('member_id');
    expect((lastDoc(wrapper).nodes[1].database as { relation: Record<string, unknown> }).relation).toMatchObject({ labelField: 'member_id' });
  });

  it('belongsToMany 中间表与两个键列从数据库选择，换中间表时清掉失效的键列', async () => {
    const wrapper = await mountTab(makeDoc([{ type: 'belongsToMany', name: 'tags', table: 'shop_tag', localField: 'id', foreignField: 'id', pivot: { table: 'shop_goods_tag', localKey: 'goods_id', foreignKey: 'tag_id' } }]));
    const pivot = cell(wrapper, 'tags', 'pivot');
    expect(pivot.find('[data-pivot="table"]').findAll('option').map((item) => item.text())).toContain('shop_tag — 标签');
    expect(mocks.databaseTableSchema).toHaveBeenCalledWith('mysql', 'shop_goods_tag');
    expect(pivot.find('[data-pivot="localKey"]').findAll('option').map((item) => item.text())).toEqual(['goods_id · bigint', 'tag_id · bigint']);
    await pivot.find('[data-pivot="table"]').setValue('shop_profile2');
    await flushPromises();
    expect(lastDoc(wrapper).relations?.[0].pivot).toEqual({ table: 'shop_profile2', localKey: 'goods_id', foreignKey: '' });
  });

  it('数据表加载失败时显示错误与重试，重试成功后可选择', async () => {
    mocks.databaseTables.mockRejectedValueOnce(new Error('连接超时'));
    const wrapper = await mountTab();
    const alert = wrapper.find('[data-testid="relation-tables-error"]');
    expect(alert.text()).toContain('连接超时');
    // 加载失败时不能判断表是否存在：不显示「表不存在」，保留已保存的值
    expect(wrapper.find('[data-missing]').exists()).toBe(false);
    expect((cell(wrapper, 'detail', 'table').find('select').element as HTMLSelectElement).value).toBe('shop_goods_ext');
    await alert.find('[data-action="relation-retry-tables"]').trigger('click');
    await flushPromises();
    expect(mocks.databaseTables).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-testid="relation-tables-error"]').exists()).toBe(false);
    expect(optionLabels(wrapper, '[data-relation-row="detail"] [data-col="table"] select')).toContain('shop_profile');
  });
});
