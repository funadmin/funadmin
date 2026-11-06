import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import Drawer from './VersionHistoryDrawer.vue';
const mocks = vi.hoisted(() => ({ permitted: true, rollback: vi.fn(), confirm: vi.fn() }));
vi.mock('@/utils/permission', () => ({ hasPermission: () => mocks.permitted }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (_key: string, ...args: unknown[]) => args.at(-1) }) }));
vi.mock('element-plus', () => ({ ElMessageBox: { confirm: mocks.confirm }, ElMessage: { success: vi.fn(), warning: vi.fn() } }));
vi.mock('@/api/development/business', () => ({ businessDevelopmentApi: { rollbackSchema: mocks.rollback, schemaVersions: vi.fn().mockResolvedValue({ list: [] }) }, isBusinessApiError: () => false }));
describe('版本回滚权限', () => {
  it('确认期间撤销权限后不得回滚服务端版本', async () => {
    mocks.permitted = true; mocks.rollback.mockReset();
    let finish!: () => void;
    mocks.confirm.mockImplementation(() => new Promise<void>(resolve => { finish = resolve; }));
    const wrapper = mount(Drawer, { props: { modelValue: true, moduleId: 7, schemaHash: 'hash' }, global: { stubs: { ElDrawer: { template: '<div><slot /></div>' }, ElTable: true, ElTableColumn: true, ElSelect: true, ElDivider: true, ElInput: true } } });
    const request = (wrapper.vm as any).rollbackVersion(2);
    await flushPromises(); mocks.permitted = false; finish(); await request;
    expect(mocks.rollback).not.toHaveBeenCalled(); wrapper.unmount();
  });
});
