import { fail, ok, type MockRoute } from '../types';
import { AI_REASONING_EFFORTS, profileCapabilityError, profileModelCapability, type AiConversation, type AiConversationGroup, type AiProfile } from '@/api/development/ai';

const now = '2026-09-12 10:00:00';
let mode: 'request_approval' | 'agent_approval' | 'full_access' = 'request_approval';
const conversation: AiConversation = { group_id: null, is_archived: false, is_unread: false, id: 501, admin_id: 1, uuid: 'mock-ai-conversation', title: '阶段五 Admin Web', status: 'reviewing', approval_mode: mode, provider: 'openai-compatible', model: 'mock-model', context: {}, created_at: now, updated_at: now };
const message = { id: 601, conversation_id: 501, parent_id: null, sequence: 1, role: 'assistant', content: [{ type: 'text', text: '已完成安全分析，等待审批。' }], metadata: {}, created_at: now };
const task = { id: 701, conversation_id: 501, message_id: 601, change_set_id: 901, idempotency_key: 'mock-task', type: 'code_change', stage: 'review', status: 'paused', approval_mode: mode, provider: 'openai-compatible', model: 'mock-model', test_result: { status: 'passed', total: 12 }, result_summary: '准备应用 2 个文件', created_at: now };
const approval = { id: 801, conversation_id: 501, task_id: 701, tool_call_id: 711, scope: 'once', risk_reason: '将修改工作区文件', impact: { files: 2, sideEffects: true }, cas_version: 0, nonce: 'mock-nonce', digest: 'd'.repeat(64), operation: 'apply_workspace', mode_snapshot: mode, status: 'pending', request: { arguments: { selection: ['admin-web/src/views/development/ai/index.vue'] } }, expires_at: '2026-09-12 12:00:00' };
const files = [{ path: 'admin-web/src/views/development/ai/index.vue', status: 'update', contentKind: 'text', baseHash: 'a'.repeat(64), localHash: 'a'.repeat(64), remoteHash: 'b'.repeat(64), mergedHash: 'b'.repeat(64) }, { path: 'public/logo.png', status: 'binary-conflict', contentKind: 'binary', contentOmitted: true }];

let conversationId = 501;
let groupId = 0;
const conversations: AiConversation[] = [conversation];
const groups: AiConversationGroup[] = [];

let profileId = 0;
const profiles: AiProfile[] = [];
const runtimeCapabilities = { reasoning_efforts: [...AI_REASONING_EFFORTS], default_omits_parameter: true, capability_source: 'administrator', unknown_policy: 'reject', fallback: true, stream_fallback: false, max_fallback_models: 3, max_requests: 12, max_reserved_seconds: 300 };
function publicProfile(item: AiProfile) { return { ...item, capabilities: profileModelCapability(item, item.model), runtime_capabilities: runtimeCapabilities }; }
function createProfile(body: Record<string, any>) {
  const { api_key, ...configuration } = body;
  if (!body.name || !body.model || !body.base_url || body.protocol !== 'openai-chat') return fail('档案字段无效');
  if (profiles.some((item) => item.name === body.name)) return fail('档案名称重复', 409);
  const profile: AiProfile = { name: body.name, model: body.model, provider: body.provider, protocol: 'openai-chat', base_url: body.base_url, enabled: true, favorite_models: [], model_capabilities: [], fallback_enabled: false, fallback_models: [], reasoning_effort: null, context_window: null, max_input_tokens: null, max_output_tokens: null, max_iterations: 10, stream_usage: false, connect_timeout: 5, request_timeout: 60, max_retries: 2, ...configuration, id: ++profileId, has_api_key: Boolean(api_key), is_default: false };
  const error = profileCapabilityError(profile);
  if (error) return fail(error);
  profiles.push(profile);
  return ok(publicProfile(profile));
}

