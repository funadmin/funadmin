import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, reactive } from 'vue';
import { checkPermission, hasPermission } from './permission';

const user = reactive({ permissions: [] as string[] });
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));

beforeEach(() => { user.permissions = []; });

describe('公共操作权限判断', () => {
  it('可选权限未声明时允许，已声明的权限默认拒绝', () => {
    expect(checkPermission([], undefined)).toBe(true);
    expect(checkPermission([], null)).toBe(true);
    expect(checkPermission([], '')).toBe(true);
    expect(checkPermission([], 'authorization:admin:update')).toBe(false);
  });

  it('仅精确授权对应动作，查看权限不会扩展为修改权限', () => {
    expect(checkPermission(['authorization:admin:index', 'authorization:admin:detail'], ['authorization:admin:index', 'authorization:admin:detail'])).toBe(true);
    expect(checkPermission(['authorization:admin:index', 'authorization:admin:detail'], 'authorization:admin:update')).toBe(false);
    expect(checkPermission(['system:user:*'], 'authorization:admin:update')).toBe(false);
  });

  it('* 允许所有已声明的操作', () => {
    expect(checkPermission(['*'], 'authorization:admin:update')).toBe(true);
    expect(checkPermission(['*'], ['authorization:admin:update', 'authorization:admin:delete'], 'all')).toBe(true);
  });

  it('*:*:* 不是超级权限，只按字面权限码精确匹配', () => {
    expect(checkPermission(['*:*:*'], 'authorization:admin:update')).toBe(false);
  });

  it('数组支持任一和全部权限判断', () => {
    expect(checkPermission(['edit'], ['edit', 'delete'])).toBe(true);
    expect(checkPermission(['edit'], ['edit', 'delete'], 'all')).toBe(false);
    expect(checkPermission(['edit', 'delete'], ['edit', 'delete'], 'all')).toBe(true);
    expect(checkPermission([], ['edit', 'delete'])).toBe(false);
  });

  it('公共入口响应权限授予、撤销和账号权限替换', () => {
    const allowed = computed(() => hasPermission('authorization:admin:update'));
    expect(allowed.value).toBe(false);
    user.permissions.push('authorization:admin:update');
    expect(allowed.value).toBe(true);
    user.permissions = ['authorization:admin:index', 'authorization:admin:detail'];
    expect(allowed.value).toBe(false);
    user.permissions = ['*'];
    expect(allowed.value).toBe(true);
    user.permissions = [];
    expect(allowed.value).toBe(false);
  });
});
