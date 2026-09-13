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

export type AiReasoningEffort = 'low' | 'medium' | 'high';
export interface AiModelDeclaration {
  model: string;
  reasoning_efforts: AiReasoningEffort[];
  output_token_parameter: 'max_tokens' | 'max_completion_tokens';
  context_window: number | null;
  max_output_tokens: number | null;
}
export interface AiModelCapability extends AiModelDeclaration {
  source: 'administrator' | 'unknown';
  unknown_policy: 'reject';
}
export interface AiCatalogModel { id: string; capabilities?: AiModelCapability }
export interface AiRuntimeCapabilities {
  reasoning_efforts: AiReasoningEffort[];
  default_omits_parameter: boolean;
  capability_source: 'administrator';
  unknown_policy: 'reject';
  fallback: boolean;
  stream_fallback: boolean;
  max_fallback_models: number;
  max_requests: number;
  max_reserved_seconds: number;
}

export interface AiProfileInput {
  name: string;
  provider: string;
  protocol: 'openai-chat';
  base_url: string;
  model: string;
  api_key?: string;
  enabled?: boolean;
  favorite_models?: string[];
  model_capabilities?: AiModelDeclaration[];
  fallback_enabled?: boolean;
  fallback_models?: string[];
  reasoning_effort?: AiReasoningEffort | null;
  context_window?: number | null;
  max_input_tokens?: number | null;
  max_output_tokens?: number | null;
  max_iterations?: number;
  stream_usage?: boolean;
  connect_timeout?: number;
  request_timeout?: number;
  max_retries?: number;
}
export interface AiProfile extends Omit<Required<AiProfileInput>, 'api_key' | 'fallback_enabled' | 'reasoning_effort'> {
  id: number;
  is_default: boolean;
  has_api_key: boolean;
  fallback_enabled: boolean;
  reasoning_effort: 'low' | 'medium' | 'high' | null;
  capabilities?: AiModelCapability;
  runtime_capabilities?: AiRuntimeCapabilities;
  created_at?: string;
  updated_at?: string;
}

// 与后端逐模型声明一致；目录和模型名称不用于推断能力。
export function profileModelCapability(profile: Pick<AiProfileInput, 'model_capabilities'>, model: string): AiModelCapability {
  const declared = profile.model_capabilities?.find(item => item.model === model);
  return { model, reasoning_efforts: [], output_token_parameter: 'max_tokens', context_window: null, max_output_tokens: null, ...declared, source: declared ? 'administrator' : 'unknown', unknown_policy: 'reject' };
}

export function profileCapabilityError(profile: AiProfileInput): string {
  const validModel = (model: string) => typeof model === 'string' && model.trim() === model && !!model && new TextEncoder().encode(model).length <= 200 && !/[\x00-\x1f\x7f]/.test(model);
  const validLimit = (n: number | null) => n === null || (Number.isInteger(n) && n >= 1 && n <= 10000000);
  const declarations = profile.model_capabilities || [];
  if (declarations.length > 100 || new Set(declarations.map(c => c.model)).size !== declarations.length || declarations.some(c => !validModel(c.model) || !Array.isArray(c.reasoning_efforts) || new Set(c.reasoning_efforts).size !== c.reasoning_efforts.length || c.reasoning_efforts.some(e => !['low', 'medium', 'high'].includes(e)) || !['max_tokens', 'max_completion_tokens'].includes(c.output_token_parameter) || !validLimit(c.context_window) || !validLimit(c.max_output_tokens))) return '模型能力声明无效、重复或预算超出范围';
  const fallback = profile.fallback_models || [];
  if (fallback.length > 3 || new Set(fallback).size !== fallback.length || fallback.includes(profile.model) || fallback.some(m => !validModel(m)) || (profile.fallback_enabled && !fallback.length)) return '备用模型必须有序、去重、排除主模型，开启时须有 1 至 3 个候选';
  for (const model of [profile.model, ...(profile.fallback_enabled ? fallback : [])]) {
    const cap = profileModelCapability(profile, model);
    if (profile.reasoning_effort != null && !cap.reasoning_efforts.includes(profile.reasoning_effort)) return `${model} 未声明支持所选推理档位，请选择默认或合法档位`;
    if (profile.fallback_enabled && (cap.context_window === null || cap.max_output_tokens === null || profile.max_output_tokens == null)) return '备用要求主模型及全部候选声明上下文、输出能力，并设置明确输出预算';
    if (profile.max_output_tokens != null && ((cap.max_output_tokens !== null && profile.max_output_tokens > cap.max_output_tokens) || (cap.context_window !== null && profile.max_output_tokens + (profile.max_input_tokens || 0) > cap.context_window))) return `${model} 的 Token 预算超过模型能力上限`;
  }
  return '';
}

