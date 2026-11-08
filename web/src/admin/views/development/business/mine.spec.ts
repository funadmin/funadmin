import { computed, defineComponent, h, inject, nextTick, provide, reactive, type ComputedRef, type PropType } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import { permission as permissionDirective } from '@/directives/permission';
import type { BusinessModule, BusinessPageResult } from '@/api/development/business';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  modules: vi.fn(),
  removeModule: vi.fn(),
  refreshBusinessMenu: vi.fn(),
  dynamicPublish: vi.fn(),
  confirm: vi.fn(),
  message: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
  push: vi.fn(),
  replace: vi.fn()
}));
const route = reactive<{ path: string; query: Record<string, string | undefined> }>({ path: '/admin/development/business/mine', query: {} });

vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return {
    ...actual,
    businessDevelopmentApi: { ...actual.businessDevelopmentApi, modules: mocks.modules, removeModule: mocks.removeModule }
  };
});
vi.mock('element-plus', async (importOriginal) => {
  const actual = await importOriginal<typeof import('element-plus')>();
  return { ...actual, ElMessageBox: { confirm: mocks.confirm }, ElMessage: mocks.message };
});
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));
vi.mock('vue-router', () => ({ useRoute: () => route, useRouter: () => ({ push: mocks.push, replace: mocks.replace, addRoute: vi.fn() }) }));
vi.mock('./composables/useBusinessMenuRefresh', () => ({
  useBusinessMenuRefresh: () => ({ refreshBusinessMenu: mocks.refreshBusinessMenu })
}));

// 子弹窗各自有独立测试；这里只验证页面如何打开它们以及如何响应它们的事件。
function dialogStub(name: string, props: string[], emits: string[] = []) {
  return async () => {
    const { defineComponent: define, h: render } = await import('vue');
    return {
      default: define({
        name,
        props,
        emits: ['update:modelValue', ...emits],
        setup(p: Record<string, unknown>, { expose }) {
          if (name === 'CodePreviewDialog') expose({ dynamicPublish: mocks.dynamicPublish });
          return () => render('div', {
            'data-dialog': name,
            'data-open': String(Boolean(p.modelValue)),
            'data-module': (p.module as { id?: number } | null | undefined)?.id ?? p.moduleId ?? undefined,
            'data-tab': p.initialTab,
            'data-count': Array.isArray(p.modules) ? p.modules.length : undefined
          });
        }
      })
    };
  };
}
vi.mock('./codegen/LoadTablesDialog.vue', dialogStub('LoadTablesDialog', ['modelValue'], ['loaded']));
vi.mock('./codegen/CreateTableDialog.vue', dialogStub('CreateTableDialog', ['modelValue'], ['created']));
vi.mock('./codegen/DatabaseSyncDialog.vue', dialogStub('DatabaseSyncDialog', ['modelValue', 'module'], ['synced']));
vi.mock('./codegen/BatchGenerateDialog.vue', dialogStub('BatchGenerateDialog', ['modelValue', 'modules'], ['generated', 'open-preview']));
vi.mock('./codegen/EditGenerationDialog.vue', dialogStub('EditGenerationDialog', ['modelValue', 'moduleId', 'initialTab'], ['saved', 'preview']));
vi.mock('./codegen/CodePreviewDialog.vue', dialogStub('CodePreviewDialog', ['modelValue', 'module'], ['generated', 'published']));
vi.mock('./codegen/RecordsPanel.vue', async () => {
  const { defineComponent: define } = await import('vue');
  return { default: define({ name: 'RecordsPanel', props: { moduleId: Number, embedded: Boolean }, template: '<section data-records-panel :data-embedded="String(embedded)">records</section>' }) };
});

import BusinessMine from './mine.vue';

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const tableRowsKey = Symbol('tableRows');
const tabsKey = Symbol('tabs');
const sampleRows: BusinessModule[] = [
  {
    id: 1,
    name: '订单',
    code: 'orders',
    origin: 'visual',
    table_name: 'fa_orders',
    lifecycle_status: 'dynamic_published',
    generation_status: 'generated',
    form_id: 10,
    runtime_route: '/orders',
    updated_at: '2026-11-07 10:21:00'
  },
  {
    id: 2,
    name: '库存',
    code: 'stock',
    origin: 'database',
    table_name: 'fa_stock',
    lifecycle_status: 'draft',
    generation_status: 'failed',
    form_id: null
  }
];

