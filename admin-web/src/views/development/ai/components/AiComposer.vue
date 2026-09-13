<template>
  <section class="ai-composer" @dragover.prevent @drop.prevent="addFiles(Array.from($event.dataTransfer?.files || []))">
    <div class="draft-files">
      <article v-for="item in draft.files" :key="item.key">
        <img v-if="item.preview" :src="item.preview" :alt="item.file.name" />
        <span>{{ item.file.name }}</span><small>{{ item.state }}</small>
        <button v-if="item.state === 'failed'" type="button" :disabled="locked" @click="upload(item)">{{ t('aiComposer.retry') }}</button>
        <button type="button" :disabled="locked || item.state === 'uploading'" @click="remove(item)">{{ t('aiComposer.remove') }}</button>
      </article>
    </div>
    <textarea v-model="draft.text" :disabled="locked || !conversationId" :placeholder="t('aiDevelopment.promptPlaceholder')" :aria-label="t('aiDevelopment.promptPlaceholder')" rows="4" @keydown="keydown" @compositionstart="composing = true" @compositionend="composing = false" @paste="paste" />
    <div class="toolbar">
      <details class="model-menu"><summary>{{ model || t('aiDevelopment.modelSelection.unset') }} · {{ profile?.name || t('aiDevelopment.profiles.title') }}</summary><div class="popover"><slot name="models" /></div></details>
      <details><summary>{{ t('aiComposer.approval') }}</summary><div class="popover"><slot name="approval" /></div></details>
      <input ref="fileInput" type="file" multiple hidden :accept="accept" @change="choose" />
      <button type="button" :aria-label="t('aiComposer.attach')" :disabled="locked || !conversationId" @click="fileInput?.click()"><i class="i-ep-paperclip" /></button>
      <button v-if="running" class="send" type="button" @click="$emit('stop')">{{ t('aiDevelopment.stop') }}</button>
      <button v-else class="send" type="button" :disabled="!canSend" @click="send">{{ t('aiDevelopment.send') }}</button>
    </div>
    <p class="privacy">{{ t('aiComposer.privacy') }}</p>
    <p v-if="draft.error || imageError" role="alert">{{ draft.error || imageError }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
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
function keydown(event: KeyboardEvent) {
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
.ai-composer { position: relative; width: calc(100% - 32px); max-width: 860px; margin: 12px auto; padding: 14px; border: 1px solid var(--el-border-color); border-radius: 18px; background: var(--el-bg-color); box-sizing: border-box; }
textarea { width: 100%; box-sizing: border-box; min-height: 94px; max-height: 220px; resize: vertical; border: 0; outline: none; background: transparent; color: var(--el-text-color-primary); font: inherit; }
textarea:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
.toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
summary, button { cursor: pointer; border: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-extra-light); border-radius: 20px; padding: 7px 10px; color: var(--el-text-color-primary); }
summary { list-style: none; overflow-wrap: anywhere; } button:disabled { opacity: .45; cursor: not-allowed; }
.send { margin-left: auto; background: var(--el-text-color-primary); color: var(--el-bg-color); }
.popover { position: absolute; bottom: calc(100% + 8px); left: 0; z-index: 20; width: min(560px, 100%); max-height: 55vh; overflow: auto; padding: 16px; box-sizing: border-box; border: 1px solid var(--el-border-color); border-radius: 14px; box-shadow: var(--el-box-shadow-light); background: var(--el-bg-color); }
.privacy { font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 0; } [role=alert] { color: var(--el-color-danger); }
.draft-files { display: flex; flex-wrap: wrap; gap: 8px; } article { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; max-width: 100%; overflow-wrap: anywhere; } article img { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; }
@media(max-width: 520px) { .ai-composer { width: calc(100% - 16px); padding: 10px; } .model-menu { max-width: 100%; } .toolbar { gap: 4px; } }
</style>
