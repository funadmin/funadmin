<template>
  <el-dialog
    :model-value="modelValue"
    :fullscreen="fullscreen"
    width="min(1280px, 96vw)"
    top="4vh"
    class="codegen-edit-dialog"
    :class="{ 'is-fullscreen': fullscreen }"
    :close-on-click-modal="false"
    :before-close="onBeforeClose"
    @update:model-value="(value: boolean) => emit('update:modelValue', value)"
  >
    <template #header>
      <div class="codegen-edit__header">
        <h3 class="codegen-edit__title">{{ t('business.codegen.edit.title', { name: headerName }, '编辑生成信息 — {name}') }}</h3>
        <el-tag v-if="editor.dirty.value" size="small" type="warning" effect="light" data-dirty-tag>{{ t('business.codegen.edit.unsaved', '有未保存的改动') }}</el-tag>
        <span class="codegen-edit__spacer" />
        <el-button
          v-if="canViewVersions"
          data-action="version-history"
          size="small"
          plain
          type="primary"
          :disabled="!editor.schemaHash.value"
          @click="versionsVisible = true"
        >{{ t('business.codegen.edit.versionHistory', '版本历史') }}</el-button>
        <el-button
          data-action="fullscreen"
          size="small"
          text
          :icon="fullscreen ? Aim : FullScreen"
          :aria-pressed="fullscreen"
          @click="fullscreen = !fullscreen"
        >{{ fullscreen ? t('business.codegen.edit.exitFullscreen', '退出全屏') : t('business.codegen.edit.fullscreen', '全屏') }}</el-button>
      </div>
    </template>

    <div class="codegen-edit" :aria-busy="editor.loading.value">
      <div v-if="editor.loading.value" class="codegen-edit__state">{{ t('business.codegen.edit.loading', '正在加载生成信息…') }}</div>
      <el-alert v-else-if="editor.loadError.value" data-testid="load-error" type="error" show-icon :closable="false" :title="editor.loadError.value">
        <el-button link type="primary" data-action="reload" @click="editor.load()">{{ t('business.codegen.edit.reload', '重新加载') }}</el-button>
      </el-alert>
      <template v-else-if="doc && publish && form">
        <el-alert v-if="editor.conflict.value" data-testid="schema-conflict-alert" type="warning" show-icon :closable="false" class="codegen-edit__alert" :title="t('business.codegen.edit.conflictBanner', 'Schema 版本已变化，保存已暂停。本地改动已保留，请核对版本并选择处理方式。')">
          <el-button size="small" data-action="review-conflict" :loading="editor.conflictLoading.value" @click="openConflictReview">{{ t('business.codegen.edit.reviewConflict', '核对版本') }}</el-button>
        </el-alert>
        <el-alert v-if="editor.publishSaveFailed.value" data-testid="publish-save-failed" type="error" show-icon :closable="false" class="codegen-edit__alert" :title="editor.publishSaveFailed.value" />

        <el-tabs v-model="activeTab" class="codegen-edit__tabs">
          <el-tab-pane name="config" :label="t('business.codegen.edit.tabConfig', '配置信息')">
            <ConfigTab :doc="doc" :publish="publish" :form="form" :module="editor.module.value" :menus="editor.menus.value" :adopted="adopted" @update:doc="setDoc" @update:publish="setPublish" />
          </el-tab-pane>
          <el-tab-pane name="fields" :label="t('business.codegen.edit.tabFields', '字段配置')">
            <FieldTab :doc="doc" :adopted="adopted" :hide-designer-link="hideDesignerLink" @update:doc="setDoc" @open-designer="openDesigner" />
          </el-tab-pane>
          <el-tab-pane name="relations" lazy :label="t('business.codegen.edit.tabRelations', '关联配置')">
            <RelationTab :key="editor.schemaRevision.value" :doc="doc" :catalog="relationCatalog" :hide-designer-link="hideDesignerLink" @update:doc="setDoc" @update:pending="(value: boolean) => (editor.pendingRelations.value = value)" @open-designer="openDesigner" />
          </el-tab-pane>
          <el-tab-pane name="menu" :label="t('business.codegen.edit.tabMenu', '菜单与权限')">
            <MenuTab :publish="publish" :menus="editor.menus.value" :form-key="doc.key || form.form_key" :plugin-code="pluginCode" @update:publish="setPublish" />
          </el-tab-pane>
        </el-tabs>
      </template>
    </div>

    <template #footer>
      <div class="codegen-edit__footer">
        <el-button data-action="cancel" :disabled="editor.saving.value" @click="requestClose">{{ t('business.codegen.edit.cancel', '取消') }}</el-button>
        <el-button data-action="save" plain type="primary" :loading="editor.saving.value && pending === 'save'" :disabled="!ready || (editor.saving.value && pending !== 'save')" @click="submit(false)">{{ t('business.codegen.edit.save', '保存') }}</el-button>
        <el-button data-action="save-preview" type="primary" :loading="editor.saving.value && pending === 'preview'" :disabled="!ready || (editor.saving.value && pending !== 'preview')" @click="submit(true)">{{ t('business.codegen.edit.savePreview', '保存并预览') }}</el-button>
      </div>
    </template>
  </el-dialog>

  <VersionHistoryDrawer v-if="canViewVersions" v-model="versionsVisible" :module-id="moduleId" :schema-hash="editor.schemaHash.value" @rollback="onRollback" />
  <SchemaConflictDialog
    :model-value="conflictVisible"
    :review="editor.conflictReview.value"
    :loading="editor.conflictLoading.value"
    :busy="editor.saving.value || editor.conflictLoading.value"
    :error="editor.conflictError.value"
    :can-overwrite="hasPermission(PERM_SAVE_SCHEMA)"
    @cancel="closeConflictReview"
    @recheck="editor.reviewConflict()"
    @resolve="resolveConflict"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Aim, FullScreen } from '@element-plus/icons-vue';
