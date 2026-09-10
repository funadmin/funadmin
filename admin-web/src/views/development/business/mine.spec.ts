import { computed, defineComponent, inject, nextTick, provide, type ComputedRef, type PropType } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import type {
  BusinessFormalGenerationPreview,
  BusinessModule,
  BusinessPageResult
} from '@/api/development/business';

const mocks = vi.hoisted(() => ({
  modules: vi.fn(),
  previewFormalGeneration: vi.fn(),
  refreshBusinessMenu: vi.fn(),
  push: vi.fn()
}));

vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return {
    ...actual,
    businessDevelopmentApi: {
      ...actual.businessDevelopmentApi,
      modules: mocks.modules,
      previewFormalGeneration: mocks.previewFormalGeneration
    }
  };
});
vi.mock('vue-router', () => ({ useRouter: () => ({ push: mocks.push, addRoute: vi.fn() }) }));
vi.mock('./composables/useBusinessMenuRefresh', () => ({
  useBusinessMenuRefresh: () => ({ refreshBusinessMenu: mocks.refreshBusinessMenu })
}));

import BusinessMine from './mine.vue';

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const tableRowsKey = Symbol('tableRows');
const sampleRows: BusinessModule[] = [
  {
    id: 1,
    name: '订单',
    code: 'orders',
    origin: 'visual',
    lifecycle_status: 'dynamic_published',
    generation_status: 'generated',
    form_id: 10,
    runtime_route: '/orders'
  },
  {
    id: 2,
    name: '库存',
    code: 'stock',
    origin: 'database',
    lifecycle_status: 'draft',
    generation_status: 'failed',
    form_id: null
  }
];
const preview: BusinessFormalGenerationPreview = {
  generationId: 8,
  definitionHash: 'definition-hash',
  schemaHash: 'schema-hash',
  routePath: '/orders',
  plan: { blocked: false, files: [{ path: 'app/Order.php', status: 'update' }] },
  conflicts: []
};

const stubs = {
  PageWrapper: defineComponent({ template: '<main><slot /></main>' }),
  DataTableShell: defineComponent({
    props: ['loading'],
    emits: ['refresh'],
    template: '<section><slot name="search" /><slot name="toolbar-left" /><slot :size="\'default\'" :stripe="false" :border="false" :headerCellStyle="{}" /></section>'
  }),
  SearchForm: defineComponent({
    props: ['model', 'loading'],
    emits: ['search', 'reset'],
    template: '<form><slot /><button data-search type="button" @click="$emit(\'search\')">搜索</button><button data-reset type="button" @click="$emit(\'reset\')">重置</button></form>'
  }),
  BusinessPageState: defineComponent({
    props: ['loading', 'error', 'empty', 'emptyText', 'onRetry'],
    template: '<section data-page-state :aria-busy="loading ? \'true\' : \'false\'"><div v-if="loading" data-loading>loading</div><div v-else-if="error" role="alert">{{ error instanceof Error ? error.message : error }}<button data-retry @click="onRetry">重试</button></div><div v-else-if="empty" data-empty>{{ emptyText }}</div><slot v-else /></section>'
  }),
  ElTable: defineComponent({
    inheritAttrs: false,
    props: { data: { type: Array as PropType<BusinessModule[]>, default: () => [] } },
    setup(props, { slots }) {
      provide(tableRowsKey, computed(() => props.data));
      return () => slots.default?.();
    }
  }),
  ElTableColumn: defineComponent({
    inheritAttrs: false,
    setup(_, { slots }) {
      const rows = inject<ComputedRef<BusinessModule[]>>(tableRowsKey)!;
      return () => rows.value.map((row) => slots.default?.({ row }));
    }
  }),
  ElButton: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'loading', 'title'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :disabled="disabled || loading" :title="title" :aria-busy="loading ? \'true\' : undefined" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElTag: defineComponent({ props: ['title'], template: '<span :title="title"><slot /></span>' }),
  ElFormItem: defineComponent({ template: '<label><slot /></label>' }),
  ElInput: defineComponent({ template: '<input />' }),
  ElSelect: defineComponent({ template: '<select><slot /></select>' }),
  ElOption: defineComponent({ template: '<option />' }),
  ElPagination: defineComponent({
    inheritAttrs: false,
    props: ['layout', 'total'],
    emits: ['change', 'update:currentPage', 'update:pageSize'],
    template: '<nav v-bind="$attrs" data-pagination :data-layout="layout" :aria-label="$attrs[\'aria-label\']">{{ total }}</nav>'
  }),
  ElDialog: defineComponent({
    props: ['modelValue', 'title', 'width'],
    emits: ['update:modelValue'],
    template: '<aside v-if="modelValue" role="dialog" :data-width="width"><h2>{{ title }}</h2><slot /><footer><slot name="footer" /></footer></aside>'
  }),
  ElAlert: defineComponent({ props: ['title'], template: '<div role="alert">{{ title }}</div>' }),
  GenerationStatusTag: defineComponent({ props: ['status'], template: '<span data-generation-status>{{ status === \'generated\' ? \'已完成\' : status === \'failed\' ? \'生成失败\' : status }}</span>' }),
  GenerationPlanView: defineComponent({ props: ['plan', 'conflicts'], template: '<section data-plan>{{ plan.files[0]?.path }}</section>' })
};