const stubs = {
  PageWrapper: defineComponent({ props: ['title', 'subtitle'], template: '<main><h1>{{ title }}</h1><p>{{ subtitle }}</p><slot /></main>' }),
  DataTableShell: defineComponent({
    props: ['loading'],
    emits: ['refresh'],
    template: '<section><slot name="search" /><div data-toolbar><slot name="toolbar-left" /></div><slot :size="\'default\'" :stripe="false" :border="false" :headerCellStyle="{}" /></section>'
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
  ElTabs: defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    setup(props, { slots, emit }) {
      provide(tabsKey, { active: computed(() => props.modelValue), select: (name: string) => emit('update:modelValue', name) });
      return () => h('div', { 'data-tabs': props.modelValue }, slots.default?.());
    }
  }),
  ElTabPane: defineComponent({
    props: ['name', 'label'],
    setup(props, { slots }) {
      const tabs = inject<{ active: ComputedRef<string>; select: (name: string) => void }>(tabsKey)!;
      return () => h('div', [
        h('button', { type: 'button', 'data-tab': props.name, onClick: () => tabs.select(props.name) }, props.label),
        tabs.active.value === props.name ? h('div', { 'data-pane': props.name }, slots.default?.()) : null
      ]);
    }
  }),
  ElTable: defineComponent({
    name: 'ElTable',
    inheritAttrs: false,
    props: { data: { type: Array as PropType<BusinessModule[]>, default: () => [] }, emptyText: { type: String, default: '' } },
    emits: ['selection-change'],
    setup(props, { slots }) {
      provide(tableRowsKey, computed(() => props.data));
      return () => (props.data.length ? h('div', { 'data-table': '' }, slots.default?.()) : h('div', { 'data-table-empty': '' }, props.emptyText));
    }
  }),
  ElTableColumn: defineComponent({
    inheritAttrs: false,
    props: ['label', 'type'],
    setup(props, { slots }) {
      const rows = inject<ComputedRef<BusinessModule[]>>(tableRowsKey)!;
      return () => h('div', { 'data-column': props.label ?? props.type }, [h('strong', props.label), ...rows.value.map((row) => slots.default?.({ row }))]);
    }
  }),
  ElButton: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'loading', 'title', 'type', 'plain'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :data-type="type" :data-plain="plain === \'\' || plain === true ? \'true\' : undefined" :disabled="disabled || loading" :title="title" :aria-busy="loading ? \'true\' : undefined" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElDropdown: defineComponent({ template: '<div data-dropdown><slot /><slot name="dropdown" /></div>' }),
  ElDropdownMenu: defineComponent({ template: '<div data-dropdown-menu><slot /></div>' }),
  ElDropdownItem: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'divided'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElTooltip: defineComponent({ props: ['content', 'disabled'], template: '<span :data-tooltip="disabled ? undefined : content"><slot /></span>' }),
  ElTag: defineComponent({ props: ['title', 'type'], template: '<span :title="title" :data-tag-type="type"><slot /></span>' }),
  ElFormItem: defineComponent({ props: ['label'], template: '<label>{{ label }}<slot /></label>' }),
  ElInput: defineComponent({ props: ['placeholder'], template: '<input :placeholder="placeholder" />' }),
  ElSelect: defineComponent({
    props: ['modelValue', 'placeholder'],
    emits: ['update:modelValue'],
    template: '<select :data-select="placeholder" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>'
  }),
  ElOption: defineComponent({ props: ['label', 'value'], template: '<option :value="value">{{ label }}</option>' }),
  ElPagination: defineComponent({
    inheritAttrs: false,
    props: ['layout', 'total'],
    emits: ['change', 'update:currentPage', 'update:pageSize'],
    template: '<nav v-bind="$attrs" data-pagination :data-layout="layout" :aria-label="$attrs[\'aria-label\']">{{ total }}</nav>'
  }),
  GenerationStatusTag: defineComponent({ props: ['status'], template: '<span data-generation-status>{{ status === \'generated\' ? \'已完成\' : status === \'failed\' ? \'生成失败\' : status }}</span>' })
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

function render(enforcePermissions = false) {
  return mount(BusinessMine, {
    global: {
      plugins: [i18n],
      stubs,
      directives: { perm: enforcePermissions ? permissionDirective : () => undefined }
    }
  });
}

const dialog = (wrapper: ReturnType<typeof render>, name: string) => wrapper.get(`[data-dialog="${name}"]`);
const visible = (element: { attributes: (key: string) => string | undefined }) => element.attributes('style')?.includes('display: none') !== true;

beforeEach(() => {
  vi.clearAllMocks();
  mocks.permissions = ['*'];
  route.query = {};
  mocks.modules.mockResolvedValue(result());
  mocks.removeModule.mockResolvedValue({ removed: 1 });
  mocks.refreshBusinessMenu.mockResolvedValue([]);
  mocks.confirm.mockResolvedValue('confirm');
  mocks.replace.mockImplementation(async (location: { query?: Record<string, string> }) => { if (location.query) route.query = { ...location.query }; });
  mocks.push.mockImplementation(async (location: string | { path?: string; query?: Record<string, string> }) => {
    if (typeof location === 'object' && location.path === route.path && location.query) route.query = { ...location.query };
  });
});

describe('代码生成页：页签、搜索、工具栏与列', () => {
  it('页面标题为代码生成，默认数据表页签，并提供生成记录页签', async () => {
    const wrapper = render(); await flushPromises();
    expect(wrapper.get('h1').text()).toBe('代码生成');
    expect(wrapper.get('[data-tabs]').attributes('data-tabs')).toBe('tables');
    expect(wrapper.get('[data-tab="tables"]').text()).toBe('数据表');
    expect(wrapper.get('[data-tab="records"]').text()).toBe('生成记录');
    expect(wrapper.find('[data-records-panel]').exists()).toBe(false);
  });

  it('搜索操作区使用紧凑的独立布局，不让查询按钮掉到大面积空白下方', async () => {
    const wrapper = render(); await flushPromises();
    expect(wrapper.find('.codegen-search-form').exists()).toBe(true);
  });

  it('列包含选择框、表名称（含业务标识）、表描述、来源、生成目标、生成类型、状态、更新时间与操作', async () => {
    const wrapper = render(); await flushPromises();
    const columns = wrapper.findAll('[data-column]').map((column) => column.attributes('data-column'));
    expect(columns).toEqual(['selection', '表名称', '表描述', '来源', '生成目标', '生成类型', '状态', '更新时间', '操作']);
    const tableName = wrapper.get('[data-column="表名称"]').text();
    expect(tableName).toContain('fa_orders');
    expect(tableName).toContain('orders');
    expect(wrapper.get('[data-column="表描述"]').text()).toContain('订单');
    const origin = wrapper.get('[data-column="来源"]').text();
    expect(origin).toContain('可视化新建');
    expect(origin).toContain('数据库装载');
    expect(wrapper.get('[data-column="生成目标"]').text()).toContain('核心');
    expect(wrapper.get('[data-column="更新时间"]').text()).toContain('2026-11-07 10:21:00');
  });

  it('生成类型取列表返回的 tree_enabled（Schema list.tree.enabled）：单表 CRUD / 树表 CRUD', async () => {
    mocks.modules.mockResolvedValue(result([
      { ...sampleRows[0]!, tree_enabled: true },
      { ...sampleRows[1]!, tree_enabled: false },
      { ...sampleRows[1]!, id: 3 }
    ]));
    const wrapper = render(); await flushPromises();
    const types = wrapper.findAll('[data-generation-type]');
    expect(types.map((item) => item.text())).toEqual(['树表 CRUD', '单表 CRUD', '单表 CRUD']);
  });

  it('搜索包含关键词、生成目标、状态，来源筛选只有可视化与数据库；生成目标作为 target 交给后端筛选', async () => {
    const wrapper = render(); await flushPromises();
    expect(mocks.modules).toHaveBeenLastCalledWith({ page: 1, pageSize: 20, keyword: '', status: '', origin: '', target: '' });
    expect(wrapper.find('input[placeholder="表名称 / 表描述 / 业务标识"]').exists()).toBe(true);
    const labels = wrapper.findAll('label').map((label) => label.text());
    expect(labels.some((text) => text.startsWith('关键词'))).toBe(true);
    expect(labels.some((text) => text.startsWith('生成目标'))).toBe(true);
    expect(labels.some((text) => text.startsWith('状态'))).toBe(true);
    const sourceOptions = wrapper.get('[data-filter="origin"]').findAll('option').map((option) => option.attributes('value'));
    expect(sourceOptions).toEqual(['visual', 'database']);
    expect(wrapper.text()).not.toContain('旧表单');

    mocks.modules.mockResolvedValue(result([{ ...sampleRows[1]!, metadata: { target: { type: 'plugin', pluginCode: 'cms', scope: 'admin' } } }]));
    await wrapper.get('[data-filter="target"]').setValue('plugin');
    await wrapper.get('[data-search]').trigger('click');
    await flushPromises();
    expect(mocks.modules).toHaveBeenLastCalledWith(expect.objectContaining({ page: 1, target: 'plugin' }));
    expect(wrapper.get('[data-column="生成目标"]').text()).toContain('插件 cms');

    await wrapper.get('[data-reset]').trigger('click');
    await flushPromises();
    expect(mocks.modules).toHaveBeenLastCalledWith(expect.objectContaining({ target: '' }));
  });

  it('工具栏：装载数据表(primary)、新建表(plain)、批量生成(success)、删除(danger)，并显示已选数量', async () => {
    const wrapper = render(); await flushPromises();
    const load = wrapper.get('[data-action="load-tables"]');
    const create = wrapper.get('[data-action="create-table"]');
    const batch = wrapper.get('[data-action="batch-generate"]');
    const remove = wrapper.get('[data-action="batch-remove"]');
    expect(load.text()).toContain('装载数据表');
    expect(load.attributes('data-type')).toBe('primary');
    expect(create.text()).toContain('新建表');
    expect(create.attributes('data-plain')).toBe('true');
    expect(batch.text()).toContain('批量生成');
    expect(batch.attributes('data-type')).toBe('success');
    expect(batch.attributes('data-plain')).toBe('true');
    expect(remove.text()).toContain('删除');
    expect(remove.attributes('data-type')).toBe('danger');
    expect(remove.attributes('data-plain')).toBe('true');
    expect(batch.attributes('disabled')).toBeDefined();
    expect(remove.attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-selection-count]').text()).toBe('已选 0 项');

    wrapper.getComponent({ name: 'ElTable' }).vm.$emit('selection-change', sampleRows);
    await nextTick();
    expect(wrapper.get('[data-selection-count]').text()).toBe('已选 2 项');
    expect(batch.attributes('disabled')).toBeUndefined();
  });

  it('装载数据表打开装载弹窗，装载完成刷新列表；新建表打开新建表弹窗，不再跳转页面', async () => {
    route.query = { plugin: 'demo', source: 'test' };
    const wrapper = render(); await flushPromises();
    expect(dialog(wrapper, 'LoadTablesDialog').attributes('data-open')).toBe('false');
    await wrapper.get('[data-action="load-tables"]').trigger('click');
    expect(dialog(wrapper, 'LoadTablesDialog').attributes('data-open')).toBe('true');
    wrapper.getComponent({ name: 'LoadTablesDialog' }).vm.$emit('loaded', []);
    await flushPromises();
    expect(mocks.modules).toHaveBeenCalledTimes(2);

    expect(wrapper.find('[data-dialog="CreateTableDialog"]').exists()).toBe(false);
    await wrapper.get('[data-action="create-table"]').trigger('click');
    expect(dialog(wrapper, 'CreateTableDialog').attributes('data-open')).toBe('true');
    expect(mocks.push).not.toHaveBeenCalled();
  });

  it('批量生成把选中模块交给批量弹窗，open-preview 打开对应模块的代码预览', async () => {
    const wrapper = render(); await flushPromises();
    wrapper.getComponent({ name: 'ElTable' }).vm.$emit('selection-change', sampleRows);
    await nextTick();
    await wrapper.get('[data-action="batch-generate"]').trigger('click');
    expect(dialog(wrapper, 'BatchGenerateDialog').attributes('data-open')).toBe('true');
    expect(dialog(wrapper, 'BatchGenerateDialog').attributes('data-count')).toBe('2');
    wrapper.getComponent({ name: 'BatchGenerateDialog' }).vm.$emit('open-preview', 2);
    await nextTick();
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-open')).toBe('true');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-module')).toBe('2');
    wrapper.getComponent({ name: 'BatchGenerateDialog' }).vm.$emit('generated', []);
    await flushPromises();
    expect(mocks.modules).toHaveBeenCalledTimes(2);
  });

  it('批量生成完成刷新列表会清空表格勾选，但批量弹窗仍保留打开时的模块快照供「重新预览」', async () => {
    const wrapper = render(); await flushPromises();
    wrapper.getComponent({ name: 'ElTable' }).vm.$emit('selection-change', sampleRows);
    await nextTick();
    await wrapper.get('[data-action="batch-generate"]').trigger('click');
    wrapper.getComponent({ name: 'BatchGenerateDialog' }).vm.$emit('generated', []);
    await flushPromises();
    expect(mocks.modules).toHaveBeenCalledTimes(2);
    expect(wrapper.get('[data-selection-count]').text()).toContain('0');
    expect(dialog(wrapper, 'BatchGenerateDialog').attributes('data-count')).toBe('2');
    wrapper.getComponent({ name: 'BatchGenerateDialog' }).vm.$emit('open-preview', 2);
    await nextTick();
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-module')).toBe('2');
  });

  it('批量删除二次确认后逐个删除，刷新列表与菜单', async () => {
    const wrapper = render(); await flushPromises();
    wrapper.getComponent({ name: 'ElTable' }).vm.$emit('selection-change', sampleRows);
    await nextTick();
    await wrapper.get('[data-action="batch-remove"]').trigger('click');
    await flushPromises();
    expect(mocks.confirm).toHaveBeenCalledOnce();
    // 逐条删除不弹成功提示，只给一条汇总
    expect(mocks.removeModule.mock.calls).toEqual([[1, { showSuccessMsg: false, showErrorMsg: false }], [2, { showSuccessMsg: false, showErrorMsg: false }]]);
    expect(mocks.message.success).toHaveBeenCalledOnce();
    expect(mocks.message.success).toHaveBeenCalledWith('删除完成：成功 2 个，失败 0 个');
    expect(mocks.modules).toHaveBeenCalledTimes(2);
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledOnce();
  });

  it('批量删除部分失败时一条警告汇总成功与失败原因', async () => {
    mocks.removeModule.mockResolvedValueOnce({ removed: 1 }).mockRejectedValueOnce(new Error('生成进行中'));
    const wrapper = render(); await flushPromises();
    wrapper.getComponent({ name: 'ElTable' }).vm.$emit('selection-change', sampleRows);
    await nextTick();
    await wrapper.get('[data-action="batch-remove"]').trigger('click');
    await flushPromises();
    // 逐条请求关闭 http 层的成功与错误提示，失败只出现在这一条汇总里
    expect(mocks.removeModule.mock.calls.map((call) => call[1])).toEqual([{ showSuccessMsg: false, showErrorMsg: false }, { showSuccessMsg: false, showErrorMsg: false }]);
    expect(mocks.message.success).not.toHaveBeenCalled();
    expect(mocks.message.error).not.toHaveBeenCalled();
    expect(mocks.message.warning).toHaveBeenCalledOnce();
    expect(mocks.message.warning.mock.calls[0]![0]).toContain('成功 1 个，失败 1 个');
    expect(mocks.message.warning.mock.calls[0]![0]).toContain('库存：生成进行中');
  });

  it('工具栏按钮按权限码独立显示', async () => {
    mocks.permissions = ['development:business:modules', 'development:business:create-visual'];
    const wrapper = render(true); await flushPromises();
    expect(visible(wrapper.get('[data-action="create-table"]'))).toBe(true);
    expect(visible(wrapper.get('[data-action="load-tables"]'))).toBe(false);
    expect(visible(wrapper.get('[data-action="batch-generate"]'))).toBe(false);
    expect(visible(wrapper.get('[data-action="batch-remove"]'))).toBe(false);
    wrapper.unmount();

    mocks.permissions = ['development:business:modules', 'development:business:database-tables', 'development:business:inspect-database', 'development:business:create-from-database'];
    const loadOnly = render(true); await flushPromises();
    expect(visible(loadOnly.get('[data-action="load-tables"]'))).toBe(true);
    expect(visible(loadOnly.get('[data-action="create-table"]'))).toBe(false);
  });
});

