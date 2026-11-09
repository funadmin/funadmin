import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { createPinia } from 'pinia';
import designerSource from './index.vue?raw';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';
import { service } from '@/utils/http';

const menuChoices = vi.hoisted(() => ({ tree: vi.fn() }));
const api = vi.hoisted(() => ({ module: vi.fn(), saveSchema: vi.fn(), savePublishConfig: vi.fn(), previewFormalGeneration: vi.fn(), formalGeneration: vi.fn(), previewPublish: vi.fn(), publish: vi.fn(), generation: vi.fn() }));
// 预览代码 / 生成 / 动态发布由代码生成的 CodePreviewDialog 承担（其流程测试见 codegen/CodePreviewDialog.spec.ts）。
const codePreview = vi.hoisted(() => ({ dynamicPublish: vi.fn() }));
vi.mock('@/api/development/business', async (original) => ({ ...await original<object>(), businessDevelopmentApi: api }));
vi.mock('vue-router', () => ({ useRoute: () => ({ query: { moduleId: 12 } }), useRouter: () => ({}), onBeforeRouteLeave: vi.fn() }));
// 兼容 t(key, fallback) 与 t(key, params, fallback)，按参数插值。
vi.mock('vue-i18n', async importOriginal => ({ ...await importOriginal<typeof import('vue-i18n')>(), useI18n: () => ({ t: (_: string, a: unknown, b?: string) => {
  const template = typeof a === 'string' ? a : String(b ?? '');
  const params = (typeof a === 'object' && a ? a : {}) as Record<string, unknown>;
  return template.replace(/\{(\w+)\}/g, (match, key: string) => (key in params ? String(params[key]) : match));
} }) }));
vi.mock('@/api/system/permission', () => ({ permissionApi: { tree: async () => [] } }));
vi.mock('@/api/system/menu', () => ({ menuApi: menuChoices }));
// 冲突与生成测试使用明确授权的设计人员，仍由公共判断验证真实动作码。
const actor = vi.hoisted(() => ({ permissions: [
  'development:business:module', 'development:business:save-schema',
  'development:business:save-publish-config', 'development:business:preview-publish',
  'development:business:publish', 'development:business:preview-formal-generation',
  'development:business:formal-generation', 'development:business:generation',
  'development:business:preview-formal-generation', 'development:business:formal-generation', 'development:business:generate', 'development:business:retry-resources', 'development:business:apply-resources'
] }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => actor }));
vi.mock('../schema/pluginComponentLoader', () => ({ loadPluginFormComponents: async () => {} }));
vi.mock('../../development/business/composables/useBusinessMenuRefresh', () => ({ useBusinessMenuRefresh: () => ({ refreshBusinessMenu: vi.fn() }) }));
vi.mock('sortablejs', () => ({ default: { create: () => ({ destroy() {} }) } }));
vi.mock('element-plus', () => ({ ElMessage: { warning: vi.fn(), success: vi.fn(), error: vi.fn() }, ElMessageBox: { confirm: vi.fn() } }));
import { ElMessage, ElMessageBox } from 'element-plus';
import Designer from './index.vue';

