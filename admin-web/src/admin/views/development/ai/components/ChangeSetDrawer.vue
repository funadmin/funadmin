<template>
  <el-drawer class="change-set-drawer" :model-value="modelValue" :title="t('aiDevelopment.changeSet.title')" size="min(960px, 94vw)" @update:model-value="$emit('update:modelValue', $event)">
    <div class="change-set-summary">
      <el-tag :type="checkType(testStatus)" effect="light" round><i class="i-ep-finished" />{{ t('aiDevelopment.changeSet.test', { status: statusLabel(testStatus) }) }}</el-tag>
      <el-tag :type="checkType(securityStatus)" effect="light" round><i class="i-ep-lock" />{{ t('aiDevelopment.changeSet.security', { status: statusLabel(securityStatus) }) }}</el-tag>
      <span class="change-set-summary__hint">{{ t('aiDevelopment.changeSet.fileSelectionOnly') }}</span>
      <span class="change-set-summary__count">{{ t('aiDevelopment.changeSet.selectedCount', { selected: selection.length, total: files.length }) }}</span>
    </div>
    <el-alert v-if="preview?.blocked" class="change-set-alert" :title="t('aiDevelopment.changeSet.conflictBlocked')" type="error" show-icon :closable="false" />
    <el-alert v-else-if="!preview" class="change-set-alert" :title="t('aiDevelopment.changeSet.previewRequired')" type="warning" show-icon :closable="false" />
    <div class="change-set-layout">
      <nav>
        <div v-for="file in files" :key="file.path" class="file-option" :class="{ active: activeFile?.path === file.path }" @click="activePath = file.path">
          <el-checkbox :model-value="selection.includes(file.path)" @click.stop @update:model-value="toggle(file.path, Boolean($event))" />
          <span class="file-option__path"><strong>{{ fileName(file.path) }}</strong><small v-if="fileDir(file.path)">{{ fileDir(file.path) }}</small></span>
          <el-tag size="small" :type="fileType(file.status)" effect="light">{{ aiEnumLabel(t, 'fileStatuses', file.status) }}</el-tag>
        </div>
      </nav>
      <FileDiffViewer v-if="activeFile" :file="activeFile" />
    </div>
    <template #footer>
      <el-button @click="$emit('update:modelValue', false)">{{ t('aiDevelopment.changeSet.close') }}</el-button>
      <el-button type="primary" plain :disabled="selection.length === 0" @click="$emit('preview', selection)">{{ t('aiDevelopment.changeSet.preview') }}</el-button>
      <el-button type="danger" :disabled="!canApply" @click="$emit('apply', preview!.confirmToken!, selection)">{{ t('aiDevelopment.changeSet.confirmApply') }}</el-button>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { TagProps } from 'element-plus';
import type { AiChangeFileStatus, AiChangeSetFile, AiChangeSetPreview } from '@/api/development/ai';
import FileDiffViewer from './FileDiffViewer.vue';
import { aiEnumLabel } from '../i18n';
const props = defineProps<{ modelValue: boolean; files: AiChangeSetFile[]; preview: AiChangeSetPreview | null; testStatus: string; securityStatus: string }>();
defineEmits<{ 'update:modelValue': [value: boolean]; preview: [selection: string[]]; apply: [confirmToken: string, selection: string[]] }>();
const { t } = useI18n();
const selection = ref<string[]>([]);
const activePath = ref('');
watch(() => props.files, (files) => { selection.value = files.filter((file) => !file.status.includes('conflict')).map((file) => file.path); activePath.value = files[0]?.path || ''; }, { immediate: true });
const activeFile = computed(() => props.files.find((file) => file.path === activePath.value) || props.files[0]);
const canApply = computed(() => Boolean(props.preview?.confirmToken) && !props.preview?.blocked && selection.value.length > 0);
const statusLabel = (status: string) => aiEnumLabel(t, 'statuses', status);
const checkType = (status: string): TagProps['type'] => (['passed', 'succeeded'].includes(status) ? 'success' : ['failed', 'rejected'].includes(status) ? 'danger' : 'info');
const fileType = (status: AiChangeFileStatus): TagProps['type'] => (status.includes('conflict') || status === 'delete' ? 'danger' : status === 'create' ? 'success' : status === 'keep-local' ? 'info' : 'primary');
const fileName = (path: string) => path.split('/').pop() || path;
const fileDir = (path: string) => path.split('/').slice(0, -1).join('/');
function toggle(path: string, checked: boolean) { selection.value = checked ? Array.from(new Set([...selection.value, path])) : selection.value.filter((item) => item !== path); activePath.value = path; }
</script>

<style scoped>
:global(.change-set-drawer .el-drawer__header) { margin-bottom: 0; padding: 16px 20px; border-bottom: 1px solid var(--el-border-color-lighter); }
:global(.change-set-drawer .el-drawer__body) { display: flex; flex-direction: column; gap: 12px; padding: 16px 20px; }
:global(.change-set-drawer .el-drawer__footer) { padding: 12px 20px; border-top: 1px solid var(--el-border-color-lighter); }
.change-set-summary { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.change-set-summary :deep(.el-tag i) { margin-right: 4px; }
.change-set-summary__hint { color: var(--el-text-color-secondary); font-size: 12px; }
.change-set-summary__count { margin-left: auto; color: var(--el-text-color-regular); font-size: 12px; }
.change-set-layout { display: grid; flex: 1; min-height: 0; grid-template-columns: minmax(240px, 34%) minmax(0, 1fr); gap: 16px; }
nav { display: grid; align-content: start; gap: 6px; min-height: 0; overflow: auto; }
.file-option { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid transparent; border-radius: 10px; background: var(--el-fill-color-light); cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
.file-option:hover { border-color: var(--el-border-color); }
.file-option.active { border-color: color-mix(in srgb, var(--el-color-primary) 55%, transparent); background: color-mix(in srgb, var(--el-color-primary) 8%, var(--el-bg-color)); }
.file-option__path { display: grid; min-width: 0; }
.file-option__path strong { overflow: hidden; color: var(--el-text-color-primary); font-size: 13px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
.file-option__path small { overflow: hidden; color: var(--el-text-color-secondary); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
@media (max-width: 720px) { .change-set-layout { grid-template-columns: 1fr; } }
</style>
