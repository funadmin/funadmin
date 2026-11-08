<template>
  <header class="designer-topbar" data-designer-topbar>
    <el-tooltip :content="t('formDesigner.topBar.back', '返回代码生成')" placement="bottom">
      <el-button class="designer-topbar__icon" text data-action="back-to-list" :aria-label="t('formDesigner.topBar.back', '返回代码生成')" @click="emit('back')">
        <i class="i-ep-back" aria-hidden="true" />
      </el-button>
    </el-tooltip>
    <div class="designer-topbar__title">
      <b data-topbar-title :title="title">{{ title || t('formDesigner.unnamed', '未命名') }}</b>
      <span data-topbar-meta :title="meta">{{ meta }}</span>
    </div>
    <span class="designer-topbar__sep" aria-hidden="true" />
    <el-tooltip :content="undoLabel" placement="bottom">
      <el-button class="designer-topbar__icon" text data-action="undo" :aria-label="undoLabel" :disabled="!canUndo" @click="emit('undo')">
        <i class="i-ep-refresh-left" aria-hidden="true" />
      </el-button>
    </el-tooltip>
    <el-tooltip :content="redoLabel" placement="bottom">
      <el-button class="designer-topbar__icon" text data-action="redo" :aria-label="redoLabel" :disabled="!canRedo" @click="emit('redo')">
        <i class="i-ep-refresh-right" aria-hidden="true" />
      </el-button>
    </el-tooltip>
    <span class="designer-topbar__sep" aria-hidden="true" />
    <div class="designer-topbar__segmented" role="radiogroup" :aria-label="t('formDesigner.topBar.modeAria', '编辑与预览模式')">
      <button
        v-for="option in modeOptions"
        :key="option.value"
        type="button"
        role="radio"
        :data-mode="option.value"
        :aria-checked="mode === option.value"
        :class="{ 'is-active': mode === option.value }"
        @click="emit('update:mode', option.value)"
      ><i :class="option.icon" aria-hidden="true" />{{ option.label }}</button>
    </div>
    <span class="designer-topbar__spacer" />
    <!-- 保存失败时用提示气泡与 title 展示服务端错误（自动保存失败时不弹窗也能看到原因） -->
    <el-tooltip :content="failedMessage" :disabled="!failedMessage" placement="bottom">
      <span class="designer-topbar__status" data-save-status :data-tone="indicator.tone" role="status" :title="failedMessage || undefined" :tabindex="failedMessage ? 0 : undefined">
        <i class="designer-topbar__dot" aria-hidden="true" />{{ statusLabel }}<i v-if="failedMessage" class="i-ep-warning designer-topbar__error-icon" data-save-error-icon aria-hidden="true" />
      </span>
    </el-tooltip>
    <el-button v-if="canSettings" data-action="settings" :disabled="settingsDisabled" @click="emit('settings')">
      <i class="i-ep-setting mr-1" aria-hidden="true" />{{ t('formDesigner.topBar.settings', '表单设置') }}
    </el-button>
    <el-dropdown trigger="click" placement="bottom-end">
      <el-button data-action="more" :aria-label="t('formDesigner.topBar.more', '更多操作')">
        <i class="i-ep-more-filled" aria-hidden="true" />
      </el-button>
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item v-if="showDynamicPublish" data-action="dynamic-publish" :disabled="actionsDisabled" @click="onMenu('dynamic-publish', actionsDisabled)">{{ t('formDesigner.topBar.dynamicPublish', '动态发布') }}</el-dropdown-item>
          <el-dropdown-item v-if="canVersions" data-action="versions" :disabled="versionsDisabled" @click="onMenu('versions', versionsDisabled)">{{ t('formDesigner.topBar.versions', '版本历史') }}</el-dropdown-item>
          <el-dropdown-item v-if="canExport" data-action="export-json" :disabled="exportDisabled" @click="onMenu('export', exportDisabled)">{{ t('formDesigner.topBar.exportJson', '导出 JSON') }}</el-dropdown-item>
          <el-dropdown-item v-if="canOpenRuntime" data-action="open-runtime" :disabled="runtimeDisabled" @click="onMenu('runtime', runtimeDisabled)">{{ t('formDesigner.topBar.openRuntime', '打开运行时') }}</el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>
    <el-button v-if="canPreview" data-action="preview-code" type="primary" plain :disabled="actionsDisabled" @click="emit('preview')">{{ t('formDesigner.topBar.previewCode', '预览代码') }}</el-button>
    <el-tooltip v-if="canGenerate" :content="generateHint" :disabled="!generateHint" placement="bottom-end">
      <span class="designer-topbar__wrap">
        <el-button data-action="generate" type="primary" :plain="false" :disabled="actionsDisabled" @click="emit('generate')">{{ t('formDesigner.topBar.generate', '生成') }}</el-button>
      </span>
    </el-tooltip>
  </header>
