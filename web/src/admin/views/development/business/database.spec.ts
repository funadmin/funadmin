import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DatabasePage from './database.vue';
import databaseSource from './database.vue?raw';

vi.mock('./visual.vue', () => ({
  default: defineComponent({
    name: 'BusinessVisual',
    props: ['initialMode'],
    template: '<main :data-mode="initialMode">创建业务</main>'
  })
}));

describe('数据库旧路由兼容包装', () => {
  it('直接复用统一表单并固定已有表初始模式，不依赖创建路由权限', () => {
    const wrapper = mount(DatabasePage);
    expect(wrapper.get('main').attributes('data-mode')).toBe('adopted');
    expect(wrapper.text()).toBe('创建业务');
    wrapper.unmount();
  });

  it('不复制表单、API、权限或导航逻辑，原路由 query 留在当前上下文', () => {
    expect(databaseSource).toContain("import BusinessVisual from './visual.vue'");
    expect(databaseSource).not.toMatch(/businessDevelopmentApi|useRouter|useRoute|el-form|router\.(push|replace)/);
  });
});
