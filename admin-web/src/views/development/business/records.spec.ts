import { defineComponent, h, inject, nextTick, provide, reactive, ref, type Ref } from 'vue';
import { enableAutoUnmount, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import Records from './records.vue';
import GenerationDetailDrawer from './components/GenerationDetailDrawer.vue';

const route = reactive<{ query: Record<string, string | undefined> }>({ query: {} });
const mocks = vi.hoisted(() => ({
  generations: vi.fn(),
  generation: vi.fn(),
  modules: vi.fn(),
  recoverGeneration: vi.fn(),
  replace: vi.fn(),
  push: vi.fn(),
  confirm: vi.fn(),
  success: vi.fn()
}));

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ replace: mocks.replace, push: mocks.push })
}));
vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return {
    ...actual,
    businessDevelopmentApi: {
      ...actual.businessDevelopmentApi,
      generations: mocks.generations,
      generation: mocks.generation,
      modules: mocks.modules,
      recoverGeneration: mocks.recoverGeneration
    }
  };
});
vi.mock('element-plus', () => ({
  ElMessageBox: { confirm: mocks.confirm },
  ElMessage: { success: mocks.success }
}));

const sampleGeneration = {
  id: 9,
  businessModuleId: 3,
  generationMode: 'managed',
  status: 'failed',
  recoveryStatus: 'recovery_required',
  planDigest: 'digest',
  availableActions: ['recover'] as const,
  error: { code: 'WRITE_FAILED', requestId: 'req-1', retryable: false, details: {} },
  updatedAt: '2026-09-10 12:00:00'
};

const tableRowKey = Symbol('tableRow');
const ElTable = defineComponent({
  props: ['data'],
  setup(props, { slots }) {
    const row = ref();
    provide(tableRowKey, row);
    return () => {
      row.value = props.data[0];
      return h('div', { class: 'record-row' }, [slots.default?.(), props.data.map((item: typeof sampleGeneration) => h('span', `${item.id} ${item.error?.code || ''} ${item.updatedAt || ''}`))]);
    };
  }
});
const ElTableColumn = defineComponent({
  props: ['label', 'prop'],
  setup(props, { slots }) {
    const row = inject<Ref<typeof sampleGeneration | undefined>>(tableRowKey, ref());
    return () => h('div', { class: 'column' }, [h('strong', props.label), slots.default?.({ row: row.value })]);
  }
});
const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const stubs = {
  PageWrapper: defineComponent({ props: ['title', 'subtitle'], template: '<main><h1>{{ title }}</h1><p>{{ subtitle }}</p><slot /></main>' }),
  DataTableShell: defineComponent({ props: ['loading'], emits: ['refresh'], template: '<section><slot name="search" /><slot name="toolbar-left" /><slot :size="\'default\'" :stripe="false" :border="false" :header-cell-style="{}" /></section>' }),
  SearchForm: defineComponent({ props: ['model', 'loading'], emits: ['search', 'reset'], template: '<form><slot /><button data-search type="button" @click="$emit(\'search\')">搜索</button><button data-reset type="button" @click="$emit(\'reset\')">重置</button></form>' }),
  ElFormItem: defineComponent({ props: ['label', 'error'], template: '<label>{{ label }}<slot /><span v-if="error" role="alert">{{ error }}</span></label>' }),
  ElSelect: defineComponent({
    props: ['modelValue', 'placeholder', 'loading'],
    emits: ['update:modelValue'],
    template: '<select :aria-label="placeholder" :value="modelValue" @change="$emit(\'update:modelValue\', Number($event.target.value) || $event.target.value)"><slot /></select>'
  }),
  ElOption: defineComponent({ props: ['label', 'value'], template: '<option :value="value">{{ label }}</option>' }),
  ElButton: defineComponent({ props: ['loading', 'disabled'], emits: ['click'], template: '<button type="button" :aria-busy="loading ? \'true\' : undefined" :disabled="disabled || loading" v-bind="$attrs" @click="$emit(\'click\')"><slot /></button>' }),
  ElTable,
  ElTableColumn,
  ElPagination: defineComponent({ props: ['currentPage', 'pageSize', 'total'], emits: ['update:currentPage', 'update:pageSize', 'change'], template: '<nav aria-label="分页">{{ total }}</nav>' }),
  ElDrawer: defineComponent({ props: ['modelValue', 'title'], emits: ['update:modelValue'], template: '<aside v-if="modelValue"><h2>{{ title }}</h2><slot /></aside>' }),
  ElSkeleton: true,
  ElResult: true,
  ElEmpty: true,
  ElTag: defineComponent({ template: '<span><slot /></span>' })
};

enableAutoUnmount(afterEach);

const render = async () => {
  const wrapper = mount(Records, { global: { plugins: [i18n], stubs } });
  await flushPromises();
  return wrapper;
};

const findComponentByName = (wrapper: VueWrapper, name: string) => wrapper.findAllComponents({ name })[0];