</template>

<script setup lang="ts">
import { computed, onActivated, onBeforeUnmount, onDeactivated, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DesignerSaveStatus } from '../../composables/useDesigner';
import { isShortcutExempt, saveIndicator, shortcutAction, type DesignerWorkspaceMode } from './designerTopBar';

const props = withDefaults(defineProps<{
  title: string;
  formKey: string;
  tableName: string;
  targetLabel: string;
  /** 「生成」按钮的提示（插件目标的生成说明）。 */
  generateHint?: string;
  canUndo: boolean;
  canRedo: boolean;
  mode: DesignerWorkspaceMode;
  saveStatus: DesignerSaveStatus;
  online: boolean;
  saveBlocked: boolean;
  /** 最近一次保存失败的原因（服务端错误信息），仅在「保存失败」状态显示。 */
  saveError?: string;
  /** 有未保存编辑、保存冲突暂停或模块未加载时，预览/生成/动态发布不可用。 */
  actionsDisabled: boolean;
  canSettings: boolean;
  canPreview: boolean;
  canGenerate: boolean;
  showDynamicPublish: boolean;
  canVersions: boolean;
  canExport: boolean;
  canOpenRuntime: boolean;
  settingsDisabled?: boolean;
  versionsDisabled?: boolean;
  exportDisabled?: boolean;
  runtimeDisabled?: boolean;
}>(), { saveError: '', generateHint: '', settingsDisabled: false, versionsDisabled: false, exportDisabled: false, runtimeDisabled: false });

const emit = defineEmits<{
  back: [];
  undo: [];
  redo: [];
  'update:mode': [mode: DesignerWorkspaceMode];
  settings: [];
  preview: [];
  generate: [];
  'dynamic-publish': [];
  versions: [];
  export: [];
  runtime: [];
}>();

const { t } = useI18n();
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent);
const undoLabel = computed(() => t('formDesigner.topBar.undoShortcut', { key: isMac ? '⌘Z' : 'Ctrl+Z' }, '撤销（{key}）'));
const redoLabel = computed(() => t('formDesigner.topBar.redoShortcut', { key: isMac ? '⇧⌘Z' : 'Ctrl+Shift+Z' }, '重做（{key}）'));
const meta = computed(() => `${props.formKey} → ${props.tableName} · ${props.targetLabel}`);
const modeOptions = computed(() => [
  { value: 'edit' as const, icon: 'i-ep-edit', label: t('formDesigner.topBar.modeEdit', '编辑') },
  { value: 'desktop' as const, icon: 'i-ep-monitor', label: t('formDesigner.topBar.modeDesktop', '桌面') },
  { value: 'tablet' as const, icon: 'i-ep-postcard', label: t('formDesigner.topBar.modeTablet', '平板') },
  { value: 'mobile' as const, icon: 'i-ep-iphone', label: t('formDesigner.topBar.modeMobile', '手机') }
]);
const indicator = computed(() => saveIndicator({ saveStatus: props.saveStatus, online: props.online, saveBlocked: props.saveBlocked }));
const failedMessage = computed(() => (indicator.value.key === 'failed' ? props.saveError : ''));
const statusLabel = computed(() => ({
  saved: t('formDesigner.saved', '已保存'),
  unsaved: t('formDesigner.unsaved', '未保存'),
  saving: t('formDesigner.saving', '保存中'),
  failed: t('formDesigner.saveFailed', '保存失败'),
  offline: t('formDesigner.offlineDraft', '离线草稿'),
  paused: t('formDesigner.topBar.paused', '保存已暂停')
})[indicator.value.key]);

