<template>
  <aside class="conversation-list">
    <div class="conversation-list__header">
      <strong class="conversation-list__title">{{ t('aiDevelopment.conversations') }}<span v-if="visibleCount" class="conversation-list__count">{{ visibleCount }}</span></strong>
      <el-button size="small" text class="conversation-list__archived" data-testid="archived-conversations" :aria-pressed="archived" @click="$emit('toggle-archived')"><i :class="archived ? 'i-ep-chat-line-round' : 'i-ep-box'" />{{ t(archived ? 'aiDevelopment.management.active' : 'aiDevelopment.management.archived') }}</el-button>
      <div class="conversation-list__actions"><el-button size="small" @click="$emit('create-group')"><i class="i-ep-folder-add" />{{ t('aiDevelopment.management.createGroup') }}</el-button><el-button size="small" type="primary" @click="$emit('create')"><i class="i-ep-plus" />{{ t('aiDevelopment.newConversation') }}</el-button><slot name="actions" /></div>
    </div>
    <div class="conversation-list__filters">
      <el-input size="small" clearable :model-value="filters?.search || ''" data-testid="conversation-search" :placeholder="t('aiDevelopment.pagination.search')" @update:model-value="value => $emit('filter', { search: String(value) })">
        <template #prefix><i class="i-ep-search" /></template>
      </el-input>
      <el-select size="small" :model-value="filters?.is_unread ?? ''" :placeholder="t('aiDevelopment.pagination.allReadStates')" :aria-label="t('aiDevelopment.pagination.unreadFilter')" @update:model-value="value => $emit('filter', { is_unread: value === '' ? undefined : value })">
        <el-option :value="''" :label="t('aiDevelopment.pagination.allReadStates')" /><el-option :value="1" :label="t('aiDevelopment.management.unread')" />
      </el-select>
      <el-select size="small" :model-value="filters?.group_id ?? -1" :aria-label="t('aiDevelopment.pagination.groupFilter')" @update:model-value="value => $emit('filter', { group_id: value === -1 ? undefined : value })">
        <el-option :value="-1" :label="t('aiDevelopment.pagination.allGroups')" /><el-option :value="0" :label="t('aiDevelopment.management.ungrouped')" />
        <el-option v-for="group in groups" :key="group.id" :value="group.id" :label="group.name" />
      </el-select>
    </div>
    <div v-for="section in sections" :key="section.id ?? 'ungrouped'" class="conversation-group">
      <div class="conversation-group__header">
        <span class="conversation-group__name"><i :class="section.id === null ? 'i-ep-files' : 'i-ep-folder'" />{{ section.name }}<small>{{ section.conversations.length }}</small></span>
        <el-dropdown v-if="section.id !== null" trigger="click" @command="(command: string) => command === 'rename' ? $emit('rename-group', section.id!) : $emit('delete-group', section.id!)">
          <el-button link class="conversation-more" :aria-label="t('aiDevelopment.management.actions')"><i class="i-ep-more" /></el-button>
          <template #dropdown><el-dropdown-menu><el-dropdown-item command="rename">{{ t('aiDevelopment.management.rename') }}</el-dropdown-item><el-dropdown-item command="delete" divided>{{ t('aiDevelopment.management.deleteGroup') }}</el-dropdown-item></el-dropdown-menu></template>
        </el-dropdown>
      </div>
      <div v-for="conversation in section.conversations" :key="conversation.id" class="conversation-row" :class="{ active: conversation.id === selectedId }">
      <button class="conversation-item" :class="{ active: conversation.id === selectedId, unread: conversation.is_unread }" :aria-current="conversation.id === selectedId ? 'true' : undefined" @click="$emit('select', conversation.id)">
        <span class="conversation-item__title"><strong>{{ conversation.title || t('aiDevelopment.unnamedConversation') }}</strong><i v-if="conversation.is_unread" class="unread-dot" :aria-label="t('aiDevelopment.management.unread')" /></span>
        <span class="conversation-item__meta"><small>{{ conversationDate(conversation.updated_at || conversation.created_at) }}</small><small class="conversation-status" :class="`conversation-status--${statusTone(conversation.status)}`">{{ statusLabel(conversation.status) }}</small></span>
      </button>
      <el-dropdown trigger="click" @command="(command: string) => $emit('action', command, conversation.id)">
        <el-button link class="conversation-more" :aria-label="t('aiDevelopment.management.actions')"><i class="i-ep-more" /></el-button>
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
    <el-button v-if="hasMore" class="conversation-list__more" data-testid="load-conversations" :loading="loading" :disabled="loading" @click="$emit('load-more')">{{ t('aiDevelopment.pagination.more') }}</el-button>
    <el-empty v-if="sections.length === 0" :image-size="72" :description="t('aiDevelopment.noConversations')" />
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiConversation, AiConversationGroup, AiConversationQuery } from '@/api/development/ai';
import { aiEnumLabel, conversationDate, groupConversations } from '../i18n';
const props = defineProps<{ conversations: AiConversation[]; groups: AiConversationGroup[]; selectedId: number | null; archived?: boolean; filters?: AiConversationQuery; hasMore?: boolean; loading?: boolean }>();
defineEmits<{ filter: [filters: Partial<AiConversationQuery>]; 'load-more': []; create: []; select: [id: number]; 'create-group': []; 'rename-group': [id: number]; 'delete-group': [id: number]; 'toggle-archived': []; action: [command: string, id: number] }>();
const { t } = useI18n();
const statusLabel = (status: string) => aiEnumLabel(t, 'statuses', status);
const statusTone = (status: string) => ({ running: 'primary', pending: 'primary', applying: 'primary', paused: 'warning', awaiting_approval: 'warning', recovery_required: 'warning', failed: 'danger', cancelled: 'muted', succeeded: 'success', completed: 'success' } as Record<string, string>)[status] || 'muted';
const sections = computed(() => {
  const visible = props.conversations.filter((item) => Boolean(item.is_archived) === Boolean(props.archived)
    && (props.filters?.is_unread === undefined || Number(item.is_unread) === props.filters.is_unread)
    && (props.filters?.group_id === undefined || (item.group_id ?? 0) === props.filters.group_id)
    && (!props.filters?.search || item.title.includes(props.filters.search.trim())));
  const groups = props.archived ? props.groups.filter((group) => visible.some((item) => item.group_id === group.id)) : props.groups;
  return groupConversations(visible, groups, t('aiDevelopment.management.ungrouped'));
});
const visibleCount = computed(() => sections.value.reduce((total, section) => total + section.conversations.length, 0));
</script>

