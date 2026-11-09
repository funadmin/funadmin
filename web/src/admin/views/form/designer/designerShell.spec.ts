import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { createPinia } from 'pinia';

const api = vi.hoisted(() => ({ module: vi.fn(), saveSchema: vi.fn(), exportSchema: vi.fn(), previewPublish: vi.fn(), publish: vi.fn() }));
const router = vi.hoisted(() => ({ push: vi.fn(), replace: vi.fn() }));
const menu = vi.hoisted(() => ({ refreshBusinessMenu: vi.fn() }));
const codePreview = vi.hoisted(() => ({ dynamicPublish: vi.fn() }));
vi.mock('@/api/development/business', async (original) => ({ ...await original<object>(), businessDevelopmentApi: api }));
vi.mock('vue-router', () => ({ useRoute: () => ({ query: { moduleId: 12 } }), useRouter: () => router, onBeforeRouteLeave: vi.fn() }));
vi.mock('vue-i18n', async importOriginal => ({ ...await importOriginal<typeof import('vue-i18n')>(), useI18n: () => ({ t: (_: string, a: unknown, b?: string) => {
  const template = typeof a === 'string' ? a : String(b ?? '');
  const params = (typeof a === 'object' && a ? a : {}) as Record<string, unknown>;
  return template.replace(/\{(\w+)\}/g, (match, key: string) => (key in params ? String(params[key]) : match));
} }) }));
vi.mock('@/api/system/permission', () => ({ permissionApi: { tree: async () => [] } }));
vi.mock('@/api/system/menu', () => ({ menuApi: { tree: vi.fn(async () => []) } }));
const actor = vi.hoisted(() => ({ permissions: [
  'development:business:module', 'development:business:save-schema', 'development:business:save-publish-config',
  'development:business:preview-publish', 'development:business:publish', 'development:business:preview-formal-generation',
  'development:business:formal-generation', 'development:business:generate', 'development:business:apply-resources',
  'development:business:schema-versions', 'development:business:export-schema'
] as string[] }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => actor }));
vi.mock('../schema/pluginComponentLoader', () => ({ loadPluginFormComponents: async () => {} }));
vi.mock('../../development/business/composables/useBusinessMenuRefresh', () => ({ useBusinessMenuRefresh: () => menu }));
vi.mock('sortablejs', () => ({ default: { create: () => ({ destroy() {} }) } }));
vi.mock('element-plus', () => ({ ElMessage: { warning: vi.fn(), success: vi.fn(), error: vi.fn() }, ElMessageBox: { confirm: vi.fn() } }));
import { ElMessage, ElMessageBox } from 'element-plus';
import Designer from './index.vue';

const hash = (letter: string) => letter.repeat(64);
const remote = (letter = 'a', title = '订单', target: Record<string, unknown> = { type: 'core', pluginCode: null, scope: 'admin' }) => ({
  module: { id: 12, code: 'orders', runtime_route: '/development/business/runtime/orders', metadata: { target } }, fields: [],
  form: { id: 7, name: title, form_key: 'orders', table_name: 'fun_orders', schema_hash: hash(letter), schema_document: { schemaVersion: 2, key: 'orders', title, nodes: [] } }
});
const conflict = { code: 409, msg: '冲突', data: { error: { code: 'FORM_SCHEMA_CONFLICT', requestId: 'r1', retryable: true, details: {} } } };