// 仅保留固定安全文本样例，不读取上传内容、不持久化、不连接后端。
let attachmentId = 0;
let messageId = 602;
const mockAttachments = new Map<number, { conversationId: number; bound: boolean }>();
const mockMessages = new Map<string, { digest: string; message: Record<string, unknown> }>();
const history: typeof message[] = [message];
function pageNumber(value: unknown, minimum = 1): boolean {
  return (typeof value === 'string' || typeof value === 'number') && /^(0|[1-9][0-9]*)$/.test(String(value)) && Number.isSafeInteger(Number(value)) && Number(value) >= minimum;
}
function validPage(params: Record<string, unknown>, messages = false): boolean {
  const allowed = messages ? ['limit', 'before', 'after'] : ['limit', 'cursor', 'search', 'group_id', 'is_archived', 'is_unread'];
  if (Object.keys(params).some(k => !allowed.includes(k))) return false;
  if ('limit' in params && (!pageNumber(params.limit) || Number(params.limit) > 100)) return false;
  if (messages) return !('before' in params && 'after' in params) && ['before','after'].every(k => !(k in params) || (k === 'after' && params[k] === '0:0') || typeof params[k] === 'string' && String(params[k]).split(':').length === 2 && String(params[k]).split(':').every(v => pageNumber(v)));
  return (!('cursor' in params) || pageNumber(params.cursor)) && (!('group_id' in params) || pageNumber(params.group_id, 0))
    && ['is_archived','is_unread'].every(k => !(k in params) || [0,1,'0','1'].includes(params[k] as never))
    && (!('search' in params) || typeof params.search === 'string' && params.search.length <= 255 && !/[\x00-\x1f\x7f]/.test(params.search));
}
export const developmentAiMockHandlers: MockRoute[] = [
  { method: 'POST', url: /^\/development\/ai\/conversations\/(\d+)\/attachments$/, paramNames: ['id'], handler: ({ body, pathParams }) => {
    const id = Number(pathParams.id);
    if (!conversations.some(c => c.id === id)) return fail('会话不存在', 404);
    const file = body instanceof FormData ? body.get('file') : null;
    if (!(file instanceof File) || file.name !== 'fixture.txt' || file.type !== 'text/plain' || file.size > 131072) return fail('Mock 仅支持 fixture.txt 安全文本样例', 400);
    const created = ++attachmentId; mockAttachments.set(created, { conversationId: id, bound: false });
    return ok({ id: created, kind: 'text', name: 'fixture.txt', mime: 'text/plain', size: 12, width: null, height: null, sha256: '360df9a51746a044a35eab66d3e4ccacec62dec411973d565801e7032a1307b0' });
  } },
  { method: 'GET', url: /^\/development\/ai\/conversations\/(\d+)\/attachments\/(\d+)\/content$/, paramNames: ['id', 'attachmentId'], handler: ({ pathParams }) => {
    const item = mockAttachments.get(Number(pathParams.attachmentId));
    return item?.conversationId === Number(pathParams.id) ? new Blob(['mock fixture'], { type: 'text/plain' }) : fail('Mock 私有附件不存在', 404);
  } },
  { method: 'DELETE', url: /^\/development\/ai\/conversations\/(\d+)\/attachments\/(\d+)$/, paramNames: ['id', 'attachmentId'], handler: ({ pathParams }) => {
    const id = Number(pathParams.attachmentId); const item = mockAttachments.get(id);
    if (item?.conversationId !== Number(pathParams.id)) return fail('Mock 私有附件不存在', 404);
    if (item.bound) return fail('附件已绑定', 409);
    mockAttachments.delete(id); return ok({ deleted: true });
  } },
  { method: 'GET', url: '/development/ai/profiles', handler: () => ok(profiles.map(publicProfile)) },
  { method: 'GET', url: '/development/ai/profiles/default', handler: () => ok(profiles.find((item) => item.is_default) ? publicProfile(profiles.find((item) => item.is_default)!) : null) },
  { method: 'POST', url: '/development/ai/profiles', handler: ({ body }) => createProfile(body) },
  { method: 'GET', url: /^\/development\/ai\/profiles\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const item = profiles.find((p) => p.id === Number(pathParams.id)); return item ? ok(publicProfile(item)) : fail('档案不存在', 404); } },
  { method: 'PATCH', url: /^\/development\/ai\/profiles\/(\d+)$/, paramNames: ['id'], handler: ({ body, pathParams }) => {
    const item = profiles.find((p) => p.id === Number(pathParams.id));
    if (!item) return fail('档案不存在', 404);
    const { api_key, ...configuration } = body;
    if (body.name && profiles.some((p) => p.id !== item.id && p.name === body.name)) return fail('档案名称重复', 409);
    const error = profileCapabilityError({ ...item, ...configuration });
    if (error) return fail(error);
    Object.assign(item, configuration, 'api_key' in body ? { has_api_key: Boolean(api_key) } : {});
    return ok(publicProfile(item));
  } },
  { method: 'DELETE', url: /^\/development\/ai\/profiles\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => {
    const index = profiles.findIndex((p) => p.id === Number(pathParams.id));
    if (index < 0) return fail('档案不存在', 404);
    profiles.splice(index, 1); return ok({ deleted: true });
  } },
  { method: 'POST', url: /^\/development\/ai\/profiles\/(\d+)\/(copy|default|models)$/, paramNames: ['id', 'action'], handler: ({ body, pathParams }) => {
    const item = profiles.find((p) => p.id === Number(pathParams.id));
    if (!item) return fail('档案不存在', 404);
    if (pathParams.action === 'copy') {
      const { id, is_default, has_api_key, ...configuration } = item;
      return createProfile({ ...configuration, name: body.name });
    }
    if (pathParams.action === 'models') return item.enabled ? ok([...new Set(['mock-model', item.model])].map(id => ({ id, capabilities: profileModelCapability(item, id) }))) : fail('档案已停用', 409);
    profiles.forEach((p) => { p.is_default = p.id === item.id; }); return ok(publicProfile(item));
  } },

  { method: 'GET', url: '/development/ai/conversation-groups', handler: () => ok(groups.map((group) => ({ ...group }))) },
  { method: 'POST', url: '/development/ai/conversation-groups', handler: ({ body }) => {
    const name = String(body.name || '').trim();
    if (!name || groups.some((group) => group.name === name)) return fail('分组名称为空或重复');
    const group = { id: ++groupId, name };
    groups.push(group);
    return ok({ ...group });
  } },
  { method: 'PUT', url: /^\/development\/ai\/conversation-groups\/(\d+)$/, paramNames: ['id'], handler: ({ body, pathParams }) => {
    const group = groups.find((item) => item.id === Number(pathParams.id));
    if (!group) return fail('分组不存在', 404);
    const name = String(body.name || '').trim();
    if (!name || groups.some((item) => item.id !== group.id && item.name === name)) return fail('分组名称为空或重复');
    group.name = name;
    return ok({ ...group });
  } },
  { method: 'DELETE', url: /^\/development\/ai\/conversation-groups\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => {
    const id = Number(pathParams.id);
    const index = groups.findIndex((item) => item.id === id);
    if (index < 0) return fail('分组不存在', 404);
    groups.splice(index, 1);
    conversations.forEach((item) => { if (item.group_id === id) Object.assign(item, { group_id: null, is_archived: true }); });
    return ok({ deleted: true });
  } },
  { method: 'PATCH', url: /^\/development\/ai\/conversations\/(\d+)\/state$/, paramNames: ['id'], handler: ({ body, pathParams }) => {
    const current = conversations.find((item) => item.id === Number(pathParams.id));
    if (!current) return fail('会话不存在', 404);
    if (['is_archived', 'is_unread'].some((key) => key in body && typeof body[key] !== 'boolean')) return fail('状态必须为 boolean');
    if ('group_id' in body && body.group_id !== null && (!Number.isSafeInteger(body.group_id) || body.group_id <= 0 || !groups.some((group) => group.id === body.group_id))) return fail('分组不存在');
    for (const key of ['group_id', 'is_archived', 'is_unread']) if (key in body) Object.assign(current, { [key]: body[key] });
    return ok({ ...current });
  } },
  { method: 'GET', url: '/development/ai/conversations', handler: ({ params }) => {
    if (!validPage(params)) return fail('分页参数无效', 400);
    if (Number(params.group_id) > 0 && !groups.some(g => g.id === Number(params.group_id))) return fail('分组不存在', 404);
    const limit = Number(params.limit ?? 30);
    const rows = conversations.filter(c => (!params.cursor || c.id < Number(params.cursor))
      && (!('is_archived' in params) || Number(c.is_archived) === Number(params.is_archived))
      && (!('is_unread' in params) || Number(c.is_unread) === Number(params.is_unread))
      && (!('group_id' in params) || (c.group_id ?? 0) === Number(params.group_id))
      && (!params.search || c.title.includes(String(params.search).trim()))).sort((a,b) => b.id-a.id).slice(0, limit+1);
    const more = rows.length > limit; if (more) rows.pop();
    return ok({ items: rows.map(c => ({ ...c })), has_more: more, next_cursor: more ? String(rows.at(-1)!.id) : null });
  } },
  { method: 'POST', url: '/development/ai/conversations', handler: ({ body }) => {
    const profile = body.profile_id ? profiles.find((p) => p.id === body.profile_id) : null;
    if (body.profile_id && !profile) return fail('档案不存在', 404);
    if (profile && !profile.enabled) return fail('档案已停用', 409);
    const effort = body.reasoning_effort ?? null;
    if (effort !== null && !AI_REASONING_EFFORTS.includes(effort)) return fail('推理档位无效');
    if (!profile && effort !== null) return fail('推理覆盖需要档案');
    const error = profile ? profileCapabilityError({ ...profile, model: body.model ?? profile.model, reasoning_effort: effort ?? profile.reasoning_effort }) : '';
    if (error) return fail(error);
    const created = { ...conversation, reasoning_effort: null, ...body, ...(profile ? { provider: profile.provider, model: body.model ?? profile.model } : {}), id: ++conversationId, group_id: null, is_archived: false, is_unread: false, approval_mode: body.approval_mode || mode };
    conversations.push(created);
    return ok({ ...created });
  } },
  { method: 'GET', url: /^\/development\/ai\/conversations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const current = conversations.find((item) => item.id === Number(pathParams.id)); return current ? ok({ ...current }) : fail('会话不存在', 404); } },
  { method: 'PUT', url: /^\/development\/ai\/conversations\/(\d+)$/, paramNames: ['id'], handler: ({ body, pathParams }) => {
    const current = conversations.find((item) => item.id === Number(pathParams.id));
    if (!current) return fail('会话不存在', 404);
    const candidate = { ...current, ...body };
    const profile = profiles.find(p => p.id === candidate.profile_id);
    if (candidate.profile_id && !profile) return fail('档案不存在', 404);
    if (profile && !profile.enabled) return fail('档案已停用', 409);
    const effort = candidate.reasoning_effort ?? null;
    if (effort !== null && !AI_REASONING_EFFORTS.includes(effort)) return fail('推理档位无效');
    if (!profile && effort !== null) return fail('推理覆盖需要档案');
    const error = profile ? profileCapabilityError({ ...profile, model: candidate.model, reasoning_effort: effort ?? profile.reasoning_effort }) : '';
    if (error) return fail(error);
    Object.assign(current, body, profile ? { provider: profile.provider, model: candidate.model } : {});
    return ok({ ...current });
  } },
  { method: 'DELETE', url: /^\/development\/ai\/conversations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const index = conversations.findIndex((item) => item.id === Number(pathParams.id)); if (index < 0) return fail('会话不存在', 404); conversations.splice(index, 1); return ok({ deleted: true }); } },
  { method: 'GET', url: /^\/development\/ai\/conversations\/(\d+)\/messages$/, paramNames: ['id'], handler: ({ params, pathParams }) => {
    const id = Number(pathParams.id);
    if (!conversations.some(c => c.id === id)) return fail('会话不存在', 404);
    if (!validPage(params, true)) return fail('消息分页参数无效', 400);
    const limit = Number(params.limit ?? 50); const forward = 'after' in params;
    const cursor = String(params.after ?? params.before ?? '').split(':').map(Number);
    const rows = history.filter(m => {
      if (m.conversation_id !== id) return false;
      if (!params.after && !params.before) return true;
      const cmp = m.sequence - cursor[0] || m.id - cursor[1];
      return forward ? cmp > 0 : cmp < 0;
    }).sort((a,b) => (forward ? 1 : -1) * (a.sequence-b.sequence || a.id-b.id)).slice(0, limit+1);
    const more = rows.length > limit; if (more) rows.pop();
    const edge = rows.at(-1);
    return ok({ items: forward ? rows : rows.reverse(), has_more: more, next_cursor: more && edge ? `${edge.sequence}:${edge.id}` : null });
  } },
  { method: 'POST', url: /^\/development\/ai\/conversations\/(\d+)\/messages$/, paramNames: ['id'], handler: ({ body, pathParams }) => {
    const id = Number(pathParams.id);
    if (!conversations.some(c => c.id === id)) return fail('会话不存在', 404);
    const key = `${id}:${body.idempotency_key}`; const digest = JSON.stringify(body.content);
    const existing = body.idempotency_key ? mockMessages.get(key) : null;
    if (existing) return existing.digest === digest ? ok(existing.message) : fail('幂等内容不一致', 409);
    const ids = (body.content || []).filter((b: any) => b.type === 'attachment').map((b: any) => b.attachment_id);
    if (ids.some((aid: number) => mockAttachments.get(aid)?.conversationId !== id)) return fail('附件不存在', 404);
    if (ids.some((aid: number) => mockAttachments.get(aid)!.bound)) return fail('附件已绑定', 409);
    const created = { ...message, ...body, conversation_id: id, id: ++messageId, sequence: messageId - 600 };
    history.push(created);
    ids.forEach((aid: number) => { mockAttachments.get(aid)!.bound = true; });
    if (body.idempotency_key) mockMessages.set(key, { digest, message: created });
    return ok(created);
  } },
  { method: 'POST', url: /^\/development\/ai\/conversations\/(\d+)\/tasks$/, paramNames: ['id'], handler: () => ok({ ...task, status: 'running' }) },
  { method: 'GET', url: /^\/development\/ai\/tasks\/(\d+)$/, paramNames: ['id'], handler: () => ok(task) },
  { method: 'POST', url: /^\/development\/ai\/tasks\/(\d+)\/cancel$/, paramNames: ['id'], handler: () => ok({ cancelled: true }) },
  { method: 'POST', url: /^\/development\/ai\/tasks\/(\d+)\/events\/ticket$/, paramNames: ['id'], handler: () => ok({ ticket: 'mock-short-lived-ticket' }) },
  { method: 'GET', url: '/development/ai/approvals', handler: () => ok([approval]) },
  { method: 'POST', url: /^\/development\/ai\/approvals\/(\d+)\/decision$/, paramNames: ['id'], handler: ({ body }) => ok({ ...approval, status: body.action === 'approve' ? 'approved' : 'denied', scope: body.scope }) },
  { method: 'GET', url: /^\/development\/ai\/tasks\/(\d+)\/tool-calls$/, paramNames: ['id'], handler: () => ok([{ id: 711, conversation_id: 501, task_id: 701, message_id: null, approval_id: 801, tool_name: 'write_file', operation: 'write_workspace', risk_level: 'high', status: 'awaiting_approval', redacted_arguments: { path: 'admin-web/src/views/development/ai/index.vue' }, approval_decision: 'pending', stdout_summary: '准备写入文件' }]) },
  { method: 'GET', url: /^\/development\/ai\/tool-calls\/(\d+)\/logs\/(stdout|stderr)$/, paramNames: ['id', 'stream'], handler: ({ pathParams }) => ok({ content: `${pathParams.stream} mock log`, hash: 'c'.repeat(64) }) },
  { method: 'GET', url: /^\/development\/ai\/change-sets\/(\d+)$/, paramNames: ['id'], handler: () => ok({ id: 901, conversation_id: 501, task_id: 701, status: 'proposed', added_count: 0, modified_count: 1, deleted_count: 0, renamed_count: 0, test_status: 'passed', test_result: { total: 12 }, security_status: 'passed', security_result: { findings: 0 }, selection: [files[0].path], summary: { files: 2 }, recovery_status: 'none', manifest: {} }) },
  { method: 'POST', url: /^\/development\/ai\/change-sets\/(\d+)\/preview$/, paramNames: ['id'], handler: ({ body }) => ok({ changeSetId: 901, blocked: body.selection.includes(files[1].path), files, selection: body.selection, planDigest: 'e'.repeat(64), confirmToken: 'mock-confirm-token', summary: { update: 1, conflict: 1 } }) },
  { method: 'POST', url: /^\/development\/ai\/change-sets\/(\d+)\/apply$/, paramNames: ['id'], handler: () => ok({ state: 'completed', transactionId: 'mock-transaction' }) },
  { method: 'GET', url: '/development/ai/settings', handler: () => ok({ provider: { name: 'openai-compatible', base_url: 'https://example.invalid/v1', model: 'mock-model', connect_timeout: 5, request_timeout: 60, max_retries: 2, configured: true, masked: 'sk-••••••••' }, limits: { max_rounds: 8 } }) },
  { method: 'POST', url: '/development/ai/settings/test', handler: () => ok({ reachable: true, model: 'mock-model', finishReason: 'stop' }) }
];
