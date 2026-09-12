<template>
  <aside class="conversation-list">
    <div class="conversation-list__header"><strong>AI 会话</strong><el-button size="small" type="primary" @click="$emit('create')">新建</el-button></div>
    <button v-for="conversation in conversations" :key="conversation.id" class="conversation-item" :class="{ active: conversation.id === selectedId }" @click="$emit('select', conversation.id)">
      <span>{{ conversation.title || '未命名会话' }}</span><small>{{ conversation.status }}</small>
    </button>
    <el-empty v-if="conversations.length === 0" description="暂无会话" />
  </aside>
</template>

<script setup lang="ts">
import type { AiConversation } from '@/api/development/ai';
defineProps<{ conversations: AiConversation[]; selectedId: number | null }>();
defineEmits<{ create: []; select: [id: number] }>();
</script>

<style scoped>
.conversation-list { display: flex; min-height: 0; flex-direction: column; gap: 8px; padding: 12px; }
.conversation-list__header { display: flex; align-items: center; justify-content: space-between; padding: 4px; }
.conversation-item { display: flex; justify-content: space-between; gap: 8px; border: 0; border-radius: 8px; padding: 11px; background: transparent; color: var(--el-text-color-primary); text-align: left; cursor: pointer; }
.conversation-item:hover, .conversation-item.active { background: var(--el-color-primary-light-9); }
.conversation-item small { color: var(--el-text-color-secondary); }
</style>
