import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, reactive } from 'vue';
import { permission } from './permission';

const user = reactive({ permissions: [] as string[] });
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));
const wrappers: ReturnType<typeof mount>[] = [];

function render(required: string | string[] = 'authorization:admin:update', mode = '') {
  const component = defineComponent({
    data: () => ({ required, mode }),
    template: '<div><button v-perm:[mode]="required" style="display: inline-flex">修改</button></div>'
  });
  const wrapper = mount(component, { attachTo: document.body, global: { directives: { perm: permission } } });
  wrappers.push(wrapper);
  return wrapper;
}

beforeEach(() => { user.permissions = []; });
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); });

describe('公共按钮权限指令', () => {
  it('无权限时隐藏按钮，后续授予时恢复按钮及其原有显示方式', async () => {
    const wrapper = render();
    expect(wrapper.find('button').exists() && wrapper.get('button').isVisible()).toBe(false);
    user.permissions = ['authorization:admin:update'];
    await nextTick();
    expect(wrapper.get('button').isVisible()).toBe(true);
    expect(wrapper.get('button').element.style.display).toBe('inline-flex');
  });

  it('撤销权限时不依赖父组件重新渲染即可隐藏按钮', async () => {
    user.permissions = ['authorization:admin:update'];
    const wrapper = render();
    expect(wrapper.get('button').isVisible()).toBe(true);
    user.permissions = [];
    await nextTick();
    expect(wrapper.find('button').exists() && wrapper.get('button').isVisible()).toBe(false);
  });

  it('更新权限条件和 all 模式后采用新的授权要求', async () => {
    user.permissions = ['edit'];
    const wrapper = render(['edit', 'delete']);
    expect(wrapper.get('button').isVisible()).toBe(true);
    await wrapper.setData({ mode: 'all' });
    expect(wrapper.find('button').exists() && wrapper.get('button').isVisible()).toBe(false);
    user.permissions.push('delete');
    await nextTick();
    expect(wrapper.get('button').isVisible()).toBe(true);
    await wrapper.setData({ required: 'publish' });
    expect(wrapper.find('button').exists() && wrapper.get('button').isVisible()).toBe(false);
  });

  it('卸载后释放监听，不再更新已卸载元素', async () => {
    user.permissions = ['authorization:admin:update'];
    const wrapper = render();
    const button = wrapper.get('button').element;
    wrapper.unmount();
    user.permissions = [];
    await nextTick();
    expect(button.style.display).toBe('inline-flex');
  });
});
