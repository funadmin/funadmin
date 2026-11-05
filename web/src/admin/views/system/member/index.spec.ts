import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import MemberPage from './index.vue';
const api = vi.hoisted(() => ({ options: vi.fn(), list: vi.fn(), exportRows: vi.fn() }));
vi.mock('@/api/system/member', () => ({ memberApi: api }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: ['*'] }) }));
vi.mock('./components/MemberFormDialog.vue', () => ({ default: { template: '<div />' } }));
const definition = () => ({ groups: [], levels: [], tags: [], page: { pageSchemaVersion: 1, key: 'system_member', search: [], columns: [], toolbar: [], rowActions: [], pagination: { pageSize: 20, pageSizes: [10, 20, 50, 100] } } });
const render = () => mount(MemberPage, { global: { stubs: {
  PageWrapper: { template: '<main><slot /></main>' },
  DataTableShell: { template: '<div />' },
  SchemaTablePage: { name: 'SchemaTablePage', props: ['schema', 'query', 'rows', 'total', 'context'], template: '<section />' },
  ElAlert: { props: ['title'], template: '<div>{{title}}</div>' },
  ElButton: { template: '<button><slot/></button>' }, ElSkeleton: true, ElAvatar: true, ElTag: true, ElSwitch: true
} } });
beforeEach(() => { vi.clearAllMocks(); api.options.mockResolvedValue(definition()); api.list.mockResolvedValue({ list: [], total: 0 }); });
describe('会员 PHP 页面接入', () => {
  it('接收空配置不回退写死按钮，保留请求字段、筛选和回收站重置', async () => {
    const w = render(); await flushPromises();
    const table = w.findComponent({ name: 'SchemaTablePage' }); expect(table.exists()).toBe(true);
    expect(table.props('schema').toolbar).toEqual([]);
    expect(api.list).toHaveBeenCalledWith(expect.objectContaining({ page: 1, pageSize: 20, recycled: 0 }));
    Object.assign(table.props('query'), { keyword: '用户', groupId: 7, levelId: 3, status: 0, page: 8 });
    table.vm.$emit('search'); await flushPromises();
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ keyword: '用户', groupId: 7, levelId: 3, status: 0, page: 1 }));
    await table.props('context').handlers.recycled.run(); await flushPromises();
    table.vm.$emit('reset'); await flushPromises();
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ keyword: '', groupId: undefined, page: 1, recycled: 1 }));
    w.unmount();
  });
  it('真实公共分类点击通过原 groupId 请求，保留其他筛选且全部清空', async () => {
    const data: any = definition();
    data.page.search = [{ field: 'groupId', label: '组', type: 'select', options: [{ label: '真实会员组', value: 7 }] }];
    data.page.list = { category: { enabled: true, field: 'groupId' } };
    api.options.mockResolvedValueOnce(data);
    const w = mount(MemberPage, { global: { stubs: { PageWrapper: { template: '<main><slot/></main>' }, DataTableShell: { template: '<div />' }, ElSkeleton: true, ElAlert: true, ElButton: true, ElAvatar: true, ElTag: true, ElSwitch: true, ElInput: true, ElSelect: true, ElOption: true, ElFormItem: true, ElTable: true, ElTableColumn: true, ElPagination: true }, directives: { loading: () => {} } } });
    await flushPromises();
    const table = w.findComponent({ name: 'SchemaTablePage' }); expect(table.exists()).toBe(true);
    Object.assign(table.props('query'), { keyword: '用户', levelId: 3, status: 0, page: 8 });
    await w.findAll('button').find(b => b.text() === '真实会员组')!.trigger('click'); await flushPromises();
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ groupId: 7, keyword: '用户', levelId: 3, status: 0, page: 1 }));
    await w.findAll('button').find(b => b.text() === '全部')!.trigger('click'); await flushPromises();
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ groupId: undefined, status: 0, page: 1 })); w.unmount();
  });
  it('配置失败不展示旧页面，可重试且空列表正常', async () => {
    api.options.mockRejectedValueOnce(new Error('失败'));
    const w = render(); await flushPromises(); expect(w.text()).toContain('加载失败');
    expect(api.list).not.toHaveBeenCalled();
    await w.findAll('button').find(b => b.text() === '重试')!.trigger('click'); await flushPromises();
    expect(w.findComponent({ name: 'SchemaTablePage' }).props('rows')).toEqual([]); w.unmount();
  });
  it('缺失页面定义不能降级，列表失败清空并允许刷新重试', async () => {
    api.options.mockResolvedValueOnce({ groups: [], levels: [], tags: [] });
    const w = render(); await flushPromises(); expect(api.list).not.toHaveBeenCalled();
    api.list.mockRejectedValueOnce(new Error('请求失败'));
    await w.findAll('button').find(b => b.text() === '重试')!.trigger('click'); await flushPromises();
    expect(w.text()).toContain('列表加载失败');
    await w.findAll('button').find(b => b.text() === '重试')!.trigger('click'); await flushPromises();
    expect(api.list).toHaveBeenCalledTimes(2); w.unmount();
  });
});
