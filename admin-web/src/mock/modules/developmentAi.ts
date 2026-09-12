import { fail, ok, type MockRoute } from '../types';
import type { AiConversation, AiConversationGroup } from '@/api/development/ai';

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

export const developmentAiMockHandlers: MockRoute[] = [
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
  { method: 'GET', url: '/development/ai/conversations', handler: () => ok(conversations.map((item) => ({ ...item }))) },
  { method: 'POST', url: '/development/ai/conversations', handler: ({ body }) => {
    const created = { ...conversation, ...body, id: ++conversationId, group_id: null, is_archived: false, is_unread: false, approval_mode: body.approval_mode || mode };
    conversations.push(created);
    return ok({ ...created });
  } },
  { method: 'GET', url: /^\/development\/ai\/conversations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const current = conversations.find((item) => item.id === Number(pathParams.id)); return current ? ok({ ...current }) : fail('会话不存在', 404); } },
  { method: 'PUT', url: /^\/development\/ai\/conversations\/(\d+)$/, paramNames: ['id'], handler: ({ body, pathParams }) => { const current = conversations.find((item) => item.id === Number(pathParams.id)); if (!current) return fail('会话不存在', 404); Object.assign(current, body); return ok({ ...current }); } },
  { method: 'DELETE', url: /^\/development\/ai\/conversations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const index = conversations.findIndex((item) => item.id === Number(pathParams.id)); if (index < 0) return fail('会话不存在', 404); conversations.splice(index, 1); return ok({ deleted: true }); } },
  { method: 'GET', url: /^\/development\/ai\/conversations\/(\d+)\/messages$/, paramNames: ['id'], handler: () => ok([message]) },
  { method: 'POST', url: /^\/development\/ai\/conversations\/(\d+)\/messages$/, paramNames: ['id'], handler: ({ body }) => ok({ ...message, id: 602, sequence: 2, ...body }) },
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
