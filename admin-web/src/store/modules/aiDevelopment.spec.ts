import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  conversations: vi.fn(), conversationGroups: vi.fn(), conversation: vi.fn(), messages: vi.fn(), task: vi.fn(), approvals: vi.fn(), toolCalls: vi.fn(),
  eventTicket: vi.fn(), eventStreamUrl: vi.fn(), decideApproval: vi.fn(), cancelTask: vi.fn(), changeSet: vi.fn(), applyChangeSet: vi.fn(),
  updateConversationState: vi.fn(), deleteConversation: vi.fn(), deleteConversationGroup: vi.fn()
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
