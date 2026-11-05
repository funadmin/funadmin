import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerMocks = vi.hoisted(() => ({ onBeforeRouteLeave: vi.fn() }));
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return { ...actual, onBeforeRouteLeave: routerMocks.onBeforeRouteLeave };
});

const permissionMocks = vi.hoisted(() => ({ reset: vi.fn(), fetchMenus: vi.fn(), setMounted: vi.fn() }));
vi.mock('@/store/modules/permission', () => ({ usePermissionStore: () => permissionMocks }));

import { useDirtyGuard } from './useDirtyGuard';
import { useBusinessMenuRefresh } from './useBusinessMenuRefresh';
import { useLatestRequest } from './useLatestRequest';

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}

describe('业务开发 composables', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    permissionMocks.fetchMenus.mockResolvedValue([]);
  });

  it('useDirtyGuard 同时保护 beforeunload 和路由离开，并允许注入 confirm', async () => {
    const dirty = ref(true);
    const confirm = vi.fn().mockReturnValue(false);
    const component = defineComponent({ setup: () => useDirtyGuard(dirty, { confirm, message: '尚未保存' }), template: '<div />' });
    const wrapper = mount(component);
    const event = new Event('beforeunload', { cancelable: true }) as BeforeUnloadEvent;
    window.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    const guard = routerMocks.onBeforeRouteLeave.mock.calls[0][0];
    expect(guard()).toBe(false);
    expect(confirm).toHaveBeenCalledWith('尚未保存');
    dirty.value = false;
    expect(guard()).toBe(true);
    wrapper.unmount();
  });

  it('useBusinessMenuRefresh 重新获取菜单并挂载路由', async () => {
    const addRoute = vi.fn();
    permissionMocks.fetchMenus.mockResolvedValue([{ name: 'BusinessOrders' }]);
    const { refreshBusinessMenu } = useBusinessMenuRefresh({ addRoute } as never);
    await refreshBusinessMenu();
    expect(permissionMocks.reset).toHaveBeenCalledOnce();
    expect(addRoute).toHaveBeenCalledWith({ name: 'BusinessOrders' });
  });

  it('useLatestRequest 防止旧响应覆盖新请求', async () => {
    const first = deferred<string>();
    const second = deferred<string>();
    let call = 0;
    const { data, loading, execute } = useLatestRequest(() => (++call === 1 ? first.promise : second.promise));
    const firstRun = execute();
    const secondRun = execute();
    second.resolve('new');
    await secondRun;
    first.resolve('old');
    await firstRun;
    await nextTick();
    expect(data.value).toBe('new');
    expect(loading.value).toBe(false);
  });
});
