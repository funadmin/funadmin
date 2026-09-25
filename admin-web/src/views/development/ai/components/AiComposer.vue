<template>
  <section class="ai-composer" @dragover.prevent @drop.prevent="addFiles(Array.from($event.dataTransfer?.files || []))">
    <div class="ai-composer__box" :class="{ 'is-disabled': locked || !conversationId }">
      <div v-if="draft.files.length" class="draft-files">
        <article v-for="item in draft.files" :key="item.key" class="draft-file" :class="`draft-file--${item.state}`">
          <img v-if="item.preview" :src="item.preview" :alt="item.file.name" />
          <span v-else class="draft-file__icon"><i class="i-ep-document" /></span>
          <span class="draft-file__body"><span class="draft-file__name">{{ item.file.name }}</span><small>{{ t(`aiComposer.states.${item.state}`) }}</small></span>
          <el-button v-if="item.state === 'failed'" size="small" link type="primary" :disabled="locked" @click="upload(item)">{{ t('aiComposer.retry') }}</el-button>
          <el-button size="small" link :disabled="locked || item.state === 'uploading'" @click="remove(item)">{{ t('aiComposer.remove') }}</el-button>
        </article>
      </div>
      <el-input v-model="draft.text" type="textarea" :disabled="locked || !conversationId" :placeholder="t('aiDevelopment.promptPlaceholder')" :aria-label="t('aiDevelopment.promptPlaceholder')" :rows="3" resize="none" @keydown="keydown" @compositionstart="composing = true" @compositionend="composing = false" @paste="paste" />
      <div class="toolbar">
        <el-popover v-model:visible="modelsOpen" trigger="click" placement="top-start" :width="360" :teleported="true" :persistent="false" :popper-style="popoverStyle" @show="approvalOpen = false">
          <template #reference><el-button size="small" round ref="modelTrigger" class="model-menu" data-testid="model-menu-trigger" :aria-expanded="modelsOpen" @keydown.esc="modelsOpen = false"><i class="i-ep-cpu" /><span>{{ model || t('aiDevelopment.modelSelection.unset') }} · {{ profile?.name || t('aiDevelopment.profiles.title') }}</span></el-button></template>
          <div v-if="modelsOpen" @keydown.esc.stop="closeModels"><slot name="models" /></div>
        </el-popover>
        <el-popover v-model:visible="approvalOpen" trigger="click" placement="top-start" :width="360" :teleported="true" :persistent="false" :popper-style="popoverStyle" @show="modelsOpen = false">
          <template #reference><el-button size="small" round :aria-expanded="approvalOpen" @keydown.esc="approvalOpen = false"><i class="i-ep-lock" />{{ t('aiComposer.approval') }}</el-button></template>
          <div v-if="approvalOpen"><slot name="approval" /></div>
        </el-popover>
        <input ref="fileInput" type="file" multiple hidden :accept="accept" @change="choose" />
        <el-button size="small" circle :aria-label="t('aiComposer.attach')" :icon="Paperclip" :disabled="locked || !conversationId" @click="fileInput?.click()" />
        <span class="send-hint">{{ t('aiComposer.sendHint') }}</span>
        <el-button v-if="running" size="small" round class="send" type="danger" @click="$emit('stop')"><i class="i-ep-video-pause" />{{ t('aiDevelopment.stop') }}</el-button>
        <el-button v-else size="small" round class="send" type="primary" :disabled="!canSend" @click="send"><i class="i-ep-promotion" />{{ t('aiDevelopment.send') }}</el-button>
      </div>
    </div>
    <p v-if="draft.error || imageError" role="alert">{{ draft.error || imageError }}</p>
    <p class="privacy"><i class="i-ep-lock" />{{ t('aiComposer.privacy') }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElButton, ElInput, ElPopover } from 'element-plus';
