<template>
  <div class="message-timeline" aria-live="polite">
    <article v-for="message in messages" :key="message.id" class="message" :class="`message--${message.role}`">
      <header>{{ roleLabel(message.role) }}</header>
      <template v-for="(part, index) in message.content" :key="index">
        <pre v-if="part.type === 'code'" class="code-block"><code :data-language="part.language || 'text'">{{ part.text || '' }}</code></pre>
        <p v-else class="message-text">{{ part.text || '' }}</p>
      </template>
    </article>
    <el-empty v-if="messages.length === 0" :description="t('aiDevelopment.messages.empty')" />
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { AiMessage } from '@/api/development/ai';

defineProps<{ messages: AiMessage[] }>();
const { t } = useI18n();
function roleLabel(role: AiMessage['role']): string { return t(`aiDevelopment.messages.roles.${role}`); }
</script>

<style scoped>
.message-timeline { display: flex; width: min(100%, 860px); max-width: 860px; margin-inline: auto; flex-direction: column; gap: 16px; padding: 18px; }
.message { max-width: 86%; padding: 12px 14px; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.message--user { align-self: flex-end; background: var(--el-color-primary-light-9); }
.message header { margin-bottom: 8px; color: var(--el-text-color-secondary); font-size: 12px; font-weight: 600; }
.message-text { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
.code-block { overflow: auto; margin: 8px 0 0; padding: 12px; border-radius: 8px; background: var(--el-fill-color-dark); color: var(--el-text-color-primary); white-space: pre; }
</style>
