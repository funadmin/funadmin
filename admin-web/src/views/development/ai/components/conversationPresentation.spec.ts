import { describe, expect, it } from 'vitest';
import type { AiConversation } from '@/api/development/ai';
import { conversationDate, groupConversations } from '../i18n';

const conversation = (id: number, groupId: number | null, updatedAt: string): AiConversation => ({
  id,
  admin_id: 1,
  uuid: `conversation-${id}`,
  title: `会话 ${id}`,
  status: 'draft',
  approval_mode: 'request_approval',
  provider: '',
  model: '',
  context: {},
  group_id: groupId,
  is_archived: false,
  is_unread: false,
  updated_at: updatedAt
});

describe('AI 会话分组和日期显示', () => {
  it('按自定义分组组织会话并保留未分组区域', () => {
    const groups = groupConversations(
      [conversation(1, 10, '2026-09-12 10:30:00'), conversation(2, null, '2026-09-11 09:00:00')],
      [{ id: 10, name: '商城项目' }]
    );

    expect(groups.map((group) => group.name)).toEqual(['商城项目', '未分组']);
    expect(groups[0].conversations[0].id).toBe(1);
    expect(groups[1].conversations[0].id).toBe(2);
  });

  it('保留空自定义分组', () => {
    expect(groupConversations([], [{ id: 10, name: '空项目' }])).toEqual([{ id: 10, name: '空项目', conversations: [] }]);
  });

  it('每个会话显示自己的日期和时间', () => {
    expect(conversationDate('2026-09-12 10:30:00')).toContain('09-12');
    expect(conversationDate('2026-09-12 10:30:00')).toContain('10:30');
    expect(conversationDate(undefined)).toBe('-');
  });
});
