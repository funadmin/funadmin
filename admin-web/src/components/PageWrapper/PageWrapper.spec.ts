import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PageWrapper from './index.vue';

describe('PageWrapper', () => {
  it('不渲染页面标题和副标题区域', () => {
    const wrapper = mount(PageWrapper, {
      props: { title: '我的业务', subtitle: '统一查看、设计、发布和生成业务模块' },
      slots: { default: '<div>列表内容</div>' }
    });

    expect(wrapper.text()).not.toContain('我的业务');
    expect(wrapper.text()).not.toContain('统一查看、设计、发布和生成业务模块');
    expect(wrapper.find('header').exists()).toBe(false);
    expect(wrapper.text()).toContain('列表内容');
  });

  it('存在顶部操作区时只渲染操作内容', () => {
    const wrapper = mount(PageWrapper, {
      props: { title: '角色管理', subtitle: '维护角色层级、基本信息与授权' },
      slots: { extra: '<button>添加顶级角色</button>', default: '<div>角色列表</div>' }
    });

    expect(wrapper.text()).not.toContain('角色管理');
    expect(wrapper.text()).not.toContain('维护角色层级、基本信息与授权');
    expect(wrapper.get('header').text()).toBe('添加顶级角色');
  });

  it('保留显式自定义头部', () => {
    const wrapper = mount(PageWrapper, {
      slots: { header: '<div>业务状态摘要</div>', default: '<div>内容</div>' }
    });

    expect(wrapper.get('header').text()).toContain('业务状态摘要');
  });
});
