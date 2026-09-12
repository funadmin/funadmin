import dayjs from 'dayjs';
import type { AiConversation, AiConversationGroup } from '@/api/development/ai';

export interface ConversationSection {
  id: number | null;
  name: string;
  conversations: AiConversation[];
}

export const groupConversations = (conversations: AiConversation[], groups: AiConversationGroup[]): ConversationSection[] => {
  const sections = groups.map((group) => ({
    id: group.id,
    name: group.name,
    conversations: conversations.filter((item) => item.group_id === group.id)
  }));
  const ungrouped = conversations.filter((item) => item.group_id === null);
  if (ungrouped.length > 0) sections.push({ id: null, name: '未分组', conversations: ungrouped });
  return sections.filter((section) => section.conversations.length > 0);
};

export const conversationDate = (value?: string): string => {
  if (!value) return '-';
  const date = dayjs(value);
  return date.isValid() ? date.format('MM-DD HH:mm') : '-';
};
