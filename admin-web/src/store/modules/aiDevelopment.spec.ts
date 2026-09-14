import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  conversationPage: vi.fn(), messagePage: vi.fn(), conversations: vi.fn(), conversationGroups: vi.fn(), conversation: vi.fn(), messages: vi.fn(), task: vi.fn(), approvals: vi.fn(), toolCalls: vi.fn(),
  eventTicket: vi.fn(), eventStreamUrl: vi.fn(), decideApproval: vi.fn(), cancelTask: vi.fn(), changeSet: vi.fn(), applyChangeSet: vi.fn(),
  updateConversation: vi.fn(), updateConversationState: vi.fn(), deleteConversation: vi.fn(), deleteConversationGroup: vi.fn()
}));
vi.mock('@/api/development/ai', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/ai')>();
  return { ...actual, aiDevelopmentApi: { ...actual.aiDevelopmentApi, ...mocks } };
});

import { service } from '@/utils/http';
import { useAiDevelopmentStore } from './aiDevelopment';

class FakeEventSource {
  static instances: FakeEventSource[] = [];
  listeners = new Map<string, EventListener>();
  onopen: ((event: Event) => void) | null = null;
  onerror: ((event: Event) => void) | null = null;
  close = vi.fn();
  constructor(public url: string) { FakeEventSource.instances.push(this); }
  addEventListener(type: string, listener: EventListener) { this.listeners.set(type, listener); }
  open() { this.onopen?.(new Event('open')); }
  emit(type: string, id: string, payload: Record<string, unknown>) {
    this.listeners.get(type)?.({ type, lastEventId: id, data: JSON.stringify(payload) } as MessageEvent);
  }
}

const conversation = { id: 1, admin_id: 1, uuid: 'one', title: '会话', status: 'running', approval_mode: 'request_approval' as const, provider: '', model: '', context: {}, group_id: null, is_archived: false, is_unread: false };
const task = { id: 8, conversation_id: 1, message_id: null, idempotency_key: 'key', type: 'chat' as const, stage: '', status: 'running' as const, approval_mode: 'request_approval' as const, provider: '', model: '' };