type MenuEvent = 'dynamic-publish' | 'versions' | 'export' | 'runtime';
function onMenu(event: MenuEvent, disabled: boolean) {
  if (disabled) return;
  // 逐个分支调用以保持 defineEmits 的类型约束。
  if (event === 'dynamic-publish') emit('dynamic-publish');
  else if (event === 'versions') emit('versions');
  else if (event === 'export') emit('export');
  else emit('runtime');
}

function onKeydown(event: KeyboardEvent) {
  const action = shortcutAction(event);
  if (!action || isShortcutExempt(event.target)) return;
  if (action === 'undo' ? !props.canUndo : !props.canRedo) return;
  event.preventDefault();
  if (action === 'undo') emit('undo');
  else emit('redo');
}

// 设计器在 KeepAlive 中缓存：停用时必须摘掉全局快捷键，避免撤销后台实例。
let listening = false;
const listen = () => {
  if (listening || typeof window === 'undefined') return;
  window.addEventListener('keydown', onKeydown);
  listening = true;
};
const unlisten = () => {
  if (!listening) return;
  window.removeEventListener('keydown', onKeydown);
  listening = false;
};
onMounted(listen);
onActivated(listen);
onDeactivated(unlisten);
onBeforeUnmount(unlisten);
</script>

<style scoped>
.designer-topbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 10px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  background: var(--el-bg-color);
}

.designer-topbar :deep(.el-button + .el-button) {
  margin-left: 0;
}

.designer-topbar__icon {
  width: 32px;
  padding: 0;
  color: var(--el-text-color-regular);
  font-size: 16px;
}

.designer-topbar__title {
  display: grid;
  min-width: 0;
  max-width: 320px;
  line-height: 1.3;
}

.designer-topbar__title b,
.designer-topbar__title span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.designer-topbar__title b {
  color: var(--el-text-color-primary);
  font-size: 15px;
}

.designer-topbar__title span {
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

.designer-topbar__sep {
  width: 1px;
  height: 22px;
  background: var(--el-border-color-lighter);
}

.designer-topbar__spacer {
  flex: 1;
}

.designer-topbar__segmented {
  display: inline-flex;
  gap: 2px;
  padding: 3px;
  border-radius: 8px;
  background: var(--el-fill-color-light);
}

.designer-topbar__segmented button {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--el-text-color-regular);
  font-size: 12px;
  line-height: 20px;
  white-space: nowrap;
  cursor: pointer;
}

.designer-topbar__segmented button:hover {
  color: var(--el-color-primary);
}

.designer-topbar__segmented button.is-active {
  background: var(--el-bg-color);
  color: var(--el-color-primary);
  font-weight: 600;
  box-shadow: var(--el-box-shadow-lighter);
}

.designer-topbar__segmented button:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: 1px;
}

.designer-topbar__status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  white-space: nowrap;
}

.designer-topbar__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--el-color-success);
}

.designer-topbar__status[data-tone='warning'] .designer-topbar__dot { background: var(--el-color-warning); }
.designer-topbar__status[data-tone='danger'] .designer-topbar__dot { background: var(--el-color-danger); }
.designer-topbar__status[data-tone='info'] .designer-topbar__dot { background: var(--el-text-color-placeholder); }
.designer-topbar__status[data-tone='danger'] { color: var(--el-color-danger); }
.designer-topbar__status[title] { cursor: help; }
.designer-topbar__error-icon { font-size: 13px; }

.designer-topbar__wrap {
  display: inline-flex;
}

@media (max-width: 760px) {
  .designer-topbar__sep {
    display: none;
  }

  .designer-topbar__title {
    max-width: calc(100% - 48px);
  }
}
</style>
