<template>
  <aside class="conversation-list">
    <div class="conversation-list__header">
      <strong>{{ t('aiDevelopment.conversations') }}</strong>
      <div><el-button size="small" @click="$emit('create-group')">{{ t('aiDevelopment.management.createGroup') }}</el-button><el-button size="small" type="primary" @click="$emit('create')">{{ t('aiDevelopment.newConversation') }}</el-button></div>
    </div>
    <el-button data-testid="archived-conversations" :aria-pressed="archived" @click="$emit('toggle-archived')">{{ t(archived ? 'aiDevelopment.management.active' : 'aiDevelopment.management.archived') }}</el-button>
    <div v-for="section in sections" :key="section.id ?? 'ungrouped'" class="conversation-group">
      <div class="conversation-group__header">
        <strong>{{ section.name }}</strong>
        <el-dropdown v-if="section.id !== null" trigger="click" @command="(command: string) => command === 'rename' ? $emit('rename-group', section.id!) : $emit('delete-group', section.id!)">
          <el-button link><i class="i-ep-more" /></el-button>
          <template #dropdown><el-dropdown-menu><el-dropdown-item command="rename">{{ t('aiDevelopment.management.rename') }}</el-dropdown-item><el-dropdown-item command="delete" divided>{{ t('aiDevelopment.management.deleteGroup') }}</el-dropdown-item></el-dropdown-menu></template>
        </el-dropdown>
      </div>
      <div v-for="conversation in section.conversations" :key="conversation.id" class="conversation-row">
      <button class="conversation-item" :class="{ active: conversation.id === selectedId, unread: conversation.is_unread }" @click="$emit('select', conversation.id)">
        <span class="conversation-item__main"><strong>{{ conversation.title || t('aiDevelopment.unnamedConversation') }}</strong><small>{{ conversationDate(conversation.updated_at || conversation.created_at) }}</small></span>
        <span class="conversation-item__aside"><i v-if="conversation.is_unread" class="unread-dot" :aria-label="t('aiDevelopment.management.unread')" /><small>{{ statusLabel(conversation.status) }}</small></span>
      </button>
      <el-dropdown trigger="click" @command="(command: string) => $emit('action', command, conversation.id)">
        <el-button link :aria-label="t('aiDevelopment.management.actions')"><i class="i-ep-more" /></el-button>
        <template #dropdown><el-dropdown-menu>
          <el-dropdown-item command="rename">{{ t('aiDevelopment.management.rename') }}</el-dropdown-item>
          <el-dropdown-item command="move">{{ t('aiDevelopment.management.move') }}</el-dropdown-item>
          <el-dropdown-item :command="conversation.is_unread ? 'read' : 'unread'">{{ t(conversation.is_unread ? 'aiDevelopment.management.markRead' : 'aiDevelopment.management.markUnread') }}</el-dropdown-item>
          <el-dropdown-item :command="conversation.is_archived ? 'restore' : 'archive'">{{ t(conversation.is_archived ? 'aiDevelopment.management.restore' : 'aiDevelopment.management.archive') }}</el-dropdown-item>
          <el-dropdown-item command="delete" divided>{{ t('aiDevelopment.management.delete') }}</el-dropdown-item>
        </el-dropdown-menu></template>
      </el-dropdown>
      </div>
    </div>
    <el-empty v-if="sections.length === 0" :description="t('aiDevelopment.noConversations')" />
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiConversation, AiConversationGroup } from '@/api/development/ai';
import { aiEnumLabel, conversationDate, groupConversations } from '../i18n';
const props = defineProps<{ conversations: AiConversation[]; groups: AiConversationGroup[]; selectedId: number | null; archived?: boolean }>();
defineEmits<{ create: []; select: [id: number]; 'create-group': []; 'rename-group': [id: number]; 'delete-group': [id: number]; 'toggle-archived': []; action: [command: string, id: number] }>();
const { t } = useI18n();
const statusLabel = (status: string) => aiEnumLabel(t, 'statuses', status);
const sections = computed(() => {
  const visible = props.conversations.filter((item) => Boolean(item.is_archived) === Boolean(props.archived));
  const groups = props.archived ? props.groups.filter((group) => visible.some((item) => item.group_id === group.id)) : props.groups;
  return groupConversations(visible, groups, t('aiDevelopment.management.ungrouped'));
});
</script>

<style scoped>
.conversation-list { display: flex; min-height: 0; flex-direction: column; gap: 10px; padding: 12px; }
.conversation-list__header, .conversation-group__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.conversation-list__header { padding: 4px; }.conversation-list__header > div { display: flex; gap: 6px; }
.conversation-group { display: grid; gap: 6px; }.conversation-group__header { padding: 4px 6px; color: var(--el-text-color-secondary); font-size: 12px; }
.conversation-row { display: flex; align-items: center; min-width: 0; }.conversation-row > .conversation-item { flex: 1; min-width: 0; }.conversation-row > :last-child { flex-shrink: 0; }
.conversation-item { display: flex; justify-content: space-between; gap: 8px; border: 0; border-radius: 8px; padding: 10px 11px; background: transparent; color: var(--el-text-color-primary); text-align: left; cursor: pointer; }
.conversation-item:hover, .conversation-item.active { background: var(--el-color-primary-light-9); }.conversation-item.unread strong { font-weight: 700; }
.conversation-item__main, .conversation-item__aside { display: flex; min-width: 0; flex-direction: column; gap: 4px; }.conversation-item__main { flex: 1; }.conversation-item__main strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conversation-item__aside { align-items: flex-end; }.conversation-item small { color: var(--el-text-color-secondary); font-size: 11px; }.unread-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--el-color-primary); }
</style>