import type { FormPublishConfig } from '@/api/form';
import { hasPermission } from '@/utils/permission';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import VersionHistoryDrawer from '@/views/form/designer/components/VersionHistoryDrawer.vue';
import { isAdoptedTable } from './fieldControl';
import { createRelationCatalog } from './relationCatalog';
import { PERM_SAVE_SCHEMA, useGenerationEditor } from './useGenerationEditor';
import SchemaConflictDialog from './SchemaConflictDialog.vue';
import ConfigTab from './tabs/ConfigTab.vue';
import FieldTab from './tabs/FieldTab.vue';
import RelationTab from './tabs/RelationTab.vue';
import MenuTab from './tabs/MenuTab.vue';

export type EditGenerationTab = 'config' | 'fields' | 'relations' | 'menu';

// hideDesignerLink：设计器内的「表单设置」打开本弹窗时隐藏「打开表单设计器」入口。
const props = withDefaults(defineProps<{ modelValue: boolean; moduleId: number; initialTab?: EditGenerationTab; hideDesignerLink?: boolean }>(), { initialTab: undefined, hideDesignerLink: false });
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: []; preview: [] }>();

const { t } = useI18n();
const router = useRouter();
// 任何一次服务端写入成功都要通知宿主（列表刷新、设计器同步 schema_hash），即使整体保存未完成；
// 一次「保存」内的多次写入合并为一次 saved。
let batchingWrites = false;
let wroteInBatch = false;
const editor = useGenerationEditor(() => props.moduleId, {
  onWrite: () => {
    if (batchingWrites) wroteInBatch = true;
    else emit('saved');
  }
});
async function saveBatch(): Promise<boolean> {
  batchingWrites = true;
  wroteInBatch = false;
  try {
    return await editor.save();
  } finally {
    batchingWrites = false;
    if (wroteInBatch) emit('saved');
  }
}

const activeTab = ref<EditGenerationTab>(props.initialTab ?? 'config');
const fullscreen = ref(false);
const versionsVisible = ref(false);
const conflictVisible = ref(false);
const pending = ref<'save' | 'preview' | null>(null);

const doc = computed(() => editor.doc.value);
const publish = computed(() => editor.publish.value);
const form = computed(() => editor.form.value);
const ready = computed(() => editor.loaded.value);
const adopted = computed(() => Boolean(doc.value) && isAdoptedTable(doc.value as FormSchemaDocument, form.value?.source_type));
const pluginCode = computed(() => {
  const target = editor.module.value?.metadata?.target;
  return target?.type === 'plugin' ? target.pluginCode : null;
});
// 关联页签的数据表/字段目录：按模块连接缓存，每次打开弹窗（或切换模块）时重置
const relationCatalog = createRelationCatalog(() => form.value?.connection || editor.module.value?.connection_name || 'mysql');
const headerName = computed(() => form.value?.table_name || editor.module.value?.table_name || editor.module.value?.code || '');
const canViewVersions = computed(() => hasPermission('development:business:schema-versions'));