function result(list = sampleRows): BusinessPageResult<BusinessModule> {
  return { list, total: list.length, page: 1, pageSize: 20 };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}

function render() {
  return mount(BusinessMine, {
    global: {
      plugins: [i18n],
      stubs,
      directives: { perm: () => undefined }
    }
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  mocks.modules.mockResolvedValue(result());
  mocks.previewFormalGeneration.mockResolvedValue(preview);
  mocks.refreshBusinessMenu.mockResolvedValue([]);
  vi.stubGlobal('crypto', { randomUUID: vi.fn(() => 'nonce') });
});

describe('BusinessMine', () => {
  it('区分 loading、请求错误、首次空态和筛选空态，并提供 aria 状态', async () => {
    const pending = deferred<BusinessPageResult<BusinessModule>>();
    mocks.modules.mockReturnValueOnce(pending.promise);
    const wrapper = render();
    await nextTick();
    expect(wrapper.get('[data-page-state]').attributes('aria-busy')).toBe('true');
    pending.resolve(result([]));
    await flushPromises();
    expect(wrapper.get('[data-empty]').text()).toContain('还没有业务模块');

    mocks.modules.mockResolvedValueOnce(result([]));
    await wrapper.get('[data-search]').trigger('click');
    await flushPromises();
    expect(wrapper.get('[data-empty]').text()).toContain('没有符合筛选条件');

    mocks.modules.mockRejectedValueOnce(new Error('网络不可用'));
    await wrapper.get('[data-search]').trigger('click');
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('网络不可用');
  });

  it('请求序号保证较旧的列表响应不会覆盖较新的结果', async () => {
    const first = deferred<BusinessPageResult<BusinessModule>>();
    const second = deferred<BusinessPageResult<BusinessModule>>();
    mocks.modules.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
    const wrapper = render();
    await wrapper.get('[data-search]').trigger('click');
    second.resolve(result([{ ...sampleRows[0], name: '新结果' }]));
    await flushPromises();
    first.resolve(result([{ ...sampleRows[0], name: '旧结果' }]));
    await flushPromises();
    expect(wrapper.text()).toContain('新结果');
    expect(wrapper.text()).not.toContain('旧结果');
  });

  it('本地化生命周期和生成状态，无 form_id 时禁用设计并说明原因', async () => {
    const wrapper = render();
    await flushPromises();
    expect(wrapper.text()).toContain('已动态发布');
    expect(wrapper.text()).toContain('已完成');
    expect(wrapper.text()).not.toContain('dynamic_published');
    const disabledDesign = wrapper.get('[data-design="2"]');
    expect(disabledDesign.attributes('disabled')).toBeDefined();
    expect(disabledDesign.attributes('title')).toContain('缺少表单');
  });

  it('生成预览使用行级 loading，同模块重复点击不重复请求且会话内复用稳定 nonce', async () => {
    const pending = deferred<BusinessFormalGenerationPreview>();
    mocks.previewFormalGeneration.mockReturnValueOnce(pending.promise);
    const wrapper = render();
    await flushPromises();
    const button = wrapper.get('[data-preview="1"]');
    await button.trigger('click');
    await button.trigger('click');
    expect(button.attributes('aria-busy')).toBe('true');
    expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(1);
    pending.resolve(preview);
    await flushPromises();
    expect(button.attributes('aria-busy')).toBeUndefined();
    await button.trigger('click');
    await flushPromises();
    expect(mocks.previewFormalGeneration).toHaveBeenNthCalledWith(1, 1, 'nonce');
    expect(mocks.previewFormalGeneration).toHaveBeenNthCalledWith(2, 1, 'nonce');
    expect(crypto.randomUUID).toHaveBeenCalledTimes(1);
  });

  it('以结构化计划展示响应式预览，并明确页面只提供预览', async () => {
    const wrapper = render();
    await flushPromises();
    await wrapper.get('[data-preview="1"]').trigger('click');
    await flushPromises();
    const dialog = wrapper.get('[role="dialog"]');
    expect(dialog.attributes('data-width')).toContain('min');
    expect(dialog.find('[data-plan]').text()).toContain('app/Order.php');
    expect(dialog.text()).toContain('仅提供生成预览');
    expect(dialog.text()).not.toContain('confirmToken');
    expect(wrapper.find('[data-formal-generation]').exists()).toBe(false);
  });

  it('运行时入口先刷新业务菜单，并为窄屏操作和分页提供响应式布局', async () => {
    const wrapper = render();
    await flushPromises();
    await wrapper.get('[data-runtime="1"]').trigger('click');
    await flushPromises();
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledOnce();
    expect(mocks.push).toHaveBeenCalledWith('/orders');
    expect(wrapper.get('[data-row-actions="1"]').classes()).toContain('business-row-actions');
    expect(wrapper.find('.business-pagination').exists()).toBe(true);
    expect(wrapper.get('[data-pagination]').attributes('aria-label')).toBe('业务模块分页');
  });
});
