<template>
  <div data-testid="private-attachment">
    <img v-if="preview" :src="preview" :alt="t('aiComposer.attachment')" />
    <button type="button" :disabled="busy" @click="download">{{ t('aiComposer.download') }} #{{ attachmentId }}</button>
    <p v-if="error" role="alert">{{ t('aiComposer.downloadFailed') }} <button type="button" @click="load">{{ t('aiComposer.retry') }}</button></p>
  </div>
</template>
<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { aiDevelopmentApi } from '@/api/development/ai';
const props = defineProps<{ conversationId: number; attachmentId: number }>();
const { t } = useI18n();
const preview = ref(''); const busy = ref(false); const error = ref(false);
let blob: Blob | null = null; let generation = 0; let controller: AbortController | null = null;
const downloads = new Set<string>();
function clear() { controller?.abort(); if (preview.value) URL.revokeObjectURL(preview.value); preview.value = ''; blob = null; }
async function load() {
  const current = ++generation; clear(); busy.value = true; error.value = false; controller = new AbortController();
  try {
    const value = await aiDevelopmentApi.attachmentContent(props.conversationId, props.attachmentId, controller.signal);
    if (current !== generation) return;
    if (!(value instanceof Blob) || !['image/png', 'image/jpeg', 'image/webp', 'text/plain'].includes(value.type.split(';')[0])) throw new Error('invalid');
    blob = value;
    if (value.type.startsWith('image/')) preview.value = URL.createObjectURL(value);
  } catch { if (current === generation) error.value = true; }
  finally { if (current === generation) busy.value = false; }
}
async function download() {
  if (busy.value) return;
  const pending = blob ? null : load();
  const current = generation;
  if (pending) await pending;
  if (current !== generation || !blob) return;
  const url = URL.createObjectURL(new Blob([blob], { type: 'application/octet-stream' })); downloads.add(url);
  const link = document.createElement('a'); link.href = url; link.download = `attachment-${props.attachmentId}.${({ 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp' } as Record<string, string>)[blob.type] || 'txt'}`;
  link.click(); setTimeout(() => { if (downloads.delete(url)) URL.revokeObjectURL(url); }, 1000);
}
watch(() => [props.conversationId, props.attachmentId], () => void load(), { immediate: true });
onBeforeUnmount(() => { generation++; clear(); downloads.forEach(url => URL.revokeObjectURL(url)); downloads.clear(); });
</script>
<style scoped>
img { display: block; max-width: 100%; max-height: 320px; object-fit: contain; border-radius: 8px; } button { cursor: pointer; } [role=alert] { color: var(--el-color-danger); }
</style>