const hash = (letter: string) => letter.repeat(64);
const remote = (letter = 'a', title = '服务端') => ({
  module: { id: 12 },
  form: { id: 7, name: title, form_key: 'orders', table_name: 'fun_orders', schema_hash: hash(letter), schema_document: { schemaVersion: 2, key: 'orders', title, nodes: [] } }
});
const conflict = { code: 409, msg: '冲突', data: { error: { code: 'FORM_SCHEMA_CONFLICT', requestId: 'r1', retryable: true, details: {} } } };
const draft = () => JSON.parse(localStorage.getItem('form-designer-draft:7')!);
let wrapper: ReturnType<typeof shallowMount>;
let state: any;
const start = async () => {
  wrapper = shallowMount(Designer, { global: { plugins: [createPinia()], renderStubDefaultSlot: true, directives: { perm: {} }, stubs: {
    ...Object.fromEntries(['ElDropdown', 'ElDropdownMenu', 'ElDropdownItem', 'ElTag', 'ElRadioButton', 'ElRadioGroup', 'ElButton', 'ElOption', 'ElSelect', 'ElAlert', 'ElCard', 'ElFormItem', 'ElForm', 'ElInput', 'ElDivider', 'ElEmpty', 'ElSteps', 'ElStep', 'ElTreeSelect', 'ElCheckbox', 'ElSwitch', 'ElDescriptionsItem', 'ElDescriptions', 'ElTableColumn', 'ElTable', 'ElCollapseItem', 'ElCollapse', 'ElTabPane', 'ElTabs', 'ElResult'].map((name) => [name, true])),
    ElButton: { props: ['disabled', 'loading'], template: '<button :disabled="disabled || loading"><slot /></button>' },
    PageWrapper: { template: '<main><slot /></main>' },
    ElCard: { template: '<section><header><slot name="header" /></header><slot /></section>' },
    ElDrawer: { props: ['modelValue', 'title', 'size'], template: '<aside v-if="modelValue" role="dialog"><h2>{{ title }}</h2><button aria-label="关闭表单大纲" @click="$emit(\'update:modelValue\', false)">关闭</button><slot /></aside>' },
    SchemaNodeTree: false,
    DesignerLeftPanel: false,
    DesignerCanvas: false,
    DesignerCanvasNode: false,
    ElDialog: { props: ['modelValue'], template: '<section v-if="modelValue"><slot /><slot name="footer" /></section>' },
    DesignerTopBar: false,
    ElTooltip: { template: '<span><slot /></span>' },
    ElDropdown: { template: '<div><slot /><slot name="dropdown" /></div>' },
    ElDropdownMenu: { template: '<ul><slot /></ul>' },
    ElDropdownItem: { inheritAttrs: false, props: ['disabled'], emits: ['click'], template: '<li><button v-bind="$attrs" :disabled="disabled" @click="$emit(\'click\')"><slot /></button></li>' },
    CodePreviewDialog: { props: ['modelValue', 'module'], emits: ['update:modelValue', 'generated', 'published'], methods: { dynamicPublish: codePreview.dynamicPublish }, template: '<div v-if="modelValue" data-code-preview :data-module-id="module && module.id" />' },
    EditGenerationDialog: { props: ['modelValue', 'moduleId', 'hideDesignerLink'], emits: ['update:modelValue', 'saved', 'preview'], template: '<div v-if="modelValue" data-edit-generation />' }
  } } });
  await flushPromises();
  state = (wrapper.vm as any).$.setupState;
};
const pause = async () => {
  state.store.updateForm({ name: '本地编辑' });
  api.saveSchema.mockRejectedValueOnce(conflict);
  await state.onSave();
  await nextTick();
};
const review = async () => {
  api.module.mockResolvedValue(remote('b', '服务端新版本'));
  await state.reviewSaveConflict();
  await nextTick();
};

beforeEach(() => {
  vi.useFakeTimers();
  vi.clearAllMocks();
  const storage = new Map<string, string>();
  vi.stubGlobal('localStorage', { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key) });
  menuChoices.tree.mockResolvedValue([{ id: 1, name: '系统管理', sourceType: 'system', sourceName: 'system', children: [] }]);
  api.module.mockResolvedValue(remote());
  api.saveSchema.mockImplementation(async (_id, document) => ({ document, schemaHash: hash('c') }));
  vi.mocked(ElMessageBox.confirm).mockResolvedValue('confirm' as never);
});
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('设计器测试请求边界', () => {
  it('初始化不触发真实 HTTP transport，也不再加载所属菜单', async () => {
    const originalAdapter = service.defaults.adapter;
    const unexpectedRequest = vi.fn(async () => { throw new Error('设计器测试不应访问真实 HTTP transport'); });
    service.defaults.adapter = unexpectedRequest;
    try {
      await start();
      expect(unexpectedRequest).not.toHaveBeenCalled();
      // 所属菜单已移入「⚙ 表单设置」（EditGenerationDialog 打开时自行加载菜单树），设计器初始化不再请求。
      expect(menuChoices.tree).not.toHaveBeenCalled();
    } finally {
      service.defaults.adapter = originalAdapter;
    }
  });
});

