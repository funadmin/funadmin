import { defineStore } from 'pinia';
import {
  aiDevelopmentApi,
  type AiApproval,
  type AiApprovalScope,
  type AiChangeSet,
  type AiConversation,
  type AiConversationGroup,
  type AiConversationQuery,
  type AiPage,
  type AiEventSourceLike,
  type AiMessage,
  type AiTask,
  type AiTaskEvent,
  type AiToolCall
} from '@/api/development/ai';
import {
  resolveAiEventSourceFactory,
  type AiEventSourceConstructor
} from '@/api/development/aiEventTransport';

const ROUTE_STATE_KEY = 'funadmin.ai.route';
const INITIAL_RECONNECT_DELAY = 1000;
const MAX_RECONNECT_DELAY = 30000;
const TERMINAL_TASK_STATUSES: AiTask['status'][] = ['succeeded', 'failed', 'cancelled'];
const EVENT_TYPES = [
  'assistant.message',
  'task.created',
  'task.started',
  'task.progress',
  'task.paused',
  'task.completed',
  'task.succeeded',
  'task.failed',
  'task.cancelled',
  'approval.required',
  'approval.decided',
  'tool_call.started',
  'tool_call.completed',
  'tool_call.failed',
  'change_set.created',
  'change_set.updated'
];

interface RouteState {
  selectedConversationId?: number;
  taskId?: number;
  cursor?: number;
}

interface AiDevelopmentState {
  conversations: AiConversation[];
  conversationFilters: AiConversationQuery;
  conversationCursor: string | null;
  conversationsHasMore: boolean;
  conversationsLoading: boolean;
  conversationGeneration: number;
  olderMessageCursor: string | null;
  latestMessageCursor: string;
  messagesHasMore: boolean;
  olderMessagesLoading: boolean;
  conversationGroups: AiConversationGroup[];
  selectedConversationId: number | null;
  messages: AiMessage[];
  activeTask: AiTask | null;
  restoredTaskId: number | null;
  events: AiTaskEvent[];
  eventCursor: number;
  approvals: AiApproval[];
  approvedFinalApproval: AiApproval | null;
  toolCalls: AiToolCall[];
  changeSet: AiChangeSet | null;
  reconnectDelay: number;
  eventSource: AiEventSourceLike | null;
  reconnectTimer: ReturnType<typeof setTimeout> | null;
  connectionGeneration: number;
  selectionGeneration: number;
  messageGeneration: number;
  modelGenerations: Record<number, number>;
  modelSaving: Record<number, boolean>;
  syncError: boolean;
}

// 同一会话的状态写入串行执行，避免手动未读被较早的自动已读覆盖。
const stateQueues = new WeakMap<object, Map<number, Promise<unknown>>>();

function readRouteState(): RouteState {
  try {
    const raw = sessionStorage.getItem(ROUTE_STATE_KEY);
    return raw ? JSON.parse(raw) as RouteState : {};
  } catch {
    return {};
  }
}

