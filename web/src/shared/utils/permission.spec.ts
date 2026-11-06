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
    expect(checkPermission([], 'system:user:edit')).toBe(false);
  });

  it('仅精确授权对应动作，查看权限不会扩展为修改权限', () => {
    expect(checkPermission(['system:user:list'], 'system:user:list')).toBe(true);
    expect(checkPermission(['system:user:list'], 'system:user:edit')).toBe(false);
    expect(checkPermission(['system:user:*'], 'system:user:edit')).toBe(false);
  });

  it.each(['*', '*:*:*'])('%s 允许所有已声明的操作', wildcard => {
    expect(checkPermission([wildcard], 'system:user:edit')).toBe(true);
    expect(checkPermission([wildcard], ['system:user:edit', 'system:user:delete'], 'all')).toBe(true);
  });

  it('数组支持任一和全部权限判断', () => {
    expect(checkPermission(['edit'], ['edit', 'delete'])).toBe(true);
    expect(checkPermission(['edit'], ['edit', 'delete'], 'all')).toBe(false);
    expect(checkPermission(['edit', 'delete'], ['edit', 'delete'], 'all')).toBe(true);
    expect(checkPermission([], ['edit', 'delete'])).toBe(false);
  });

  it('公共入口响应权限授予、撤销和账号权限替换', () => {
    const allowed = computed(() => hasPermission('system:user:edit'));
    expect(allowed.value).toBe(false);
    user.permissions.push('system:user:edit');
    expect(allowed.value).toBe(true);
    user.permissions = ['system:user:list'];
    expect(allowed.value).toBe(false);
    user.permissions = ['*'];
    expect(allowed.value).toBe(true);
    user.permissions = [];
    expect(allowed.value).toBe(false);
  });
});