describe('常驻表单大纲（左栏页签）', () => {
  // 大纲从抽屉改为左栏「大纲」页签：默认显示「控件」，切换页签即显示/隐藏大纲树。
  const open = async () => {
    const tab = wrapper.find('[data-left-tab="outline"]');
    expect(tab.exists(), '左栏提供大纲页签').toBe(true);
    await tab.trigger('click');
  };
  const close = async () => { await wrapper.get('[data-left-tab="controls"]').trigger('click'); };
  const treeVisible = () => {
    const tree = wrapper.find('.schema-node-tree');
    return tree.exists() && (tree.element.closest('[role="tabpanel"]') as HTMLElement).style.display !== 'none';
  };
  it.each(['basic', 'advanced'])('%s 默认不显示树或来源提示，切到大纲页签显示，切回隐藏', async (mode) => {
    await start();
    state.designerMode = mode;
    await nextTick();
    expect(treeVisible()).toBe(false);
    expect(wrapper.html()).not.toContain('Schema 来源');
    expect(wrapper.text()).not.toContain('FormSchema v2 AST 节点树');
    await open();
    expect(wrapper.get('[data-left-tab="outline"]').attributes('aria-selected')).toBe('true');
    expect(treeVisible()).toBe(true);
    await close();
    expect(treeVisible()).toBe(false);
  });
  it('标题使用真实字段名与中文类型，布局不伪造字段；选择同步画布', async () => {
    await start();
    state.store.addNode('input');
    state.store.addNode('group');
    const field = state.store.nodes.value[0];
    await open();
    const rows = wrapper.findAll('.schema-tree-row');
    expect(rows[0].get('.schema-tree-title').text()).toBe(field.title);
    expect(rows[0].get('.schema-tree-field').text()).toBe(field.field);
    expect(rows[0].text()).toContain('单行输入');
    expect(rows[1].text()).toContain('分组');
    expect(rows[1].text()).not.toContain('（');
    await rows[0].trigger('click');
    expect(wrapper.get(`.designer-node[data-node-id="${field.id}"]`).attributes('aria-selected')).toBe('true');
    expect(state.store.selectedNode.value.field).toBe(field.field);
    await wrapper.get(`.designer-node[data-node-id="${state.store.nodes.value[1].id}"]`).trigger('click');
    expect(rows[1].classes()).toContain('is-selected');
  });
  it('大纲内移动、复制、添加分组保留真实结构操作', async () => {
    await start();
    state.store.addNode('input'); state.store.addNode('select');
    const firstId = state.store.nodes.value[0].id;
    await open();
    await wrapper.findAll('.schema-tree-row')[0].get('[title="下移"]').trigger('click');
    expect(state.store.nodes.value[1].id).toBe(firstId);
    await wrapper.findAll('.schema-tree-row')[1].get('[title="复制"]').trigger('click');
    expect(state.store.nodes.value).toHaveLength(3);
    expect(new Set(state.store.nodes.value.map((node: any) => node.field)).size).toBe(3);
    const addGroup = wrapper.get('[data-action="add-layout-group"]');
    expect(addGroup.text()).toBe('添加布局分组');
    await addGroup.trigger('click');
    expect(state.store.nodes.value[3].type).toBe('group');
  });
  it('切换页签不改变 Schema、历史、dirty、保存状态或草稿', async () => {
    await start();
    const schema = JSON.stringify(state.store.schemaDocument.value);
    localStorage.setItem('form-designer-draft:7', '保留草稿');
    await open();
    await close();
    await vi.advanceTimersByTimeAsync(5000);
    expect(state.store.dirty.value).toBe(false);
    expect(state.store.saveStatus.value).toBe('saved');
    expect(state.store.canUndo.value).toBe(false);
    expect(JSON.stringify(state.store.schemaDocument.value)).toBe(schema);
    expect(localStorage.getItem('form-designer-draft:7')).toBe('保留草稿');
    expect(api.saveSchema).not.toHaveBeenCalled();
    await pause();
    await vi.advanceTimersByTimeAsync(1000);
    const savedDraft = localStorage.getItem('form-designer-draft:7');
    await open();
    await close();
    await vi.advanceTimersByTimeAsync(5000);
    expect(state.store.dirty.value).toBe(true);
    expect(state.saveBlocked).toBe(true);
    expect(localStorage.getItem('form-designer-draft:7')).toBe(savedDraft);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
  });
  it.each(['desktop', 'tablet', 'mobile'])('切换 %s 预览收起左栏与大纲，返回编辑不自动打开大纲', async (mode) => {
    await start(); await open();
    state.workspaceMode = mode; await nextTick();
    expect(wrapper.find('.schema-node-tree').exists()).toBe(false);
    expect(wrapper.findAll('[data-canvas-toolbar] button')).toHaveLength(0);
    expect(state.activeTab).toBe('design');
    state.workspaceMode = 'edit'; await nextTick();
    expect(treeVisible()).toBe(false);
  });
  it('窄屏抽屉宽度限制在视口内', async () => {
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: 700 <= Number(/max-width:\s*(\d+)px/.exec(query)?.[1] ?? Infinity), media: query, addEventListener() {}, removeEventListener() {} }));
    await start();
    await wrapper.get('[data-action="toggle-left-panel"]').trigger('click');
    expect(wrapper.getComponent<any>('[role="dialog"]').props('size')).toBe('min(288px, 100vw)');
    await open();
    expect(treeVisible()).toBe(true);
  });
});

