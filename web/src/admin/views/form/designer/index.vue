<template>
  <PageWrapper>
    <DesignerTopBar
      v-model:mode="workspaceMode"
      class="designer-command-bar"
      :title="String(store.form.value.name ?? '')"
      :form-key="String(store.form.value.form_key ?? '')"
      :table-name="String(store.form.value.table_name ?? '')"
      :target-label="targetLabel"
      :generate-hint="generateHint"
      :can-undo="store.canUndo.value"
      :can-redo="store.canRedo.value"
      :save-status="store.saveStatus.value"
      :save-error="saveError"
      :online="online"
      :save-blocked="saveBlocked"
      :actions-disabled="generationLocked"
      :can-settings="Boolean(moduleId)"
      :settings-disabled="!businessModule"
      :can-preview="canPreviewCode"
      :can-generate="canGenerate"
      :show-dynamic-publish="canDynamicPublish"
      :can-versions="canViewVersions"
      :versions-disabled="generationLocked"
      :can-export="canExportSchema"
      :export-disabled="!businessModule"
      :can-open-runtime="Boolean(businessModule) && !isPluginTarget"
      :runtime-disabled="!runtimeRoute"
      @back="router.push('/admin/development/business/mine')"
      @undo="store.undo()"
      @redo="store.redo()"
      @settings="openSettings"
      @preview="openCodePreview"
      @generate="openCodePreview"
      @dynamic-publish="openDynamicPublish"
      @versions="openVersions"
      @export="exportSchemaJson"
      @runtime="openRuntime"
    />
    <div v-if="saveBlocked" data-testid="save-conflict-alert" role="alert" class="designer-conflict-banner">
      <strong>{{ t('formDesigner.savePausedTitle', '保存已暂停。') }}</strong>
      <span>{{ t('formDesigner.savePausedHint', '本地草稿已保留，刷新不会解除暂停。请核对版本并明确选择恢复方式。') }}</span>
      <span class="designer-conflict-banner__spacer" />
      <el-button size="small" data-action="review-conflict" :loading="conflictReviewLoading" :disabled="conflictResolving" @click="reviewSaveConflict">{{ t('formDesigner.reviewVersion', '核对版本') }}</el-button>
    </div>

    <el-dialog v-model="conflictReviewVisible" class="save-conflict-dialog" :title="t('formDesigner.saveConflict.title', '核对保存冲突')" width="calc(100% - 32px)" top="16px" :close-on-click-modal="false" :close-on-press-escape="!conflictResolving" :show-close="!conflictResolving">
      <p class="save-conflict-notice">{{ t('formDesigner.saveConflict.notice', '保存已暂停。以下为完整只读 JSON；取消保留本地编辑并继续暂停保存。继续编辑后需重新核对。') }}</p>
      <p v-if="conflictReviewError" class="save-conflict-error" role="alert">{{ conflictReviewError }}</p>
      <template v-if="conflictReview">
        <p class="save-conflict-hash">{{ t('formDesigner.saveConflict.hash', '核对的服务端版本 hash：') }}<code>{{ conflictReview.server.schema_hash }}</code></p>
        <div class="save-conflict-comparison">
          <label class="save-conflict-card">
            <span class="save-conflict-card-title">{{ t('formDesigner.saveConflict.local', '本地草稿（核对快照）') }}</span>
            <textarea readonly :value="formatDebug(conflictReview.local)" rows="12" :aria-label="t('formDesigner.saveConflict.localJson', '本地草稿完整 JSON')" spellcheck="false" />
          </label>
          <label class="save-conflict-card">
            <span class="save-conflict-card-title">{{ t('formDesigner.saveConflict.server', '服务端版本') }}</span>
            <textarea readonly :value="formatDebug(conflictReview.server)" rows="12" :aria-label="t('formDesigner.saveConflict.serverJson', '服务端完整 JSON')" spellcheck="false" />
          </label>
        </div>
      </template>
      <p class="save-conflict-risk">{{ t('formDesigner.saveConflict.risk', '采用服务端将放弃本地未保存编辑；本地覆盖将替换已核对的服务端版本。两种操作均需再次确认，服务端再次变化时拒绝覆盖。') }}</p>
      <template #footer>
        <div class="save-conflict-footer">
          <el-button :loading="conflictReviewLoading" :disabled="conflictResolving" @click="reviewSaveConflict">{{ t('formDesigner.saveConflict.review', '重新核对版本') }}</el-button>
          <div class="save-conflict-actions">
            <el-button :disabled="conflictResolving" @click="cancelConflictReview">{{ t('formDesigner.saveConflict.cancel', '取消，保留本地') }}</el-button>
            <el-button :disabled="!conflictReview || conflictReviewLoading || conflictResolving" @click="resolveSaveConflict('server')">{{ t('formDesigner.saveConflict.useServer', '放弃本地，采用服务端') }}</el-button>
            <el-button v-if="hasPermission('development:business:save-schema')" type="primary" :loading="conflictResolving" :disabled="!conflictReview || conflictReviewLoading || conflictResolving" @click="resolveSaveConflict('local')">{{ t('formDesigner.saveConflict.useLocal', '以核对后的本地覆盖') }}</el-button>
          </div>
        </div>
      </template>
    </el-dialog>

    <div class="designer-tabs-wrap">
    <div v-if="workspaceMode !== 'edit' && activeTab === 'design'" class="designer-tabs-extra" data-preview-tools>
      <el-radio-group v-model="previewMode" size="small" :aria-label="t('formDesigner.layout.previewModeAria', '预览状态')">
        <el-radio-button v-for="option in previewModeOptions" :key="option.value" :value="option.value" :data-preview-mode="option.value">{{ option.label }}</el-radio-button>
      </el-radio-group>
      <el-button size="small" @click="previewSettingsVisible = true">{{ t('formDesigner.previewData', '预览数据') }}</el-button>
    </div>
    <div v-if="activeTab === 'buttons'" id="designer-buttons-tab-extra" class="designer-tabs-extra" data-buttons-catalog />
    <el-tabs v-model="activeTab" class="designer-tabs">
      <el-tab-pane :label="t('formDesigner.tabDesign', '表单设计')" name="design" :lazy="false">
    <div class="designer-work" :class="{ 'is-preview': workspaceMode !== 'edit', 'has-left': showLeftColumn, 'has-right': showRightColumn }">
      <!-- 左：控件 / 大纲（<760px 收成抽屉） -->
      <aside v-if="showLeftColumn" class="designer-column designer-column--left" data-designer-left>
        <DesignerLeftPanel :store="store" :controls="designerControls" />
      </aside>

      <!-- 中：画布 -->
      <main class="designer-center" data-designer-center>
        <div v-if="workspaceMode === 'edit'" class="designer-canvas-toolbar" data-canvas-toolbar>
          <el-button v-if="narrowLeft" size="small" data-action="toggle-left-panel" :aria-expanded="leftDrawerVisible" @click="leftDrawerVisible = true"><i class="i-ep-menu" /> {{ t('formDesigner.layout.tabControls', '控件') }}</el-button>
          <span>{{ t('formDesigner.layout.canvasStats', { fields: canvasStats.fields, layouts: canvasStats.layouts }, '{fields} 个字段 · {layouts} 个布局') }}</span>
          <span class="designer-canvas-toolbar__spacer" />
          <span class="designer-canvas-toolbar__meta">{{ store.form.value.name || t('formDesigner.unnamed', '未命名') }} → {{ store.form.value.table_name }}</span>
          <el-button v-if="narrowRight && store.selected.value" size="small" data-action="open-props" @click="propsDrawerVisible = true"><i class="i-ep-setting" /> {{ t('formDesigner.fieldPropsTitle', '字段属性') }}</el-button>
        </div>
        <el-alert
          v-if="workspaceMode === 'edit' && catalogDiagnostics.length"
          class="designer-catalog-alert"
          :title="catalogDiagnostics.map((item) => item.message).join('；')"
          type="error"
          :closable="false"
          show-icon
        />
        <div v-show="workspaceMode === 'edit'" class="designer-paper">
          <DesignerCanvas class="designer-canvas" :nodes="store.nodes.value" :store="store" />
        </div>
        <div v-if="workspaceMode !== 'edit'" class="designer-canvas schema-preview" :class="`schema-preview-${workspaceMode}`">
          <SchemaRenderer
            ref="previewRenderer"
            :schema="previewSchema"
            :values="previewValues"
            :form-key="String(store.form.value.form_key ?? '')"
            :options-request="previewOptionsRequest"
            :disabled="previewReadOnly"
          />
        </div>
      </main>

      <!-- 右：属性面板（<1100px 变为选中字段时弹出的抽屉） -->
      <aside v-if="showRightColumn" class="designer-column designer-column--right" data-designer-right>
        <PropsPanel v-if="store.selected.value" data-props-panel :module-id="moduleId" :field="store.selected.value" :group-title="selectedGroupTitle" :source-type="store.form.value.source_type ?? 'created'" :controls="designerControls" @update="store.updateField" />
        <el-empty v-else :image-size="64" :description="t('formDesigner.emptyProps', '点选画布字段编辑参数')" />
      </aside>
    </div>

      </el-tab-pane>
      <el-tab-pane :label="t('formDesigner.tabButtons', '按钮与工具')" name="buttons" :lazy="false" />
      <el-tab-pane :label="t('formDesigner.tabCategories', '分类管理')" name="list" :lazy="false" />
    </el-tabs>
    </div>
    <ListConfigurationPanel v-if="activeTab === 'buttons' || activeTab === 'list'" :mode="activeTab === 'buttons' ? 'buttons' : 'categories'" :model-value="store.schemaDocument.value.list ?? {}" :fields="store.fields.value" :module-id="moduleId" :form-key="store.form.value.form_key" :permissions="buttonUser.permissions" :plugin-target="isPluginTarget" :catalog-teleport="activeTab === 'buttons' ? '#designer-buttons-tab-extra' : undefined" @update="store.updateList" />

    <el-drawer v-if="narrowLeft" v-model="leftDrawerVisible" data-left-drawer class="designer-side-drawer" direction="ltr" size="min(288px, 100vw)" :title="t('formDesigner.layout.leftTabsAria', '控件与大纲')" append-to-body>
      <DesignerLeftPanel :store="store" :controls="designerControls" @added="leftDrawerVisible = false" />
    </el-drawer>
    <el-drawer v-if="narrowRight" v-model="propsDrawerVisible" data-props-drawer class="designer-side-drawer" direction="rtl" size="min(360px, 100vw)" :title="t('formDesigner.fieldPropsTitle', '字段属性')" append-to-body>
      <PropsPanel v-if="store.selected.value" data-props-panel :module-id="moduleId" :field="store.selected.value" :group-title="selectedGroupTitle" :source-type="store.form.value.source_type ?? 'created'" :controls="designerControls" @update="store.updateField" />
      <el-empty v-else :image-size="64" :description="t('formDesigner.emptyProps', '点选画布字段编辑参数')" />
    </el-drawer>

    <el-dialog v-model="previewSettingsVisible" :title="t('formDesigner.previewSettingsTitle', '预览数据与服务端错误')" width="680px">
      <el-form label-width="120px">
        <el-form-item :label="t('formDesigner.previewValuesJson', '初始值 JSON')"><el-input v-model="previewValuesJson" type="textarea" :rows="8" /></el-form-item>
        <el-form-item :label="t('formDesigner.previewErrorsJson', '字段错误 JSON')"><el-input v-model="previewErrorsJson" type="textarea" :rows="6" :placeholder="t('formDesigner.previewErrorsPlaceholder', '{&quot;field&quot;:&quot;服务端错误&quot;}')" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="previewSettingsVisible = false">{{ t('common.cancel', '取消') }}</el-button><el-button type="primary" @click="applyPreviewSettings">{{ t('formDesigner.applyPreview', '应用预览') }}</el-button></template>
    </el-dialog>

    <EditGenerationDialog v-if="moduleId" v-model="settingsVisible" :module-id="moduleId" :hide-designer-link="true" @saved="onSettingsSaved" @preview="onSettingsPreview" />
    <CodePreviewDialog ref="codePreviewRef" v-model="codePreviewVisible" :module="businessModule" @generated="onGenerated" />
    <VersionHistoryDrawer v-if="canViewVersions && moduleId" v-model="versionsVisible" :module-id="moduleId" :schema-hash="String(store.form.value.schema_hash ?? '')" @rollback="onRollback" />


  </PageWrapper>
