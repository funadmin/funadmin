import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';
import { ElMessage, ElSwitch, ElTable, ElTableColumn } from 'element-plus';
import UserPage from './index.vue';

const api = vi.hoisted(() => ({
  list: vi.fn(),
  toggleStatus: vi.fn(),
  remove: vi.fn()
}));
const user = reactive({ userInfo: { id: 1 }, permissions: [] as string[] });

vi.mock('@/api/system/user', () => ({ userApi: api }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));
vi.mock('./components/UserFormDialog.vue', () => ({ default: { render: () => null } }));

const wrappers: ReturnType<typeof mount>[] = [];

async function render(permissions: string[], isSuper = false) {
  user.permissions = permissions;
  api.list.mockResolvedValue({
    list: [{ id: isSuper ? 1 : 2, isSuper, username: 'operator', nickname: '操作员', status: 1 }],
    total: 1,
    page: 1,
    pageSize: 10
  });
  const wrapper = mount(UserPage, {
    global: {
      components: { ElTable, ElTableColumn, ElSwitch },
      stubs: {
        PageWrapper: { template: '<div><slot /></div>' },
        DataTableShell: {
          template: '<div><slot :column-keys="[\'status\']" /></div>'
        },
        ElPagination: true,
        ElInput: true,
        ElFormItem: true,
        ElOption: true,
        ElSelect: true,
        SearchForm: true,
        ElButton: true,
        ElTag: true
      },
      directives: { loading: {}, perm: {} }
    }
  });
  wrappers.push(wrapper);
  await flushPromises();
  await nextTick();
  return wrapper.getComponent(ElSwitch);
}

beforeEach(() => {
  vi.clearAllMocks();
  api.toggleStatus.mockResolvedValue(undefined);
});

afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount());
  ElMessage.closeAll();
});

describe('管理员状态操作权限', () => {
  it('只有查看或编辑权限时禁用开关，触发 change 也不发送状态请求', async () => {
    const control = await render(['system:user:list', 'system:user:edit']);
    expect(control.props('disabled')).toBe(true);
    control.vm.$emit('change', false);
    await flushPromises();
    expect(api.toggleStatus).not.toHaveBeenCalled();
    expect(control.props('modelValue')).toBe(true);
  });

  it.each(['system:user:status', '*', '*:*:*'])('%s 允许切换普通管理员状态', async permission => {
    const control = await render([permission]);
    expect(control.props('disabled')).toBe(false);
    control.vm.$emit('change', false);
    await flushPromises();
    expect(api.toggleStatus).toHaveBeenCalledExactlyOnceWith(2, 0);
    expect(control.props('modelValue')).toBe(false);
  });

  it('超级管理员本人也不能停用超级管理员账号', async () => {
    const control = await render(['*'], true);
    expect(control.props('disabled')).toBe(true);
    control.vm.$emit('change', false);
    await flushPromises();
    expect(api.toggleStatus).not.toHaveBeenCalled();
  });

  it('权限撤销后禁用已有开关，并停止发送状态请求', async () => {
    const control = await render(['system:user:status']);
    expect(control.props('disabled')).toBe(false);
    user.permissions = ['system:user:list'];
    await nextTick();
    expect(control.props('disabled')).toBe(true);
    control.vm.$emit('change', false);
    await flushPromises();
    expect(api.toggleStatus).not.toHaveBeenCalled();
  });
});
