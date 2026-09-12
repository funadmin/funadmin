<template>
  <el-drawer :model-value="modelValue" title="ChangeSet / Diff" size="min(920px, 94vw)" @update:model-value="$emit('update:modelValue', $event)">
    <div class="change-set-summary"><el-tag>测试：{{ testStatus }}</el-tag><el-tag>安全：{{ securityStatus }}</el-tag><span>按文件选择，不支持逐块选择</span></div>
    <div class="change-set-layout">
      <nav>
        <label v-for="file in files" :key="file.path" class="file-option">
          <el-checkbox :model-value="selection.includes(file.path)" @update:model-value="toggle(file.path, Boolean($event))" />
          <span>{{ file.path }}</span><el-tag size="small">{{ file.status }}</el-tag>
        </label>
      </nav>
      <FileDiffViewer v-if="activeFile" :file="activeFile" />
    </div>
    <el-alert v-if="preview?.blocked" title="冲突阻断：请取消冲突文件或重新生成 ChangeSet" type="error" :closable="false" />
    <el-alert v-else-if="!preview" title="预览后才能应用；最终应用总是需要二次确认" type="warning" :closable="false" />
    <template #footer>
      <el-button @click="$emit('update:modelValue', false)">关闭</el-button>
      <el-button type="primary" :disabled="selection.length === 0" @click="$emit('preview', selection)">预览</el-button>
      <el-button type="danger" :disabled="!canApply" @click="$emit('apply', preview!.confirmToken!, selection)">二次确认并应用</el-button>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AiChangeSetFile, AiChangeSetPreview } from '@/api/development/ai';
import FileDiffViewer from './FileDiffViewer.vue';
const props = defineProps<{ modelValue: boolean; files: AiChangeSetFile[]; preview: AiChangeSetPreview | null; testStatus: string; securityStatus: string }>();
defineEmits<{ 'update:modelValue': [value: boolean]; preview: [selection: string[]]; apply: [confirmToken: string, selection: string[]] }>();
const selection = ref<string[]>([]);
const activePath = ref('');
watch(() => props.files, (files) => { selection.value = files.filter((file) => !file.status.includes('conflict')).map((file) => file.path); activePath.value = files[0]?.path || ''; }, { immediate: true });
const activeFile = computed(() => props.files.find((file) => file.path === activePath.value) || props.files[0]);
const canApply = computed(() => Boolean(props.preview?.confirmToken) && !props.preview?.blocked && selection.value.length > 0);
function toggle(path: string, checked: boolean) { selection.value = checked ? Array.from(new Set([...selection.value, path])) : selection.value.filter((item) => item !== path); activePath.value = path; }
</script>

<style scoped>
.change-set-summary { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.change-set-layout { display: grid; grid-template-columns: minmax(220px, 32%) 1fr; gap: 16px; margin-bottom: 12px; }
nav { display: grid; align-content: start; gap: 6px; }
.file-option { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 6px; padding: 8px; border-radius: 8px; background: var(--el-fill-color-light); overflow-wrap: anywhere; }
@media (max-width: 720px) { .change-set-layout { grid-template-columns: 1fr; } }
</style>
