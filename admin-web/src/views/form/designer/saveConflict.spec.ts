import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';

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
    PageWrapper: { template: '<main><slot /></main>' },
    ElDialog: { props: ['modelValue'], template: '<section v-if="modelValue"><slot /><slot name="footer" /></section>' }
  } } });
  await flushPromises();
  state = (wrapper.vm as any).$ .setupState;
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
  localStorage.clear();
  api.module.mockResolvedValue(remote());
  api.saveSchema.mockImplementation(async (_id, document) => ({ document, schemaHash: hash('c') }));
  vi.spyOn(window, 'confirm').mockReturnValue(true);
});
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); });

describe('保存冲突的明确恢复', () => {
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
