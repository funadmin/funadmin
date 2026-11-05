import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import GenerationDetailDrawer from './GenerationDetailDrawer.vue';
import type { BusinessGeneration } from '@/api/development/business';

const mocks = vi.hoisted(() => ({ recover: vi.fn() }));
vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return { ...actual, businessDevelopmentApi: { ...actual.businessDevelopmentApi, recoverGeneration: mocks.recover } };
});

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const stubs = {
  ElDrawer: defineComponent({ props: ['modelValue', 'title'], emits: ['update:modelValue'], template: '<aside><h2>{{ title }}</h2><slot /></aside>' }),
  ElButton: defineComponent({ attrs: false, emits: ['click'], template: '<button v-bind="$attrs" type="button" @click="$emit(\'click\')"><slot /></button>' }),
  ElTag: defineComponent({ template: '<span><slot /></span>' }),
  GenerationPlanView: defineComponent({ template: '<div data-plan>plan</div>' })
};
const generation: BusinessGeneration = {
  id: 9,
  businessModuleId: 3,
  status: 'failed',
  recoveryStatus: 'recovery_required',
  planDigest: 'a'.repeat(64),
  definitionHash: 'b'.repeat(64),
  schemaHash: 'c'.repeat(64),
  routePath: '/generated/orders',
  availableActions: ['recover'],
  plan: { blocked: false, files: [{ path: 'app/Order.php', status: 'update' }] },
  recovery: { state: 'recovery_required', transactionId: 'tx-safe' },
  result: { state: 'failed' },
  error: { code: 'GENERATION_RECOVERY_REQUIRED', requestId: 'request-safe', retryable: false, details: {} }
};

function render(value = generation) {
  return mount(GenerationDetailDrawer, {
    props: { modelValue: true, generation: value },
    global: { plugins: [i18n], stubs }
  });
}

describe('GenerationDetailDrawer', () => {
  beforeEach(() => { mocks.recover.mockReset().mockResolvedValue({ state: 'rolled_back' }); });

  it('按摘要、状态、hash、文件、恢复、结果和安全错误分区', () => {
    const wrapper = render();
    for (const section of ['summary', 'status', 'hashes', 'files', 'recovery', 'result', 'safe-error']) {
      expect(wrapper.find(`[data-section="${section}"]`).exists(), section).toBe(true);
    }
    expect(wrapper.text()).toContain('GENERATION_RECOVERY_REQUIRED');
    expect(wrapper.text()).not.toContain('/Users/');
  });

  it('path 和 hash 可通过键盘复制', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    const wrapper = render();
    const path = wrapper.get('[data-copy="/generated/orders"]');
    expect(path.attributes('tabindex')).toBe('0');
    await path.trigger('keydown', { key: 'Enter' });
    const hash = wrapper.get(`[data-copy="${'a'.repeat(64)}"]`);
    await hash.trigger('keydown', { key: ' ' });
    expect(writeText).toHaveBeenNthCalledWith(1, '/generated/orders');
    expect(writeText).toHaveBeenNthCalledWith(2, 'a'.repeat(64));
  });

  it('仅 availableActions 含 recover 时展示并发送 recover', async () => {
    const wrapper = render();
    await wrapper.get('[data-action="recover"]').trigger('click');
    expect(mocks.recover).toHaveBeenCalledWith(9, 'recovery_required');

    const unavailable = render({ ...generation, availableActions: [] });
    expect(unavailable.find('[data-action="recover"]').exists()).toBe(false);
  });
});