describe('插件业务生成闭环', () => {
  const pluginRemote = () => ({ ...remote(), module: { id: 12, runtime_route: '/development/business/runtime/orders', metadata: { target: { type: 'plugin', pluginCode: 'demo', scope: 'console', tableStrategy: 'owned', locked: true } } } });
  const menuItem = (action: string) => wrapper.find(`[data-action="${action}"]`);
  it('展示所属插件和锁定，隐藏动态发布且方法也拒绝调用', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    expect(wrapper.text()).toContain('demo');
    expect(wrapper.text()).toContain('已锁定');
    expect(wrapper.findAll('button').some((button) => button.text() === '动态发布')).toBe(false);
    await state.openDynamicPublish();
    expect(codePreview.dynamicPublish).not.toHaveBeenCalled();
    expect(api.previewPublish).not.toHaveBeenCalled();
  });
  it('保存草稿后直接预览生成，完成提示待发布而不是打开运行时', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    state.store.updateForm({ name: '新草稿' });
    await nextTick();
    expect(menuItem('generate').attributes('disabled'), '未保存时不能生成').toBeDefined();
    await state.onSave();
    await nextTick();
    await menuItem('generate').trigger('click');
    expect(wrapper.get('[data-code-preview]').attributes('data-module-id')).toBe('12');
    expect(api.publish).not.toHaveBeenCalled();
    (wrapper.getComponent('[data-code-preview]') as unknown as VueWrapper).vm.$emit('generated', { generationId: 42, state: 'completed', resourceApplyStatus: 'pending_publication', routePath: '/plugins/demo/orders' });
    await nextTick();
    // 插件目标不提供运行时入口，生成结果的路由也不被当作运行时。
    expect(menuItem('open-runtime').exists()).toBe(false);
    expect(state.runtimeRoute).toBe('');
  });
  it('Schema 变化立即关闭已打开的预览，未保存时不能重新打开', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    state.openCodePreview();
    await nextTick();
    expect(wrapper.find('[data-code-preview]').exists()).toBe(true);
    state.store.updateForm({ name: '预览期间编辑' });
    await nextTick();
    expect(wrapper.find('[data-code-preview]').exists()).toBe(false);
    state.openCodePreview();
    await nextTick();
    expect(wrapper.find('[data-code-preview]').exists()).toBe(false);
  });
  it('目标变化使已打开的预览失效', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    state.openCodePreview();
    await nextTick();
    state.businessModule.metadata.target.pluginCode = 'other';
    await nextTick();
    expect(state.codePreviewVisible).toBe(false);
  });
  it('保存冲突暂停时预览与生成都不可用', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start(); await pause();
    expect(menuItem('preview-code').attributes('disabled')).toBeDefined();
    expect(menuItem('generate').attributes('disabled')).toBeDefined();
    state.openCodePreview();
    await nextTick();
    expect(wrapper.find('[data-code-preview]').exists()).toBe(false);
  });
});