<style scoped>
.conversation-list { display: flex; min-height: 0; flex-direction: column; gap: 12px; padding: 16px 12px; }
.conversation-list__header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 8px; padding: 0 4px; }
.conversation-list__title { display: inline-flex; align-items: center; gap: 8px; font-size: 15px; color: var(--el-text-color-primary); }
.conversation-list__count { min-width: 20px; padding: 0 6px; border-radius: 10px; background: var(--el-fill-color); color: var(--el-text-color-secondary); font-size: 11px; font-weight: 500; line-height: 18px; text-align: center; }
.conversation-list__archived { color: var(--el-text-color-secondary); }
.conversation-list__archived[aria-pressed="true"] { color: var(--el-color-primary); }
.conversation-list__header > div { display: flex; flex: 1 1 100%; flex-wrap: wrap; gap: 6px; min-width: 0; }
.conversation-list__header > div :deep(.el-button) { flex: 1 1 auto; min-width: 0; padding-inline: 8px; }
.conversation-list__header :deep(.el-button) { margin-left: 0; }
.conversation-list :deep(.el-button + .el-button) { margin-left: 0; }
.conversation-list :deep(.el-button--small) { min-height: 28px; padding: 5px 8px; }
.conversation-list :deep(.el-button [class^="i-ep-"]), .conversation-list :deep(.el-button [class*=" i-ep-"]) { margin-right: 4px; font-size: 13px; }
.conversation-list__header > div :deep(.el-button > span) { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.conversation-list__filters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; padding: 0 4px; }
.conversation-list__filters > .el-input { grid-column: 1 / -1; }
.conversation-list__filters > .el-input, .conversation-list__filters > .el-select { width: 100%; min-width: 0; }
.conversation-group { display: grid; gap: 2px; }
.conversation-group__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 26px; padding: 0 6px 0 8px; }
.conversation-group__name { display: inline-flex; align-items: center; gap: 6px; min-width: 0; color: var(--el-text-color-secondary); font-size: 12px; font-weight: 500; }
.conversation-group__name small { color: var(--el-text-color-placeholder); font-size: 11px; }
.conversation-row { position: relative; display: flex; align-items: center; min-width: 0; border-radius: 10px; transition: background-color .15s ease; }
.conversation-row:hover { background: var(--el-fill-color-light); }
.conversation-row.active { background: color-mix(in srgb, var(--el-color-primary) 11%, var(--el-bg-color)); }
.conversation-row.active::before { position: absolute; top: 10px; bottom: 10px; left: 0; width: 3px; border-radius: 0 3px 3px 0; background: var(--el-color-primary); content: ''; }
.conversation-row > .conversation-item { flex: 1; min-width: 0; }
.conversation-row > :last-child { flex-shrink: 0; }
.conversation-item { display: grid; gap: 4px; border: 0; border-radius: 10px; padding: 9px 4px 9px 12px; background: transparent; color: var(--el-text-color-regular); font: inherit; text-align: left; cursor: pointer; }
.conversation-item:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: -2px; }
.conversation-item__title { display: flex; align-items: center; gap: 6px; min-width: 0; }
.conversation-item__title strong { min-width: 0; overflow: hidden; font-size: 13px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
.conversation-item.active .conversation-item__title strong { color: var(--el-color-primary); }
.conversation-item.unread .conversation-item__title strong { color: var(--el-text-color-primary); font-weight: 700; }
.conversation-item__meta { display: flex; align-items: center; gap: 8px; min-width: 0; }
.conversation-item small { color: var(--el-text-color-secondary); font-size: 11px; line-height: 16px; }
.conversation-status { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.conversation-status::before { width: 6px; height: 6px; border-radius: 50%; background: var(--el-text-color-placeholder); content: ''; }
.conversation-status--primary::before { background: var(--el-color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 20%, transparent); }
.conversation-status--warning::before { background: var(--el-color-warning); }
.conversation-status--danger::before { background: var(--el-color-danger); }
.conversation-status--success::before { background: var(--el-color-success); }
.unread-dot { flex: none; width: 7px; height: 7px; border-radius: 50%; background: var(--el-color-primary); }
.conversation-more { width: 28px; height: 28px; margin-right: 4px; color: var(--el-text-color-secondary); opacity: 0; transition: opacity .15s ease; }
.conversation-row:hover .conversation-more, .conversation-row:focus-within .conversation-more, .conversation-row.active .conversation-more, .conversation-group__header:hover .conversation-more, .conversation-more:focus-visible { opacity: 1; }
.conversation-list__more { align-self: center; }
.conversation-list :deep(.el-empty) { padding: 24px 0; }
@media (hover: none) { .conversation-more { opacity: 1; } }
</style>
