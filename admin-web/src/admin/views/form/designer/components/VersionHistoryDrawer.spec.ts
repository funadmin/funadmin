import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  rollbackSchema: vi.fn(),
  schemaVersions: vi.fn(),
  confirm: vi.fn(),
  success: vi.fn(),
  warning: vi.fn()
}));

vi.mock('@/api/development/business', () => ({
  businessDevelopmentApi: {
    rollbackSchema: mocks.rollbackSchema,
    schemaVersions: mocks.schemaVersions,
    schemaVersion: vi.fn(),
    schemaDiff: vi.fn()
  },
  isBusinessApiError: (value: unknown) => Boolean(
    value && typeof value === 'object' && 'data' in value
    && (value as { data?: { error?: { code?: string } } }).data?.error?.code
  )
}));
vi.mock('element-plus', () => ({
  ElMessageBox: { confirm: mocks.confirm },
  ElMessage: { success: mocks.success, warning: mocks.warning }
}));

import VersionHistoryDrawer from './VersionHistoryDrawer.vue';

const rolledBackVersion = {
  id: 8,
  form_id: 2,
  version: 8,
  schema_version: 2,
  schema_hash: 'next-hash',
  schema_document: { schemaVersion: 2 as const, key: 'orders', title: '订单', nodes: [] },
  origin: 'rollback' as const
};

function render() {
  return mount(VersionHistoryDrawer, {
    props: { modelValue: true, moduleId: 12, schemaHash: 'current-hash' },
    global: {
      directives: { loading: () => undefined },
      stubs: {
        'el-drawer': { template: '<div><slot /></div>' },
        'el-table': { template: '<div><slot /></div>' },
        'el-table-column': { template: '<div><slot :row="{ version: 7 }" /></div>' },
        'el-button': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
        'el-select': true,
        'el-option': true,
        'el-divider': true,
        'el-input': true
      }
    }
  });
}

describe('VersionHistoryDrawer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.confirm.mockResolvedValue(undefined);
    mocks.schemaVersions.mockResolvedValue({ list: [] });
  });

  it('回滚发送当前 Schema hash，并仅在成功时发出新不可变版本', async () => {
    mocks.rollbackSchema.mockResolvedValue(rolledBackVersion);
    const wrapper = render();

    await (wrapper.vm as unknown as { rollbackVersion: (version: number) => Promise<void> }).rollbackVersion(7);

    expect(mocks.rollbackSchema).toHaveBeenCalledWith(12, 7, 'current-hash');
    expect(wrapper.emitted('rollback')).toEqual([[rolledBackVersion]]);
    expect(mocks.success).toHaveBeenCalledWith('回滚版本已创建');
  });

  it('Schema 冲突提示刷新且不发出回滚成功事件', async () => {
    mocks.rollbackSchema.mockRejectedValue({
      code: 409,
      msg: 'Schema 已变化',
      data: { error: { code: 'FORM_SCHEMA_CONFLICT', requestId: 'req-1', retryable: true, details: {} } }
    });
    const wrapper = render();

    await (wrapper.vm as unknown as { rollbackVersion: (version: number) => Promise<void> }).rollbackVersion(7);
    await flushPromises();

    expect(mocks.warning).toHaveBeenCalledWith('Schema 已更新，请刷新后重试');
    expect(wrapper.emitted('rollback')).toBeUndefined();
    expect(mocks.success).not.toHaveBeenCalled();
  });
});
