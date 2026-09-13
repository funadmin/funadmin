import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import designerSource from './index.vue?raw';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';

const api = vi.hoisted(() => ({ module: vi.fn(), saveSchema: vi.fn() }));
vi.mock('@/api/development/business', async (original) => ({ ...await original<object>(), businessDevelopmentApi: api }));
vi.mock('vue-router', () => ({ useRoute: () => ({ query: { moduleId: 12 } }), useRouter: () => ({}), onBeforeRouteLeave: vi.fn() }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (_: string, fallback: string) => fallback }) }));
vi.mock('@/api/system/permission', () => ({ permissionApi: { tree: async () => [] } }));
vi.mock('../schema/pluginComponentLoader', () => ({ loadPluginFormComponents: async () => {} }));
vi.mock('../../development/business/composables/useBusinessMenuRefresh', () => ({ useBusinessMenuRefresh: () => ({ refreshBusinessMenu: vi.fn() }) }));
vi.mock('sortablejs', () => ({ default: { create: () => ({ destroy() {} }) } }));
vi.mock('element-plus', () => ({ ElMessage: { warning: vi.fn(), success: vi.fn(), error: vi.fn() } }));
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
  wrapper = shallowMount(Designer, { global: { renderStubDefaultSlot: true, directives: { perm: {} }, stubs: {
    ...Object.fromEntries(['ElTag', 'ElRadioButton', 'ElRadioGroup', 'ElButton', 'ElOption', 'ElSelect', 'ElAlert', 'ElCard', 'ElFormItem', 'ElForm', 'ElInput', 'ElDivider', 'ElEmpty', 'ElSteps', 'ElStep', 'ElTreeSelect', 'ElCheckbox', 'ElSwitch', 'ElDescriptionsItem', 'ElDescriptions', 'ElTableColumn', 'ElTable', 'ElCollapseItem', 'ElCollapse', 'ElTabPane', 'ElTabs', 'ElResult'].map((name) => [name, true])),
    ElButton: { props: ['disabled', 'loading'], template: '<button :disabled="disabled || loading"><slot /></button>' },
    PageWrapper: { template: '<main><slot /></main>' },
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
  vi.spyOn(window, 'confirm').mockReturnValue(true);
});
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

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
    vi.mocked(window.confirm).mockReturnValue(false);
    await actions()[1].trigger('click');
    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('放弃本地'));
    await actions()[2].trigger('click');
    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('覆盖'));
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
    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('放弃本地'));
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
    vi.mocked(window.confirm).mockReturnValue(false);
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