let wrapper: ReturnType<typeof shallowMount>;
let state: any;
const start = async () => {
  wrapper = shallowMount(Designer, { attachTo: document.body, global: { plugins: [createPinia()], renderStubDefaultSlot: true, directives: { perm: {} }, stubs: {
    PageWrapper: { template: '<main><slot /></main>' },
    DesignerTopBar: false,
    ElTooltip: { template: '<span><slot /></span>' },
    ElButton: { inheritAttrs: false, props: ['disabled', 'loading'], emits: ['click'], template: '<button v-bind="$attrs" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>' },
    ElTabs: { props: ['modelValue'], template: '<div data-tabs :data-active="modelValue"><slot /></div>' },
    ElTabPane: { props: ['name', 'label'], template: '<section :data-tab-pane="name"><h2 data-tab-label>{{ label }}</h2><slot /></section>' },
    ElDropdown: { template: '<div><slot /><slot name="dropdown" /></div>' },
    ElDropdownMenu: { template: '<ul><slot /></ul>' },
    ElDropdownItem: { inheritAttrs: false, props: ['disabled'], emits: ['click'], template: '<li><button v-bind="$attrs" :disabled="disabled" @click="$emit(\'click\')"><slot /></button></li>' },
    ElDialog: { props: ['modelValue'], template: '<section v-if="modelValue"><slot /><slot name="footer" /></section>' },
    ElDrawer: { name: 'ElDrawer', props: ['modelValue', 'size', 'direction'], template: '<aside><slot /></aside>' },
    CodePreviewDialog: { props: ['modelValue', 'module'], emits: ['update:modelValue', 'generated', 'published'], methods: { dynamicPublish: codePreview.dynamicPublish }, template: '<div v-if="modelValue" data-code-preview :data-module-id="module && module.id" />' },
    EditGenerationDialog: { props: ['modelValue', 'moduleId', 'hideDesignerLink'], emits: ['update:modelValue', 'saved', 'preview'], template: '<div v-if="modelValue" data-edit-generation :data-module-id="moduleId" :data-hide-designer-link="String(hideDesignerLink)" />' },
    VersionHistoryDrawer: { props: ['modelValue', 'moduleId', 'schemaHash'], emits: ['update:modelValue', 'rollback'], template: '<div v-if="modelValue" data-version-history :data-hash="schemaHash" />' }
  } } });
  await flushPromises();
  state = (wrapper.vm as any).$.setupState;
};
const action = (name: string) => wrapper.find(`[data-action="${name}"]`);
const child = (selector: string) => wrapper.getComponent(selector) as unknown as VueWrapper;
const edit = async (name = '本地编辑') => { state.store.updateForm({ name }); await nextTick(); };

beforeEach(() => {
  vi.useFakeTimers();
  vi.clearAllMocks();
  const storage = new Map<string, string>();
  vi.stubGlobal('localStorage', { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key) });
  api.module.mockResolvedValue(remote());
  api.saveSchema.mockImplementation(async (_id, document) => ({ document, schemaHash: hash('c') }));
  vi.mocked(ElMessageBox.confirm).mockResolvedValue('confirm' as never);
  menu.refreshBusinessMenu.mockResolvedValue([]);
});
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = ''; });

describe('设计器外壳：顶栏与页签', () => {
  it('页签只有 表单设计 / 按钮与工具 / 分类管理，默认表单设计；不再有基本信息', async () => {
    await start();
    expect(wrapper.findAll('[data-tab-label]').map((item) => item.text())).toEqual(['表单设计', '按钮与工具', '分类管理']);
    expect(state.activeTab).toBe('design');
    expect(wrapper.text()).not.toContain('表单基本信息');
    expect(wrapper.find('[data-tab-pane="basic"]').exists()).toBe(false);
  });

  it('顶栏标题显示表单名与 key → 表名 · 生成目标', async () => {
    await start();
    expect(wrapper.get('[data-topbar-title]').text()).toBe('订单');
    expect(wrapper.get('[data-topbar-meta]').text()).toBe('orders → fun_orders · 核心后台');
  });

  it('预览模式与「预览数据」只在预览时出现在页签行右侧', async () => {
    await start();
    expect(wrapper.find('[data-preview-tools]').exists()).toBe(false);
    await wrapper.get('[data-mode="mobile"]').trigger('click');
    expect(state.workspaceMode).toBe('mobile');
    expect(wrapper.find('[data-preview-tools]').text()).toContain('预览数据');
  });

  it('撤销/重做按钮与 ⌘Z / ⇧⌘Z 快捷键驱动 store 历史', async () => {
    await start();
    await edit('改名一');
    await action('undo').trigger('click');
    expect(state.store.form.value.name).toBe('订单');
    await action('redo').trigger('click');
    expect(state.store.form.value.name).toBe('改名一');
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', metaKey: true, bubbles: true }));
    expect(state.store.form.value.name).toBe('订单');
    await nextTick();
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, shiftKey: true, bubbles: true }));
    expect(state.store.form.value.name).toBe('改名一');
  });

  it('保存冲突时顶栏下方显示整条横幅，「核对版本」打开核对', async () => {
    await start();
    api.saveSchema.mockRejectedValueOnce(conflict);
    await edit();
    await state.onSave();
    await nextTick();
    const banner = wrapper.get('[data-testid="save-conflict-alert"]');
    expect(banner.text()).toContain('保存已暂停');
    expect(wrapper.get('[data-save-status]').attributes('data-tone')).toBe('danger');
    api.module.mockResolvedValue(remote('b'));
    await banner.get('[data-action="review-conflict"]').trigger('click');
    await flushPromises();
    expect(state.conflictReviewVisible).toBe(true);
    expect(state.conflictReview.server.schema_hash).toBe(hash('b'));
  });

  it('保存失败时顶栏「保存失败」带上服务端错误信息，再次保存成功后清除', async () => {
    await start();
    api.saveSchema.mockRejectedValueOnce({ code: 422, msg: '字段映射不能为空', data: { error: { code: 'FORM_SCHEMA_INVALID', requestId: 'r2', retryable: false, details: {} } } });
    await edit();
    await state.onSave();
    await nextTick();
    const status = () => wrapper.get('[data-save-status]');
    expect(status().text()).toContain('保存失败');
    expect(status().attributes('title')).toBe('字段映射不能为空（请求 ID：r2）');
    await edit('再次编辑');
    await state.onSave();
    await flushPromises();
    expect(status().text()).toContain('已保存');
    expect(status().attributes('title')).toBeUndefined();
  });

  it('返回按钮回到代码生成页', async () => {
    await start();
    await action('back-to-list').trigger('click');
    expect(router.push).toHaveBeenCalledWith('/admin/development/business/mine');
  });
});

