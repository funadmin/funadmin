import http from '@/utils/http';
import { APP_CONFIG } from '@/config';

const PREFIX = '/development/ai';

export type AiApprovalMode = 'request_approval' | 'agent_approval' | 'full_access';
export type AiTaskStatus = 'pending' | 'running' | 'paused' | 'succeeded' | 'failed' | 'cancelled' | 'recovery_required';
export type AiRiskLevel = 'low' | 'medium' | 'high' | 'critical';
export type AiApprovalScope = 'once' | 'session_operation';

export interface AiConversationGroup {
  id: number;
  admin_id?: number;
  name: string;
  created_at?: string;
  updated_at?: string;
}

export interface AiConversation {
  id: number;
  admin_id: number;
  uuid: string;
  title: string;
  status: string;
  approval_mode: AiApprovalMode;
  provider: string;
  model: string;
  context: Record<string, unknown>;
  group_id: number | null;
  is_archived: boolean;
  is_unread: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface AiContentPart {
  type?: 'text' | 'code' | string;
  text?: string;
  language?: string;
  [key: string]: unknown;
}

export interface AiMessage {
  id: number;
  conversation_id: number;
  parent_id: number | null;
  sequence: number;
  role: 'system' | 'user' | 'assistant' | 'tool';
  content: AiContentPart[];
  metadata: Record<string, unknown>;
  usage?: Record<string, unknown> | null;
  created_at?: string;
}

export interface AiTask {
  id: number;
  conversation_id: number;
  message_id: number | null;
  change_set_id?: number | null;
  idempotency_key: string;
  type: 'chat' | 'crud' | 'code_change' | 'fix' | 'test' | 'migration';
  stage: string;
  status: AiTaskStatus;
  approval_mode: AiApprovalMode;
  provider: string;
  model: string;
  usage?: Record<string, unknown> | null;
  input?: Record<string, unknown> | null;
  output?: Record<string, unknown> | null;
  error?: Record<string, unknown> | null;
  result_summary?: string | null;
  test_result?: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
}

export interface AiTaskEvent {
  id: number;
  taskId: number;
  type: string;
  payload: Record<string, unknown>;
}

export interface AiToolCall {
  id: number;
  conversation_id: number;
  task_id: number;
  message_id: number | null;
  approval_id: number | null;
  tool_name: string;
  operation: string;
  risk_level: AiRiskLevel;
  status: 'pending' | 'awaiting_approval' | 'running' | 'succeeded' | 'failed' | 'denied';
  redacted_arguments: Record<string, unknown>;
  approval_decision: 'not_required' | 'pending' | 'approved' | 'denied' | 'expired' | 'cancelled';
  result?: Record<string, unknown> | null;
  error?: Record<string, unknown> | null;
  exit_code?: number | null;
  stdout_summary?: string | null;
  stderr_summary?: string | null;
  duration_ms?: number | null;
}

export interface AiApproval {
  id: number;
  conversation_id: number;
  task_id: number;
  tool_call_id: number;
  scope: AiApprovalScope;
  risk_reason: string;
  impact: Record<string, unknown>;
  cas_version: number;
  nonce: string;
  digest: string;
  operation: string;
  mode_snapshot: AiApprovalMode;
  status: 'pending' | 'approved' | 'denied' | 'expired' | 'cancelled';
  request: Record<string, unknown>;
  expires_at?: string | null;
}

export type AiChangeFileStatus = 'create' | 'update' | 'auto-merged' | 'keep-local' | 'delete' | 'conflict' | 'binary-conflict' | 'conflict-no-base';
export interface AiChangeSetFile {
  path: string;
  status: AiChangeFileStatus;
  baseHash?: string | null;
  localHash?: string | null;
  remoteHash?: string | null;
  mergedHash?: string | null;
  contentKind?: 'text' | 'binary';
  contentType?: string;
  artifactType?: string;
  contentOmitted?: boolean;
}

export interface AiChangeSet {
  id: number;
  conversation_id: number;
  task_id: number;
  status: string;
  added_count: number;
  modified_count: number;
  deleted_count: number;
  renamed_count: number;
  test_status: string;
  test_result?: Record<string, unknown> | null;
  security_status: string;
  security_result?: Record<string, unknown> | null;
  selection?: string[] | null;
  summary?: Record<string, unknown> | null;
  manifest?: Record<string, unknown>;
  recovery_status?: string;
}

export interface AiChangeSetPreview {
  changeSetId: number;
  blocked: boolean;
  files: AiChangeSetFile[];
  selection: string[];
  planDigest: string;
  confirmToken?: string;
  summary?: Record<string, number>;
}

export interface AiProviderSettings {
  provider: {
    name: string;
    base_url: string;
    model: string;
    connect_timeout: number;
    request_timeout: number;
    max_retries: number;
    configured?: boolean;
    masked?: string;
  };
  limits: Record<string, number>;
}

export interface AiApprovalDecision {
  action: 'approve' | 'reject';
  scope: AiApprovalScope;
  nonce: string;
  digest: string;
  casVersion: number;
  mode: AiApprovalMode;
  feedback?: string;
}

export interface AiEventSourceLike {
  addEventListener(type: string, listener: EventListener): void;
  close(): void;
  onopen: ((event: Event) => void) | null;
  onerror: ((event: Event) => void) | null;
}

function eventUrl(taskId: number, ticket: string, cursor: number): string {
  const base = APP_CONFIG.baseApi.replace(/\/$/, '');
  return `${base}${PREFIX}/tasks/${taskId}/events?ticket=${encodeURIComponent(ticket)}&cursor=${cursor}`;
}

export const aiDevelopmentApi = {
  conversations: () => http.get<AiConversation[]>('/development/ai/conversations'),
  conversationGroups: () => http.get<AiConversationGroup[]>(`${PREFIX}/conversation-groups`),
  createConversationGroup: (name: string) => http.post<AiConversationGroup>(`${PREFIX}/conversation-groups`, { name }),
  updateConversationGroup: (id: number, name: string) => http.put<AiConversationGroup>(`${PREFIX}/conversation-groups/${id}`, { name }),
  deleteConversationGroup: (id: number) => http.delete<{ deleted: boolean }>(`${PREFIX}/conversation-groups/${id}`),
  updateConversationState: (id: number, payload: Partial<Pick<AiConversation, 'group_id' | 'is_archived' | 'is_unread'>>) => http.patch<AiConversation>(`${PREFIX}/conversations/${id}/state`, payload),
  createConversation: (payload: Pick<AiConversation, 'title' | 'approval_mode'> & Partial<Pick<AiConversation, 'provider' | 'model' | 'context'>>) => http.post<AiConversation>('/development/ai/conversations', payload),
  conversation: (id: number) => http.get<AiConversation>(`${PREFIX}/conversations/${id}`),
  updateConversation: (id: number, payload: Partial<Pick<AiConversation, 'title' | 'approval_mode' | 'context'>>) => http.put<AiConversation>(`${PREFIX}/conversations/${id}`, payload),
  deleteConversation: (id: number) => http.delete<{ deleted: boolean }>(`${PREFIX}/conversations/${id}`),
  messages: (id: number) => http.get<AiMessage[]>(`${PREFIX}/conversations/${id}/messages`),
  createMessage: (id: number, payload: Pick<AiMessage, 'role' | 'content'> & Partial<Pick<AiMessage, 'metadata' | 'parent_id'>>) => http.post<AiMessage>(`${PREFIX}/conversations/${id}/messages`, payload),
  executeTask: (id: number, payload: { idempotency_key: string; type?: AiTask['type']; message_id?: number; input?: Record<string, unknown> }) => http.post<AiTask>(`${PREFIX}/conversations/${id}/tasks`, payload),
  task: (id: number) => http.get<AiTask>(`${PREFIX}/tasks/${id}`),
  cancelTask: (id: number) => http.post<{ cancelled: boolean }>(`/development/ai/tasks/${id}/cancel`),
  eventTicket: (id: number) => http.post<{ ticket: string }>(`/development/ai/tasks/${id}/events/ticket`),
  eventStreamUrl: (id: number, ticket: string, cursor = 0) => eventUrl(id, ticket, cursor),
  approvals: () => http.get<AiApproval[]>('/development/ai/approvals'),
  decideApproval: (id: number, payload: AiApprovalDecision) => http.post<AiApproval>(`/development/ai/approvals/${id}/decision`, payload),
  toolCalls: (id: number) => http.get<AiToolCall[]>(`/development/ai/tasks/${id}/tool-calls`),
  toolLog: (id: number, stream: 'stdout' | 'stderr') => http.get<{ content: string; hash: string | null }>(`/development/ai/tool-calls/${id}/logs/${stream}`),
  changeSet: (id: number) => http.get<AiChangeSet>(`/development/ai/change-sets/${id}`),
  previewChangeSet: (id: number, selection: string[]) => http.post<AiChangeSetPreview>(`/development/ai/change-sets/${id}/preview`, { selection }),
  applyChangeSet: (id: number, payload: { selection: string[]; confirmToken: string; finalApprovalId: number }) => http.post<{ state: string; transactionId?: string }>(`/development/ai/change-sets/${id}/apply`, payload),
  settings: () => http.get<AiProviderSettings>('/development/ai/settings'),
  testSettings: (payload?: Record<string, unknown>) => http.post<{ reachable: boolean; model: string; finishReason: string }>('/development/ai/settings/test', payload)
};
