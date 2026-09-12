import dayjs from 'dayjs';
import type { ComposerTranslation } from 'vue-i18n';
import type { AiConversation, AiConversationGroup } from '@/api/development/ai';

export type AiEnumGroup = 'statuses' | 'riskLevels' | 'operations' | 'fileStatuses' | 'taskStages' | 'taskTypes' | 'changeSetStatuses';

export function aiEnumLabel(t: ComposerTranslation, group: AiEnumGroup, value: string): string {
  const key = `aiDevelopment.enums.${group}.${value}`;
  const translated = t(key, {}, { missingWarn: false, fallbackWarn: false });
  return translated === key ? value : translated;
}

export interface ConversationSection {
  id: number | null;
  name: string;
  conversations: AiConversation[];
}

export const groupConversations = (conversations: AiConversation[], groups: AiConversationGroup[], ungroupedName = '未分组'): ConversationSection[] => {
  const sections: ConversationSection[] = groups.map((group) => ({
    id: group.id,
    name: group.name,
    conversations: conversations.filter((item) => item.group_id === group.id)
  }));
  const ungrouped = conversations.filter((item) => !groups.some((group) => group.id === item.group_id));
  if (ungrouped.length > 0) sections.push({ id: null, name: ungroupedName, conversations: ungrouped });
  return sections;
};

export const conversationDate = (value?: string): string => {
  if (!value) return '-';
  const date = dayjs(value);
  return date.isValid() ? date.format('MM-DD HH:mm') : '-';
};