describe('代码生成页：行操作', () => {
  it('行操作依次为 预览、编辑、设计、同步、生成、更多，更多含动态发布/打开运行时/版本历史/生成记录/删除', async () => {
    const wrapper = render(); await flushPromises();
    const actions = wrapper.get('[data-row-actions="1"]');
    expect(actions.classes()).toContain('business-row-actions');
    const text = actions.text();
    const order = ['预览', '编辑', '设计', '同步', '生成', '更多'].map((label) => text.indexOf(label));
    expect(order.every((index) => index >= 0)).toBe(true);
    expect([...order].sort((a, b) => a - b)).toEqual(order);
    const more = actions.get('[data-dropdown-menu]').text();
    for (const label of ['动态发布', '打开运行时', '版本历史', '生成记录', '删除']) expect(more).toContain(label);
  });

  it('预览与生成都打开代码预览弹窗（内含生成到项目），生成/发布完成后刷新列表', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-preview="1"]').trigger('click');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-open')).toBe('true');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-module')).toBe('1');
    wrapper.getComponent({ name: 'CodePreviewDialog' }).vm.$emit('update:modelValue', false);
    await nextTick();
    await wrapper.get('[data-generate="2"]').trigger('click');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-open')).toBe('true');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-module')).toBe('2');
    wrapper.getComponent({ name: 'CodePreviewDialog' }).vm.$emit('generated', { state: 'completed' });
    await flushPromises();
    wrapper.getComponent({ name: 'CodePreviewDialog' }).vm.$emit('published');
    await flushPromises();
    expect(mocks.modules).toHaveBeenCalledTimes(3);
  });

  it('编辑打开编辑生成信息弹窗；保存刷新列表，保存并预览打开代码预览', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-edit="1"]').trigger('click');
    const edit = dialog(wrapper, 'EditGenerationDialog');
    expect(edit.attributes('data-open')).toBe('true');
    expect(edit.attributes('data-module')).toBe('1');
    expect(edit.attributes('data-tab')).toBe('config');
    wrapper.getComponent({ name: 'EditGenerationDialog' }).vm.$emit('saved');
    await flushPromises();
    expect(mocks.modules).toHaveBeenCalledTimes(2);
    wrapper.getComponent({ name: 'EditGenerationDialog' }).vm.$emit('preview');
    await nextTick();
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-open')).toBe('true');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-module')).toBe('1');
  });

  it('版本历史打开编辑弹窗的配置信息页签（弹窗右上角含版本历史）', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-versions="2"]').trigger('click');
    const edit = dialog(wrapper, 'EditGenerationDialog');
    expect(edit.attributes('data-open')).toBe('true');
    expect(edit.attributes('data-module')).toBe('2');
    expect(edit.attributes('data-tab')).toBe('config');
  });

  it('同步只对数据库装载的模块可用；可视化新建的行禁用并提示原因', async () => {
    const wrapper = render(); await flushPromises();
    const visualSync = wrapper.get('[data-sync="1"]');
    expect(visualSync.attributes('disabled')).toBeDefined();
    const tooltip = wrapper.get('[data-sync-tip="1"] [data-tooltip]').attributes('data-tooltip');
    expect(tooltip).toContain('可视化新建');
    expect(wrapper.find('[data-sync-tip="2"] [data-tooltip]').exists()).toBe(false);
    const databaseSync = wrapper.get('[data-sync="2"]');
    expect(databaseSync.attributes('disabled')).toBeUndefined();
    await databaseSync.trigger('click');
    expect(dialog(wrapper, 'DatabaseSyncDialog').attributes('data-open')).toBe('true');
    expect(dialog(wrapper, 'DatabaseSyncDialog').attributes('data-module')).toBe('2');
    wrapper.getComponent({ name: 'DatabaseSyncDialog' }).vm.$emit('synced');
    await flushPromises();
    expect(mocks.modules).toHaveBeenCalledTimes(2);
  });

  it('动态发布复用代码预览弹窗暴露的发布流程，不先打开预览', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-dynamic-publish="1"]').trigger('click');
    await flushPromises();
    expect(mocks.dynamicPublish).toHaveBeenCalledOnce();
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-module')).toBe('1');
    expect(dialog(wrapper, 'CodePreviewDialog').attributes('data-open')).toBe('false');
  });

  it('生成记录切到生成记录页签并按模块过滤', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-records="1"]').trigger('click');
    await flushPromises();
    expect(route.query).toMatchObject({ tab: 'records', moduleId: '1' });
    expect(wrapper.get('[data-tabs]').attributes('data-tabs')).toBe('records');
    expect(wrapper.get('[data-records-panel]').attributes('data-embedded')).toBe('true');
  });

  it('插件业务展示归属、待发布及冲突恢复状态，不暴露动态运行时与动态发布', async () => {
    mocks.modules.mockResolvedValue(result([
      { ...sampleRows[0]!, metadata: { target: { type: 'plugin', pluginCode: 'demo', scope: 'admin', locked: true } } },
      { ...sampleRows[1]!, generation_status: 'failed', recovery_status: 'recovery_required' },
      { ...sampleRows[1]!, id: 3, generation_status: 'running', recovery_status: 'recovering' }
    ]));
    const wrapper = render(); await flushPromises();
    expect(wrapper.text()).toContain('插件 demo');
    expect(wrapper.text()).toContain('待安装／更新发布');
    expect(wrapper.text()).toContain('需要恢复');
    expect(wrapper.text()).toContain('恢复中');
    expect(wrapper.find('[data-runtime="1"]').exists()).toBe(false);
    expect(wrapper.find('[data-dynamic-publish="1"]').exists()).toBe(false);
  });

  it('本地化生命周期和生成状态，无 form_id 时设计按钮常驻但禁用并说明原因', async () => {
    const wrapper = render(); await flushPromises();
    expect(wrapper.text()).toContain('已动态发布');
    expect(wrapper.text()).toContain('已完成');
    expect(wrapper.text()).not.toContain('dynamic_published');
    const disabledDesign = wrapper.get('[data-design="2"]');
    expect(disabledDesign.attributes('disabled')).toBeDefined();
    expect(disabledDesign.attributes('title')).toContain('缺少表单');
    await wrapper.get('[data-design="1"]').trigger('click');
    expect(mocks.push).toHaveBeenCalledWith({ path: '/admin/development/business/designer', query: { id: '10', moduleId: '1' } });
  });

  it('打开运行时先刷新业务菜单，分页提供 aria 标签', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-runtime="1"]').trigger('click');
    await flushPromises();
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledOnce();
    expect(mocks.push).toHaveBeenCalledWith('/orders');
    expect(wrapper.find('.table-pagination').exists()).toBe(true);
    expect(wrapper.get('[data-pagination]').attributes('aria-label')).toBe('数据表分页');
  });

  it('删除二次确认后删除单个模块并刷新列表与菜单', async () => {
    const wrapper = render(); await flushPromises();
    await wrapper.get('[data-remove="2"]').trigger('click');
    await flushPromises();
    expect(mocks.confirm).toHaveBeenCalledOnce();
    expect(mocks.removeModule).toHaveBeenCalledWith(2);
    expect(mocks.modules).toHaveBeenCalledTimes(2);
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledOnce();
  });

  it('有设计权限但无删除权限仍可打开设计器，删除与同步按权限隐藏', async () => {
    mocks.permissions = ['development:business:save-schema'];
    const wrapper = render(true); await flushPromises();
    expect(visible(wrapper.get('[data-design="1"]'))).toBe(true);
    expect(visible(wrapper.get('[data-remove="1"]'))).toBe(false);
    expect(visible(wrapper.get('[data-sync-tip="2"]'))).toBe(false);
    await wrapper.get('[data-design="1"]').trigger('click');
    expect(mocks.push).toHaveBeenCalledWith({ path: '/admin/development/business/designer', query: { id: '10', moduleId: '1' } });
  });
});