beforeEach(() => {
  setActivePinia(createPinia());
  vi.clearAllMocks();
  FakeEventSource.instances = [];
  mocks.conversationPage.mockImplementation(async () => ({ items: await mocks.conversations(), has_more: false, next_cursor: null }));
  mocks.messagePage.mockImplementation(async (id) => ({ items: await mocks.messages(id), has_more: false, next_cursor: null }));
  mocks.conversations.mockResolvedValue([conversation]);
  mocks.conversationGroups.mockResolvedValue([]);
  mocks.updateConversationState.mockImplementation(async (id, state) => ({ ...conversation, id, ...state }));
  mocks.deleteConversation.mockResolvedValue({ deleted: true });
  mocks.deleteConversationGroup.mockResolvedValue({ deleted: true });
  mocks.conversation.mockResolvedValue(conversation);
  mocks.messages.mockResolvedValue([]);
  mocks.task.mockResolvedValue(task);
  mocks.approvals.mockResolvedValue([]);
  mocks.toolCalls.mockResolvedValue([]);
  mocks.eventTicket.mockResolvedValue({ ticket: 'short-ticket' });
  mocks.eventStreamUrl.mockImplementation((_id, ticket, cursor) => `/events?ticket=${ticket}&cursor=${cursor}`);
  mocks.decideApproval.mockResolvedValue({ status: 'approved' });
  mocks.cancelTask.mockResolvedValue({ cancelled: true });
  mocks.changeSet.mockResolvedValue(null);
  mocks.applyChangeSet.mockResolvedValue({ state: 'completed' });
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe('AI Development store', () => {
  const msg = (id: number) => ({ id, sequence: id, conversation_id: 1, parent_id: null, role: 'assistant' as const, content: [], metadata: {} });
  it('服务端筛选重置游标且隔离旧列表响应，追加不重复', async () => {
    const store = useAiDevelopmentStore();
    expect(store).toHaveProperty('loadConversations');
    let resolve!: (page: unknown) => void;
    mocks.conversationPage.mockReturnValueOnce(new Promise(r => { resolve = r; }));
    const old = store.loadConversations({ search: '旧' });
    mocks.conversationPage.mockResolvedValueOnce({ items: [{ ...conversation, id: 90 }], has_more: true, next_cursor: '90' });
    await store.loadConversations({ is_archived: 1, is_unread: 1, group_id: 4, search: '新' });
    resolve({ items: [conversation], has_more: false, next_cursor: null }); await old;
    expect(store.conversations.map(c => c.id)).toEqual([90]);
    mocks.conversationPage.mockResolvedValueOnce({ items: [{ ...conversation, id: 89 }], has_more: false, next_cursor: null });
    await store.loadMoreConversations();
    expect(mocks.conversationPage).toHaveBeenLastCalledWith({ limit: 30, cursor: '90', is_archived: 1, is_unread: 1, group_id: 4, search: '新' });
    expect(store.conversations.map(c => c.id)).toEqual([90, 89]);
  });
  it('状态保存成功后在途列表不得覆盖归档与未读状态', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation }];
    store.selectedConversationId = 1;
    let resolve!: (page: unknown) => void;
    mocks.conversationPage.mockReturnValueOnce(new Promise(r => { resolve = r; }));
    const pending = store.loadConversations();
    await store.updateConversationState(1, { is_unread: true });
    resolve({ items: [{ ...conversation, is_unread: false }], has_more: false, next_cursor: null });
    await pending;
    expect(store.conversations[0].is_unread).toBe(true);
    expect(store.conversationsLoading).toBe(false);
  });
  it('删除分组后在途列表不得恢复旧分组成员', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, group_id: 4 }];
    store.selectedConversationId = 1;
    let resolve!: (page: unknown) => void;
    mocks.conversationPage.mockReturnValueOnce(new Promise(r => { resolve = r; }));
    const pending = store.loadConversations();
    await store.deleteConversationGroup(4);
    resolve({ items: [{ ...conversation, group_id: 4 }], has_more: false, next_cursor: null });
    await pending;
    expect(store.conversations[0]).toMatchObject({ group_id: null, is_archived: true });
  });
  it('初始最新页、向上合并，SSE after 超过一页连续补齐且不清历史', async () => {
    const store = useAiDevelopmentStore();
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(100), msg(101)], has_more: true, next_cursor: '100:100' });
    await store.selectConversation(1);
    expect(store.messages.map(m => m.id)).toEqual([100, 101]);
    expect(store).toHaveProperty('loadOlderMessages');
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(98), msg(99)], has_more: true, next_cursor: '98:98' });
    await store.loadOlderMessages();
    expect(mocks.messagePage).toHaveBeenLastCalledWith(1, { limit: 50, before: '100:100' });
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(102), msg(103)], has_more: true, next_cursor: '103:103' });
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(104)], has_more: false, next_cursor: null });
    await store.refreshViewedMessages(1);
    expect(mocks.messagePage).toHaveBeenLastCalledWith(1, { limit: 50, after: '103:103' });
    expect(store.messages.map(m => m.id)).toEqual([98,99,100,101,102,103,104]);
    expect(store.olderMessageCursor).toBe('98:98');
  });
  it('向上加载中切换会话后旧页不得污染，首屏外详情可恢复', async () => {
    const store = useAiDevelopmentStore();
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(100)], has_more: true, next_cursor: '100:100' });
    await store.selectConversation(1);
    expect(store.conversations.some(c => c.id === 1)).toBe(true);
    let resolve!: (page: unknown) => void;
    mocks.messagePage.mockReturnValueOnce(new Promise(r => { resolve = r; }));
    const old = store.loadOlderMessages();
    mocks.conversation.mockResolvedValueOnce({ ...conversation, id: 2 });
    mocks.messagePage.mockResolvedValueOnce({ items: [], has_more: false, next_cursor: null });
    await store.selectConversation(2);
    resolve({ items: [msg(1)], has_more: false, next_cursor: null }); await old;
    expect(store.messages).toEqual([]);
    expect(store.selectedConversationId).toBe(2);
  });
  it('发送回执先到不能推进增量水位而跳过中间消息', async () => {
    const store = useAiDevelopmentStore();
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(10)], has_more: false, next_cursor: null });
    await store.selectConversation(1);
    store.messages.push(msg(15));
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(11),msg(12),msg(13),msg(14),msg(15)], has_more: false, next_cursor: null });
    await store.refreshViewedMessages(1);
    expect(mocks.messagePage).toHaveBeenLastCalledWith(1, { limit: 50, after: '10:10' });
    expect(store.messages.map(m => m.id)).toEqual([10,11,12,13,14,15]);
  });
  it('空首屏后新增超过一页也要从起点连续补齐', async () => {
    const store = useAiDevelopmentStore();
    mocks.messagePage.mockResolvedValueOnce({ items: [], has_more: false, next_cursor: null });
    await store.selectConversation(1);
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(1)], has_more: true, next_cursor: '1:1' });
    mocks.messagePage.mockResolvedValueOnce({ items: [msg(2)], has_more: false, next_cursor: null });
    await store.refreshViewedMessages(1);
    expect(mocks.messagePage).toHaveBeenCalledWith(1, { limit: 50, after: '0:0' });
    expect(store.messages.map(m => m.id)).toEqual([1,2]);
  });
  it('删除成功后列表旧响应不得复活会话', async () => {
    const store = useAiDevelopmentStore();
    let resolve!: (page: unknown) => void;
    mocks.conversationPage.mockReturnValueOnce(new Promise(r => { resolve = r; }));
    const pending = store.loadConversations();
    await store.deleteConversation(1);
    resolve({ items: [conversation], has_more: false, next_cursor: null }); await pending;
    expect(store.conversations).toEqual([]);
  });
  it('思考覆盖与模型共用锁，保留并发状态、旧详情保护及任务 SSE，null 显式继承', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, model: 'm', profile_id: 2 }];
    store.activeTask = { ...task };
    await store.connectEvents(FakeEventSource as never);
    const source = store.eventSource;
    let resolveSave!: (value: unknown) => void;
    let resolveDetail!: (value: unknown) => void;
    mocks.conversation.mockReturnValueOnce(new Promise(resolve => { resolveDetail = resolve; }));
    const selecting = store.selectConversation(1);
    store.activeTask = { ...task };
    store.eventSource = source;
    mocks.updateConversation.mockReturnValueOnce(new Promise(resolve => { resolveSave = resolve; }));
    const saving = store.updateConversationReasoning(1, 'low');
    expect(mocks.updateConversation).toHaveBeenCalledWith(1, { reasoning_effort: 'low' });
    await expect(store.updateConversationModel(1, 'other')).rejects.toThrow('正在保存');
    store.conversations[0].title = '并发改名';
    resolveSave({ ...conversation, model: 'm', profile_id: 2, reasoning_effort: 'low' });
    await saving;
    expect(store.conversations[0].title).toBe('并发改名');
    resolveDetail({ ...conversation, model: 'm', profile_id: 2, reasoning_effort: null });
    await selecting;
    expect(store.conversations[0].reasoning_effort).toBe('low');
    expect(store.activeTask).toEqual(task);
    expect(store.eventSource).toBe(source);
    mocks.updateConversation.mockResolvedValueOnce({ ...conversation, reasoning_effort: null });
    await store.updateConversationReasoning(1, null);
    expect(mocks.updateConversation).toHaveBeenLastCalledWith(1, { reasoning_effort: null });
    expect(store.conversations[0].reasoning_effort).toBeNull();
  });
  it('档案切换与模型保存共用锁，仅回写原会话选择，不污染任务及 SSE', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, profile_id: 2 }, { ...conversation, id: 2, model: 'other' }];
    store.selectedConversationId = 1;
    store.activeTask = { ...task, model: 'frozen' };
    const source = new FakeEventSource('/events');
    store.eventSource = source;
    let resolve!: (value: unknown) => void;
    mocks.updateConversation.mockReturnValue(new Promise((done) => { resolve = done; }));
    expect(typeof store.updateConversationProfile).toBe('function');
    const pending = store.updateConversationProfile(1, 3, 'profile-model');
    await expect(store.updateConversationModel(1, 'duplicate')).rejects.toThrow();
    store.selectedConversationId = 2;
    store.conversations[0].title = '并发改名';
    resolve({ ...conversation, profile_id: 3, model: 'profile-model', provider: 'openai-compatible' });
    await pending;
    expect(mocks.updateConversation).toHaveBeenCalledWith(1, { profile_id: 3, model: 'profile-model' });
    expect(store.conversations[0]).toMatchObject({ profile_id: 3, title: '并发改名' });
    expect(store.conversations[1].model).toBe('other');
    expect(store.activeTask?.model).toBe('frozen');
    expect(source.close).not.toHaveBeenCalled();
    expect(store.modelGenerations[1]).toBe(1);
  });
  it('模型保存使用现有 PUT，仅合并模型字段且保持任务和 SSE', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, model: 'old', title: '并发改名' }];
    store.selectedConversationId = 1;
    store.activeTask = { ...task, model: 'frozen' };
    await store.connectEvents(FakeEventSource as never);
    const source = store.eventSource;
    const active = store.activeTask;
    store.eventCursor = 12;
    mocks.updateConversation.mockResolvedValue({ ...conversation, model: 'new', provider: 'configured' });

    await store.updateConversationModel(1, '  new  ');

    expect(mocks.updateConversation).toHaveBeenCalledWith(1, { model: 'new' });
    expect(store.conversations[0]).toMatchObject({ model: 'new', provider: 'configured', title: '并发改名' });
    expect(store.activeTask).toBe(active);
    expect(store.activeTask?.model).toBe('frozen');
    expect(store.eventSource).toBe(source);
    expect(source?.close).not.toHaveBeenCalled();
    expect(store.eventCursor).toBe(12);
    expect(mocks.eventTicket).toHaveBeenCalledTimes(1);
  });

  it('保存后切走只更新原会话列表，不污染新会话或复活已删除会话', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation }, { ...conversation, id: 2, model: 'second' }];
    store.selectedConversationId = 1;
    let finish!: (value: typeof conversation) => void;
    mocks.updateConversation.mockImplementation(() => new Promise((resolve) => { finish = resolve; }));
    const saving = store.updateConversationModel(1, 'new');
    store.selectedConversationId = 2;
    store.selectionGeneration += 1;
    finish({ ...conversation, model: 'new' });
    await saving;
    expect(store.conversations.map((item) => item.model)).toEqual(['new', 'second']);
    expect(store.selectedConversationId).toBe(2);
    const deleted = store.updateConversationModel(1, 'later');
    store.conversations = store.conversations.filter((item) => item.id !== 1);
    finish({ ...conversation, model: 'later' });
    await deleted;
    expect(store.conversations.map((item) => item.id)).toEqual([2]);
  });

  it('空模型或不存在的会话不请求，失败不更改模型', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, model: 'old' }];
    await store.updateConversationModel(1, '  ');
    await store.updateConversationModel(99, 'new');
    expect(mocks.updateConversation).not.toHaveBeenCalled();
    mocks.updateConversation.mockRejectedValueOnce(new Error('保存失败'));
    await expect(store.updateConversationModel(1, 'new')).rejects.toThrow('保存失败');
    expect(store.conversations[0].model).toBe('old');
  });

  it.each(['保存前', '保存中'])('详情 GET 在%s发起、晚于 PUT 返回时保留新模型并更新其他详情', async (timing) => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, model: 'old', provider: 'old-provider' }];
    let finishDetail!: (value: typeof conversation) => void;
    let finishSave!: (value: typeof conversation) => void;
    mocks.conversation.mockImplementationOnce(() => new Promise((resolve) => { finishDetail = resolve; }));
    mocks.updateConversation.mockImplementationOnce(() => new Promise((resolve) => { finishSave = resolve; }));
    mocks.messages.mockResolvedValueOnce([{ id: 77 }]);

    const opening = timing === '保存前' ? store.selectConversation(1) : undefined;
    const saving = store.updateConversationModel(1, 'new');
    const concurrentOpening = opening ?? store.selectConversation(1);
    expect(mocks.conversation).toHaveBeenCalledWith(1);
    expect(mocks.messages).toHaveBeenCalledWith(1);
    expect(mocks.updateConversation).toHaveBeenCalledWith(1, { model: 'new' });
    finishSave({ ...conversation, model: 'new', provider: 'new-provider' });
    await saving;
    finishDetail({ ...conversation, model: 'old', provider: 'old-provider', title: '服务端详情', context: { refreshed: true } });
    await concurrentOpening;

    expect(store.conversations[0]).toMatchObject({ model: 'new', provider: 'new-provider', title: '服务端详情', context: { refreshed: true } });
    expect(store.messages).toEqual([{ id: 77 }]);
    expect(mocks.updateConversationState).toHaveBeenCalledWith(1, { is_unread: false });
    mocks.conversation.mockResolvedValueOnce({ ...conversation, model: 'latest', provider: 'latest-provider' });
    await store.selectConversation(1);
    expect(store.conversations[0]).toMatchObject({ model: 'latest', provider: 'latest-provider' });
  });

  it('同会话拒绝重复模型保存，其他会话仍并发且完成后可再次保存', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation }, { ...conversation, id: 2 }];
    let finish!: (value: typeof conversation) => void;
    mocks.updateConversation.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    mocks.updateConversation.mockResolvedValue({ ...conversation, id: 2, model: 'other' });
    const first = store.updateConversationModel(1, 'first');
    await expect(store.updateConversationModel(1, 'second')).rejects.toThrow('模型正在保存');
    await store.updateConversationModel(2, 'other');
    expect(mocks.updateConversation).toHaveBeenCalledTimes(2);
    expect(store.conversations[1].model).toBe('other');
    finish({ ...conversation, model: 'first' });
    await first;
    expect(store.conversations[0].model).toBe('first');
    mocks.updateConversation.mockResolvedValueOnce({ ...conversation, model: 'second' });
    await store.updateConversationModel(1, 'second');
    expect(store.conversations[0].model).toBe('second');
  });

  it('模型保存失败后释放锁，详情可正常更新且允许重试', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, model: 'old' }];
    let finish!: (value: typeof conversation) => void;
    mocks.conversation.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const opening = store.selectConversation(1);
    mocks.updateConversation.mockRejectedValueOnce(new Error('保存失败'));
    await expect(store.updateConversationModel(1, 'failed')).rejects.toThrow('保存失败');
    finish({ ...conversation, model: 'server', provider: 'server-provider' });
    await opening;
    expect(store.conversations[0]).toMatchObject({ model: 'server', provider: 'server-provider' });
    mocks.updateConversation.mockResolvedValueOnce({ ...conversation, model: 'retry' });
    await store.updateConversationModel(1, 'retry');
    expect(store.conversations[0].model).toBe('retry');
  });

  it('模型保存与详情并发期间删除当前会话，晚到响应不复活工作区', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation }];
    let finishDetail!: (value: typeof conversation) => void;
    let finishSave!: (value: typeof conversation) => void;
    mocks.conversation.mockImplementationOnce(() => new Promise((resolve) => { finishDetail = resolve; }));
    mocks.updateConversation.mockImplementationOnce(() => new Promise((resolve) => { finishSave = resolve; }));
    const opening = store.selectConversation(1);
    const saving = store.updateConversationModel(1, 'new');
    await store.deleteConversation(1);
    finishSave({ ...conversation, model: 'new' });
    await saving;
    finishDetail({ ...conversation });
    await opening;
    expect(store.conversations).toEqual([]);
    expect(store.selectedConversationId).toBeNull();
    expect(store.messages).toEqual([]);
    expect(mocks.updateConversationState).not.toHaveBeenCalled();
  });

  it('打开会话只 PATCH 已读字段，且立即移除旧消息', async () => {
    const store = useAiDevelopmentStore();
    store.messages = [{ id: 99 } as never];
    store.conversations = [{ ...conversation, is_unread: true }];
    const opening = store.selectConversation(1);
    expect(store.messages).toEqual([]);
    await opening;
    expect(mocks.updateConversationState).toHaveBeenCalledWith(1, { is_unread: false });
    expect(store.conversations[0].is_unread).toBe(false);
  });

  it('当前会话回复和终态刷新消息并清未读，其他会话不被清除', async () => {
    const store = useAiDevelopmentStore();
    store.selectedConversationId = 1;
    store.activeTask = task;
    store.conversations = [{ ...conversation, is_unread: true }, { ...conversation, id: 2, is_unread: true }];
    mocks.messages.mockResolvedValue([{ id: 33, role: 'assistant' }]);
    await store.connectEvents(FakeEventSource as never);
    const source = FakeEventSource.instances[0];
    source.emit('assistant.message', '1', {});
    await vi.waitFor(() => expect(mocks.updateConversationState).toHaveBeenCalledWith(1, { is_unread: false }));
    expect(store.messages).toEqual([{ id: 33, role: 'assistant' }]);
    mocks.updateConversationState.mockClear();
    source.emit('task.succeeded', '2', {});
    await vi.waitFor(() => expect(mocks.updateConversationState).toHaveBeenCalledWith(1, { is_unread: false }));
    expect(store.conversations[1].is_unread).toBe(true);
  });

  it('回复刷新途中切换会话，不清旧会话未读也不回写旧消息', async () => {
    const store = useAiDevelopmentStore();
    store.selectedConversationId = 1;
    store.activeTask = task;
    store.conversations = [{ ...conversation, is_unread: true }, { ...conversation, id: 2 }];
    let finish!: (value: unknown[]) => void;
    mocks.messages.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    await store.connectEvents(FakeEventSource as never);
    FakeEventSource.instances[0].emit('assistant.message', '1', {});
    await store.selectConversation(2);
    finish([{ id: 77 }]);
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(mocks.updateConversationState).not.toHaveBeenCalledWith(1, expect.anything());
    expect(store.messages).toEqual([]);
    expect(store.conversations[0].is_unread).toBe(true);
  });

  it('已读响应晚于手动未读时不得覆盖手动状态', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation }];
    let finish!: (value: typeof conversation) => void;
    mocks.updateConversationState.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const opening = store.selectConversation(1);
    await vi.waitFor(() => expect(mocks.updateConversationState).toHaveBeenCalled());
    const marking = store.updateConversationState(1, { is_unread: true });
    finish({ ...conversation, is_unread: false });
    await Promise.all([opening, marking]);
    expect(store.conversations[0].is_unread).toBe(true);
    expect(mocks.updateConversationState).toHaveBeenLastCalledWith(1, { is_unread: true });
  });

  it('自动已读请求返回前切走且旧会话收到新未读，不覆盖旧会话状态', async () => {
    const store = useAiDevelopmentStore();
    store.conversations = [{ ...conversation, is_unread: true }, { ...conversation, id: 2 }];
    let finish!: (value: typeof conversation) => void;
    mocks.updateConversationState.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const opening = store.selectConversation(1);
    await vi.waitFor(() => expect(mocks.updateConversationState).toHaveBeenCalled());
    await store.selectConversation(2);
    store.conversations[0].is_unread = true;
    finish({ ...conversation, is_unread: false });
    await opening;
    expect(store.conversations[0].is_unread).toBe(true);
  });

  it('恢复列表未返回前切换会话，旧恢复不能覆盖选择或消息', async () => {
    sessionStorage.setItem('funadmin.ai.route', JSON.stringify({ selectedConversationId: 1 }));
    const store = useAiDevelopmentStore();
    let finish!: (value: typeof conversation[]) => void;
    mocks.conversations.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
    const restoring = store.restoreRouteState();
    await store.selectConversation(2);
    finish([conversation]);
    await restoring;
    expect(store.selectedConversationId).toBe(2);
    expect(mocks.updateConversationState).not.toHaveBeenCalledWith(1, expect.anything());
  });

  it('归档当前会话清空全部工作区与连接，失败不清空', async () => {
    const store = useAiDevelopmentStore();
    store.selectedConversationId = 1;
    store.activeTask = task;
    store.conversations = [{ ...conversation }];
    store.messages = [{ id: 1 } as never];
    store.approvedFinalApproval = {} as never;
    await store.connectEvents(FakeEventSource as never);
    mocks.updateConversationState.mockRejectedValueOnce(new Error('失败'));
    await expect(store.updateConversationState(1, { is_archived: true })).rejects.toThrow();
    expect(store.selectedConversationId).toBe(1);
    await store.updateConversationState(1, { is_archived: true });
    expect(store.selectedConversationId).toBeNull();
    expect(store.messages).toEqual([]);
    expect(store.activeTask).toBeNull();
    expect(store.approvedFinalApproval).toBeNull();
    expect(FakeEventSource.instances[0].close).toHaveBeenCalled();
  });

  it('删除组归档所有成员并清选中，删除其他会话不打断当前任务', async () => {
    const store = useAiDevelopmentStore();
    store.selectedConversationId = 1;
    store.activeTask = task;
    store.conversationGroups = [{ id: 10, name: '项目' }];
    store.conversations = [{ ...conversation, group_id: 10 }, { ...conversation, id: 2, group_id: 10 }];
    await store.deleteConversation(2);
    expect(store.activeTask?.id).toBe(8);
    await store.deleteConversationGroup(10);
    expect(store.conversationGroups).toEqual([]);
    expect(store.conversations[0]).toMatchObject({ group_id: null, is_archived: true });
    expect(store.selectedConversationId).toBeNull();
  });
  it('恢复选中会话但不持久化 API key', async () => {
    sessionStorage.setItem('funadmin.ai.route', JSON.stringify({ selectedConversationId: 1, taskId: 8, cursor: 4 }));
    const store = useAiDevelopmentStore();
    await store.restoreRouteState();
    expect(store.selectedConversationId).toBe(1);
    expect(store.eventCursor).toBe(4);
    expect(JSON.parse(sessionStorage.getItem('funadmin.ai.route')!)).toMatchObject({ selectedConversationId: 1, taskId: 8, cursor: 4 });
    expect(JSON.stringify(store.$state)).not.toContain('must-not-survive');
    expect(store.$persist).toBeUndefined();
  });

  it('恢复 persisted taskId 时加载服务端任务，且只绑定到选中且管理员可见的会话', async () => {
    sessionStorage.setItem('funadmin.ai.route', JSON.stringify({ selectedConversationId: 1, taskId: 8, cursor: 4 }));
    const store = useAiDevelopmentStore();

    await store.restoreRouteState();

    expect(mocks.task).toHaveBeenCalledWith(8);
    expect(store.activeTask?.id).toBe(8);
    expect(store.eventCursor).toBe(4);
    await store.refreshTaskContext();
    await store.connectEvents(FakeEventSource as never);
    expect(mocks.toolCalls).toHaveBeenCalledWith(8);
    expect(FakeEventSource.instances[0].url).toBe('/events?ticket=short-ticket&cursor=4');

    mocks.task.mockResolvedValue({ ...task, conversation_id: 99 });
    setActivePinia(createPinia());
    const otherStore = useAiDevelopmentStore();
    await otherStore.restoreRouteState();
    expect(otherStore.activeTask).toBeNull();

    mocks.task.mockRejectedValue(new Error('404'));
    setActivePinia(createPinia());
    const invisibleStore = useAiDevelopmentStore();
    await expect(invisibleStore.restoreRouteState()).resolves.toBeUndefined();
    expect(invisibleStore.activeTask).toBeNull();
  });

  it('恢复无任务、无效任务或其他任务时清理旧任务上下文并关闭旧连接', async () => {
    vi.useFakeTimers();
    const cases = [
      { route: { selectedConversationId: 1 }, taskResult: null },
      { route: { selectedConversationId: 1, taskId: 8 }, taskResult: new Error('404') },
      { route: { selectedConversationId: 1, taskId: 8 }, taskResult: { ...task, id: 9, conversation_id: 1 } }
    ];

    for (const testCase of cases) {
      sessionStorage.setItem('funadmin.ai.route', JSON.stringify(testCase.route));
      if (testCase.taskResult instanceof Error) mocks.task.mockRejectedValueOnce(testCase.taskResult);
      else if (testCase.taskResult) mocks.task.mockResolvedValueOnce(testCase.taskResult);
      const store = useAiDevelopmentStore();
      store.selectedConversationId = 1;
      store.activeTask = task;
      store.events = [{ id: 1, taskId: 8, type: 'task.progress', payload: {} }];
      store.approvals = [{} as never];
      store.approvedFinalApproval = {} as never;
      store.toolCalls = [{} as never];
      store.changeSet = {} as never;
      store.reconnectDelay = 4000;
      await store.connectEvents(FakeEventSource as never);
      const oldSource = FakeEventSource.instances.at(-1)!;
      oldSource.onerror?.(new Event('error'));

      await store.restoreRouteState();

      expect(oldSource.close).toHaveBeenCalled();
      expect(store.activeTask).toBeNull();
      expect(store.restoredTaskId).toBeNull();
      expect(store.events).toEqual([]);
      expect(store.approvals).toEqual([]);
      expect(store.approvedFinalApproval).toBeNull();
      expect(store.toolCalls).toEqual([]);
      expect(store.changeSet).toBeNull();
      expect(store.reconnectDelay).toBe(0);
      expect(store.reconnectTimer).toBeNull();
      sessionStorage.removeItem('funadmin.ai.route');
    }
    vi.useRealTimers();
  });

  it('会话切换会清理旧任务上下文和 EventSource，并忽略旧请求回写', async () => {
    const store = useAiDevelopmentStore();
    store.selectedConversationId = 1;
    store.activeTask = task;
    store.events = [{ id: 1, taskId: 8, type: 'task.progress', payload: {} }];
    store.approvals = [{} as never];
    store.toolCalls = [{} as never];
    await store.connectEvents(FakeEventSource as never);
    const oldSource = FakeEventSource.instances[0];
    let resolveConversation!: (value: typeof conversation) => void;
    mocks.conversation
      .mockImplementationOnce(() => new Promise((resolve) => { resolveConversation = resolve; }))
      .mockResolvedValueOnce(conversation);
    mocks.messages.mockResolvedValueOnce([{ id: 2 }]).mockResolvedValueOnce([{ id: 1 }]);

    const staleSelection = store.selectConversation(2);
    expect(oldSource.close).toHaveBeenCalled();
    expect(store.activeTask).toBeNull();
    expect(store.events).toEqual([]);
    expect(store.approvals).toEqual([]);
    expect(store.toolCalls).toEqual([]);

    await store.selectConversation(1);
    resolveConversation({ ...conversation, id: 2 });
    await staleSelection;
    expect(store.selectedConversationId).toBe(1);
    expect(store.messages).toEqual([{ id: 1 }]);
  });

  it('任务切换后忽略旧任务上下文的异步回写', async () => {
    const store = useAiDevelopmentStore();
    store.activeTask = task;
    let resolveToolCalls!: (value: Array<{ id: number }>) => void;
    mocks.toolCalls.mockImplementationOnce(() => new Promise((resolve) => { resolveToolCalls = resolve; }));

    const staleRefresh = store.refreshTaskContext();
    store.activateTask({ ...task, id: 9 });
    resolveToolCalls([{ id: 88 }]);
    await staleRefresh;

    expect(store.toolCalls).toEqual([]);
    expect(store.approvals).toEqual([]);
  });

  it('任务切换会使尚未返回的旧 ticket 失效，成功建立新 SSE 连接后重置 backoff', async () => {
    vi.useFakeTimers();
    const store = useAiDevelopmentStore();
    store.activeTask = task;
    let resolveOldTicket!: (value: { ticket: string }) => void;
    mocks.eventTicket.mockImplementationOnce(() => new Promise((resolve) => { resolveOldTicket = resolve; }));

    const oldConnection = store.connectEvents(FakeEventSource as never);
    store.activateTask({ ...task, id: 9 });
    resolveOldTicket({ ticket: 'old-ticket' });
    await oldConnection;
    expect(FakeEventSource.instances).toHaveLength(0);

    store.reconnectDelay = 4000;
    await store.connectEvents(FakeEventSource as never);
    FakeEventSource.instances[0].open();
    expect(store.reconnectDelay).toBe(0);
    vi.useRealTimers();
  });

  it('恢复到终态任务时不签发 ticket 或建立 SSE', async () => {
    const store = useAiDevelopmentStore();
    store.activeTask = { ...task, status: 'failed' };

    await store.connectEvents(FakeEventSource as never);

    expect(mocks.eventTicket).not.toHaveBeenCalled();
    expect(FakeEventSource.instances).toHaveLength(0);
  });

  it('Axios mock 已命中时即使 transport 模块读取到旧环境值也不创建原生 EventSource', async () => {
    const originalAdapter = service.defaults.adapter;
    vi.stubEnv('VITE_APP_MOCK', 'true');
    await import('@/mock');
    const adapter = service.defaults.adapter;
    expect(adapter).toBeTypeOf('function');
    if (typeof adapter !== 'function') throw new Error('mock adapter 未安装');
    const response = await adapter({
      url: '/development/ai/tasks/701/events/ticket',
      method: 'POST',
      headers: {}
    } as never);
    expect(response.data.data).toEqual({ ticket: 'mock-short-lived-ticket' });

    vi.useFakeTimers();
    vi.stubEnv('VITE_APP_MOCK', 'false');
    const NativeEventSource = vi.fn(function (url: string) {
      return new FakeEventSource(url);
    });
    vi.stubGlobal('EventSource', NativeEventSource);
    const store = useAiDevelopmentStore();
    store.activeTask = { ...task, id: 701, status: 'paused' };

    await store.connectEvents();
    await vi.runOnlyPendingTimersAsync();

    expect(NativeEventSource).not.toHaveBeenCalled();
    expect(store.events.map((event) => event.type)).toContain('approval.required');
    service.defaults.adapter = originalAdapter;
  });

  it('mock 模式使用内存事件传输，不创建原生 EventSource，并保持 paused 审批语义', async () => {
    vi.useFakeTimers();
    vi.stubEnv('VITE_APP_MOCK', 'true');
    const NativeEventSource = vi.fn(function (url: string) {
      return new FakeEventSource(url);
    });
    vi.stubGlobal('EventSource', NativeEventSource);
    const store = useAiDevelopmentStore();
    store.activeTask = { ...task, status: 'paused' };

    await store.connectEvents();
    await vi.runOnlyPendingTimersAsync();

    expect(NativeEventSource).not.toHaveBeenCalled();
    expect(store.events.map((event) => event.type)).toContain('approval.required');
    expect(store.activeTask.status).toBe('paused');
  });

  it('mock 事件传输在关闭和任务切换后清理定时器，且不会错误重连', async () => {
    vi.useFakeTimers();
    vi.stubEnv('VITE_APP_MOCK', 'true');
    const store = useAiDevelopmentStore();
    store.activeTask = { ...task, status: 'paused' };

    await store.connectEvents();
    const source = store.eventSource;
    const close = vi.spyOn(source!, 'close');
    store.activateTask({ ...task, id: 9, status: 'paused' });
    await vi.runAllTimersAsync();

    expect(close).toHaveBeenCalledOnce();
    expect(store.events).toEqual([]);
    expect(store.reconnectTimer).toBeNull();
    expect(mocks.eventTicket).toHaveBeenCalledTimes(1);
  });

  it('任务进入终态时更新任务状态、关闭 SSE 且不再安排重连', async () => {
    vi.useFakeTimers();
    const store = useAiDevelopmentStore();
    store.activeTask = task;
    await store.connectEvents(FakeEventSource as never);
    const source = FakeEventSource.instances[0];

    source.emit('task.succeeded', '9', { status: 'succeeded' });
    source.onerror?.(new Event('error'));

    expect(store.activeTask?.status).toBe('succeeded');
    expect(source.close).toHaveBeenCalled();
    await vi.runOnlyPendingTimersAsync();
    expect(FakeEventSource.instances).toHaveLength(1);
    vi.useRealTimers();
  });

  it('SSE 只在 URL 使用短期 ticket 和 cursor，并按 event id 去重与阻止乱序', async () => {
    const store = useAiDevelopmentStore();
    store.activeTask = task;
    await store.connectEvents(FakeEventSource as never);
    expect(FakeEventSource.instances[0].url).toBe('/events?ticket=short-ticket&cursor=0');
    FakeEventSource.instances[0].emit('task.progress', '5', { stage: 'new' });
    FakeEventSource.instances[0].emit('task.progress', '5', { stage: 'duplicate' });
    FakeEventSource.instances[0].emit('task.progress', '3', { stage: 'old' });
    expect(store.events).toHaveLength(1);
    expect(store.events[0].payload.stage).toBe('new');
    expect(store.eventCursor).toBe(5);
  });

  it('SSE 错误指数退避并以最后游标重新签发票据', async () => {
    vi.useFakeTimers();
    const store = useAiDevelopmentStore();
    store.activeTask = task;
    await store.connectEvents(FakeEventSource as never);
    FakeEventSource.instances[0].emit('task.progress', '7', {});
    FakeEventSource.instances[0].onerror?.(new Event('error'));
    expect(store.reconnectDelay).toBe(1000);
    await vi.advanceTimersByTimeAsync(1000);
    expect(mocks.eventTicket).toHaveBeenCalledTimes(2);
    expect(mocks.eventStreamUrl).toHaveBeenLastCalledWith(8, 'short-ticket', 7);
    vi.useRealTimers();
  });

  it('apply_workspace 审批通过并从 pending 列表消失后仍携带当前 ChangeSet 的 finalApprovalId 执行 apply', async () => {
    const store = useAiDevelopmentStore();
    const changeSetTask = { ...task, change_set_id: 12, status: 'paused' as const };
    const pendingApproval = { id: 9, conversation_id: 1, task_id: 8, tool_call_id: 2, scope: 'once' as const, risk_reason: '', impact: {}, cas_version: 0, nonce: 'n', digest: 'd', operation: 'apply_workspace', mode_snapshot: 'request_approval' as const, status: 'pending' as const, request: {} };
    store.selectedConversationId = 1;
    store.activeTask = changeSetTask;
    store.changeSet = { id: 12, conversation_id: 1, task_id: 8, status: 'approved', added_count: 0, modified_count: 1, deleted_count: 0, renamed_count: 0, test_status: 'passed', security_status: 'passed' };
    store.approvals = [pendingApproval];
    mocks.decideApproval.mockResolvedValue({ ...pendingApproval, status: 'approved' });
    mocks.approvals.mockResolvedValue([]);
    mocks.changeSet.mockResolvedValue(store.changeSet);

    await store.decideApproval(pendingApproval, 'approve', 'once');
    expect(store.approvals).toEqual([]);
    await store.applyChangeSet('confirm-token', ['safe.ts']);

    expect(mocks.applyChangeSet).toHaveBeenCalledWith(12, {
      selection: ['safe.ts'],
      confirmToken: 'confirm-token',
      finalApprovalId: 9
    });
  });

  it('拒绝把其他 conversation、task 或 ChangeSet 的旧审批用于当前 apply', async () => {
    const store = useAiDevelopmentStore();
    store.selectedConversationId = 1;
    store.activeTask = { ...task, change_set_id: 12 };
    store.changeSet = { id: 12, conversation_id: 1, task_id: 8, status: 'approved', added_count: 0, modified_count: 1, deleted_count: 0, renamed_count: 0, test_status: 'passed', security_status: 'passed' };
    store.approvedFinalApproval = { id: 7, conversation_id: 1, task_id: 99, tool_call_id: 2, scope: 'once', risk_reason: '', impact: {}, cas_version: 1, nonce: 'old', digest: 'old', operation: 'apply_workspace', mode_snapshot: 'request_approval', status: 'approved', request: {} };

    await expect(store.applyChangeSet('confirm-token', ['safe.ts'])).rejects.toThrow('当前 ChangeSet');
    expect(mocks.applyChangeSet).not.toHaveBeenCalled();
  });

  it('审批后刷新审批与工具并恢复事件，取消任务会关闭流', async () => {
    const store = useAiDevelopmentStore();
    store.activeTask = task;
    await store.connectEvents(FakeEventSource as never);
    store.approvals = [{ id: 9, conversation_id: 1, task_id: 8, tool_call_id: 2, scope: 'once', risk_reason: '', impact: {}, cas_version: 0, nonce: 'n', digest: 'd', operation: 'write_workspace', mode_snapshot: 'request_approval', status: 'pending', request: {} }];
    await store.decideApproval(store.approvals[0], 'approve', 'session_operation', '允许本会话');
    expect(mocks.decideApproval).toHaveBeenCalledWith(9, expect.objectContaining({ action: 'approve', scope: 'session_operation', feedback: '允许本会话' }));
    expect(mocks.approvals).toHaveBeenCalled();
    expect(mocks.toolCalls).toHaveBeenCalledWith(8);
    await store.cancelActiveTask();
    expect(FakeEventSource.instances.at(-1)?.close).toHaveBeenCalled();
    expect(store.activeTask?.status).toBe('cancelled');
  });
});
