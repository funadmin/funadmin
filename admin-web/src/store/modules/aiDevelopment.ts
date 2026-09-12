import { defineStore } from 'pinia';
import {
  aiDevelopmentApi,
  type AiApproval,
  type AiApprovalScope,
  type AiChangeSet,
  type AiConversation,
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
}

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
    connectionGeneration: 0
  }),

  actions: {
    saveRouteState() {
      sessionStorage.setItem(ROUTE_STATE_KEY, JSON.stringify({
        selectedConversationId: this.selectedConversationId,
        taskId: this.activeTask?.id ?? this.restoredTaskId,
        cursor: this.eventCursor
      }));
    },

    async restoreRouteState() {
      this.closeEvents();
      const restored = readRouteState();
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
      this.conversations = await aiDevelopmentApi.conversations();
      if (this.selectedConversationId !== null) {
        const [conversation, messages] = await Promise.all([
          aiDevelopmentApi.conversation(this.selectedConversationId),
          aiDevelopmentApi.messages(this.selectedConversationId)
        ]);
        const index = this.conversations.findIndex((item) => item.id === conversation.id);
        if (index >= 0) this.conversations[index] = conversation;
        else this.conversations.unshift(conversation);
        this.messages = messages;
        if (this.restoredTaskId !== null) {
          try {
            const task = await aiDevelopmentApi.task(this.restoredTaskId);
            if (task.id === this.restoredTaskId && task.conversation_id === this.selectedConversationId && this.conversations.some((item) => item.id === this.selectedConversationId)) {
              this.activeTask = task;
            } else {
              this.restoredTaskId = null;
            }
          } catch {
            this.restoredTaskId = null;
          }
        }
      }
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

    async selectConversation(id: number) {
      const generation = this.connectionGeneration + 1;
      this.closeEvents();
      this.selectedConversationId = id;
      this.activeTask = null;
      this.restoredTaskId = null;
      this.events = [];
      this.eventCursor = 0;
      this.approvals = [];
      this.toolCalls = [];
      this.changeSet = null;
      const [conversation, messages] = await Promise.all([aiDevelopmentApi.conversation(id), aiDevelopmentApi.messages(id)]);
      if (generation !== this.connectionGeneration || this.selectedConversationId !== id) return;
      const index = this.conversations.findIndex((item) => item.id === id);
      if (index >= 0) this.conversations[index] = conversation;
      this.messages = messages;
      this.saveRouteState();
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
      this.closeEvents();
      this.activeTask = { ...this.activeTask, status: 'cancelled' };
      this.saveRouteState();
    }
  }
});