describe('BusinessRecords', () => {
  beforeEach(() => {
    route.query = {};
    vi.clearAllMocks();
    mocks.generations.mockResolvedValue({ list: [sampleGeneration], total: 1, page: 1, pageSize: 20 });
    mocks.generation.mockResolvedValue(sampleGeneration);
    mocks.modules.mockResolvedValue({ list: [{ id: 3, name: '订单管理', code: 'orders', origin: 'visual', lifecycle_status: 'published' }], total: 1, page: 1, pageSize: 100 });
    mocks.recoverGeneration.mockResolvedValue({ state: 'rolled_back' });
    mocks.confirm.mockResolvedValue(undefined);
  });

  it('监听 route moduleId，重置分页并仅采用最新请求结果', async () => {
    route.query = { moduleId: '3', page: '4', pageSize: '50', status: 'failed' };
    const wrapper = await render();
    expect(mocks.generations).toHaveBeenLastCalledWith({ page: 4, pageSize: 50, moduleId: 3, status: 'failed' });

    route.query = { ...route.query, moduleId: '7' };
    await nextTick();
    await flushPromises();
    expect(mocks.generations).toHaveBeenLastCalledWith({ page: 1, pageSize: 50, moduleId: 7, status: 'failed' });
    expect(wrapper.text()).toContain('生成记录');
  });

  it('筛选与 URL 双向同步，非法 moduleId 显示校验错误且不请求列表', async () => {
    route.query = { moduleId: 'abc', status: 'failed' };
    const wrapper = await render();
    expect(wrapper.get('[role="alert"]').text()).toContain('模块');
    expect(mocks.generations).not.toHaveBeenCalled();

    route.query = {};
    await nextTick();
    await flushPromises();
    await wrapper.get('select[aria-label="选择业务模块"]').setValue('3');
    await wrapper.get('[data-search]').trigger('click');
    await flushPromises();
    expect(mocks.replace).toHaveBeenLastCalledWith(expect.objectContaining({ query: expect.objectContaining({ moduleId: '3', page: '1' }) }));
  });

  it('全局模式通过 modules API 选择模块，并展示模块名称标识与返回入口', async () => {
    const wrapper = await render();
    expect(mocks.modules).toHaveBeenCalledWith({ page: 1, pageSize: 100 });
    expect(wrapper.text()).toContain('订单管理');
    expect(wrapper.text()).toContain('orders');
    await wrapper.get('[data-action="back-to-mine"]').trigger('click');
    expect(mocks.push).toHaveBeenCalledWith('/development/business/mine');
  });

  it('使用统一页面状态、状态标签和结构化详情 drawer，并显示失败摘要与更新时间', async () => {
    let resolveDetail!: (value: typeof sampleGeneration) => void;
    mocks.generation.mockImplementation(() => new Promise((resolve) => { resolveDetail = resolve; }));
    const wrapper = await render();
    expect(findComponentByName(wrapper, 'BusinessPageState')).toBeTruthy();
    expect(findComponentByName(wrapper, 'GenerationStatusTag')).toBeTruthy();
    expect(wrapper.text()).toContain('WRITE_FAILED');
    expect(wrapper.text()).toContain('2026-09-10 12:00:00');

    await wrapper.get('[data-action="detail-9"]').trigger('click');
    expect(wrapper.get('[data-action="detail-9"]').attributes('aria-busy')).toBe('true');
    resolveDetail(sampleGeneration);
    await flushPromises();
    const drawer = wrapper.findComponent(GenerationDetailDrawer);
    expect(drawer.exists()).toBe(true);
    expect(drawer.props('generation')).toMatchObject({ id: 9 });
  });

  it('仅 availableActions 含 recover 时二次确认恢复，防重复并成功刷新列表详情', async () => {
    let resolveRecovery!: (value: { state: string }) => void;
    mocks.recoverGeneration.mockImplementation(() => new Promise((resolve) => { resolveRecovery = resolve; }));
    const wrapper = await render();
    await wrapper.get('[data-action="detail-9"]').trigger('click');
    await flushPromises();

    const recover = wrapper.get('[data-action="recover"]');
    await recover.trigger('click');
    await recover.trigger('click');
    await flushPromises();
    expect(mocks.confirm).toHaveBeenCalledOnce();
    expect(mocks.recoverGeneration).toHaveBeenCalledOnce();
    expect(mocks.recoverGeneration).toHaveBeenCalledWith(9, 'recovery_required');

    resolveRecovery({ state: 'rolled_back' });
    await flushPromises();
    expect(mocks.generations).toHaveBeenCalledTimes(2);
    expect(mocks.generation).toHaveBeenCalledTimes(2);
    expect(mocks.success).toHaveBeenCalled();

    mocks.generation.mockResolvedValue({ ...sampleGeneration, availableActions: [] });
    await wrapper.get('[data-action="detail-9"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-action="recover"]').exists()).toBe(false);
  });

  it('提供可访问的响应式列表区域和 loading/error/empty/content 状态', async () => {
    const contentWrapper = await render();
    expect(contentWrapper.get('[aria-label="生成记录列表"]').classes()).toContain('records-table-wrap');
    contentWrapper.unmount();

    mocks.generations.mockResolvedValueOnce({ list: [], total: 0, page: 1, pageSize: 20 });
    const emptyWrapper = await render();
    const state = findComponentByName(emptyWrapper, 'BusinessPageState');
    expect(state.props('empty')).toBe(true);
    expect(state.props()).toEqual(expect.objectContaining({ loading: false, error: null }));
  });
});
