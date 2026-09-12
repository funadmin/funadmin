<template>
  <aside class="conversation-list">
    <div class="conversation-list__header"><strong>{{ t('aiDevelopment.conversations') }}</strong><el-button size="small" type="primary" @click="$emit('create')">{{ t('aiDevelopment.newConversation') }}</el-button></div>
    <button v-for="conversation in conversations" :key="conversation.id" class="conversation-item" :class="{ active: conversation.id === selectedId }" @click="$emit('select', conversation.id)">
      <span>{{ conversation.title || t('aiDevelopment.unnamedConversation') }}</span><small>{{ statusLabel(conversation.status) }}</small>
    </button>
    <el-empty v-if="conversations.length === 0" :description="t('aiDevelopment.noConversations')" />
  </aside>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { AiConversation } from '@/api/development/ai';
import { aiEnumLabel } from '../i18n';
defineProps<{ conversations: AiConversation[]; selectedId: number | null }>();
defineEmits<{ create: []; select: [id: number] }>();
const { t } = useI18n();
const statusLabel = (status: string) => aiEnumLabel(t, 'statuses', status);
</script>

<style scoped>
.conversation-list { display: flex; min-height: 0; flex-direction: column; gap: 8px; padding: 12px; }
.conversation-list__header { display: flex; align-items: center; justify-content: space-between; padding: 4px; }
.conversation-item { display: flex; justify-content: space-between; gap: 8px; border: 0; border-radius: 8px; padding: 11px; background: transparent; color: var(--el-text-color-primary); text-align: left; cursor: pointer; }
.conversation-item:hover, .conversation-item.active { background: var(--el-color-primary-light-9); }
.conversation-item small { color: var(--el-text-color-secondary); }
</style>