describe('设计器外壳：表单设置', () => {
  it('「⚙ 表单设置」打开同一个编辑生成信息弹窗，并隐藏「打开表单设计器」', async () => {
    await start();
    await action('settings').trigger('click');
    await flushPromises();
    const dialog = wrapper.get('[data-edit-generation]');
    expect(dialog.attributes('data-module-id')).toBe('12');
    expect(dialog.attributes('data-hide-designer-link')).toBe('true');
    expect(ElMessageBox.confirm).not.toHaveBeenCalled();
  });

  it('有未保存编辑时先提示保存：取消不打开也不保存，确认后保存再打开', async () => {
    await start();
    await edit();
    vi.mocked(ElMessageBox.confirm).mockRejectedValueOnce('cancel');
    await state.openSettings();
    await nextTick();
    expect(wrapper.find('[data-edit-generation]').exists()).toBe(false);
    expect(api.saveSchema).not.toHaveBeenCalled();
    await state.openSettings();
    await flushPromises();
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    expect(state.store.dirty.value).toBe(false);
    expect(wrapper.find('[data-edit-generation]').exists()).toBe(true);
  });

  it('保存冲突暂停时不打开表单设置，提示先核对版本', async () => {
    await start();
    api.saveSchema.mockRejectedValueOnce(conflict);
    await edit();
    await state.onSave();
    await state.openSettings();
    await nextTick();
    expect(wrapper.find('[data-edit-generation]').exists()).toBe(false);
    expect(ElMessage.warning).toHaveBeenLastCalledWith('保存已暂停，请先核对版本处理冲突');
  });

  it('弹窗保存后重新加载模块，使 schema_hash 与文档和服务端一致', async () => {
    await start();
    await state.openSettings();
    await nextTick();
    api.module.mockResolvedValue(remote('d', '订单（设置后）'));
    child('[data-edit-generation]').vm.$emit('saved');
    await flushPromises();
    expect(state.store.form.value.schema_hash).toBe(hash('d'));
    expect(state.store.schemaDocument.value.title).toBe('订单（设置后）');
    expect(state.store.dirty.value).toBe(false);
    // 下一次编辑基于新版本保存，不会产生冲突。
    await edit('设置后编辑');
    await vi.advanceTimersByTimeAsync(1500);
    expect(api.saveSchema).toHaveBeenLastCalledWith(12, expect.objectContaining({ title: '设置后编辑' }), hash('d'), expect.any(String));
  });

  it('弹窗「保存并预览」在重新加载完成后打开预览代码', async () => {
    await start();
    await state.openSettings();
    await nextTick();
    const dialog = child('[data-edit-generation]');
    api.module.mockResolvedValue(remote('d'));
    dialog.vm.$emit('saved');
    dialog.vm.$emit('preview');
    await flushPromises();
    expect(state.store.form.value.schema_hash).toBe(hash('d'));
    expect(wrapper.get('[data-code-preview]').attributes('data-module-id')).toBe('12');
  });
});

