import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { createPinia } from 'pinia';
import designerSource from './index.vue?raw';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';

const api = vi.hoisted(() => ({ module: vi.fn(), saveSchema: vi.fn(), savePublishConfig: vi.fn(), previewFormalGeneration: vi.fn(), formalGeneration: vi.fn(), previewPublish: vi.fn(), publish: vi.fn(), generation: vi.fn() }));
vi.mock('@/api/development/business', async (original) => ({ ...await original<object>(), businessDevelopmentApi: api }));
vi.mock('vue-router', () => ({ useRoute: () => ({ query: { moduleId: 12 } }), useRouter: () => ({}), onBeforeRouteLeave: vi.fn() }));
vi.mock('vue-i18n', async importOriginal => ({ ...await importOriginal<typeof import('vue-i18n')>(), useI18n: () => ({ t: (_: string, fallback: string) => fallback }) }));
vi.mock('@/api/system/permission', () => ({ permissionApi: { tree: async () => [] } }));
// 用户权限不是冲突测试的目标；导入前隔离会话读取，草稿仍使用每例独立的 localStorage。
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: [] }) }));
vi.mock('../schema/pluginComponentLoader', () => ({ loadPluginFormComponents: async () => {} }));
vi.mock('../../development/business/composables/useBusinessMenuRefresh', () => ({ useBusinessMenuRefresh: () => ({ refreshBusinessMenu: vi.fn() }) }));
vi.mock('sortablejs', () => ({ default: { create: () => ({ destroy() {} }) } }));
vi.mock('element-plus', () => ({ ElMessage: { warning: vi.fn(), success: vi.fn(), error: vi.fn() }, ElMessageBox: { confirm: vi.fn() } }));
import { ElMessageBox } from 'element-plus';
import Designer from './index.vue';