import { Paperclip } from '@element-plus/icons-vue';
import { aiDevelopmentApi as api, profileModelCapability, type AiAttachment, type AiMessage, type AiProfile, type AiTask } from '@/api/development/ai';
const props = defineProps<{ conversationId: number | null; model: string; profile?: AiProfile; running: boolean; saving: boolean }>();
const emit = defineEmits<{ sent: [task: AiTask, message: AiMessage, conversationId: number]; stop: [] }>();
const { t } = useI18n();
type Entry = { key: string; file: File; preview: string; state: 'uploading' | 'ready' | 'failed'; attachment?: AiAttachment };
type Draft = { text: string; files: Entry[]; error: string; busy: boolean; message?: AiMessage; payload?: { role: 'user'; content: AiMessage['content']; idempotency_key: string }; taskKey: string };
const drafts = reactive(new Map<number, Draft>());
const empty = (): Draft => ({ text: '', files: [], error: '', busy: false, taskKey: crypto.randomUUID() });
const blank = reactive(empty());
const draft = computed(() => {
  if (!props.conversationId) return blank;
  if (!drafts.has(props.conversationId)) drafts.set(props.conversationId, empty());
  return drafts.get(props.conversationId)!;
});
const locked = computed(() => draft.value.busy || !!draft.value.payload);
const composing = ref(false);
const fileInput = ref<HTMLInputElement>();
const modelsOpen = ref(false);
const approvalOpen = ref(false);
const popoverStyle = { maxWidth: 'calc(100vw - 32px)', maxHeight: '55vh', overflow: 'auto', overflowWrap: 'anywhere' } as const;
const modelTrigger = ref<InstanceType<typeof ElButton>>();
async function closeModels() {
  modelsOpen.value = false;
  await nextTick();
  modelTrigger.value?.$el.focus();
}
const extensions = 'txt md csv json yaml yml xml html css scss js jsx ts tsx vue php py rb go rs java c h cpp hpp cs sql sh bash zsh toml ini conf log diff patch kt swift'.split(' ');
const imageMimes: Record<string, string> = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', webp: 'image/webp' };
const accept = [...Object.keys(imageMimes), ...extensions].map(e => `.${e}`).join(',');
const imageError = computed(() => {
  const images = draft.value.files.filter(f => f.attachment?.kind === 'image');
  if (!images.length) return '';
  if (!props.profile || !props.profile.enabled) return t('aiComposer.incompatible');
  const models = [props.model, ...(props.profile.fallback_enabled ? props.profile.fallback_models : [])];
  return models.some(model => {
    const cap = profileModelCapability(props.profile!, model);
    return !cap.image_input || images.length > (cap.max_images || 4) || images.some(f => !cap.image_mime_types?.includes(f.attachment!.mime));
  }) ? t('aiComposer.incompatible') : '';
});
const canSend = computed(() => !!props.conversationId && !props.running && !props.saving && !draft.value.busy && !imageError.value && (!!draft.value.text.trim() || draft.value.files.length > 0) && draft.value.files.every(f => f.state === 'ready'));
function release(d: Draft) { for (const f of d.files) { if (f.preview) URL.revokeObjectURL(f.preview); f.preview = ''; } }
watch(() => props.conversationId, (_, previous) => {
  modelsOpen.value = false;
  approvalOpen.value = false;
  if (previous && drafts.has(previous)) release(drafts.get(previous)!);
  for (const f of draft.value.files) if (f.file.type.startsWith('image/') && !f.preview) f.preview = URL.createObjectURL(f.file);
});
let alive = true;
onBeforeUnmount(() => { alive = false; drafts.forEach(release); });
async function addFiles(files: File[]) {
  const d = draft.value;
  const id = props.conversationId;
  if (!id || locked.value) return;
  d.error = '';
  for (const file of files) {
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    const mime = imageMimes[ext];
    if ((!mime && !extensions.includes(ext)) || (mime && file.type && mime !== file.type) || !file.size || file.size > (mime ? 5 * 1024 * 1024 : 128 * 1024) || /[\x00-\x1f\x7f/\\]/.test(file.name) || new TextEncoder().encode(file.name).length > 255 || d.files.length >= 4 || d.files.reduce((n, f) => n + Math.max(f.file.size, f.attachment?.size || 0), file.size) > 12 * 1024 * 1024) { d.error = t('aiComposer.invalid'); continue; }
    const item: Entry = reactive({ key: crypto.randomUUID(), file, preview: mime && props.conversationId === id ? URL.createObjectURL(file) : '', state: 'uploading' });
    d.files.push(item);
    void upload(item, d, id);
  }
}
async function upload(item: Entry, d = draft.value, id = props.conversationId) {
  if (!id || d.busy || d.message) return;
  item.state = 'uploading';
  try {
    const ext = item.file.name.split('.').pop()?.toLowerCase() || '';
    if (!imageMimes[ext]) {
      const text = new TextDecoder('utf-8', { fatal: true }).decode(await item.file.arrayBuffer());
      if (/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/.test(text) || /^(%PDF-|PK\x03\x04|MZ)/.test(text)) throw new Error('invalid');
    }
    item.attachment = await api.uploadAttachment(id, item.file);
    item.state = 'ready';
    if (d.files.every(f => f.state === 'ready')) d.error = '';
  } catch { item.state = 'failed'; d.error = t('aiComposer.uploadFailed'); }
  if (!alive && item.preview) { URL.revokeObjectURL(item.preview); item.preview = ''; }
}
async function remove(item: Entry) {
  const d = draft.value; const id = props.conversationId;
  if (!id || locked.value || item.state === 'uploading') return;
  d.busy = true;
  try {
    if (item.attachment) await api.deleteAttachment(id, item.attachment.id);
    if (item.preview) URL.revokeObjectURL(item.preview);
    d.files.splice(d.files.indexOf(item), 1); d.error = '';
  } catch { d.error = t('aiComposer.removeFailed'); }
  finally { d.busy = false; }
}
function choose(event: Event) { const input = event.target as HTMLInputElement; void addFiles(Array.from(input.files || [])); input.value = ''; }
function paste(event: ClipboardEvent) { const files = Array.from(event.clipboardData?.files || []); if (files.length) { event.preventDefault(); void addFiles(files); } }
function keydown(event: Event | KeyboardEvent) {
  if (!(event instanceof KeyboardEvent)) return;
  if (event.key !== 'Enter' || event.shiftKey || composing.value || event.isComposing || event.keyCode === 229) return;
  event.preventDefault(); if (!event.repeat) void send();
}
async function send() {
  if (!canSend.value) return;
  const d = draft.value; const id = props.conversationId!;
  d.busy = true; d.error = '';
  try {
    d.payload ??= { role: 'user', idempotency_key: d.taskKey, content: [...(d.text.trim() ? [{ type: 'text', text: d.text.trim() }] : []), ...d.files.map(f => ({ type: 'attachment', attachment_id: f.attachment!.id }))] };
    if (!d.message) d.message = await api.createMessage(id, d.payload);
    if (!alive || props.conversationId !== id) return;
    const task = await api.executeTask(id, { message_id: d.message.id, idempotency_key: d.taskKey, type: 'chat' });
    const message = d.message;
    release(d); drafts.set(id, empty());
    if (alive) emit('sent', task, message, id);
  } catch { d.error = t('aiComposer.sendFailed'); }
  finally { d.busy = false; }
}
</script>