describe('保存冲突的明确恢复', () => {
  it('核对弹窗提供双卡片、完整只读 JSON、次级完整 hash 和分组操作', async () => {
    await start(); await pause(); await review();
    const dialog = wrapper.get('.save-conflict-dialog');
    const cards = dialog.findAll('.save-conflict-card');
    expect(cards).toHaveLength(2);
    expect(cards.map((card) => JSON.parse((card.get('textarea').element as HTMLTextAreaElement).value)))
      .toEqual([state.conflictReview.local, state.conflictReview.server]);
    expect(dialog.findAll('textarea[readonly]')).toHaveLength(2);
    expect(dialog.get('.save-conflict-hash').text()).toContain(hash('b'));
    expect(dialog.get('.save-conflict-notice').text()).toContain('保存已暂停');
    expect(dialog.get('.save-conflict-risk').text()).toContain('放弃本地');
    expect(dialog.get('.save-conflict-footer > button').text()).toBe('重新核对版本');
    expect(dialog.findAll('.save-conflict-actions button').map((button) => button.text()))
      .toEqual(['取消，保留本地', '放弃本地，采用服务端', '以核对后的本地覆盖']);
  });

  it('布局约束包含安全间距、等宽卡片、内部滚动和窄屏堆叠', () => {
    expect(designerSource).toContain('max-width: 1280px');
    expect(designerSource).toContain('calc(100% - 32px)');
    expect(designerSource).toContain('max-height: calc(100dvh - 32px)');
    expect(designerSource).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))');
    expect(designerSource).toContain('@media (max-width: 899px)');
    expect(designerSource).toContain(':global(.save-conflict-dialog)');
    expect(designerSource).toMatch(/:global\(\.save-conflict-dialog \.el-dialog__body\)[\s\S]*?overflow-y: auto/);
    expect(designerSource).toMatch(/:global\(\.save-conflict-dialog \.el-dialog__footer\)[\s\S]*?flex-shrink: 0/);
    expect(designerSource).toContain('overflow-wrap: anywhere');
    expect(designerSource).toContain('flex-wrap: wrap');
  });

  it('弹窗文案中英键一致且包含风险说明', () => {
    const zh = (zhCN.formDesigner as any).saveConflict;
    const en = (enUS.formDesigner as any).saveConflict;
    expect(zh).toBeDefined();
    expect(en).toBeDefined();
    expect(Object.keys(zh).sort()).toEqual(Object.keys(en).sort());
    for (const value of Object.values(en)) expect(value).toEqual(expect.any(String));
    expect(zh.risk).toContain('放弃本地');
  });

  it('按钮取消保留草稿，读取和处理期间阻止危险操作并保留二次确认', async () => {
    await start(); await pause(); await review();
    const actions = () => wrapper.get('.save-conflict-actions').findAll('button');
    vi.mocked(ElMessageBox.confirm).mockRejectedValue(new Error('cancel'));
    await actions()[1].trigger('click');
    expect(ElMessageBox.confirm).toHaveBeenCalledWith(expect.stringContaining('放弃本地'), expect.any(String), expect.any(Object));
    await actions()[2].trigger('click');
    expect(ElMessageBox.confirm).toHaveBeenCalledWith(expect.stringContaining('覆盖'), expect.any(String), expect.any(Object));
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    state.conflictReviewLoading = true;
    await nextTick();
    expect(actions().slice(1).every((button) => (button.element as HTMLButtonElement).disabled)).toBe(true);
    state.conflictReviewLoading = false;
    state.conflictResolving = true;
    await nextTick();
    expect(wrapper.get('.save-conflict-footer').findAll('button').every((button) => (button.element as HTMLButtonElement).disabled)).toBe(true);
    const dialog = wrapper.getComponent('.save-conflict-dialog');
    expect(dialog.attributes('close-on-click-modal')).toBe('false');
    expect(dialog.attributes('close-on-press-escape')).toBe('false');
    expect(dialog.attributes('show-close')).toBe('false');
    state.conflictResolving = false;
    await nextTick();
    await actions()[0].trigger('click');
    expect(state.conflictReviewVisible).toBe(false);
    expect(state.saveBlocked).toBe(true);
    expect(draft().definition.name).toBe('本地编辑');
  });

  it('冲突持久提示、暂停自动保存，保存按钮进入核对而非静默返回', async () => {
    await start(); await pause();
    expect(draft().saveBlocked).toBe(true);
    expect(wrapper.find('[data-testid="save-conflict-alert"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('保存已暂停');
    await vi.advanceTimersByTimeAsync(5000);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    await state.onSave();
    expect(api.module).toHaveBeenCalledTimes(2);
    expect(state.conflictReviewVisible).toBe(true);
  });

  it('刷新恢复暂停草稿仍有核对入口，保留本地且不自动提交', async () => {
    await start(); await pause(); wrapper.unmount();
    api.module.mockResolvedValue(remote('b'));
    await start();
    expect(state.store.form.value.name).toBe('本地编辑');
    expect(wrapper.find('[data-testid="save-conflict-alert"]').exists()).toBe(true);
    await vi.advanceTimersByTimeAsync(5000);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    await review();
    expect(state.conflictReview.local.schema_document.title).toBe('本地编辑');
    expect(state.conflictReview.server.schema_hash).toBe(hash('b'));
    expect(wrapper.findAll('textarea[readonly]')).toHaveLength(2);
    expect(wrapper.text()).toContain(hash('b'));
  });

  it('明确采用本地只提交已核对快照和 hash，成功后恢复自动保存', async () => {
    await start(); await pause(); await review();
    await state.resolveSaveConflict('local');
    expect(api.saveSchema).toHaveBeenLastCalledWith(12, expect.objectContaining({ title: '本地编辑' }), hash('b'), expect.any(String));
    expect(state.saveBlocked).toBe(false);
    expect(state.store.form.value.schema_hash).toBe(hash('c'));
    expect(state.store.dirty.value).toBe(false);
    state.store.updateForm({ name: '恢复后编辑' });
    await nextTick(); await vi.advanceTimersByTimeAsync(1500);
    expect(api.saveSchema).toHaveBeenCalledTimes(3);
  });

  it('明确确认放弃本地才采用服务端，不发送覆盖请求', async () => {
    await start(); await pause(); await review();
    await state.resolveSaveConflict('server');
    expect(ElMessageBox.confirm).toHaveBeenCalledWith(expect.stringContaining('放弃本地'), expect.any(String), expect.any(Object));
    expect(state.store.form.value.name).toBe('服务端新版本');
    expect(state.store.form.value.schema_hash).toBe(hash('b'));
    expect(state.saveBlocked).toBe(false);
    expect(state.store.dirty.value).toBe(false);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    state.store.updateForm({ name: '采用服务端后编辑' });
    await nextTick(); await vi.advanceTimersByTimeAsync(1500);
    expect(api.saveSchema).toHaveBeenLastCalledWith(12, expect.objectContaining({ title: '采用服务端后编辑' }), hash('b'), expect.any(String));
  });

  it.each(['local', 'server'])('取消 %s 确认不丢编辑、不清除暂停和草稿', async (choice) => {
    await start(); await pause(); await review();
    vi.mocked(ElMessageBox.confirm).mockRejectedValue(new Error('cancel'));
    await state.resolveSaveConflict(choice);
    expect(state.store.form.value.name).toBe('本地编辑');
    expect(state.saveBlocked).toBe(true);
    expect(draft().definition.name).toBe('本地编辑');
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
  });

  it('服务端再次变化返回409，失效核对结果并继续暂停，无自动重试', async () => {
    await start(); await pause(); await review();
    api.saveSchema.mockRejectedValueOnce(conflict);
    await state.resolveSaveConflict('local');
    expect(state.saveBlocked).toBe(true);
    expect(state.conflictReview).toBeNull();
    expect(draft().definition.name).toBe('本地编辑');
    await vi.advanceTimersByTimeAsync(5000);
    expect(api.saveSchema).toHaveBeenCalledTimes(2);
  });

  it.each(['local', 'server'])('核对后新增编辑使 %s 选择失效，不提交或丢弃未核对内容', async (choice) => {
    await start(); await pause(); await review();
    state.store.updateForm({ name: '核对后编辑' });
    await state.resolveSaveConflict(choice);
    expect(state.saveBlocked).toBe(true);
    expect(state.conflictReview).toBeNull();
    expect(state.store.form.value.name).toBe('核对后编辑');
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
  });

  it('覆盖请求期间的新编辑不会进入请求或随后自动提交，保留草稿等待重新核对', async () => {
    await start(); await pause(); await review();
    let finish!: (value: unknown) => void;
    api.saveSchema.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.resolveSaveConflict('local');
    await flushPromises(); // 二次确认改为 ElMessageBox 异步确认：先放行确认微任务派发覆盖请求
    state.store.updateForm({ name: '请求期间编辑' });
    const sent = api.saveSchema.mock.calls.at(-1)![1];
    expect(sent.title).toBe('本地编辑');
    finish({ document: sent, schemaHash: hash('c') });
    await pending;
    expect(state.store.form.value.name).toBe('请求期间编辑');
    expect(state.store.form.value.schema_hash).toBe(hash('c'));
    expect(state.saveBlocked).toBe(true);
    expect(draft().definition.name).toBe('请求期间编辑');
    await vi.advanceTimersByTimeAsync(5000);
    expect(api.saveSchema).toHaveBeenCalledTimes(2);
  });

  it('核对请求期间继续编辑后必须重新核对', async () => {
    await start(); await pause();
    let finish!: (value: unknown) => void;
    api.module.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.reviewSaveConflict();
    state.store.updateForm({ name: '读取期间编辑' });
    finish(remote('b'));
    await pending;
    await state.resolveSaveConflict('local');
    expect(state.conflictReview).toBeNull();
    expect(state.saveBlocked).toBe(true);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    expect(draft().definition.name).toBe('读取期间编辑');
  });

  it('取消核对弹窗不丢编辑，迟到的读取结果不能重新开放选择', async () => {
    await start(); await pause();
    let finish!: (value: unknown) => void;
    api.module.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.reviewSaveConflict();
    state.cancelConflictReview();
    finish(remote('b'));
    await pending;
    expect(state.conflictReviewVisible).toBe(false);
    expect(state.conflictReview).toBeNull();
    expect(state.saveBlocked).toBe(true);
    expect(draft().definition.name).toBe('本地编辑');
  });

  it('缺失服务端 hash 时不能恢复或覆盖', async () => {
    await start(); await pause();
    api.module.mockResolvedValueOnce(remote(''));
    await state.reviewSaveConflict();
    expect(state.conflictReview).toBeNull();
    expect(state.conflictReviewError).toContain('hash 缺失');
    expect(state.saveBlocked).toBe(true);
  });

  it('停用设计器阻止迟到核对结果、恢复操作和自动保存，保留草稿', async () => {
    await start(); await pause();
    let finish!: (value: unknown) => void;
    api.module.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.reviewSaveConflict();
    state.deactivateDesigner();
    finish(remote('b'));
    await pending;
    await state.resolveSaveConflict('local');
    await vi.advanceTimersByTimeAsync(5000);
    expect(state.conflictReview).toBeNull();
    expect(state.saveBlocked).toBe(true);
    expect(draft().definition.name).toBe('本地编辑');
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
  });

  it('获取版本失败不清暂停且保留本地', async () => {
    await start(); await pause();
    api.module.mockRejectedValueOnce(new Error('网络不可用'));
    await state.reviewSaveConflict();
    expect(state.saveBlocked).toBe(true);
    expect(state.conflictReview).toBeNull();
    expect(state.conflictReviewError).toContain('网络不可用');
    expect(draft().definition.name).toBe('本地编辑');
  });
});