describe('设计器外壳：预览代码与生成', () => {
  it('「预览代码」「生成」都打开预览代码弹窗', async () => {
    await start();
    await action('preview-code').trigger('click');
    expect(wrapper.find('[data-code-preview]').exists()).toBe(true);
    state.codePreviewVisible = false;
    await nextTick();
    await action('generate').trigger('click');
    expect(wrapper.get('[data-code-preview]').attributes('data-module-id')).toBe('12');
  });

  it('有未保存编辑时禁用，方法调用也拒绝', async () => {
    await start();
    await edit();
    expect(action('preview-code').attributes('disabled')).toBeDefined();
    expect(action('generate').attributes('disabled')).toBeDefined();
    state.openCodePreview();
    await nextTick();
    expect(wrapper.find('[data-code-preview]').exists()).toBe(false);
  });

  it('预览打开期间设计器产生编辑（如快捷键撤销）立即关闭预览，保存前不能再打开', async () => {
    await start();
    await edit('已保存编辑');
    await state.onSave();
    await nextTick();
    await action('preview-code').trigger('click');
    expect(wrapper.find('[data-code-preview]').exists()).toBe(true);
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', metaKey: true, bubbles: true }));
    await nextTick();
    expect(state.store.dirty.value).toBe(true);
    expect(wrapper.find('[data-code-preview]').exists()).toBe(false);
    expect(action('preview-code').attributes('disabled')).toBeDefined();
    expect(action('generate').attributes('disabled')).toBeDefined();
  });

  it('无预览权限时不显示预览代码与生成', async () => {
    const saved = actor.permissions;
    actor.permissions = saved.filter((code) => code !== 'development:business:preview-formal-generation');
    try {
      await start();
      expect(action('preview-code').exists()).toBe(false);
      expect(action('generate').exists()).toBe(false);
    } finally {
      actor.permissions = saved;
    }
  });

  it('核心目标生成完成后「打开运行时」打开生成的独立页面并先刷新菜单', async () => {
    await start();
    state.openCodePreview();
    await nextTick();
    child('[data-code-preview]').vm.$emit('generated', { generationId: 42, state: 'completed', resourceApplyStatus: 'applied', routePath: '/business/orders' });
    await nextTick();
    expect(state.businessTarget.locked).toBe(true);
    await action('open-runtime').trigger('click');
    await flushPromises();
    expect(menu.refreshBusinessMenu).toHaveBeenCalled();
    expect(router.push).toHaveBeenCalledWith('/business/orders');
  });
});

describe('设计器外壳：「⋯」菜单', () => {
  it('包含动态发布、版本历史、导出 JSON、打开运行时', async () => {
    await start();
    for (const name of ['dynamic-publish', 'versions', 'export-json', 'open-runtime']) expect(action(name).exists(), name).toBe(true);
  });

  it('动态发布复用预览代码弹窗的发布流程；有未保存编辑时不执行', async () => {
    await start();
    await action('dynamic-publish').trigger('click');
    await flushPromises();
    expect(codePreview.dynamicPublish).toHaveBeenCalledTimes(1);
    await edit();
    await state.openDynamicPublish();
    expect(codePreview.dynamicPublish).toHaveBeenCalledTimes(1);
  });

  it('插件目标不显示动态发布与打开运行时', async () => {
    api.module.mockResolvedValue(remote('a', '订单', { type: 'plugin', pluginCode: 'demo', scope: 'console', tableStrategy: 'owned' }));
    await start();
    expect(action('dynamic-publish').exists()).toBe(false);
    expect(action('open-runtime').exists()).toBe(false);
    expect(action('versions').exists()).toBe(true);
  });

  it('版本历史打开抽屉；回滚后重新加载设计器', async () => {
    await start();
    await action('versions').trigger('click');
    const drawer = child('[data-version-history]');
    expect(drawer.attributes('data-hash')).toBe(hash('a'));
    api.module.mockResolvedValue(remote('e', '回滚版本'));
    drawer.vm.$emit('rollback', { version: 3 });
    await flushPromises();
    expect(state.store.form.value.schema_hash).toBe(hash('e'));
    expect(state.store.form.value.name).toBe('回滚版本');
    expect(wrapper.find('[data-version-history]').exists()).toBe(false);
  });

  it('导出 JSON 调用服务端导出并下载文件', async () => {
    api.exportSchema.mockResolvedValue({ document: '{"schemaVersion":2}' });
    const createObjectURL = vi.fn(() => 'blob:schema');
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL, revokeObjectURL: vi.fn() }));
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
      expect(this.download).toBe('orders.schema.json');
    });
    await start();
    await action('export-json').trigger('click');
    await flushPromises();
    expect(api.exportSchema).toHaveBeenCalledWith(12, expect.objectContaining({ key: 'orders', schemaVersion: 2 }));
    expect(click).toHaveBeenCalledTimes(1);
  });

  it('打开运行时：未生成时打开模块运行时路由', async () => {
    await start();
    await action('open-runtime').trigger('click');
    await flushPromises();
    expect(router.push).toHaveBeenCalledWith('/development/business/runtime/orders');
  });
});