<style scoped>
.ai-composer { position: relative; width: 100%; min-width: 0; padding: 12px 16px; box-sizing: border-box; border-top: 1px solid var(--el-border-color-lighter); background: var(--el-bg-color); }
.ai-composer__box { display: grid; gap: 8px; min-width: 0; padding: 10px 12px; border: 1px solid var(--el-border-color); border-radius: 16px; background: var(--el-bg-color); box-shadow: 0 2px 10px rgba(15, 23, 42, .05); transition: border-color .15s ease, box-shadow .15s ease; }
.ai-composer__box:focus-within { border-color: var(--el-color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 16%, transparent); }
.ai-composer__box.is-disabled { background: var(--el-fill-color-lighter); box-shadow: none; }
.ai-composer :deep(.el-textarea__inner) { min-height: 72px !important; max-height: 220px; padding: 2px 2px; border: 0; background: transparent; box-shadow: none; font-size: 14px; line-height: 1.7; }
.ai-composer :deep(.el-textarea.is-disabled .el-textarea__inner) { background: transparent; }
.toolbar { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.toolbar :deep(.el-button) { margin-left: 0; min-height: 28px; }
.toolbar :deep(.el-button:not(.is-circle)) { padding: 5px 12px; }
.toolbar :deep(.el-button i) { margin-right: 4px; font-size: 13px; }
.toolbar .send { margin-left: 8px; }
.send-hint { margin-left: auto; color: var(--el-text-color-placeholder); font-size: 12px; white-space: nowrap; }
.model-menu { min-width: 0; max-width: 100%; }
.model-menu :deep(span) { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.privacy { display: flex; gap: 6px; align-items: flex-start; margin: 8px 4px 0; color: var(--el-text-color-placeholder); font-size: 11px; line-height: 1.5; }
.privacy i { flex: none; margin-top: 2px; }
[role=alert] { margin: 8px 4px 0; color: var(--el-color-danger); font-size: 12px; }
.draft-files { display: flex; flex-wrap: wrap; gap: 8px; }
.draft-file { display: flex; align-items: center; gap: 8px; max-width: 100%; padding: 6px 10px 6px 6px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: var(--el-fill-color-lighter); overflow-wrap: anywhere; }
.draft-file--failed { border-color: color-mix(in srgb, var(--el-color-danger) 45%, transparent); }
.draft-file img, .draft-file__icon { flex: none; width: 36px; height: 36px; border-radius: 8px; object-fit: cover; }
.draft-file__icon { display: grid; place-items: center; background: var(--el-bg-color); color: var(--el-text-color-secondary); font-size: 16px; }
.draft-file__body { display: grid; min-width: 0; }
.draft-file__name { overflow: hidden; max-width: 180px; color: var(--el-text-color-primary); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.draft-file small { color: var(--el-text-color-secondary); font-size: 11px; }
.draft-file--failed small { color: var(--el-color-danger); }
.draft-file :deep(.el-button + .el-button) { margin-left: 0; }
@media(max-width: 520px) { .ai-composer { padding: 10px 16px; } .model-menu { max-width: 100%; } .toolbar { gap: 4px; } .send-hint { display: none; } .toolbar .send { margin-left: auto; } }
</style>