const hash = (letter: string) => letter.repeat(64);
const remote = (letter = 'a', title = '服务端') => ({
  module: { id: 12 }, fields: [],
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
    DesignerCanvas: false,
    DesignerCanvasNode: false,
    ElDialog: { props: ['modelValue'], template: '<section v-if="modelValue"><slot /><slot name="footer" /></section>' }
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
  api.module.mockResolvedValue(remote());
  api.saveSchema.mockImplementation(async (_id, document) => ({ document, schemaHash: hash('c') }));
  vi.mocked(ElMessageBox.confirm).mockResolvedValue('confirm' as never);
});
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('按需表单大纲', () => {
  const open = async () => {
    const button = wrapper.findAll('.designer-canvas-heading button').find((item) => item.text() === '表单大纲');
    expect(button, '画布提供次要大纲入口').toBeDefined();
    await button!.trigger('click');
  };
  it.each(['basic', 'advanced'])('%s 默认不内联树或来源提示，点击打开并关闭', async (mode) => {
    await start();
    state.designerMode = mode;
    await nextTick();
    expect(wrapper.find('.schema-node-tree').exists()).toBe(false);
    expect(wrapper.html()).not.toContain('Schema 来源');
    expect(wrapper.text()).not.toContain('FormSchema v2 AST 节点树');
    await open();
    expect(wrapper.get('[role="dialog"] h2').text()).toBe('表单大纲');
    expect(wrapper.find('.schema-node-tree').exists()).toBe(true);
    await wrapper.get('[aria-label="关闭表单大纲"]').trigger('click');
    expect(wrapper.find('.schema-node-tree').exists()).toBe(false);
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
    expect(wrapper.get(`[data-node-id="${field.id}"]`).attributes('aria-selected')).toBe('true');
    expect(state.store.selected.value.field_name).toBe(field.field);
    await wrapper.get(`[data-node-id="${state.store.nodes.value[1].id}"]`).trigger('click');
    expect(rows[1].classes()).toContain('is-selected');
  });
  it('抽屉内移动、复制、添加分组保留真实结构操作', async () => {
    await start();
    state.store.addNode('input'); state.store.addNode('select');
    const firstId = state.store.nodes.value[0].id;
    await open();
    await wrapper.findAll('.schema-tree-row')[0].get('[title="下移"]').trigger('click');
    expect(state.store.nodes.value[1].id).toBe(firstId);
    await wrapper.findAll('.schema-tree-row')[1].get('[title="复制"]').trigger('click');
    expect(state.store.nodes.value).toHaveLength(3);
    expect(new Set(state.store.nodes.value.map((node: any) => node.field)).size).toBe(3);
    await wrapper.findAll('[role="dialog"] button').find((item) => item.text() === '添加布局分组')!.trigger('click');
    expect(state.store.nodes.value[3].type).toBe('group');
  });
  it('开关不改变 Schema、历史、dirty、保存状态或草稿', async () => {
    await start();
    const schema = JSON.stringify(state.store.schemaDocument.value);
    localStorage.setItem('form-designer-draft:7', '保留草稿');
    await open();
    await wrapper.get('[aria-label="关闭表单大纲"]').trigger('click');
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
    await wrapper.get('[aria-label="关闭表单大纲"]').trigger('click');
    await vi.advanceTimersByTimeAsync(5000);
    expect(state.store.dirty.value).toBe(true);
    expect(state.saveBlocked).toBe(true);
    expect(localStorage.getItem('form-designer-draft:7')).toBe(savedDraft);
    expect(api.saveSchema).toHaveBeenCalledTimes(1);
  });
  it.each(['desktop', 'tablet', 'mobile'])('切换 %s 预览关闭大纲，返回编辑不自动打开', async (mode) => {
    await start(); await open();
    state.workspaceMode = mode; await nextTick();
    expect(wrapper.find('.schema-node-tree').exists()).toBe(false);
    expect(wrapper.findAll('.designer-canvas-heading button')).toHaveLength(0);
    expect(state.activeTab).toBe('design');
    state.workspaceMode = 'edit'; await nextTick();
    expect(wrapper.find('.schema-node-tree').exists()).toBe(false);
  });
  it('抽屉宽度限制在视口内', async () => {
    await start(); await open();
    expect(wrapper.getComponent<any>('[role="dialog"]').props('size')).toBe('min(480px, 100vw)');
  });
});