describe('表单设计页签：三栏', () => {
  // 只在本组用例内模拟视口宽度；未模拟时 jsdom 没有 matchMedia，按宽屏布局。
  const viewport = (width: number) => vi.stubGlobal('matchMedia', (query: string) => {
    const max = Number(/max-width:\s*(\d+)px/.exec(query)?.[1] ?? Infinity);
    return { matches: width <= max, media: query, addEventListener() {}, removeEventListener() {} };
  });
  const props = (selector: string) => wrapper.findComponent(selector) as unknown as VueWrapper<any>;

  it('编辑模式：左栏控件/大纲、中间画布与工具行、右栏属性；不再有大纲抽屉入口', async () => {
    await start();
    expect(wrapper.get('[data-designer-left]').findComponent({ name: 'DesignerLeftPanel' }).exists()).toBe(true);
    expect(wrapper.get('[data-designer-center] [data-canvas-toolbar]').text()).toContain('0 个字段 · 0 个布局');
    expect(wrapper.get('[data-designer-right] el-empty').attributes('description')).toBe('点选画布字段编辑参数');
    expect(wrapper.text()).not.toContain('表单大纲');
    const group = state.store.addNode('group');
    state.store.addNode('input', group.id);
    await nextTick();
    expect(wrapper.get('[data-canvas-toolbar]').text()).toContain('1 个字段 · 1 个布局');
    const panel = props('[data-designer-right] [data-props-panel]');
    expect(panel.props('groupTitle')).toBe(group.title);
    expect(panel.props('node')).toStrictEqual(state.store.selectedNode.value);
    expect(panel.props('node').field).toBe(state.store.selectedNode.value.field);
  });

  it('预览模式收起左右栏，页签行显示 创建/编辑/只读/搜索 与「预览数据」', async () => {
    await start();
    await wrapper.get('[data-mode="desktop"]').trigger('click');
    expect(wrapper.find('[data-designer-left]').exists()).toBe(false);
    expect(wrapper.find('[data-designer-right]').exists()).toBe(false);
    expect(wrapper.find('[data-canvas-toolbar]').exists()).toBe(false);
    const tools = wrapper.get('[data-preview-tools]');
    // 保持改版前的四种预览状态，不丢失「搜索」；渲染器无独立详情模式，故不新增「详情」。
    expect(tools.findAll('[data-preview-mode]').map((item) => [item.attributes('data-preview-mode'), item.text()])).toEqual([['create', '创建'], ['edit', '编辑'], ['readonly', '只读'], ['search', '搜索']]);
    expect(tools.text()).toContain('预览数据');
    state.previewMode = 'readonly';
    await nextTick();
    expect(state.previewSchema.form).toMatchObject({ mode: 'readonly', readOnly: true });
    state.previewMode = 'search';
    await nextTick();
    expect(state.previewSchema.form).toMatchObject({ mode: 'search', readOnly: false });
    await wrapper.get('[data-mode="edit"]').trigger('click');
    expect(wrapper.find('[data-designer-left]').exists()).toBe(true);
    expect(wrapper.find('[data-preview-tools]').exists()).toBe(false);
  });

  it('窄屏 <1100px：属性栏变为抽屉，选中字段时弹出', async () => {
    viewport(1000);
    await start();
    expect(wrapper.find('[data-designer-right]').exists()).toBe(false);
    expect(wrapper.find('[data-designer-left]').exists()).toBe(true);
    expect(props('[data-props-drawer]').props('modelValue')).toBe(false);
    state.store.addNode('input');
    await nextTick();
    expect(props('[data-props-drawer]').props('modelValue')).toBe(true);
    expect(props('[data-props-drawer]').props('size')).toBe('min(360px, 100vw)');
  });

  it('窄屏 <760px：控件栏也收成抽屉，由切换按钮打开，点击追加后关闭', async () => {
    viewport(700);
    await start();
    expect(wrapper.find('[data-designer-left]').exists()).toBe(false);
    expect(props('[data-left-drawer]').props('modelValue')).toBe(false);
    await wrapper.get('[data-action="toggle-left-panel"]').trigger('click');
    expect(props('[data-left-drawer]').props('modelValue')).toBe(true);
    props('[data-left-drawer]').findComponent({ name: 'DesignerLeftPanel' }).vm.$emit('added');
    await nextTick();
    expect(props('[data-left-drawer]').props('modelValue')).toBe(false);
  });
});
