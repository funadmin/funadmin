import { afterEach, describe, expect, it } from 'vitest';
import { service } from '@/utils/http';
import { aiDevelopmentApi as api } from './ai';

const originalAdapter = service.defaults.adapter;
afterEach(() => { service.defaults.adapter = originalAdapter; });
// 保留真实 axios 请求/响应拦截器，只在网络边界提供 8000 实测的响应协议。
function respond(data: unknown) {
  service.defaults.adapter = async (config) => ({ config, status: 200, statusText: 'OK', headers: {}, data: { code: 200, msg: '操作成功', time: 1789261654, data } });
}
const conversation = { id: '2', admin_id: '1', group_id: null, title: '新 AI 会话', status: 'draft', model: '', context: [], is_archived: false, is_unread: false };

describe('AI 真实 HTTP 响应边界', () => {
  it('列表数组不能被当成详情：拒绝实测错误路由响应，不能写入 undefined ID', async () => {
    respond([conversation]);
    await expect(api.conversation(2)).rejects.toThrow();
  });
  it('列表和单记录只解包一次，所有会话及分组入口统一 ID 类型', async () => {
    respond([conversation]);
    expect((await api.conversations())[0]).toMatchObject({ id: 2, admin_id: 1, group_id: null });
    respond(conversation);
    for (const record of [await api.conversation(2), await api.createConversation({ title: '新 AI 会话', approval_mode: 'request_approval' }), await api.updateConversation(2, { title: '改名' }), await api.updateConversationState(2, { group_id: null })]) {
      expect(record).toMatchObject({ id: 2, admin_id: 1, group_id: null, title: '新 AI 会话' });
    }
    const group = { id: '1', admin_id: '1', name: '分组' };
    respond([group]);
    expect((await api.conversationGroups())[0].id).toBe(1);
    respond(group);
    expect((await api.createConversationGroup('分组')).id).toBe(1);
    expect((await api.updateConversationGroup(1, '分组')).id).toBe(1);
  });
  it('消息/任务/审批/变更集中的关联 ID 统一且不改写业务 JSON', async () => {
    const row = { id: '3', conversation_id: '2', task_id: '4', message_id: null, context: { id: 'external' }, input: { conversation_id: 'external' } };
    respond(row);
    for (const record of [await api.task(4), await api.changeSet(3)]) expect(record).toMatchObject({ id: 3, conversation_id: 2, task_id: 4, message_id: null, input: { conversation_id: 'external' } });
    respond([row]);
    for (const records of [await api.messages(2), await api.approvals(), await api.toolCalls(4)]) expect(records[0]).toMatchObject({ conversation_id: 2, task_id: 4 });
  });
  it('不允许非法或超出 JS 安全范围的 ID 悄然转换', async () => {
    for (const id of ['9007199254740993', 'undefined', '', 1.5]) {
      respond({ ...conversation, id });
      await expect(api.conversation(2)).rejects.toThrow();
    }
  });
});
