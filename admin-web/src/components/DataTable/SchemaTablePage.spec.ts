import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SchemaTablePage from './SchemaTablePage.vue';
import DataTableShell from './DataTableShell.vue';
import DataTableToolbar from './DataTableToolbar.vue';
import { loadColumnKeys, saveColumnKeys } from './tableDisplayStorage';
import type { PageSchema } from './pageSchema';
const schema: PageSchema = { pageSchemaVersion: 1, key: 'demo', search: [{ field: 'keyword', type: 'input', label: '关键词' }], columns: [{ key: 'email', prop: 'email', label: '邮箱', formatter: 'emptyText' }], toolbar: [{ id: 'add', label: '新增', action: { type: 'registered', key: 'add', capabilityVersion: '1' } }], rowActions: [], pagination: { pageSize: 20, pageSizes: [10, 20] } };
const stubs = {
  DataTableShell: { name: 'DataTableShell', template: '<div><slot name="search"/><slot name="toolbar-left"/><slot :size="\'small\'" /></div>' },
  SearchForm: { template: '<form><slot/><button @click.prevent="$emit(\'search\')">查询</button></form>' },
  ElFormItem: { props: ['label'], template: '<label>{{label}}<slot/></label>' },
  ElInput: { props: ['modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
  ElTable: { template: '<div><slot/></div>' },
  ElTableColumn: { props: ['label'], template: '<div>{{label}}<slot :row="{email: \'\'}" /></div>' },
  ElButton: { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
  ElPagination: true, ElSelect: true, ElOption: true, ElDatePicker: true,
  ElIcon: true, ElDropdown: true, ElDropdownMenu: true, ElDropdownItem: true,
  ElAlert: true, ElInputNumber: true, ElSwitch: true, ElForm: true
};
describe('声明式公共表格渲染', () => {
  it('日期区间、树表主键、远程排序和工具开关进入公共渲染', async () => {
    const query = { page: 4, pageSize: 20 };
    const rows = [{ record_id: '007', parent_id: null }, { record_id: 0, parent_id: '007' }];
    const w = mount(SchemaTablePage, { props: { schema: { ...schema, primaryKey: 'record_id', search: [{ field: 'created', label: '日期', type: 'date' }, { field: 'price', label: '价格', type: 'range' }], columns: [{ key: 'record_id', prop: 'record_id', label: '编号', sortable: true }], list: { tree: { enabled: true, parentField: 'parent_id' }, tools: { refresh: false, density: false, fullscreen: false, columns: false } } }, query, rows, total: 2, context: { values: {}, permissions: [], handlers: {} } }, global: { stubs: { ...stubs, ElDatePicker: true, ElTable: { name: 'ElTable', props: ['data', 'rowKey', 'treeProps'], template: '<div><slot/></div>' } }, directives: { loading: () => {} } } });
    expect(w.findComponent({ name: 'ElDatePicker' }).exists()).toBe(true);
    expect(w.findAll('input')).toHaveLength(2);
    const table = w.findComponent({ name: 'ElTable' });
    expect(table.props('rowKey')).toBe('record_id');
    expect(table.props('data')).toEqual([{ ...rows[0], __listChildren: [rows[1]] }]);
    expect(w.findComponent({ name: 'ElPagination' }).exists()).toBe(false);
    expect(w.findComponent({ name: 'DataTableShell' }).attributes()).toMatchObject({ 'show-refresh': 'false', 'show-density': 'false', 'show-fullscreen': 'false', 'show-column-setting': 'false' });
    table.vm.$emit('sort-change', { prop: 'record_id', order: 'descending' });
    expect(w.emitted('sortChange')).toEqual([[{ prop: 'record_id', order: 'descending' }]]);
    expect(rows[0]).not.toHaveProperty('__listChildren');
    w.unmount();
  });
  it('复用选项分类，零和字符串 ID 保真，全部仅清分类并重置页码', async () => {
    const query = { groupId: undefined as string | number | undefined, keyword: '原筛选', status: 0, page: 8, pageSize: 20 };
    const categorySchema = { ...schema, search: [...schema.search, { field: 'groupId', label: '会员组', type: 'select', options: [{ label: '零分类', value: 0 }, { label: '字符串组', value: '007' }] }], list: { category: { enabled: true, field: 'groupId' } } } as PageSchema;
    const w = mount(SchemaTablePage, { props: { schema: categorySchema, query, rows: [], total: 0, context: { values: {}, permissions: [], handlers: {} } }, global: { stubs, directives: { loading: () => {} } } });
    await w.findAll('button').find(b => b.text() === '零分类')!.trigger('click');
    expect(query).toMatchObject({ groupId: 0, page: 1, status: 0, keyword: '原筛选' });
    await w.findAll('button').find(b => b.text() === '字符串组')!.trigger('click'); expect(query.groupId).toBe('007');
    await w.findAll('button').find(b => b.text() === '全部')!.trigger('click'); expect(query.groupId).toBeUndefined();
    expect(w.emitted('search')).toHaveLength(3); w.unmount();
  });
  it('业务树复用宿主绑定与权限，选择保留原 filters，缺少绑定不访问通用接口', async () => {
    const config = { enabled: true, source: { type: 'module', module: 'categories' }, mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id' }, selection: { mode: 'multiple', includeDescendants: true }, actions: { delete: false } };
    const query = { page: 6, pageSize: 20, filters: { status: 0 } };
    const w = mount(SchemaTablePage, { props: { schema: { ...schema, list: { leftTree: config, buttons: { categoryNode: [] } } } as PageSchema, query, rows: [], total: 0, context: { values: {}, permissions: [], handlers: {} } }, global: { stubs: { ...stubs, ListSourceTree: { name: 'ListSourceTree', props: ['formKey', 'schemaHash', 'config', 'list', 'canMutate', 'canReadForm'], template: '<aside />' } }, directives: { loading: () => {} } } });
    expect(w.findComponent({ name: 'ListSourceTree' }).exists()).toBe(false);
    await w.setProps({ sourceBinding: { formKey: 'orders', schemaHash: 'target-hash' } } as any);
    const tree = w.findComponent({ name: 'ListSourceTree' }); expect(tree.props()).toMatchObject({ formKey: 'orders', schemaHash: 'target-hash', canMutate: false, canReadForm: false });
    tree.vm.$emit('change', [0, '007']); expect(query).toMatchObject({ page: 1, filters: { status: 0, __leftTree: [0, '007'] } });
    tree.vm.$emit('change', []); expect(query.filters).toEqual({ status: 0, __leftTree: [] }); w.unmount();
  });
  it('渲染配置搜索、列与注册按钮，绑定查询与 formatter', async () => {
    const run = vi.fn(); const query = { keyword: '', page: 1, pageSize: 20 };
    const wrapper = mount(SchemaTablePage, { props: { schema, query, rows: [], total: 0, context: { values: {}, permissions: [], handlers: { add: { version: '1', run } } }, formatters: { emptyText: (value: unknown) => value || '-' } }, global: { stubs, directives: { loading: () => {} } } });
    expect(wrapper.text()).toContain('邮箱'); expect(wrapper.text()).toContain('-');
    await wrapper.find('input').setValue('关键词值'); expect(query.keyword).toBe('关键词值');
    await wrapper.findAll('button').find(b => b.text() === '新增')!.trigger('click'); await flushPromises(); expect(run).toHaveBeenCalledOnce();
    await wrapper.findAll('button').find(b => b.text() === '查询')!.trigger('click'); expect(wrapper.emitted('search')).toHaveLength(1);
    wrapper.unmount();
  });
  it('异步元数据从基础列补全为完整列时，初始空列也会跟踪新列', async () => {
    const wrapper = mount(DataTableShell, {
      props: { storageKey: 'published-async-columns', columnOptions: [] },
      global: { stubs: { DataTableToolbar: { props: ['columnKeys'], template: '<div />' } } }
    });

    expect((wrapper.vm as any).columnKeys).toEqual([]);
    await wrapper.setProps({ columnOptions: [{ key: 'id', label: 'ID' }] });
    expect((wrapper.vm as any).columnKeys).toEqual(['id']);
    await wrapper.setProps({ columnOptions: [{ key: 'id', label: 'ID' }, { key: 'title', label: '标题' }] });
    expect((wrapper.vm as any).columnKeys).toEqual(['id', 'title']);
    wrapper.unmount();
  });

  it('Schema 显式空列配置不回退到默认列', () => {
    const wrapper = mount(SchemaTablePage, { props: { schema: { ...schema, columns: [] }, query: { page: 1, pageSize: 20 }, rows: [], total: 0, context: { values: {}, permissions: [], handlers: {} } }, global: { stubs, directives: { loading: () => {} } } });
    expect(wrapper.findAllComponents({ name: 'ElTableColumn' })).toHaveLength(0);
    wrapper.unmount();
  });

  it('空按钮不回退、未注册按钮不展示', () => {
    const wrapper = mount(SchemaTablePage, { props: { schema, query: { page: 1, pageSize: 20 }, rows: [], total: 0, context: { values: {}, permissions: ['*'], handlers: {} } }, global: { stubs, directives: { loading: () => {} } } });
    expect(wrapper.text()).not.toContain('新增'); wrapper.unmount();
  });
});

describe('共享表格异步列偏好', () => {
  const storageKey = 'async-column-preference-regression';
  const base = [{ key: 'id', label: 'ID' }];
  const full = [...base, { key: 'title', label: '标题' }, { key: 'email', label: '邮箱' }];
  const wrappers: ReturnType<typeof mount>[] = [];
  function mountShell(columnOptions = base) {
    const wrapper = mount(DataTableShell, {
      props: { storageKey, columnOptions },
      global: { stubs: { DataTableToolbar: { props: ['columnKeys'], template: '<div />' } } }
    });
    wrappers.push(wrapper);
    return wrapper;
  }
  beforeEach(() => {
    const values = new Map<string, string>();
    vi.stubGlobal('localStorage', {
      getItem: (key: string) => values.get(key) ?? null,
      setItem: (key: string, value: string) => { values.set(key, value); },
      removeItem: (key: string) => { values.delete(key); }
    });
  });
  afterEach(() => {
    wrappers.splice(0).forEach(wrapper => wrapper.unmount());
    localStorage.removeItem(`data-table:${storageKey}:columns`);
    localStorage.removeItem(`data-table:${storageKey}:other:columns`);
    vi.unstubAllGlobals();
  });

  it.each([['空列', []], ['基础列', base]] as const)('预存偏好在初始%s后恢复完整业务列且不改写存储', async (_, initial) => {
    saveColumnKeys(storageKey, ['id', 'title']);
    const wrapper = mountShell([...initial]);
    await wrapper.setProps({ columnOptions: base });
    expect(wrapper.findComponent(DataTableToolbar).props('columnKeys')).toEqual(['id']);
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.findComponent(DataTableToolbar).props('columnKeys')).toEqual(['id', 'title']);
    expect(loadColumnKeys(storageKey)).toEqual(['id', 'title']);
  });

  it('尚无匹配列时仅投影为空，业务列到达后恢复', async () => {
    saveColumnKeys(storageKey, ['title']);
    const wrapper = mountShell();
    expect(wrapper.vm.columnKeys).toEqual([]);
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.vm.columnKeys).toEqual(['title']);
  });

  it('基础列阶段手动取消列保留尚未到达的业务偏好', async () => {
    saveColumnKeys(storageKey, ['id', 'title']);
    const wrapper = mountShell();
    wrapper.findComponent(DataTableToolbar).vm.$emit('update:column-keys', []);
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.vm.columnKeys).toEqual(['title']);
    expect(loadColumnKeys(storageKey)).toEqual(['title']);
  });

  it('无偏好时手动取消全部，异步列更新不重新选中', async () => {
    const wrapper = mountShell();
    wrapper.findComponent(DataTableToolbar).vm.$emit('update:column-keys', []);
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.vm.columnKeys).toEqual([]);
    expect(loadColumnKeys(storageKey)).toEqual([]);
  });

  it('用户取消的列在临时空列后不会重新选中', async () => {
    const wrapper = mountShell(full);
    wrapper.findComponent(DataTableToolbar).vm.$emit('update:column-keys', ['title']);
    await wrapper.setProps({ columnOptions: [] });
    expect(wrapper.vm.columnKeys).toEqual([]);
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.vm.columnKeys).toEqual(['title']);
    expect(loadColumnKeys(storageKey)).toEqual(['title']);
  });

  it.each([{ saved: null }, { saved: [] }])('无偏好或存储空数组 $saved 维持默认全选', async ({ saved }) => {
    if (saved) saveColumnKeys(storageKey, saved);
    const wrapper = mountShell([]);
    await wrapper.setProps({ columnOptions: base });
    expect(wrapper.vm.columnKeys).toEqual(['id']);
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.vm.columnKeys).toEqual(['id', 'title', 'email']);
    expect(loadColumnKeys(storageKey)).toEqual(saved);
  });

  it('空列阶段切换存储键重新读取偏好，不沿用上一张表', async () => {
    saveColumnKeys(storageKey, ['id']);
    saveColumnKeys(`${storageKey}:other`, ['email']);
    const wrapper = mountShell();
    await wrapper.setProps({ storageKey: `${storageKey}:other`, columnOptions: [] });
    await wrapper.setProps({ columnOptions: full });
    expect(wrapper.vm.columnKeys).toEqual(['email']);
  });
});
