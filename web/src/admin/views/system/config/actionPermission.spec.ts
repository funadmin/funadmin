import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { reactive } from 'vue';
import { ElMessageBox } from 'element-plus';
import ConfigPage from './index.vue';

const api = vi.hoisted(() => ({ options: vi.fn(), list: vi.fn(), remove: vi.fn() }));
const user = reactive({ permissions: [] as string[] });
vi.mock('@/api/system/config', () => ({ configApi: api }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));
vi.mock('./components/ConfigFormDialog.vue', () => ({ default: { render: () => null } }));
vi.mock('./components/ConfigValueDialog.vue', () => ({ default: { render: () => null } }));
vi.mock('./components/ConfigGroupDialog.vue', () => ({ default: { render: () => null } }));
beforeEach(() => { vi.restoreAllMocks(); vi.clearAllMocks(); user.permissions = []; api.options.mockResolvedValue({ groups: [], types: [], verifies: [] }); api.list.mockResolvedValue({ list: [], total: 0 }); api.remove.mockResolvedValue(undefined); });
async function render() { const wrapper = shallowMount(ConfigPage, { global: { stubs: Object.fromEntries(['PageWrapper', 'SearchForm', 'DataTableShell', 'ElInput', 'ElFormItem', 'ElOption', 'ElSelect', 'ElButton', 'ElTableColumn', 'ElTag', 'ElSwitch', 'ElTable', 'ElPagination', 'ElDrawer'].map(name => [name, true])), directives: { perm: {}, loading: {} } } }); await flushPromises(); return wrapper; }
function actions(wrapper: ReturnType<typeof shallowMount>) { return (wrapper.vm as any).$.setupState as { removeOne(row: { id: number; code: string }): Promise<void> }; }
describe('配置删除异步权限', () => {
  it('无删除权限不显示确认且不请求', async () => { const confirm = vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as any); const wrapper = await render(); await actions(wrapper).removeOne({ id: 2, code: 'site' }); expect(confirm).not.toHaveBeenCalled(); expect(api.remove).not.toHaveBeenCalled(); wrapper.unmount(); });
  it('确认期间权限撤销后不请求', async () => { let approve!: () => void; vi.spyOn(ElMessageBox, 'confirm').mockImplementation(() => new Promise(resolve => { approve = () => resolve('confirm' as any); })); user.permissions = ['system:config:delete']; const wrapper = await render(); const pending = actions(wrapper).removeOne({ id: 2, code: 'site' }); user.permissions = []; approve(); await pending; expect(api.remove).not.toHaveBeenCalled(); wrapper.unmount(); });
  it('有删除权限确认后发送对应请求', async () => { vi.spyOn(ElMessageBox, 'confirm').mockResolvedValue('confirm' as any); user.permissions = ['system:config:delete']; const wrapper = await render(); await actions(wrapper).removeOne({ id: 2, code: 'site' }); expect(api.remove).toHaveBeenCalledExactlyOnceWith([2]); wrapper.unmount(); });
});