</template>

<script setup lang="ts">
import { hasPermission } from '@/utils/permission';
import { computed, onActivated, onBeforeUnmount, onDeactivated, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { businessDevelopmentApi, isBusinessApiError, type BusinessModule, type BusinessFormalGenerationResult } from '@/api/development/business';
import { formDataApi } from '@/api/formData';
import { dictApi } from '@/api/system/dict';
import type { FormDataSourceRequest } from '../dataSource/useFormDataSource';
import { flattenSchemaNodes } from '../schema/types';
import { leftTreeMissingLabels, sanitizeLeftTreeForSave } from '../schema/leftTreeGuard';
import { CONTROL_REGISTRY } from '../registry';
import { useDesigner } from '../composables/useDesigner';
import { pluginCatalog } from './pluginCatalog';
import { loadPluginFormComponents } from '../schema/pluginComponentLoader';
import SchemaRenderer from '../components/SchemaRenderer.vue';
import DesignerCanvas from './components/DesignerCanvas.vue';
import PropsPanel from './components/PropsPanel.vue';
import ListConfigurationPanel from './components/ListConfigurationPanel.vue';
import { useUserStore } from '@/store/modules/user';
import DesignerLeftPanel from './components/DesignerLeftPanel.vue';
import { useMediaQuery } from './useMediaQuery';
import DesignerTopBar from './components/DesignerTopBar.vue';
import type { DesignerWorkspaceMode } from './components/designerTopBar';
import VersionHistoryDrawer from './components/VersionHistoryDrawer.vue';
import { useBusinessMenuRefresh } from '../../development/business/composables/useBusinessMenuRefresh';
import EditGenerationDialog from '../../development/business/codegen/EditGenerationDialog.vue';
import CodePreviewDialog from '../../development/business/codegen/CodePreviewDialog.vue';
import { PERM_GENERATE, PERM_PREVIEW } from '../../development/business/codegen/formalGeneration';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
// 路由容器以 fullPath 缓存实例；身份必须固定，避免后台实例跟随全局路由串写。
const buttonUser = useUserStore();
const instanceModuleId = Number(route.query.moduleId ?? 0);
const moduleId = computed(() => instanceModuleId);
const { refreshBusinessMenu } = useBusinessMenuRefresh(router);
const store = useDesigner();
const businessModule = ref<BusinessModule | null>(null);
const businessTarget = computed(() => businessModule.value?.metadata?.target);
const isPluginTarget = computed(() => businessTarget.value?.type === 'plugin');
// 区域切换仅改变显示，不进入 Schema、历史和自动保存通道。
// 原「基本信息」页签由「⚙ 表单设置」打开的代码生成「编辑生成信息」弹窗承担。
const activeTab = ref<'design' | 'buttons' | 'list'>('design');
const workspaceMode = ref<DesignerWorkspaceMode>('edit');
// 三栏与窄屏抽屉仅为临时视图状态，不进入草稿与自动保存通道。
// <1100px 属性栏改为选中字段时弹出的右侧抽屉；<760px 控件/大纲栏也收成左侧抽屉。
const narrowRight = useMediaQuery('(max-width: 1099px)');
const narrowLeft = useMediaQuery('(max-width: 759px)');
const leftDrawerVisible = ref(false);
const propsDrawerVisible = ref(false);
const closeSideDrawers = () => { leftDrawerVisible.value = false; propsDrawerVisible.value = false; };
// 预览模式收起左右栏。
const showLeftColumn = computed(() => workspaceMode.value === 'edit' && !narrowLeft.value);
const showRightColumn = computed(() => workspaceMode.value === 'edit' && !narrowRight.value);
watch(workspaceMode, (mode) => {
  activeTab.value = 'design';
  if (mode !== 'edit') closeSideDrawers();
});
watch(activeTab, (tab) => { if (tab !== 'design') closeSideDrawers(); });
watch(narrowLeft, (narrow) => { if (!narrow) leftDrawerVisible.value = false; });
watch(narrowRight, (narrow) => { if (!narrow) propsDrawerVisible.value = false; });
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
// 与改版前一致的四种预览状态；渲染器只消费 readOnly，没有独立的「详情」模式。
const previewMode = ref<'create' | 'edit' | 'readonly' | 'search'>('create');
const previewModeOptions = computed(() => [
  { value: 'create', label: t('formDesigner.previewCreate', '创建') },
  { value: 'edit', label: t('formDesigner.previewEdit', '编辑') },
  { value: 'readonly', label: t('formDesigner.previewReadonly', '只读') },
  { value: 'search', label: t('formDesigner.previewSearch', '搜索') }
] as const);
const previewReadOnly = computed(() => previewMode.value === 'readonly');
const previewSettingsVisible = ref(false);
const previewValuesJson = ref('{}');
const previewErrorsJson = ref('{}');
const previewRenderer = ref<{ setFieldErrors: (errors: Record<string, string>) => Promise<void> }>();
// 预览画布渲染草稿节点（含未保存草稿）：字典/静态选项按草稿节点客户端解析，
// 其余来源带 draft=1 走后端草稿 Schema 解析，未发布字段不再报“选项加载失败”。
const previewOptionsRequest: FormDataSourceRequest = async (formKey, field, params, signal) => {
  const node = flattenSchemaNodes(store.schemaDocument.value.nodes).map(({ node }) => node).find((item) => item.field === field);
  const source = (node?.dataSource ?? {}) as Record<string, unknown>;
  const kind = String(source.kind ?? source.mode ?? '');
  if (kind === 'static') {
    const options = Array.isArray(source.options) ? (source.options as Array<Record<string, unknown>>) : [];
    return { options: options.map((option) => ({ label: String(option.label ?? ''), value: option.value as string | number })) };
  }
  if (kind === 'dictionary') {
    const code = String(source.dictionary ?? '');
    const options = code ? await dictApi.options(code) : [];
    return { options: options.map((option) => ({ label: String(option.label), value: option.value })) };
  }
  if (kind === 'self-tree') {
    return { options: [] };
  }
  return formDataApi.options(formKey, field, { ...params, draft: 1 }, signal);
};
const previewValues = reactive<Record<string, unknown>>(Object.fromEntries(store.fields.value.map((field) => [field.field_name, field.default_value])));
watch(() => store.fields.value.map((field) => [field.field_name, field.default_value] as const), (fields) => {
  const fieldNames = new Set(fields.map(([name]) => name));
  for (const [name, defaultValue] of fields) if (!(name in previewValues)) previewValues[name] = defaultValue;
  for (const key of Object.keys(previewValues)) if (!fieldNames.has(key)) delete previewValues[key];
});
const designerControls = computed(() => [...CONTROL_REGISTRY, ...pluginCatalog.controls.value]);
const catalogDiagnostics = computed(() => pluginCatalog.fieldDiagnostics(store.fields.value));
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;
let localDraftTimer: ReturnType<typeof setTimeout> | null = null;
let saveInFlight = false;
const saveBlocked = ref(false);
// 最近一次保存失败的原因：顶栏「保存失败」的提示气泡展示它；离开失败状态（再次保存、编辑）即清除。
const saveError = ref('');
watch(() => store.saveStatus.value, (status) => { if (status !== 'failed') saveError.value = ''; });
const conflictReviewVisible = ref(false);
const conflictReviewLoading = ref(false);
const conflictResolving = ref(false);
const conflictReviewError = ref('');
const conflictReview = ref<{ local: ReturnType<typeof definition>; server: import('@/api/form').FormDefinition; moduleId: number } | null>(null);
let saveQueued = false;
let designerActive = true;
let localDraftRestorePending = false;
const localDraftKey = computed(() => `form-designer-draft:${String(store.form.value.id ?? store.form.value.form_key ?? 'new')}`);
const persistLocalDraft = () => {
  if (!designerActive || typeof localStorage === 'undefined') return;
  localStorage.setItem(localDraftKey.value, JSON.stringify({ definition: definition(), savedAt: Date.now(), saveBlocked: saveBlocked.value }));
};
const scheduleLocalDraft = () => {
  if (localDraftTimer) clearTimeout(localDraftTimer);
  localDraftTimer = setTimeout(() => {
    localDraftTimer = null;
    persistLocalDraft();
  }, 500);
};
const clearLocalDraft = () => { if (typeof localStorage !== 'undefined') localStorage.removeItem(localDraftKey.value); };
const restoreLocalDraft = async () => {
  if (!designerActive || !localDraftRestorePending) return;
  localDraftRestorePending = false;
  if (typeof localStorage === 'undefined') return;
  const raw = localStorage.getItem(localDraftKey.value);
  if (!raw) return;
  try {
    const draft = JSON.parse(raw) as { definition?: import('@/api/form').FormDefinition; saveBlocked?: boolean };
    if (draft.definition?.schema_document?.schemaVersion === 2 && (await ElMessageBox.confirm(t('formDesigner.draftRestoreConfirm', '检测到未同步的本地表单草稿，是否恢复？'), t('formDesigner.draftRestoreTitle', '恢复本地草稿'), { type: 'warning', confirmButtonText: t('formDesigner.draftRestore', '恢复'), cancelButtonText: t('formDesigner.draftIgnore', '忽略') }).then(() => true).catch(() => false))) {
      const loadedHash = store.form.value.schema_hash;
      // 历史草稿不是服务端基线；版本不明时保留内容并暂停，禁止换 hash 盲目覆盖。
      saveBlocked.value = draft.saveBlocked === true || !loadedHash || draft.definition.schema_hash !== loadedHash;
      store.load({ ...draft.definition, schema_hash: loadedHash });
      store.updateForm({ schema_origin: 'designer' });
      if (saveBlocked.value) {
        store.failSave();
        ElMessage.warning(t('formDesigner.draftRestoredPaused', '本地草稿已恢复，保存仍暂停，请点击“核对版本”选择恢复方式'));
      }
    }
  } catch { clearLocalDraft(); }
};

// 分类树映射未补全时按未开启保存（见 leftTreeGuard），保存成功后提示；本地随后采用服务端返回的关闭值。
const warnLeftTreeSavedDisabled = (missing: string[]) => {
  if (!missing.length || !designerActive) return;
  ElMessage.warning(t('formDesigner.leftTreeSavedDisabled', { missing: leftTreeMissingLabels(t, missing) }, '左侧分类树的字段对应未填完（缺少：{missing}），已按未开启保存'));
};
const definition = () => ({
  ...store.form.value,
  schema_version: 2,
  schema_document: store.schemaDocument.value,
  schemaHash: store.form.value.schema_hash,
  schema_origin: 'designer',
  fields: store.fields.value
});
const previewSchema = computed(() => ({
  ...store.schemaDocument.value,
  form: { ...(store.schemaDocument.value.form ?? {}), mode: previewMode.value, readOnly: previewReadOnly.value }
}));
const applyPreviewSettings = async () => {
  try {
    const values = JSON.parse(previewValuesJson.value) as Record<string, unknown>;
    const errors = JSON.parse(previewErrorsJson.value) as Record<string, string>;
    Object.keys(previewValues).forEach((key) => delete previewValues[key]);
    Object.assign(previewValues, values);
    await previewRenderer.value?.setFieldErrors(errors);
    previewSettingsVisible.value = false;
  } catch { ElMessage.warning(t('formDesigner.invalidJson', '请输入合法 JSON')); }
};
const formatDebug = (value: unknown) => JSON.stringify(value, null, 2);
const canvasStats = computed(() => {
  const entries = store.flattenedNodes.value;
  const fields = entries.filter((entry) => entry.node.kind === 'field').length;
  return { fields, layouts: entries.length - fields };
});
// 属性面板标题区「位于「分组」」：选中节点的父容器标题。
const selectedGroupTitle = computed(() => {
  const id = store.selectedNodeId.value;
  const parentId = id ? store.flattenedNodes.value.find((entry) => entry.node.id === id)?.parentId : null;
  return parentId ? store.findNode(parentId)?.title ?? '' : '';
});
// 窄屏下选中字段即弹出属性抽屉。
watch(() => store.selectedNodeId.value, (id) => {
  if (id && narrowRight.value && workspaceMode.value === 'edit' && activeTab.value === 'design') propsDrawerVisible.value = true;
});
const normalizeIdentifier = (value: string) => value
  .trim()
  .toLowerCase()
  .replace(/[\s-]+/g, '_')
  .replace(/[^a-z0-9_]/g, '')
  .replace(/^_+|_+$/g, '')
  .slice(0, 61);
const normalizeFormKey = () => {
  const current = normalizeIdentifier(String(store.form.value.form_key ?? ''));
  const fromTable = normalizeIdentifier(String(store.form.value.table_name ?? ''));
  const normalized = current || fromTable;
  const patch: Record<string, unknown> = {};
  if (normalized !== store.form.value.form_key) patch.form_key = normalized;
  if (store.form.value.source_type === 'created' && !store.form.value.table_name) {
    const tableName = normalized;
    if (tableName !== store.form.value.table_name) patch.table_name = tableName;
  }
  if (Object.keys(patch).length) store.updateForm(patch);
};
const validateDefinitionBasics = (silent = false) => {
  normalizeFormKey();
  if (!String(store.form.value.name ?? '').trim()) {
    if (!silent) ElMessage.warning(t('formDesigner.nameRequired', '请填写表单名称'));
    return false;
  }
  if (!/^[a-z][a-z0-9_]{0,60}$/.test(String(store.form.value.form_key ?? ''))) {
    if (!silent) ElMessage.warning(t('formDesigner.keyInvalid', '请填写正确的表单标识'));
    return false;
  }
  if (!/^[a-z][a-z0-9_]*$/.test(String(store.form.value.table_name ?? ''))) {
    if (!silent) ElMessage.warning(t('formDesigner.tableInvalid', '请填写正确的绑定表名'));
    return false;
  }
  return true;
};

async function load() {
  if (!moduleId.value) {
    await router.replace('/admin/development/business/mine');
    return;
  }
  const data = await businessDevelopmentApi.module(moduleId.value);
  if (!data.form) throw new Error(t('formDesigner.noDesignableForm', '业务模块没有可设计表单'));
  businessModule.value = data.module;
  store.load({ ...data.form, fields: data.fields });
}

async function onSave() {
  if (saveBlocked.value) return reviewSaveConflict();
  return saveDefinition(false);
}

// 核对只读取服务端，不替换本地；提交只使用此处冻结的快照和版本。
async function reviewSaveConflict() {
  if (!designerActive || !saveBlocked.value || saveInFlight || conflictReviewLoading.value || conflictResolving.value) return;
  conflictReviewVisible.value = true;
  conflictReview.value = null;
  conflictReviewError.value = '';
  conflictReviewLoading.value = true;
  const reviewedModuleId = moduleId.value;
  const local = JSON.parse(JSON.stringify(definition())) as ReturnType<typeof definition>;
  persistLocalDraft();
  try {
    const data = await businessDevelopmentApi.module(reviewedModuleId);
    if (!designerActive || !conflictReviewVisible.value || moduleId.value !== reviewedModuleId) return;
    if (!data.form?.schema_hash || data.form.schema_document?.schemaVersion !== 2 || !Array.isArray(data.form.schema_document.nodes)) {
      throw new Error(t('formDesigner.serverDocMissing', '服务端文档或版本 hash 缺失，无法安全恢复，请重新核对'));
    }
    conflictReview.value = { local, server: JSON.parse(JSON.stringify({ ...data.form, fields: data.fields })), moduleId: reviewedModuleId };
  } catch (error) {
    conflictReviewError.value = error instanceof Error ? error.message : t('formDesigner.versionReadFailed', '版本读取失败，请重新核对');
  } finally {
    conflictReviewLoading.value = false;
  }
}

function cancelConflictReview() {
  if (conflictResolving.value) return;
  conflictReviewVisible.value = false;
  conflictReview.value = null;
  persistLocalDraft();
}

async function resolveSaveConflict(choice: 'local' | 'server') {
  if (choice === 'local' && !hasPermission('development:business:save-schema')) return;
  const reviewed = conflictReview.value;
  if (!designerActive || !saveBlocked.value || !reviewed || conflictReviewLoading.value || conflictResolving.value || saveInFlight) return;
  const unchanged = () => moduleId.value === reviewed.moduleId && JSON.stringify(definition()) === JSON.stringify(reviewed.local);
  if (!unchanged()) {
    conflictReview.value = null;
    conflictReviewError.value = t('formDesigner.localChangedRecheck', '本地内容已变化，请重新核对版本后再选择');
    persistLocalDraft();
    return;
  }
  const prompt = choice === 'local'
    ? t('formDesigner.overwriteConfirm', '确认以已核对的本地快照覆盖此服务端版本？服务端再次变化时将拒绝保存。')
    : t('formDesigner.useServerConfirm', '确认放弃本地全部未保存编辑，采用已核对的服务端版本？');
  try {
    await ElMessageBox.confirm(prompt, choice === 'local' ? t('formDesigner.overwriteTitle', '本地覆盖确认') : t('formDesigner.useServerTitle', '采用服务端确认'), { type: 'warning' });
  } catch {
    return;
  }
  if (!unchanged() || (choice === 'local' && !hasPermission('development:business:save-schema'))) return;
  conflictResolving.value = true;
  saveInFlight = true;
  conflictReviewError.value = '';
  try {
    if (choice === 'local') {
      store.beginSave();
      const guarded = sanitizeLeftTreeForSave(reviewed.local.schema_document);
      const saved = await businessDevelopmentApi.saveSchema(reviewed.moduleId, guarded.doc, String(reviewed.server.schema_hash), t('formDesigner.reasonConflictLocal', '核对冲突后采用本地'));
      warnLeftTreeSavedDisabled(guarded.pendingMissing);
      if (!unchanged()) {
        // 只推进已成功的版本；请求期间的新编辑必须重新核对，不能排队自动覆盖。
        store.acknowledgeSave(saved.schemaHash);
        store.failSave();
        conflictReviewError.value = t('formDesigner.snapshotSavedButEdited', '核对快照已保存，但本地又有编辑，保存仍暂停，请重新核对版本');
        return;
      }
      store.markSaved({ ...reviewed.local, schema_document: saved.document, schema_hash: saved.schemaHash } as import('@/api/form').FormDefinition);
    } else {
      store.load(reviewed.server);
    }
    saveBlocked.value = false;
    if (localDraftTimer) clearTimeout(localDraftTimer);
    localDraftTimer = null;
    clearLocalDraft();
    conflictReviewVisible.value = false;
    ElMessage.success(t('formDesigner.conflictResolved', '冲突已处理，后续编辑将继续自动保存'));
  } catch (error) {
    store.failSave();
    conflictReviewError.value = isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT'
      ? t('formDesigner.serverChangedAgain', '服务端版本再次变化，保存仍暂停，请重新核对版本；本地编辑已保留')
      : error instanceof Error ? error.message : t('formDesigner.restoreFailed', '恢复失败，保存仍暂停，请重新核对版本');
  } finally {
    conflictReview.value = null;
    conflictResolving.value = false;
    saveInFlight = false;
    saveQueued = false;
    if (saveBlocked.value) persistLocalDraft();
  }
}

async function saveDefinition(automatic: boolean) {
  if (!hasPermission('development:business:save-schema')) return;
  if (!designerActive || saveBlocked.value || !store.dirty.value || (automatic && !online.value)) return;
  if (saveInFlight) { saveQueued = true; return; }
  if (!validateDefinitionBasics(automatic)) return;
  const expectedHash = String(store.form.value.schema_hash ?? '');
  if (!moduleId.value || !expectedHash) {
    store.failSave();
    saveError.value = t('formDesigner.versionMissing', '当前表单版本信息缺失，请刷新页面后重试');
    if (!automatic) ElMessage.warning(t('formDesigner.versionMissing', '当前表单版本信息缺失，请刷新页面后重试'));
    return;
  }
  saveInFlight = true;
  const payload = definition();
  const payloadHash = JSON.stringify(payload);
  store.beginSave();
  try {
    const guarded = sanitizeLeftTreeForSave(store.schemaDocument.value);
    const saved = await businessDevelopmentApi.saveSchema(moduleId.value, guarded.doc, expectedHash, t('formDesigner.reasonDesignerSave', '业务设计器保存'));
    warnLeftTreeSavedDisabled(guarded.pendingMissing);
    const unchanged = JSON.stringify(definition()) === payloadHash;
    if (unchanged) {
      store.markSaved({ ...store.form.value, schema_document: saved.document, schema_hash: saved.schemaHash, fields: store.fields.value } as import('@/api/form').FormDefinition);
      clearLocalDraft();
      if (designerActive && !automatic) ElMessage.success(t('formDesigner.saveSuccess', '保存成功'));
    } else {
      // 请求成功必须推进版本，但不能用旧请求内容覆盖其间的新编辑。
      store.acknowledgeSave(saved.schemaHash);
      saveQueued = true;
    }
  } catch (error) {
    store.failSave();
    saveError.value = isBusinessApiError(error)
      ? t('formDesigner.requestErrorWithId', { msg: error.msg, id: error.data.error.requestId }, '{msg}（请求 ID：{id}）')
      : error instanceof Error && error.message ? error.message : t('formDesigner.saveError', '保存失败，请重试');
    if (isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT') {
      saveBlocked.value = true;
      saveQueued = false;
      if (autoSaveTimer) clearTimeout(autoSaveTimer);
      autoSaveTimer = null;
      persistLocalDraft();
    }
    if (!designerActive) return;
    if (isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT') {
      ElMessage.warning(t('formDesigner.schemaConflictPaused', 'Schema 版本已变化，保存已暂停，请点击“核对版本”选择恢复方式'));
    } else if (isBusinessApiError(error)) {
      ElMessage.error(t('formDesigner.requestErrorWithId', { msg: error.msg, id: error.data.error.requestId }, '{msg}（请求 ID：{id}）'));
    } else {
      ElMessage.error(error instanceof Error ? error.message : t('formDesigner.saveError', '保存失败，请重试'));
    }
  } finally {
    saveInFlight = false;
    if (saveQueued && !saveBlocked.value && designerActive && online.value) scheduleAutoSave(500);
    saveQueued = false;
  }
}


// ---- 顶栏外壳：表单设置 / 预览代码 / 生成 / 「⋯」菜单 ----
const settingsVisible = ref(false);
const codePreviewVisible = ref(false);
const versionsVisible = ref(false);
const codePreviewRef = ref<{ dynamicPublish: () => Promise<void> } | null>(null);
// 本次会话正式生成成功后的独立页面路由；「打开运行时」优先打开它。
const generatedRoute = ref('');
const targetLabel = computed(() => {
  if (!businessModule.value) return '';
  const label = isPluginTarget.value
    ? t('formDesigner.pluginBadge', { code: businessTarget.value?.pluginCode ?? '' }, '所属插件：{code}')
    : t('formDesigner.coreAdmin', '核心后台');
  return businessTarget.value?.locked ? `${label} · ${t('formDesigner.targetLocked', '目标已锁定')}` : label;
});
const generateHint = computed(() => {
  if (!isPluginTarget.value) return '';
  const table = businessTarget.value?.tableStrategy === 'external'
    ? t('formDesigner.pluginExternalHint', '外部依赖：不生成该表 CREATE／ALTER，安装／更新时校验兼容性。')
    : t('formDesigner.pluginOwnedHint', '插件拥有新表：这里只生成迁移，安装／更新时才执行。');
  return `${t('formDesigner.pluginGenerateHint', '使用已保存草稿生成源码，不动态发布；安装／更新后生效。')}${table}`;
});
// 预览与生成只针对已保存的服务端 Schema：有未保存编辑或冲突暂停时一律不可用。
const generationLocked = computed(() => !businessModule.value || store.dirty.value || saveBlocked.value);
const canPreviewCode = computed(() => hasPermission(PERM_PREVIEW));
const canGenerate = computed(() => hasPermission([PERM_PREVIEW, PERM_GENERATE], 'all'));
const canDynamicPublish = computed(() => Boolean(businessModule.value) && !isPluginTarget.value && hasPermission(['development:business:preview-publish', 'development:business:publish'], 'all'));
const canViewVersions = computed(() => hasPermission('development:business:schema-versions'));
const canExportSchema = computed(() => hasPermission('development:business:export-schema'));
const runtimeRoute = computed(() => isPluginTarget.value ? '' : generatedRoute.value || businessModule.value?.runtime_route || '');

// 保存期间的新编辑与 Schema 版本变化都会让已打开的预览失效。
watch(() => store.dirty.value, (dirty) => { if (dirty) codePreviewVisible.value = false; }, { flush: 'sync' });
watch([() => JSON.stringify(businessTarget.value ?? null), moduleId], () => { codePreviewVisible.value = false; }, { flush: 'sync' });

const ensureSavedForGeneration = () => {
  if (!generationLocked.value && !saveInFlight) return true;
  ElMessage.warning(saveBlocked.value ? t('formDesigner.topBar.resolveConflictFirst', '保存已暂停，请先核对版本处理冲突') : t('formDesigner.saveSchemaFirst', '请先保存业务 Schema'));
  return false;
};
function openCodePreview() {
  if (!canPreviewCode.value || !ensureSavedForGeneration()) return;
  codePreviewVisible.value = true;
}
async function openDynamicPublish() {
  if (!canDynamicPublish.value || !ensureSavedForGeneration() || !validateDefinitionBasics()) return;
  await codePreviewRef.value?.dynamicPublish();
}
function onGenerated(result: BusinessFormalGenerationResult) {
  if (result.state !== 'completed') return;
  if (businessTarget.value) businessTarget.value.locked = true;
  if (!isPluginTarget.value && result.routePath) generatedRoute.value = result.routePath;
}

// 从服务端重新载入模块与表单，使 schema_hash 与文档和服务端一致（避免下一次自动保存冲突）。
let reloadToken = 0;
let settingsReload: Promise<void> | null = null;
async function reloadFromServer() {
  const token = ++reloadToken;
  const id = moduleId.value;
  try {
    const data = await businessDevelopmentApi.module(id);
    if (token !== reloadToken || !designerActive || id !== moduleId.value) return;
    if (!data.form) throw new Error(t('formDesigner.noDesignableForm', '业务模块没有可设计表单'));
    businessModule.value = data.module;
    // 期间产生的本地编辑不能被静默覆盖：保留它们，交由下一次保存的版本冲突核对处理。
    if (store.dirty.value) return;
    store.load({ ...data.form, fields: data.fields });
    clearLocalDraft();
  } catch {
    if (token === reloadToken) ElMessage.error(t('formDesigner.topBar.reloadFailed', '表单设置已保存，但设计器重新加载失败，请刷新页面'));
  }
}
async function openSettings() {
  if (!businessModule.value) return;
  if (saveBlocked.value) {
    ElMessage.warning(t('formDesigner.topBar.resolveConflictFirst', '保存已暂停，请先核对版本处理冲突'));
    return;
  }
  if (store.dirty.value || saveInFlight) {
    try {
      await ElMessageBox.confirm(
        t('formDesigner.topBar.settingsDirtyConfirm', '设计器有未保存的改动，需要先保存才能打开表单设置。是否保存并打开？'),
        t('formDesigner.topBar.settingsDirtyTitle', '打开表单设置'),
        { type: 'warning', confirmButtonText: t('formDesigner.topBar.saveAndOpen', '保存并打开'), cancelButtonText: t('formDesigner.topBar.cancel', '取消') }
      );
    } catch {
      return;
    }
    if (saveInFlight) {
      ElMessage.warning(t('formDesigner.topBar.saveBeforeContinue', '请先等待设计器保存完成'));
      return;
    }
    await saveDefinition(false);
    // 保存失败或进入冲突暂停时不打开，避免两处编辑基于不同版本。
    if (store.dirty.value || saveBlocked.value) return;
  }
  settingsVisible.value = true;
}
function onSettingsSaved() {
  settingsReload = reloadFromServer().finally(() => { settingsReload = null; });
}
async function onSettingsPreview() {
  if (settingsReload) await settingsReload;
  openCodePreview();
}
function openVersions() {
  if (!canViewVersions.value || !ensureSavedForGeneration()) return;
  versionsVisible.value = true;
}
async function onRollback() {
  versionsVisible.value = false;
  await reloadFromServer();
  ElMessage.success(t('formDesigner.topBar.rollbackReloaded', '已回滚到历史版本，设计器已重新加载'));
}
async function exportSchemaJson() {
  if (!canExportSchema.value || !businessModule.value) return;
  try {
    const { document: content } = await businessDevelopmentApi.exportSchema(moduleId.value, store.schemaDocument.value);
    const url = URL.createObjectURL(new Blob([content], { type: 'application/json;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${String(store.form.value.form_key || 'form')}.schema.json`;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    try {
      anchor.click();
    } finally {
      anchor.remove();
      setTimeout(() => URL.revokeObjectURL(url), 0);
    }
    ElMessage.success(t('formDesigner.topBar.exportSuccess', '已导出 JSON'));
  } catch (error) {
    ElMessage.error(isBusinessApiError(error) ? error.msg : error instanceof Error && error.message ? error.message : t('formDesigner.topBar.exportFailed', '导出失败'));
  }
}
// 与代码生成页「运行时」一致：先刷新业务菜单再跳转，插件目标不提供运行时。
async function openRuntime() {
  const target = runtimeRoute.value;
  if (!target) return;
  try {
    await refreshBusinessMenu();
  } catch {
    // 菜单刷新失败不阻止打开页面。
  }
  await router.push(target);
}

const beforeUnload = (event: BeforeUnloadEvent) => {
  if (!designerActive || !store.dirty.value) return;
  persistLocalDraft();
  event.preventDefault();
  event.returnValue = '';
};
onBeforeRouteLeave(() => {
  if (!store.dirty.value) return true;
  return ElMessageBox.confirm(t('formDesigner.unsavedLeaveConfirm', '当前表单尚未保存，确认离开吗？'), t('formData.leaveConfirmTitle', '离开确认'), { type: 'warning' }).then(() => true).catch(() => false);
});

const scheduleAutoSave = (delay = 1200) => {
  if (!designerActive || saveBlocked.value || !store.dirty.value) return;
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(() => {
    autoSaveTimer = null;
    void saveDefinition(true);
  }, delay);
};
watch([() => store.form.value, () => store.nodes.value], () => {
  if (!designerActive || !store.dirty.value) return;
  scheduleLocalDraft();
  scheduleAutoSave();
}, { deep: true });
const onOnline = () => { online.value = true; if (designerActive) void saveDefinition(true); };
const onOffline = () => { online.value = false; persistLocalDraft(); };
// KeepAlive 停用不卸载组件，必须同时停止定时器、监听器和异步保存的后续排队。
const deactivateDesigner = () => {
  closeSideDrawers();
  if (store.dirty.value) persistLocalDraft();
  designerActive = false;
  codePreviewVisible.value = false;
  saveQueued = false;
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  if (localDraftTimer) clearTimeout(localDraftTimer);
  autoSaveTimer = null;
  localDraftTimer = null;
  window.removeEventListener('beforeunload', beforeUnload);
  window.removeEventListener('online', onOnline);
  window.removeEventListener('offline', onOffline);
};
onDeactivated(deactivateDesigner);
onActivated(() => {
  designerActive = true;
  online.value = navigator.onLine;
  window.addEventListener('beforeunload', beforeUnload);
  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);
  void restoreLocalDraft();
  if (store.dirty.value) {
    scheduleLocalDraft();
    scheduleAutoSave();
  }
});
onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnload);
  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);
  await Promise.allSettled([loadPluginFormComponents()]);
  await load();
  localDraftRestorePending = true;
  void restoreLocalDraft();
});
onBeforeUnmount(() => {
  deactivateDesigner();
});
</script>

