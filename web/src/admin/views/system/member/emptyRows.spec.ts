import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import ElementPlus from 'element-plus';
import MemberPage from './index.vue';
const api = vi.hoisted(() => ({ options: vi.fn(), list: vi.fn(), exportRows: vi.fn() }));
vi.mock('@/api/system/member', () => ({ memberApi: api }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: ['*'] }) }));
vi.mock('./components/MemberFormDialog.vue', () => ({ default: { template: '<div />' } }));

describe('会员列表的会员列', () => {
  it('会员缺少用户名时仍能渲染头像，不让整个页面报错', async () => {
    api.options.mockResolvedValue({ groups: [], levels: [], tags: [], page: { pageSchemaVersion: 1, key: 'system_member', search: [], toolbar: [], rowActions: [],
      columns: [{ key: 'member', prop: 'username', label: '会员', slot: 'member' }], pagination: { pageSize: 20, pageSizes: [20] } } });
    api.list.mockResolvedValue({ list: [{ id: 1, mobile: '13800000000' }, { id: 2, username: 'zhang' }], total: 2 });
    const errors: unknown[] = [];
    const wrapper = mount(MemberPage, { attachTo: document.body, global: { plugins: [ElementPlus], config: { errorHandler: (error) => { errors.push(error); } }, stubs: { PageWrapper: { template: '<main><slot /></main>' } } } });
    await flushPromises(); await flushPromises();
    expect(errors).toEqual([]);
    const text = wrapper.text();
    expect(text).toContain('13800000000');
    expect(text).toContain('Z');
    wrapper.unmount();
  });
});