export interface AiConversation {
  // null 继承档案；更新时省略保留原覆盖。
  reasoning_effort?: AiReasoningEffort | null;
  profile_id?: number | null;
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

// HTTP 已解包 data；这里只校验 AI 实体形状并规范化 ORM bigint，不递归改写业务 JSON。
const ID_FIELDS = ['id', 'admin_id', 'group_id', 'conversation_id', 'task_id', 'message_id', 'parent_id', 'change_set_id', 'approval_id', 'tool_call_id', 'profile_id'] as const;
function aiRecord<T>(value: T): T {
  if (!value || typeof value !== 'object' || Array.isArray(value) || !('id' in value)) throw new Error('AI 接口未返回有效实体');
  const record = { ...value } as Record<string, unknown>;
  for (const field of ID_FIELDS) {
    if (!(field in record)) continue;
    const raw = record[field];
    if (raw === null && field !== 'id') continue;
    const id = typeof raw === 'string' && /^[1-9][0-9]*$/.test(raw) ? Number(raw) : raw;
    if (typeof id !== 'number' || !Number.isSafeInteger(id) || id <= 0) throw new Error(`AI 接口 ${field} 无效或超出安全整数范围`);
    record[field] = id;
  }
  return record as T;
}
function aiRecords<T>(value: T[]): T[] {
  if (!Array.isArray(value)) throw new Error('AI 接口未返回有效列表');
  return value.map(aiRecord);
}

export const aiDevelopmentApi = {
  profiles: () => http.get<AiProfile[]>(`${PREFIX}/profiles`).then(aiRecords),
  profile: (id: number) => http.get<AiProfile>(`${PREFIX}/profiles/${id}`).then(aiRecord),
  createProfile: (payload: AiProfileInput) => http.post<AiProfile>(`${PREFIX}/profiles`, payload).then(aiRecord),
  updateProfile: (id: number, payload: Partial<AiProfileInput>) => http.patch<AiProfile>(`${PREFIX}/profiles/${id}`, payload).then(aiRecord),
  deleteProfile: (id: number) => http.delete<{ deleted: boolean }>(`${PREFIX}/profiles/${id}`),
  defaultProfile: () => http.get<AiProfile | null>(`${PREFIX}/profiles/default`).then((value) => value === null ? null : aiRecord(value)),
  makeDefaultProfile: (id: number) => http.post<AiProfile>(`${PREFIX}/profiles/${id}/default`).then(aiRecord),
  copyProfile: (id: number, name: string) => http.post<AiProfile>(`${PREFIX}/profiles/${id}/copy`, { name }).then(aiRecord),
  profileModels: (id: number) => http.post<AiCatalogModel[]>(`${PREFIX}/profiles/${id}/models`),
  conversations: () => http.get<AiConversation[]>('/development/ai/conversations').then(aiRecords),
  conversationGroups: () => http.get<AiConversationGroup[]>(`${PREFIX}/conversation-groups`).then(aiRecords),
  createConversationGroup: (name: string) => http.post<AiConversationGroup>(`${PREFIX}/conversation-groups`, { name }).then(aiRecord),
  updateConversationGroup: (id: number, name: string) => http.put<AiConversationGroup>(`${PREFIX}/conversation-groups/${id}`, { name }).then(aiRecord),
  deleteConversationGroup: (id: number) => http.delete<{ deleted: boolean }>(`${PREFIX}/conversation-groups/${id}`),
  updateConversationState: (id: number, payload: Partial<Pick<AiConversation, 'group_id' | 'is_archived' | 'is_unread'>>) => http.patch<AiConversation>(`${PREFIX}/conversations/${id}/state`, payload).then(aiRecord),
  createConversation: (payload: Pick<AiConversation, 'title' | 'approval_mode'> & Partial<Pick<AiConversation, 'provider' | 'model' | 'context' | 'profile_id' | 'reasoning_effort'>>) => http.post<AiConversation>('/development/ai/conversations', payload).then(aiRecord),
  conversation: (id: number) => http.get<AiConversation>(`${PREFIX}/conversations/${id}`).then(aiRecord),
  updateConversation: (id: number, payload: Partial<Pick<AiConversation, 'title' | 'approval_mode' | 'context' | 'model' | 'profile_id' | 'reasoning_effort'>>) => http.put<AiConversation>(`${PREFIX}/conversations/${id}`, payload).then(aiRecord),
  deleteConversation: (id: number) => http.delete<{ deleted: boolean }>(`${PREFIX}/conversations/${id}`),
  messages: (id: number) => http.get<AiMessage[]>(`${PREFIX}/conversations/${id}/messages`).then(aiRecords),
  createMessage: (id: number, payload: Pick<AiMessage, 'role' | 'content'> & Partial<Pick<AiMessage, 'metadata' | 'parent_id'>>) => http.post<AiMessage>(`${PREFIX}/conversations/${id}/messages`, payload).then(aiRecord),
  executeTask: (id: number, payload: { idempotency_key: string; type?: AiTask['type']; message_id?: number; input?: Record<string, unknown> }) => http.post<AiTask>(`${PREFIX}/conversations/${id}/tasks`, payload).then(aiRecord),
  task: (id: number) => http.get<AiTask>(`${PREFIX}/tasks/${id}`).then(aiRecord),
  cancelTask: (id: number) => http.post<{ cancelled: boolean }>(`/development/ai/tasks/${id}/cancel`),
  eventTicket: (id: number) => http.post<{ ticket: string }>(`/development/ai/tasks/${id}/events/ticket`),
  eventStreamUrl: (id: number, ticket: string, cursor = 0) => eventUrl(id, ticket, cursor),
  approvals: () => http.get<AiApproval[]>('/development/ai/approvals').then(aiRecords),
  decideApproval: (id: number, payload: AiApprovalDecision) => http.post<AiApproval>(`/development/ai/approvals/${id}/decision`, payload).then(aiRecord),
  toolCalls: (id: number) => http.get<AiToolCall[]>(`/development/ai/tasks/${id}/tool-calls`).then(aiRecords),
  toolLog: (id: number, stream: 'stdout' | 'stderr') => http.get<{ content: string; hash: string | null }>(`/development/ai/tool-calls/${id}/logs/${stream}`),
  changeSet: (id: number) => http.get<AiChangeSet>(`/development/ai/change-sets/${id}`).then(aiRecord),
  previewChangeSet: (id: number, selection: string[]) => http.post<AiChangeSetPreview>(`/development/ai/change-sets/${id}/preview`, { selection }),
  applyChangeSet: (id: number, payload: { selection: string[]; confirmToken: string; finalApprovalId: number }) => http.post<{ state: string; transactionId?: string }>(`/development/ai/change-sets/${id}/apply`, payload),
  settings: () => http.get<AiProviderSettings>('/development/ai/settings'),
  testSettings: (payload?: Record<string, unknown>) => http.post<{ reachable: boolean; model: string; finishReason: string }>('/development/ai/settings/test', payload)
};