<style scoped>
:global(.save-conflict-dialog) {
  max-width: 1280px;
  max-height: calc(100vh - 32px);
  max-height: calc(100dvh - 32px);
  display: flex;
  flex-direction: column;
  margin-bottom: 16px;
  overflow: hidden;
}
:global(.save-conflict-dialog .el-dialog__header),
:global(.save-conflict-dialog .el-dialog__footer) {
  flex-shrink: 0;
}
:global(.save-conflict-dialog .el-dialog__body) {
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.save-conflict-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 6px;
  background: var(--el-color-warning-light-9);
  color: var(--el-text-color-regular);
  line-height: 1.6;
}
.save-conflict-hash {
  margin: 0 0 12px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  overflow-wrap: anywhere;
}
.save-conflict-comparison {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.save-conflict-card {
  display: flex;
  flex-direction: column;
  min-width: 0;
  border: 1px solid var(--el-border-color);
  border-radius: 8px;
  overflow: hidden;
}
.save-conflict-card-title {
  padding: 10px 12px;
  background: var(--el-fill-color-light);
  border-bottom: 1px solid var(--el-border-color-light);
  color: var(--el-text-color-primary);
  font-weight: 600;
}
.save-conflict-card textarea {
  box-sizing: border-box;
  width: 100%;
  height: clamp(220px, 45vh, 520px);
  padding: 12px;
  border: 0;
  border-radius: 0;
  resize: none;
  overflow: auto;
  background: var(--el-bg-color);
  color: var(--el-text-color-regular);
  font: 12px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  tab-size: 2;
}
.save-conflict-card textarea:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: -2px;
}
.save-conflict-risk {
  margin: 12px 0 0;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.6;
}
.save-conflict-error {
  color: var(--el-color-danger);
  overflow-wrap: anywhere;
}
.save-conflict-footer,
.save-conflict-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.save-conflict-footer {
  justify-content: space-between;
}
.save-conflict-actions {
  justify-content: flex-end;
  margin-left: auto;
}
.save-conflict-footer :deep(.el-button) {
  margin-left: 0;
  max-width: 100%;
  height: auto;
  min-height: 32px;
  white-space: normal;
}
.save-conflict-footer :deep(.el-button > span) {
  overflow-wrap: anywhere;
}
@media (max-width: 899px) {
  .save-conflict-comparison {
    grid-template-columns: minmax(0, 1fr);
  }
  .save-conflict-card textarea {
    height: 240px;
  }
}
/* 冲突横幅：顶栏下方整条，操作与核对对话框不变。 */
.designer-conflict-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 10px;
  padding: 8px 14px;
  border-bottom: 1px solid var(--el-color-warning-light-7);
  background: var(--el-color-warning-light-9);
  color: var(--el-color-warning-dark-2);
  font-size: 13px;
  line-height: 1.6;
}
.designer-conflict-banner__spacer {
  flex: 1;
}
.designer-tabs-wrap {
  position: relative;
}
.designer-tabs-wrap > .designer-tabs > :deep(.el-tabs__header) {
  margin-bottom: 12px;
}
/* 预览模式与「预览数据」放在页签行右侧，仅预览时显示。 */
.designer-tabs-extra {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
}
@media (max-width: 760px) {
  .designer-tabs-extra {
    position: static;
    height: auto;
    margin: 8px 0;
  }
}
/* 表单设计三栏：左 248px 控件/大纲 · 中间画布占满 · 右 320px 属性；各栏独立滚动。 */
.designer-work {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  height: max(560px, calc(100vh - 200px));
  height: max(560px, calc(100dvh - 200px));
  overflow: hidden;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 12px;
  background: var(--el-bg-color);
}
.designer-work.has-left {
  grid-template-columns: 248px minmax(0, 1fr);
}
.designer-work.has-right {
  grid-template-columns: minmax(0, 1fr) 320px;
}
.designer-work.has-left.has-right {
  grid-template-columns: 248px minmax(0, 1fr) 320px;
}
.designer-column {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
}
.designer-column--left {
  border-right: 1px solid var(--el-border-color-lighter);
}
.designer-column--right {
  overflow-y: auto;
  border-left: 1px solid var(--el-border-color-lighter);
  scrollbar-width: thin;
}
.designer-column--right > .el-empty {
  margin: auto 0;
}
.designer-center {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
  min-height: 0;
  overflow-y: auto;
  padding: 14px;
  background: var(--el-fill-color-lighter);
  scrollbar-width: thin;
}
.designer-canvas-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
}
.designer-canvas-toolbar__spacer {
  flex: 1;
}
.designer-canvas-toolbar__meta {
  overflow: hidden;
  max-width: 100%;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.designer-canvas-toolbar :deep(.el-button + .el-button) {
  margin-left: 0;
}
.designer-catalog-alert {
  flex-shrink: 0;
}
.designer-paper,
.schema-preview {
  box-sizing: border-box;
  width: 100%;
  margin: 0 auto;
  padding: 18px 22px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 12px;
  background: var(--el-bg-color);
}
.designer-paper {
  max-width: 820px;
}
:global(.designer-side-drawer .el-drawer__header) {
  margin-bottom: 0;
  padding: 10px 14px;
}
:global(.designer-side-drawer .el-drawer__body) {
  display: flex;
  flex-direction: column;
  padding: 0;
}
.designer-command-bar {
  position: sticky;
  top: 0;
  z-index: 20;
  margin: 0 -16px;
}
.schema-preview-mobile {
  max-width: 320px;
  min-height: 480px;
  padding: 18px 14px;
  border: 10px solid var(--el-text-color-primary);
  border-radius: 32px;
}
.schema-preview-tablet {
  max-width: 768px;
}
.schema-preview-desktop {
  max-width: 1100px;
}
@media (max-width: 759px) {
  .designer-center {
    padding: 10px;
  }
  .designer-paper {
    padding: 12px;
  }
}
</style>
