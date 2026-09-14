import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SchemaTablePage from './SchemaTablePage.vue';
import type { PageSchema } from './pageSchema';
const schema: PageSchema = { pageSchemaVersion: 1, key: 'demo', search: [{ field: 'keyword', type: 'input', label: '关键词' }], columns: [{ key: 'email', prop: 'email', label: '邮箱', formatter: 'emptyText' }], toolbar: [{ id: 'add', label: '新增', action: { type: 'registered', key: 'add', capabilityVersion: '1' } }], rowActions: [], pagination: { pageSize: 20, pageSizes: [10, 20] } };
const stubs = {
  DataTableShell: { template: '<div><slot name="search"/><slot name="toolbar-left"/><slot :size="\'small\'" /></div>' },
  SearchForm: { template: '<form><slot/><button @click.prevent="$emit(\'search\')">查询</button></form>' },
  ElFormItem: { props: ['label'], template: '<label>{{label}}<slot/></label>' },
  ElInput: { props: ['modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
  ElTable: { template: '<div><slot/></div>' },
  ElTableColumn: { props: ['label'], template: '<div>{{label}}<slot :row="{email: \'\'}" /></div>' },
  ElButton: { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
  ElPagination: true, ElSelect: true, ElOption: true
};
describe('声明式公共表格渲染', () => {
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
  it('空按钮不回退、未注册按钮不展示', () => {
    const wrapper = mount(SchemaTablePage, { props: { schema, query: { page: 1, pageSize: 20 }, rows: [], total: 0, context: { values: {}, permissions: ['*'], handlers: {} } }, global: { stubs, directives: { loading: () => {} } } });
    expect(wrapper.text()).not.toContain('新增'); wrapper.unmount();
  });
});