export const useAiDevelopmentStore = defineStore('aiDevelopment', {
  state: (): AiDevelopmentState => ({
    conversations: [],
    conversationFilters: { is_archived: 0 },
    conversationCursor: null,
    conversationsHasMore: false,
    conversationsLoading: false,
    conversationGeneration: 0,
    olderMessageCursor: null,
    latestMessageCursor: '0:0',
    messagesHasMore: false,
    olderMessagesLoading: false,
    conversationGroups: [],
    selectedConversationId: null,
    messages: [],
    activeTask: null,
    restoredTaskId: null,
    events: [],
    eventCursor: 0,
    approvals: [],
    approvedFinalApproval: null,
    toolCalls: [],
    changeSet: null,
    reconnectDelay: 0,
    eventSource: null,
    reconnectTimer: null,
    connectionGeneration: 0,
    selectionGeneration: 0,
    messageGeneration: 0,
    modelGenerations: {},
    modelSaving: {},
    syncError: false
  }),

  actions: {
    async loadConversations(filters?: AiConversationQuery) {
      this.conversationGeneration++;
      this.conversationFilters = { ...(filters ?? this.conversationFilters) };
      delete this.conversationFilters.cursor;
      this.conversationCursor = null;
      this.conversationsHasMore = true;
      this.conversationsLoading = false;
      // 保留当前详情对象，但不让它参与列表游标。
      this.conversations = this.conversations.filter(c => c.id === this.selectedConversationId);
      await this.loadMoreConversations();
    },

    async loadMoreConversations() {
      if (this.conversationsLoading || !this.conversationsHasMore) return;
      const generation = this.conversationGeneration;
      this.conversationsLoading = true;
      try {
        const page = await aiDevelopmentApi.conversationPage({ ...this.conversationFilters, limit: 30, ...(this.conversationCursor ? { cursor: this.conversationCursor } : {}) });
        if (generation !== this.conversationGeneration) return;
        const rows = new Map(this.conversations.map(c => [c.id, c]));
        for (const row of page.items) rows.set(row.id, row);
        this.conversations = [...rows.values()].sort((a, b) => b.id - a.id);
        this.conversationCursor = page.next_cursor;
        this.conversationsHasMore = page.has_more;
      } catch {
        if (generation === this.conversationGeneration) this.syncError = true;
      } finally {
        if (generation === this.conversationGeneration) this.conversationsLoading = false;
      }
    },

    setMessagePage(page: AiPage<AiMessage>) {
      this.messages = page.items;
      const last = page.items.at(-1);
      this.latestMessageCursor = last ? `${last.sequence}:${last.id}` : '0:0';
      this.olderMessageCursor = page.next_cursor;
      this.messagesHasMore = page.has_more;
    },

    mergeMessages(rows: AiMessage[]) {
      const messages = new Map(this.messages.map(m => [m.id, m]));
      for (const row of rows) messages.set(row.id, row);
      this.messages = [...messages.values()].sort((a, b) => a.sequence - b.sequence || a.id - b.id);
    },

    async loadOlderMessages() {
      const id = this.selectedConversationId;
      if (!id || this.olderMessagesLoading || !this.messagesHasMore || !this.olderMessageCursor) return;
      const generation = this.selectionGeneration;
      this.olderMessagesLoading = true;
      try {
        const page = await aiDevelopmentApi.messagePage(id, { limit: 50, before: this.olderMessageCursor });
        if (generation !== this.selectionGeneration || this.selectedConversationId !== id) return;
        this.mergeMessages(page.items);
        this.olderMessageCursor = page.next_cursor;
        this.messagesHasMore = page.has_more;
      } catch {
        if (generation === this.selectionGeneration) this.syncError = true;
      } finally {
        if (generation === this.selectionGeneration) this.olderMessagesLoading = false;
      }
    },

    saveRouteState() {
      sessionStorage.setItem(ROUTE_STATE_KEY, JSON.stringify({
        selectedConversationId: this.selectedConversationId,
        taskId: this.activeTask?.id ?? this.restoredTaskId,
        cursor: this.eventCursor
      }));
    },

    async restoreRouteState() {
      const restored = readRouteState();
      this.clearWorkspace();
      const generation = this.selectionGeneration;
      this.selectedConversationId = restored.selectedConversationId ?? null;
      this.restoredTaskId = restored.taskId ?? null;
      this.eventCursor = restored.cursor ?? 0;
      this.activeTask = null;
      this.events = [];
      this.approvals = [];
      this.approvedFinalApproval = null;
      this.toolCalls = [];
      this.changeSet = null;
      this.reconnectDelay = 0;
      const [, groups] = await Promise.all([
        this.loadConversations(),
        aiDevelopmentApi.conversationGroups()
      ]);
      if (generation !== this.selectionGeneration) return;
      this.conversationGroups = groups;
      if (this.selectedConversationId !== null) {
        const id = this.selectedConversationId;
        const [conversation, messages] = await Promise.all([
          aiDevelopmentApi.conversation(this.selectedConversationId),
          aiDevelopmentApi.messagePage(this.selectedConversationId, { limit: 50 })
        ]);
        if (generation !== this.selectionGeneration) return;
        if (conversation.is_archived) { this.clearWorkspace(); return; }
        const index = this.conversations.findIndex((item) => item.id === conversation.id);
        if (index >= 0) this.conversations[index] = conversation;
        else this.conversations.unshift(conversation);
        this.setMessagePage(messages);
        await this.markViewedRead(id, generation);
        if (generation !== this.selectionGeneration) return;
        if (this.restoredTaskId !== null) {
          try {
            const task = await aiDevelopmentApi.task(this.restoredTaskId);
            if (generation !== this.selectionGeneration) return;
            if (task.id === this.restoredTaskId && task.conversation_id === this.selectedConversationId && this.conversations.some((item) => item.id === this.selectedConversationId)) {
              this.activeTask = task;
            } else {
              this.restoredTaskId = null;
            }
          } catch {
            if (generation !== this.selectionGeneration) return;
            this.restoredTaskId = null;
          }
        }
      }
      this.saveRouteState();
    },

    activateTask(task: AiTask | null) {
      this.closeEvents();
      this.activeTask = task;
      this.restoredTaskId = task?.id ?? null;
      this.events = [];
      this.eventCursor = 0;
      this.approvals = [];
      this.toolCalls = [];
      this.changeSet = null;
    },

    clearWorkspace() {
      this.selectionGeneration += 1;
      this.messageGeneration += 1;
      this.activateTask(null);
      this.selectedConversationId = null;
      this.messages = [];
      this.latestMessageCursor = '0:0';
      this.olderMessageCursor = null;
      this.messagesHasMore = false;
      this.olderMessagesLoading = false;
      this.approvedFinalApproval = null;
      this.reconnectDelay = 0;
      this.syncError = false;
      this.saveRouteState();
    },

    async updateConversationState(id: number, payload: Partial<Pick<AiConversation, 'group_id' | 'is_archived' | 'is_unread'>>, guard?: () => boolean) {
      let queue = stateQueues.get(this);
      if (!queue) { queue = new Map(); stateQueues.set(this, queue); }
      const request = (queue.get(id) ?? Promise.resolve()).catch(() => {}).then(async () => {
        if (guard && !guard()) return;
        const updated = await aiDevelopmentApi.updateConversationState(id, payload);
        if (guard && !guard()) return;
        this.conversationGeneration += 1;
        this.conversationsLoading = false;
        const current = this.conversations.find((item) => item.id === id);
        // 只合并本次修改字段，避免完整响应覆盖并发改名、移动等操作。
        if (current) for (const key of Object.keys(payload) as Array<keyof typeof payload>) {
          Object.assign(current, { [key]: updated[key] });
        }
        if (payload.is_archived && this.selectedConversationId === id) this.clearWorkspace();
      });
      queue.set(id, request);
      try { await request; } finally { if (queue.get(id) === request) queue.delete(id); }
    },

    async updateConversationProfile(id: number, profileId: number, model: string) {
      return this.updateConversationModel(id, model, profileId);
    },

    async updateConversationModel(id: number, model: string, profileId?: number) {
      model = model.trim();
      if (!model || !this.conversations.some((item) => item.id === id)) return;
      return this.updateConversationSelection(id, { model, ...(profileId === undefined ? {} : { profile_id: profileId }) });
    },

    async updateConversationReasoning(id: number, effort: AiConversation['reasoning_effort']) {
      if (effort === undefined) return;
      return this.updateConversationSelection(id, { reasoning_effort: effort });
    },

    async updateConversationSelection(id: number, payload: Partial<Pick<AiConversation, 'model' | 'profile_id' | 'reasoning_effort'>>) {
      if (!this.conversations.some((item) => item.id === id)) return;
      if (this.modelSaving[id]) throw new Error('模型正在保存，请稍后重试');
      this.modelSaving[id] = true;
      try {
        const updated = await aiDevelopmentApi.updateConversation(id, payload);
        const conversation = this.conversations.find((item) => item.id === id);
        // 只更新原会话的模型选择，不覆盖并发状态，也不触碰已冻结任务及事件连接。
        if (conversation) {
          if ('model' in payload || 'profile_id' in payload) Object.assign(conversation, { model: updated.model, provider: updated.provider, ...(updated.profile_id === undefined ? {} : { profile_id: updated.profile_id }) });
          if ('reasoning_effort' in payload) conversation.reasoning_effort = updated.reasoning_effort;
          // 成功回写时递增，使保存前或保存中发起的旧详情都能识别更新。
          this.modelGenerations[id] = (this.modelGenerations[id] ?? 0) + 1;
        }
      } finally {
        delete this.modelSaving[id];
      }
    },

    async markViewedRead(id: number, generation: number) {
      const current = () => this.selectedConversationId === id && this.selectionGeneration === generation;
      if (!current()) return;
      await this.updateConversationState(id, { is_unread: false }, current);
    },

    async refreshViewedMessages(id: number) {
      if (this.selectedConversationId !== id) return;
      const generation = this.selectionGeneration;
      const refresh = ++this.messageGeneration;
      try {
        let after = this.latestMessageCursor;
        do {
          const page = await aiDevelopmentApi.messagePage(id, { limit: 50, ...(after ? { after } : {}) });
          if (generation !== this.selectionGeneration || refresh !== this.messageGeneration || this.selectedConversationId !== id) return;
          this.mergeMessages(page.items);
          const last = page.items.at(-1);
          if (last) this.latestMessageCursor = `${last.sequence}:${last.id}`;
          if (!page.has_more) break;
          if (!page.next_cursor || page.next_cursor === after) throw new Error('消息分页游标未前进');
          after = page.next_cursor;
        } while (true);
        await this.markViewedRead(id, generation);
      } catch {
        if (generation === this.selectionGeneration) this.syncError = true;
      }
    },

    async deleteConversation(id: number) {
      await aiDevelopmentApi.deleteConversation(id);
      this.conversationGeneration++;
      this.conversationsLoading = false;
      this.conversations = this.conversations.filter((item) => item.id !== id);
      if (this.selectedConversationId === id) this.clearWorkspace();
    },

    async deleteConversationGroup(id: number) {
      await aiDevelopmentApi.deleteConversationGroup(id);
      this.conversationGeneration += 1;
      this.conversationsLoading = false;
      this.conversationGroups = this.conversationGroups.filter((group) => group.id !== id);
      for (const item of this.conversations) {
        if (item.group_id !== id) continue;
        Object.assign(item, { group_id: null, is_archived: true });
        if (this.selectedConversationId === item.id) this.clearWorkspace();
      }
      // 以删除成功时的筛选为准，保留期间切换的分组及其他条件。
      if (this.conversationFilters.group_id === id) {
        const filters = { ...this.conversationFilters };
        delete filters.group_id;
        await this.loadConversations(filters);
      }
    },

    async selectConversation(id: number) {
      this.clearWorkspace();
      const generation = this.selectionGeneration;
      this.selectedConversationId = id;
      const modelGeneration = this.modelGenerations[id] ?? 0;
      const [conversation, messages] = await Promise.all([aiDevelopmentApi.conversation(id), aiDevelopmentApi.messagePage(id, { limit: 50 })]);
      if (generation !== this.selectionGeneration || this.selectedConversationId !== id) return;
      const index = this.conversations.findIndex((item) => item.id === id);
      if (index >= 0) {
        const current = this.conversations[index];
        // 旧 GET 仍更新其他详情字段，但不得覆盖期间已保存的模型配置。
        this.conversations[index] = modelGeneration === (this.modelGenerations[id] ?? 0)
          ? conversation
          : { ...conversation, model: current.model, provider: current.provider, profile_id: current.profile_id, reasoning_effort: current.reasoning_effort };
      }
      if (index < 0) this.conversations.push(conversation);
      this.setMessagePage(messages);
      this.saveRouteState();
      await this.markViewedRead(id, generation);
    },

    closeEvents() {
      this.connectionGeneration += 1;
      if (this.reconnectTimer !== null) {
        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = null;
      }
      this.eventSource?.close();
      this.eventSource = null;
    },

    async connectEvents(EventSourceImpl?: AiEventSourceConstructor) {
      if (!this.activeTask || TERMINAL_TASK_STATUSES.includes(this.activeTask.status)) return;
      const eventSourceFactory = resolveAiEventSourceFactory(EventSourceImpl);
      if (!eventSourceFactory) return;
      const taskId = this.activeTask.id;
      this.connectionGeneration += 1;
      const generation = this.connectionGeneration;
      this.eventSource?.close();
      const { ticket } = await aiDevelopmentApi.eventTicket(taskId);
      if (generation !== this.connectionGeneration || this.activeTask?.id !== taskId) return;
      const source = eventSourceFactory.create(aiDevelopmentApi.eventStreamUrl(taskId, ticket, this.eventCursor));
      this.eventSource = source;
      source.onopen = () => { this.reconnectDelay = 0; };

      const receive = (event: Event) => {
        if (generation !== this.connectionGeneration || this.activeTask?.id !== taskId) return;
        const message = event as MessageEvent<string>;
        const id = Number(message.lastEventId);
        if (!Number.isSafeInteger(id) || id <= this.eventCursor) return;
        let payload: Record<string, unknown>;
        try {
          payload = JSON.parse(message.data) as Record<string, unknown>;
        } catch {
          return;
        }
        this.eventCursor = id;
        this.events.push({ id, taskId, type: event.type, payload });
        if (['assistant.message', 'task.completed', 'task.succeeded', 'task.failed', 'task.cancelled'].includes(event.type)) {
          const conversationId = this.activeTask.conversation_id;
          const conversation = this.conversations.find((item) => item.id === conversationId);
          if (conversation) conversation.is_unread = true;
          void this.refreshViewedMessages(conversationId);
        }
        if (['task.succeeded', 'task.failed', 'task.cancelled'].includes(event.type)) {
          this.activeTask = { ...this.activeTask, status: event.type.replace('task.', '') as AiTask['status'] };
          this.closeEvents();
        }
        this.saveRouteState();
      };
      EVENT_TYPES.forEach((type) => source.addEventListener(type, receive));

      source.onerror = () => {
        if (generation !== this.connectionGeneration || this.activeTask?.id !== taskId) return;
        source.close();
        this.reconnectDelay = this.reconnectDelay > 0
          ? Math.min(this.reconnectDelay * 2, MAX_RECONNECT_DELAY)
          : INITIAL_RECONNECT_DELAY;
        const delay = this.reconnectDelay;
        this.reconnectTimer = setTimeout(() => {
          this.reconnectTimer = null;
          void this.connectEvents(EventSourceImpl);
        }, delay);
      };
    },

    async refreshTaskContext() {
      if (!this.activeTask) return;
      const taskId = this.activeTask.id;
      const [approvals, toolCalls] = await Promise.all([
        aiDevelopmentApi.approvals(),
        aiDevelopmentApi.toolCalls(taskId)
      ]);
      if (this.activeTask?.id !== taskId) return;
      this.approvals = approvals.filter((item) => item.task_id === taskId);
      if (this.approvedFinalApproval && (
        this.approvedFinalApproval.conversation_id !== this.activeTask.conversation_id
        || this.approvedFinalApproval.task_id !== taskId
      )) this.approvedFinalApproval = null;
      this.toolCalls = toolCalls;
      if (this.activeTask.change_set_id) {
        const changeSet = await aiDevelopmentApi.changeSet(this.activeTask.change_set_id);
        if (this.activeTask?.id !== taskId) return;
        this.changeSet = changeSet;
      }
    },

    async decideApproval(approval: AiApproval, action: 'approve' | 'reject', scope: AiApprovalScope, feedback?: string) {
      const decided = await aiDevelopmentApi.decideApproval(approval.id, {
        action,
        scope,
        nonce: approval.nonce,
        digest: approval.digest,
        casVersion: approval.cas_version,
        mode: approval.mode_snapshot,
        feedback
      });
      if (action === 'approve' && decided.status === 'approved' && decided.operation === 'apply_workspace'
        && this.selectedConversationId === decided.conversation_id && this.activeTask?.id === decided.task_id
        && this.activeTask.change_set_id) {
        this.approvedFinalApproval = decided;
      }
      await this.refreshTaskContext();
      if (this.activeTask) await this.connectEvents();
    },

    async applyChangeSet(confirmToken: string, selection: string[]) {
      const approval = this.approvedFinalApproval;
      const changeSet = this.changeSet;
      if (!approval || !changeSet || approval.status !== 'approved' || approval.operation !== 'apply_workspace'
        || approval.conversation_id !== changeSet.conversation_id || approval.task_id !== changeSet.task_id
        || this.selectedConversationId !== changeSet.conversation_id || this.activeTask?.id !== changeSet.task_id
        || this.activeTask.change_set_id !== changeSet.id) {
        throw new Error('当前 ChangeSet 的最终 apply 审批不存在或尚未批准');
      }
      return aiDevelopmentApi.applyChangeSet(changeSet.id, { selection, confirmToken, finalApprovalId: approval.id });
    },

    async cancelActiveTask() {
      if (!this.activeTask) return;
      const taskId = this.activeTask.id;
      await aiDevelopmentApi.cancelTask(taskId);
      if (this.activeTask?.id !== taskId) return;
      this.closeEvents();
      this.activeTask = { ...this.activeTask, status: 'cancelled' };
      this.saveRouteState();
    }
  }
});
