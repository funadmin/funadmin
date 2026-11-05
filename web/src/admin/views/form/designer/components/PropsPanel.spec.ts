import { defineComponent } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';
import PropsPanel from './PropsPanel.vue';
import { CONTROL_REGISTRY, createField } from '../../registry';
import type { FormFieldDef } from '@/api/form';
import zhCN from '@/locales/zh-CN';

const dictTypeApi = vi.hoisted(() => ({ list: vi.fn() }));
vi.mock('@/api/system/dict', () => ({ dictTypeApi, dictApi: { options: vi.fn(), batch: vi.fn() }, dictItemApi: {} }));
vi.mock('@/api/development/business', () => ({ businessDevelopmentApi: { databaseTables: vi.fn(), databaseTableSchema: vi.fn() } }));

const selectStub = defineComponent({
  props: ['modelValue'],
  emits: ['update:modelValue', 'visible-change'],
  template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'visible-change\', true)"><slot /></select>'
});
const optionStub = defineComponent({ props: ['label', 'value'], template: '<option :value="value">{{ label }}</option>' });

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const render = (field: FormFieldDef) => mount(PropsPanel, {
  props: { moduleId: 1, field, sourceType: 'created', controls: CONTROL_REGISTRY },
  global: { plugins: [ElementPlus, i18n], stubs: { ElSelect: selectStub, ElOption: optionStub } }
});

describe('字典控件数据源配置', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({
      list: [
        { id: 1, code: 'order_status', name: '订单状态', status: 1 },
        { id: 2, code: 'pay_channel', name: '支付渠道', status: 1 }
      ],
      total: 2
    });
  });

  it('字典控件显示字典选择器并加载字典分类，选择后收敛写入 kind 与 dictionary', async () => {
    const field = createField('dictionary', 1);
    expect(field.options_source).toMatchObject({ mode: 'dictionary', dictionary: '' });
    const wrapper = render(field);
    await flushPromises();
    expect(dictTypeApi.list).toHaveBeenCalled();
    const select = wrapper.get('[data-testid="dictionary-code-select"]');
    await select.setValue('order_status');
    const patch = wrapper.emitted('update')?.at(-1)?.[0] as Record<string, unknown>;
    const source = patch.options_source as Record<string, unknown>;
    expect(source).toMatchObject({ kind: 'dictionary', dictionary: 'order_status' });
    expect(source).not.toHaveProperty('mode');
    wrapper.unmount();
  });

  it('回显已保存的字典编码（兼容 kind 与旧 mode 键）', async () => {
    const byKind = render({ ...createField('dictionary', 1), options_source: { kind: 'dictionary', dictionary: 'pay_channel', options: [] } });
    await flushPromises();
    expect(byKind.get('[data-testid="dictionary-code-select"]').element).toHaveProperty('value', 'pay_channel');
    byKind.unmount();
    const legacy = render({ ...createField('dictionary', 1), options_source: { mode: 'dictionary', dictionary: 'order_status', options: [] } });
    await flushPromises();
    expect(legacy.get('[data-testid="dictionary-code-select"]').element).toHaveProperty('value', 'order_status');
    legacy.unmount();
  });

  it('非字典控件不渲染字典选择器', async () => {
    const wrapper = render(createField('input', 1));
    await flushPromises();
    expect(wrapper.find('[data-testid="dictionary-code-select"]').exists()).toBe(false);
    expect(dictTypeApi.list).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});