watch(() => [props.modelValue, props.moduleId] as const, ([visible, id], previous) => {
  if (!visible || !id) return;
  if (previous && previous[0] && previous[1] === id) return;
  activeTab.value = props.initialTab ?? 'config';
  relationCatalog.reset();
  void editor.load();
}, { immediate: true });

const setDoc = (next: FormSchemaDocument) => { editor.doc.value = next; };
const setPublish = (patch: Partial<FormPublishConfig>) => {
  if (editor.publish.value) editor.publish.value = { ...editor.publish.value, ...patch };
};

async function confirmDiscard(): Promise<boolean> {
  if (!editor.dirty.value) return true;
  try {
    await ElMessageBox.confirm(
      t('business.codegen.edit.discardConfirm', '有未保存的改动，关闭后将丢失。确定关闭？'),
      t('business.codegen.edit.discardTitle', '放弃改动'),
      { type: 'warning', confirmButtonText: t('business.codegen.edit.discard', '放弃改动'), cancelButtonText: t('business.codegen.edit.keepEditing', '继续编辑') }
    );
    return true;
  } catch {
    return false;
  }
}

async function requestClose() {
  if (editor.saving.value) return;
  if (await confirmDiscard()) emit('update:modelValue', false);
}

function onBeforeClose(done: () => void) {
  if (editor.saving.value) return;
  void confirmDiscard().then((ok) => { if (ok) done(); });
}

async function submit(preview: boolean) {
  if (!ready.value || editor.saving.value) return;
  const changed = editor.dirty.value;
  pending.value = preview ? 'preview' : 'save';
  try {
    if (!(await saveBatch())) return;
  } finally {
    pending.value = null;
  }
  if (changed) ElMessage.success(t('business.codegen.edit.saveSuccess', '生成信息已保存'));
  emit('update:modelValue', false);
  if (preview) emit('preview');
}

async function openDesigner() {
  const formId = form.value?.id ?? editor.module.value?.form_id;
  if (!formId || !ready.value) return;
  if (editor.dirty.value) {
    try {
      await ElMessageBox.confirm(
        t('business.codegen.edit.designerDirty', '有未保存的改动，需要先保存才能打开表单设计器。是否保存并打开？'),
        t('business.codegen.edit.openDesignerTitle', '打开表单设计器'),
        { type: 'warning', confirmButtonText: t('business.codegen.edit.saveAndOpen', '保存并打开'), cancelButtonText: t('business.codegen.edit.cancel', '取消') }
      );
    } catch {
      return;
    }
    if (!(await saveBatch())) return;
  }
  emit('update:modelValue', false);
  void router.push({ path: '/admin/development/business/designer', query: { id: String(formId), moduleId: String(props.moduleId) } });
}

async function openConflictReview() {
  conflictVisible.value = true;
  await editor.reviewConflict();
}

function closeConflictReview() {
  conflictVisible.value = false;
  editor.cancelConflictReview();
}

async function resolveConflict(choice: 'local' | 'server') {
  if (!(await editor.resolveConflict(choice))) return;
  conflictVisible.value = false;
  ElMessage.success(choice === 'server'
    ? t('business.codegen.edit.conflictUsedServer', '已采用服务端版本，发布设置改动仍保留')
    : t('business.codegen.edit.conflictUsedLocal', '已以本地版本覆盖，请继续保存发布设置'));
}

async function onRollback() {
  versionsVisible.value = false;
  if (editor.schemaDirty.value) ElMessage.warning(t('business.codegen.edit.rollbackReload', '已回滚到历史版本，未保存的字段改动已被服务端版本替换'));
  await editor.load();
  emit('saved');
}
</script>

<style scoped>
.codegen-edit__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding-right: 28px;
}

.codegen-edit__title {
  margin: 0;
  color: var(--el-text-color-primary);
  font-size: 16px;
  font-weight: 600;
  word-break: break-all;
}

.codegen-edit__spacer {
  flex: 1;
}

.codegen-edit {
  min-height: 360px;
}

.codegen-edit__state {
  padding: 48px 0;
  text-align: center;
  color: var(--el-text-color-secondary);
}

.codegen-edit__alert {
  margin-bottom: 10px;
}

.codegen-edit__tabs :deep(.el-tabs__content) {
  max-height: calc(92vh - 230px);
  overflow-y: auto;
  padding-right: 4px;
}

.is-fullscreen .codegen-edit__tabs :deep(.el-tabs__content) {
  max-height: calc(100vh - 200px);
}

.codegen-edit__footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
</style>

<style>
.codegen-edit-dialog .el-dialog__body {
  padding-top: 4px;
}
</style>
