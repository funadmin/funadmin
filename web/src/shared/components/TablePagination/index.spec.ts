import { mount } from '@vue/test-utils';
import { ElPagination, ElSelect } from 'element-plus';
import { describe, expect, it, vi } from 'vitest';
import TablePagination from './index.vue';

describe('公共表格分页', () => {
  it('下一页同时更新页码并且只发出一次查询事件', async () => {
    const update = vi.fn();
    const change = vi.fn();
    const wrapper = mount(TablePagination, {
      global: { components: { ElPagination } },
      props: { total: 135, currentPage: 1, pageSize: 10, 'onUpdate:currentPage': (page: number) => { update(page); void wrapper.setProps({ currentPage: page }); }, 'onUpdate:pageSize': () => {}, onChange: change }
    });
    await wrapper.get('button.btn-next').trigger('click');
    expect(update).toHaveBeenCalledExactlyOnceWith(2);
    expect(change).toHaveBeenCalledExactlyOnceWith(2, 10);
    wrapper.unmount();
  });

  it('切换每页条数保留双向绑定并只触发一次查询', async () => {
    const update = vi.fn();
    const change = vi.fn();
    const wrapper = mount(TablePagination, {
      global: { components: { ElPagination } },
      props: { total: 135, currentPage: 1, pageSize: 10, 'onUpdate:currentPage': () => {}, 'onUpdate:pageSize': (size: number) => { update(size); void wrapper.setProps({ pageSize: size }); }, onChange: change }
    });
    wrapper.getComponent(ElSelect).vm.$emit('change', 20);
    await wrapper.vm.$nextTick();
    expect(update).toHaveBeenCalledExactlyOnceWith(20);
    expect(change).toHaveBeenCalledExactlyOnceWith(1, 20);
    wrapper.unmount();
  });

  it('固定条数的窄栏分页不展示条数选择，禁用时不能翻页', async () => {
    const change = vi.fn();
    const wrapper = mount(TablePagination, {
      global: { components: { ElPagination } },
      props: { total: 135, currentPage: 1, pageSize: 12, layout: 'total, prev, pager, next', disabled: true, 'onUpdate:currentPage': () => {}, onChange: change }
    });
    expect(wrapper.findComponent(ElSelect).exists()).toBe(false);
    await wrapper.get('button.btn-next').trigger('click');
    expect(change).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});