describe('保存前兜底：分类树映射未补全按未开启保存', () => {
  const staleTree = { enabled: true, source: { type: 'current' }, mapping: { valueField: '', labelField: '', targetField: '' } };
  const staleRemote = () => {
    const data = remote();
    return { ...data, form: { ...data.form, schema_document: { ...data.form.schema_document, list: { leftTree: staleTree } } } };
  };
  const warned = () => vi.mocked(ElMessage.warning).mock.calls.map(([message]) => String(message));

  it('自动保存发送关闭的分类树并保留映射草稿，提示缺少项；本地同步为已保存值，不再循环保存', async () => {
    api.module.mockResolvedValue(staleRemote());
    await start();
    state.store.updateForm({ name: '本地编辑' });
    await nextTick(); await vi.advanceTimersByTimeAsync(1500);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
    const sent = api.saveSchema.mock.calls[0][1];
    expect(sent.list.leftTree).toEqual({ ...staleTree, enabled: false });
    expect(warned()).toContain('左侧分类树的字段对应未填完（缺少：来源主键 / 显示字段 / 右表关联字段），已按未开启保存');
    expect(state.store.schemaDocument.value.list.leftTree.enabled).toBe(false);
    expect(state.store.dirty.value).toBe(false);
    await vi.advanceTimersByTimeAsync(5000);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
  });

  it('映射完整或未开启时原样发送，不提示', async () => {
    await start();
    state.store.updateList({ leftTree: { ...staleTree, mapping: { valueField: 'id', labelField: 'title', targetField: 'cid' } } });
    await state.onSave();
    expect(api.saveSchema.mock.calls[0][1].list.leftTree.enabled).toBe(true);
    expect(warned().some((message) => message.includes('已按未开启保存'))).toBe(false);
  });

  it('冲突核对后采用本地同样发送关闭的分类树', async () => {
    api.module.mockResolvedValue(staleRemote());
    await start(); await pause(); await review();
    await state.resolveSaveConflict('local');
    expect(api.saveSchema).toHaveBeenCalledTimes(2);
    expect(api.saveSchema.mock.calls[1][1].list.leftTree).toEqual({ ...staleTree, enabled: false });
    expect(warned().some((message) => message.includes('已按未开启保存'))).toBe(true);
    expect(state.store.schemaDocument.value.list.leftTree.enabled).toBe(false);
  });
});