describe('代码生成页：加载状态与 URL', () => {
  it('区分 loading、请求错误、首次空态和筛选空态', async () => {
    const pending = deferred<BusinessPageResult<BusinessModule>>();
    mocks.modules.mockReturnValueOnce(pending.promise);
    const wrapper = render();
    await nextTick();
    expect(wrapper.get('[data-page-state]').attributes('aria-busy')).toBe('true');
    pending.resolve(result([]));
    await flushPromises();
    expect(wrapper.get('[data-table-empty]').text()).toContain('还没有数据表');

    mocks.modules.mockResolvedValueOnce(result([]));
    await wrapper.get('[data-search]').trigger('click');
    await flushPromises();
    expect(wrapper.get('[data-table-empty]').text()).toContain('没有符合筛选条件');

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
    second.resolve(result([{ ...sampleRows[0]!, name: '新结果' }]));
    await flushPromises();
    first.resolve(result([{ ...sampleRows[0]!, name: '旧结果' }]));
    await flushPromises();
    expect(wrapper.text()).toContain('新结果');
    expect(wrapper.text()).not.toContain('旧结果');
  });

  it('?tab=records 打开生成记录页签并保留 moduleId；切回数据表移除 tab 参数', async () => {
    route.query = { tab: 'records', moduleId: '3' };
    const wrapper = render(); await flushPromises();
    expect(wrapper.get('[data-tabs]').attributes('data-tabs')).toBe('records');
    expect(wrapper.find('[data-records-panel]').exists()).toBe(true);
    await wrapper.get('[data-tab="tables"]').trigger('click');
    await flushPromises();
    expect(route.query.tab).toBeUndefined();
    expect(route.query.moduleId).toBe('3');
    expect(wrapper.find('[data-records-panel]').exists()).toBe(false);
    await wrapper.get('[data-tab="records"]').trigger('click');
    await flushPromises();
    expect(route.query).toMatchObject({ tab: 'records', moduleId: '3' });
  });

  it('无生成记录权限时 ?tab=records 回落到数据表页签', async () => {
    mocks.permissions = ['development:business:modules'];
    route.query = { tab: 'records' };
    const wrapper = render(true); await flushPromises();
    expect(wrapper.get('[data-tabs]').attributes('data-tabs')).toBe('tables');
    expect(wrapper.find('[data-tab="records"]').exists()).toBe(false);
  });

  it('?load=1 挂载后打开装载弹窗，并从 URL 移除 load 参数', async () => {
    route.query = { load: '1', plugin: 'demo' };
    const wrapper = render(); await flushPromises();
    expect(dialog(wrapper, 'LoadTablesDialog').attributes('data-open')).toBe('true');
    expect(route.query).toEqual({ plugin: 'demo' });
  });

  it('?create=1 挂载后打开新建表弹窗，并从 URL 移除 create 参数', async () => {
    route.query = { create: '1', plugin: 'demo' };
    const wrapper = render(); await flushPromises();
    expect(dialog(wrapper, 'CreateTableDialog').attributes('data-open')).toBe('true');
    expect(route.query).toEqual({ plugin: 'demo' });
  });

  it('无创建新表权限时 ?create=1 不打开弹窗，但仍移除 create 参数', async () => {
    mocks.permissions = ['development:business:modules'];
    route.query = { create: '1' };
    const wrapper = render(); await flushPromises();
    expect(wrapper.find('[data-dialog="CreateTableDialog"]').exists()).toBe(false);
    expect(route.query).toEqual({});
  });
});
