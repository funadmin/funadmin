import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import BusinessPageState from './BusinessPageState.vue';

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const stubs = {
  ElSkeleton: defineComponent({ template: '<div class="skeleton">loading</div>' }),
  ElEmpty: defineComponent({ props: ['description'], template: '<div class="empty">{{ description }}</div>' }),
  ElButton: defineComponent({ emits: ['click'], template: '<button type="button" @click="$emit(\'click\')"><slot /></button>' }),
  ElResult: defineComponent({ props: ['title'], template: '<div class="result"><span>{{ title }}</span><slot name="extra" /></div>' })
};

function render(props: Record<string, unknown> = {}) {
  return mount(BusinessPageState, {
    props,
    slots: { default: '<div class="content">业务内容</div>' },
    global: { plugins: [i18n], stubs }
  });
}

describe('BusinessPageState', () => {
  it('loading 时标记 aria-busy 并播报加载状态', () => {
    const wrapper = render({ loading: true });
    expect(wrapper.attributes('aria-busy')).toBe('true');
    expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true);
    expect(wrapper.find('.skeleton').exists()).toBe(true);
    expect(wrapper.find('.content').exists()).toBe(false);
  });

  it('error 时使用 alert 并允许 retry', async () => {
    const retry = vi.fn();
    const wrapper = render({ error: '连接失败', onRetry: retry });
    expect(wrapper.find('[role="alert"]').text()).toContain('连接失败');
    await wrapper.get('button').trigger('click');
    expect(retry).toHaveBeenCalledOnce();
  });

  it('empty 时显示空态而非内容', () => {
    const wrapper = render({ empty: true });
    expect(wrapper.find('.empty').exists()).toBe(true);
    expect(wrapper.find('.content').exists()).toBe(false);
  });

  it('正常时渲染 content slot', () => {
    const wrapper = render();
    expect(wrapper.find('.content').text()).toBe('业务内容');
  });
});
