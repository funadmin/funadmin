import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { reactive } from 'vue';
import ApplicationPage from './index.vue';

const api = vi.hoisted(() => ({ list: vi.fn(), create: vi.fn(), update: vi.fn(), saveDatabase: vi.fn(), saveAssignments: vi.fn(), saveDomains: vi.fn() }));
const user = reactive({ permissions: [] as string[] });
vi.mock('@/api/identity/applications', async importOriginal => ({ ...await importOriginal<any>(), applicationApi: api }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }));
const prefix = 'identity.enterpriseapplication:';
const creationPermissions = [prefix + 'save', prefix + 'savedatabase', prefix + 'saveassignments'];
beforeEach(() => { vi.clearAllMocks(); user.permissions = []; api.list.mockResolvedValue({ list: [] }); api.create.mockResolvedValue({ id: 8 }); api.saveDatabase.mockResolvedValue(undefined); api.saveAssignments.mockResolvedValue(undefined); });
async function render() { const wrapper = shallowMount(ApplicationPage, { global: { stubs: Object.fromEntries(['PageWrapper', 'ElInput', 'ElButton', 'ElTable', 'ElTableColumn', 'ElTag', 'ElEmpty', 'ElDrawer', 'ElTabs', 'ElTabPane', 'ElForm', 'ElFormItem', 'ElRadioGroup', 'ElRadioButton', 'ElSelect', 'ElOption', 'ElInputNumber', 'ElColorPicker', 'ElSwitch', 'ElAlert', 'ElCard'].map(name => [name, true])), directives: { perm: {}, loading: {} } } }); await flushPromises(); return wrapper; }
function actions(wrapper: ReturnType<typeof shallowMount>) { return (wrapper.vm as any).$.setupState as { saveSettings(): Promise<void>; domain: { identityCallback: string }; }; }
describe('应用设置组合权限', () => {
  it('缺少子操作权限时不创建半成品', async () => { user.permissions = [prefix + 'save']; const wrapper = await render(); await actions(wrapper).saveSettings(); expect(api.create).not.toHaveBeenCalled(); expect(api.saveDatabase).not.toHaveBeenCalled(); wrapper.unmount(); });
  it('创建权限无需额外更新权限，完整保存应用和子设置', async () => { user.permissions = creationPermissions; const wrapper = await render(); await actions(wrapper).saveSettings(); expect(api.create).toHaveBeenCalledOnce(); expect(api.saveDatabase).toHaveBeenCalledExactlyOnceWith(8, expect.any(Object)); expect(api.saveAssignments).toHaveBeenCalledExactlyOnceWith(8, expect.any(Array)); expect(api.saveDomains).not.toHaveBeenCalled(); wrapper.unmount(); });
  it('创建异步返回前撤销授权，不再写入后续设置', async () => { let resolveCreate!: (value: { id: number }) => void; api.create.mockImplementationOnce(() => new Promise(resolve => { resolveCreate = resolve; })); user.permissions = creationPermissions; const wrapper = await render(); const pending = actions(wrapper).saveSettings(); user.permissions = []; resolveCreate({ id: 8 }); await pending; expect(api.saveDatabase).not.toHaveBeenCalled(); expect(api.saveAssignments).not.toHaveBeenCalled(); wrapper.unmount(); });
  it('有域名修改时必须额外具备保存域名权限', async () => { user.permissions = creationPermissions; const wrapper = await render(); actions(wrapper).domain.identityCallback = 'https://example.com/callback'; await actions(wrapper).saveSettings(); expect(api.create).not.toHaveBeenCalled(); wrapper.unmount(); });
});
