<template>
  <div class="message-timeline" aria-live="polite">
    <article v-for="message in messages" :key="message.id" class="message" :class="`message--${message.role}`" :aria-label="roleLabel(message.role)">
      <template v-for="(part, index) in message.content" :key="index">
        <PrivateAttachment v-if="part.type === 'attachment' && part.attachment_id" :conversation-id="message.conversation_id" :attachment-id="part.attachment_id" />
        <div v-else-if="part.type === 'code'" class="code-card">
          <div class="code-card__header">
            <span>{{ part.language || 'text' }}</span>
            <el-button link size="small" :aria-label="t('aiDevelopment.messages.copy')" @click="copy(`${message.id}-${index}`, part.text || '')"><i :class="copied === `${message.id}-${index}` ? 'i-ep-check' : 'i-ep-copy-document'" />{{ copied === `${message.id}-${index}` ? t('aiDevelopment.messages.copied') : t('aiDevelopment.messages.copy') }}</el-button>
          </div>
          <pre class="code-block"><code :data-language="part.language || 'text'">{{ part.text || '' }}</code></pre>
        </div>
        <p v-else class="message-text">{{ part.text || '' }}</p>
      </template>
      <div v-if="callsByMessage.get(message.id)?.length" class="message-tools">
        <InlineToolCall v-for="call in callsByMessage.get(message.id)" :key="call.id" :call="call" @open-log="(id, stream) => $emit('open-log', id, stream)" />
      </div>
    </article>
    <div v-if="unassignedCalls.length" class="message-tools message-tools--unassigned" data-testid="unassigned-tool-calls">
      <InlineToolCall v-for="call in unassignedCalls" :key="call.id" :call="call" @open-log="(id, stream) => $emit('open-log', id, stream)" />
    </div>
    <el-empty v-if="messages.length === 0 && !unassignedCalls.length" :image-size="80" :description="t('aiDevelopment.messages.empty')" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AiMessage, AiToolCall } from '@/api/development/ai';
import PrivateAttachment from './PrivateAttachment.vue';
import InlineToolCall from './InlineToolCall.vue';

const props = withDefaults(defineProps<{ messages: AiMessage[]; toolCalls?: AiToolCall[] }>(), { toolCalls: () => [] });
defineEmits<{ 'open-log': [id: number, stream: 'stdout' | 'stderr'] }>();
// 工具调用按 message_id 归属到消息；无归属或消息尚未加载的调用排在末尾，保证不丢失。
const callsByMessage = computed(() => {
  const ids = new Set(props.messages.map(message => message.id));
  const map = new Map<number, AiToolCall[]>();
  for (const call of props.toolCalls) {
    if (call.message_id === null || !ids.has(call.message_id)) continue;
    map.set(call.message_id, [...(map.get(call.message_id) || []), call]);
  }
  return map;
});
const unassignedCalls = computed(() => {
  const ids = new Set(props.messages.map(message => message.id));
  return props.toolCalls.filter(call => call.message_id === null || !ids.has(call.message_id));
});
const { t } = useI18n();
const copied = ref('');
let copiedTimer: ReturnType<typeof setTimeout> | undefined;
function roleLabel(role: AiMessage['role']): string { return t(`aiDevelopment.messages.roles.${role}`); }
async function copy(key: string, text: string) {
  try { await navigator.clipboard.writeText(text); } catch { return; }
  copied.value = key;
  clearTimeout(copiedTimer);
  copiedTimer = setTimeout(() => { copied.value = ''; }, 1600);
}
onBeforeUnmount(() => clearTimeout(copiedTimer));
</script>

<style scoped>
.message-timeline { display: flex; width: 100%; min-width: 0; box-sizing: border-box; flex-direction: column; gap: 18px; padding: 24px 16px; }
.message { position: relative; display: grid; gap: 10px; max-width: 86%; min-width: 0; box-sizing: border-box; padding: 12px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 4px 16px 16px 16px; background: var(--el-bg-color); color: var(--el-text-color-primary); font-size: 14px; line-height: 1.7; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
.message--user { align-self: flex-end; border-color: transparent; border-radius: 16px 4px 16px 16px; background: color-mix(in srgb, var(--el-color-primary) 12%, var(--el-bg-color)); box-shadow: none; }
.message:not(.message--user) { align-self: flex-start; }
.message--assistant { margin-left: 40px; }
.message--assistant::before { position: absolute; top: 0; left: -40px; display: grid; width: 30px; height: 30px; place-items: center; border-radius: 10px; background: linear-gradient(135deg, var(--el-color-primary), color-mix(in srgb, var(--el-color-primary) 55%, #13c2c2)); color: #fff; font-size: 11px; font-weight: 700; letter-spacing: .02em; content: 'AI'; }
.message--system { align-self: center !important; max-width: min(86%, 640px); padding: 6px 14px; border-style: dashed; border-radius: 999px; background: transparent; color: var(--el-text-color-secondary); font-size: 12px; box-shadow: none; }
.message--tool { margin-left: 40px; border-radius: 10px; background: var(--el-fill-color-light); color: var(--el-text-color-regular); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; box-shadow: none; }
.message-tools { display: grid; gap: 6px; min-width: 0; }
.message-tools--unassigned { align-self: stretch; margin-left: 40px; }
.message-text { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
.code-card { overflow: hidden; min-width: 0; border-radius: 10px; background: #0f172a; }
.code-card__header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 8px 4px 12px; border-bottom: 1px solid rgba(148, 163, 184, .16); color: #94a3b8; font-size: 12px; }
.code-card__header :deep(.el-button) { color: #94a3b8; font-size: 12px; }
.code-card__header :deep(.el-button:hover) { color: #e2e8f0; }
.code-card__header :deep(.el-button i) { margin-right: 4px; }
.code-block { overflow: auto; margin: 0; padding: 12px 14px; background: transparent; color: #e2e8f0; font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 13px; line-height: 1.6; white-space: pre; }
.message-timeline :deep(.el-empty) { margin: auto; }
@media (max-width: 680px) { .message--assistant, .message--tool, .message-tools--unassigned { margin-left: 0; } .message--assistant::before { display: none; } }
</style>