describe('插件业务生成闭环', () => {
  const pluginRemote = () => ({ ...remote(), module: { id: 12, metadata: { target: { type: 'plugin', pluginCode: 'demo', scope: 'console', tableStrategy: 'owned', locked: true } } } });
  const plan = () => ({ generationId: 42, schemaHash: hash('a'), plan: { blocked: false, files: [] }, conflicts: [], sensitive: { confirmToken: 'token' } });
  it('展示所属插件和锁定，隐藏动态发布且方法也拒绝调用', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    expect(wrapper.text()).toContain('demo');
    expect(wrapper.text()).toContain('已锁定');
    expect(wrapper.findAll('button').some((button) => button.text() === '动态发布')).toBe(false);
    await state.onDynamicPublish();
    expect(api.previewPublish).not.toHaveBeenCalled();
  });
  it('保存草稿后直接预览生成，完成提示待发布而不是打开运行时', async () => {
    api.module.mockResolvedValue(pluginRemote());
    api.previewFormalGeneration.mockResolvedValue(plan());
    api.formalGeneration.mockResolvedValue({ generationId: 42, state: 'completed', resourceApplyStatus: 'pending_publication', routePath: '/plugin/demo/orders' });
    await start();
    state.store.updateForm({ name: '新草稿' });
    await state.onSave();
    await state.openFormalGeneration();
    expect(api.previewFormalGeneration).toHaveBeenCalledWith(12, expect.any(String));
    expect(api.publish).not.toHaveBeenCalled();
    await state.onPublish();
    await nextTick();
    expect(state.generationResultTitle).toContain('待安装／更新发布');
    expect(wrapper.findAll('button').some((button) => button.text() === '打开独立页面')).toBe(false);
  });
  it('Schema 变化立即清除计划和令牌，迟到预览不能恢复旧计划', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    let finish!: (value: unknown) => void;
    api.previewFormalGeneration.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.openFormalGeneration();
    state.store.updateForm({ name: '预览期间编辑' });
    finish(plan());
    await pending; await nextTick();
    expect(state.publishPreview).toBeNull();
    await state.onPublish();
    expect(api.formalGeneration).not.toHaveBeenCalled();
  });
  it('旧预览入口也丢弃 Schema 变化后的迟到响应', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    Object.assign(state.publishConfig, { module: 'generated', apiPrefix: '/generated/orders', routePath: '/generated/orders', menuName: '订单' });
    api.savePublishConfig.mockImplementationOnce(async (_id: number, publishConfig: object) => ({ publishConfig }));
    let finish!: (value: unknown) => void;
    api.previewFormalGeneration.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const pending = state.onPreviewPublish();
    // 发布设置先落库，预览请求发出后再模拟编辑。
    await vi.waitFor(() => expect(api.previewFormalGeneration).toHaveBeenCalled());
    expect(api.savePublishConfig).toHaveBeenCalledWith(expect.any(Number), expect.objectContaining({ apiPrefix: '/generated/orders' }));
    state.store.updateForm({ name: '预览中编辑' });
    finish(plan());
    await pending;
    expect(state.publishPreview).toBeNull();
  });
  it('启用前台接口但未选择整数归属字段时不保存发布设置也不预览', async () => {
    api.module.mockResolvedValue(pluginRemote());
    await start();
    Object.assign(state.publishConfig, { module: 'generated', apiPrefix: '/generated/orders', routePath: '/generated/orders', menuName: '订单', memberApiEnabled: true, memberApiOwnerField: 'not_a_field' });
    await state.onPreviewPublish();
    expect(api.savePublishConfig).not.toHaveBeenCalled();
    expect(api.previewFormalGeneration).not.toHaveBeenCalled();
  });
  it('执行期间 Schema 变化后网络失败仍查询原生成记录', async () => {
    api.module.mockResolvedValue(pluginRemote());
    api.previewFormalGeneration.mockResolvedValue(plan());
    api.generation.mockResolvedValue({ id: 42, status: 'completed', result: { state: 'completed', resourceApplyStatus: 'pending_publication' } });
    await start(); await state.openFormalGeneration();
    let fail!: (error: Error) => void;
    api.formalGeneration.mockImplementationOnce(() => new Promise((_resolve, reject) => { fail = reject; }));
    const pending = state.onPublish();
    state.store.updateForm({ name: '执行中编辑' });
    fail(new Error('连接中断'));
    await pending;
    expect(api.generation).toHaveBeenCalledWith(42);
    expect(state.generationResultTitle).toContain('待安装／更新发布');
  });
  it('无确认令牌不得执行', async () => {
    api.module.mockResolvedValue(pluginRemote());
    api.previewFormalGeneration.mockResolvedValue({ ...plan(), sensitive: undefined });
    await start(); await state.openFormalGeneration(); await state.onPublish();
    expect(api.formalGeneration).not.toHaveBeenCalled();
  });
  it('目标变化使已有计划失效，blocked 不得确认生成', async () => {
    api.module.mockResolvedValue(pluginRemote());
    api.previewFormalGeneration.mockResolvedValue(plan());
    await start(); await state.openFormalGeneration();
    state.businessModule.metadata.target.pluginCode = 'other';
    await nextTick();
    expect(state.publishPreview).toBeNull();
    api.previewFormalGeneration.mockResolvedValue({ ...plan(), plan: { blocked: true, files: [] } });
    await state.openFormalGeneration(); await state.onPublish();
    expect(api.formalGeneration).not.toHaveBeenCalled();
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
