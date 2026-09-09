<template>
  <PageWrapper :title="t('formDesigner.title', '表单设计器')" :subtitle="t('formDesigner.subtitle', '拖拽控件到画布；右侧编辑字段参数；创建表保存前需应用守卫式迁移')">
    <template #extra>
      <div class="flex flex-wrap items-center gap-2">
        <el-tag v-if="!online" type="warning" effect="plain">离线草稿</el-tag>
        <el-button :disabled="!store.canUndo.value" @click="store.undo()">{{ t('formDesigner.undo', '撤销') }}</el-button>
        <el-button :disabled="!store.canRedo.value" @click="store.redo()">{{ t('formDesigner.redo', '重做') }}</el-button>
        <el-radio-group v-model="workspaceMode" size="small">
          <el-radio-button value="edit">{{ t('formDesigner.editMode', '编辑模式') }}</el-radio-button>
          <el-radio-button value="desktop">{{ t('formDesigner.desktopPreview', '桌面预览') }}</el-radio-button>
          <el-radio-button value="tablet">{{ t('formDesigner.tabletPreview', '平板预览') }}</el-radio-button>
          <el-radio-button value="mobile">{{ t('formDesigner.mobilePreview', '移动预览') }}</el-radio-button>
        </el-radio-group>
        <el-select v-if="workspaceMode !== 'edit'" v-model="previewMode" size="small" class="w-[110px]">
          <el-option label="创建" value="create" /><el-option label="编辑" value="edit" /><el-option label="只读" value="readonly" /><el-option label="搜索" value="search" />
        </el-select>
        <el-button v-if="workspaceMode !== 'edit'" size="small" @click="previewSettingsVisible = true">预览数据</el-button>
        <el-button @click="jsonEditorVisible = true">{{ t('formDesigner.advancedJson', '高级 JSON') }}</el-button>
        <el-button @click="onExportSchema">{{ t('formDesigner.exportSchema', '导出 Schema') }}</el-button>
        <el-button :disabled="!store.form.value.id" @click="versionVisible = true">{{ t('formDesigner.versionHistory', '版本历史') }}</el-button>
        <el-button v-if="store.form.value.source_type === 'adopted'" @click="inferVisible = true">{{ t('formDesigner.reInfer', '重新推断') }}</el-button>
        <el-button v-if="store.form.value.source_type === 'created'" @click="onPreview">{{ t('formDesigner.migrationPreview', '迁移预览') }}</el-button>
        <el-tag :type="saveStatusType" effect="plain">{{ saveStatusLabel }}</el-tag>
        <el-button
          :type="store.dirty.value ? 'primary' : 'default'"
          :loading="store.saveStatus.value === 'saving'"
          :disabled="!store.dirty.value || store.saveStatus.value === 'saving'"
          @click="onSave"
        >{{ t('formDesigner.saveDraft', '保存草稿') }}</el-button>
        <el-button type="primary" @click="openPublish">{{ t('formDesigner.publish', '发布') }}</el-button>
      </div>
    </template>

    <el-card shadow="never" class="mb-3">
      <template #header>{{ t('formDesigner.basicInfo', '表单基本信息') }}</template>
      <el-form label-width="90px" class="designer-meta-form">
        <el-form-item :label="t('formDesigner.formName', '表单名称')" required>
          <el-input :model-value="store.form.value.name" maxlength="100" :placeholder="t('formDesigner.namePlaceholder', '如：活动报名')" @update:model-value="(name) => store.updateForm({ name })" />
        </el-form-item>
        <el-form-item :label="t('formDesigner.formKey', '表单标识')" required>
          <el-input
            :model-value="store.form.value.form_key"
            maxlength="61"
            @update:model-value="(form_key) => store.updateForm({ form_key })"
            :placeholder="t('formDesigner.keyPlaceholder', '如 activity_form')"
            @blur="normalizeFormKey"
          />
          <div class="form-tip">{{ t('formDesigner.keyTip', '用于接口和数据页地址，以小写字母开头，只能包含小写字母、数字和下划线。') }}</div>
        </el-form-item>
        <el-form-item :label="t('formDesigner.source', '来源')" required>
          <el-radio-group :model-value="store.form.value.source_type" @update:model-value="updateSourceType">
            <el-radio-button value="created">{{ t('formDesigner.createTable', '创建新表') }}</el-radio-button>
            <el-radio-button value="adopted">{{ t('formDesigner.adoptTable', '采纳已有表') }}</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('formDesigner.boundTable', '绑定表')" required>
          <el-input :model-value="store.form.value.table_name" :placeholder="t('formDesigner.tablePlaceholder', '如 fun_activity')" @update:model-value="(table_name) => store.updateForm({ table_name })" @blur="normalizeFormKey" />
        </el-form-item>
      </el-form>
    </el-card>

    <el-alert
      class="mb-3"
      :title="`Schema 来源：${schemaOriginLabel}。高级 JSON 应用后来源将切换为外部导入。`"
      type="info"
      :closable="false"
      show-icon
    />
    <el-alert
      v-if="catalogDiagnostics.length"
      class="mb-3"
      :title="catalogDiagnostics.map((item) => item.message).join('；')"
      type="error"
      :closable="false"
      show-icon
    />

    <el-card shadow="never" class="mb-3">
      <template #header>
        <div class="flex items-center justify-between">
          <span>FormSchema v2 AST 节点树</span>
          <el-button link type="primary" @click="store.addNode('group')">添加根容器</el-button>
        </div>
      </template>
      <SchemaNodeTree :nodes="store.nodes.value" :store="store" />
    </el-card>

    <div class="designer-layout flex gap-3" :class="`workspace-${workspaceMode}`">
      <!-- 左：控件 palette -->
      <el-card shadow="never" class="w-[230px] shrink-0">
        <template #header>控件（{{ designerControls.length }}）</template>
        <div ref="paletteRef" class="palette-list flex max-h-[calc(100vh-250px)] flex-col gap-2 overflow-y-auto pr-1">
          <template v-for="group in controlGroups" :key="group">
            <div class="sticky top-0 z-10 bg-[var(--el-bg-color-overlay)] py-1 text-xs font-semibold text-[var(--el-text-color-secondary)]">
              {{ group }}
            </div>
            <div
              v-for="control in controlsOf(group)"
              :key="control.type"
              class="palette-item cursor-grab rounded border border-[var(--el-border-color)] px-2 py-1.5 text-sm"
              :data-type="control.type"
              role="button"
              tabindex="0"
              :aria-label="`添加${control.label}`"
              @keydown.enter.prevent="store.addNode(control.type)"
            >
              {{ control.label }}
            </div>
          </template>
        </div>
      </el-card>

      <!-- 中：画布 -->
      <el-card shadow="never" class="min-w-0 flex-1">
        <template #header>
          <div class="flex items-center justify-between">
            <span>画布（{{ store.fields.value.length }} 字段）</span>
            <span class="text-xs text-[var(--el-text-color-secondary)]">{{ store.form.value.name || '未命名' }} → {{ store.form.value.table_name }}</span>
          </div>
        </template>
        <DesignerCanvas
          v-if="workspaceMode === 'edit'"
          class="designer-canvas"
          :nodes="store.nodes.value"
          :store="store"
        />
        <div v-else class="designer-canvas schema-preview" :class="`schema-preview-${workspaceMode}`">
          <SchemaRenderer
            ref="previewRenderer"
            :schema="previewSchema"
            :values="previewValues"
            :form-key="String(store.form.value.form_key ?? '')"
            :disabled="previewMode === 'readonly'"
          />
        </div>
      </el-card>

      <!-- 右：属性面板 -->
      <el-card shadow="never" class="w-[360px] shrink-0">
        <template #header>字段属性</template>
        <PropsPanel v-if="store.selected.value" :field="store.selected.value" :source-type="store.form.value.source_type ?? 'created'" :controls="designerControls" @update="store.updateField" />
        <el-empty v-else description="点选画布字段编辑参数" />
        <template v-if="store.selectedNode.value">
          <el-divider content-position="left">结构化配置</el-divider>
          <SchemaStructurePanel :node="store.selectedNode.value" @update="store.updateNode" />
        </template>
      </el-card>
    </div>

    <el-dialog v-model="publishVisible" title="发布表单" width="900px" :close-on-click-modal="false">
      <el-steps :active="publishStep" finish-status="success" align-center class="mb-5">
        <el-step title="发布设置" />
        <el-step title="变更预览" />
        <el-step title="冲突确认" />
        <el-step title="发布结果" />
      </el-steps>

      <el-form v-if="publishStep === 0" :model="publishConfig" label-width="110px" class="publish-config-grid">
        <el-form-item label="模块名"><el-input v-model="publishConfig.module" /></el-form-item>
        <el-form-item label="API 前缀"><el-input v-model="publishConfig.apiPrefix" /></el-form-item>
        <el-form-item label="页面路由"><el-input v-model="publishConfig.routePath" /></el-form-item>
        <el-form-item label="菜单名称"><el-input v-model="publishConfig.menuName" /></el-form-item>
        <el-form-item label="父级菜单">
          <el-tree-select v-model="publishConfig.parentSourceName" :data="parentMenus" node-key="sourceName" :props="menuTreeProps" check-strictly clearable class="w-full" />
        </el-form-item>
        <el-form-item label="菜单图标"><el-select v-model="publishConfig.icon" filterable class="w-full"><el-option v-for="icon in icons" :key="icon" :label="icon" :value="icon" /></el-select></el-form-item>
        <el-form-item label="表单容器"><el-radio-group v-model="publishConfig.formMode"><el-radio-button value="dialog">弹窗</el-radio-button><el-radio-button value="drawer">抽屉</el-radio-button></el-radio-group></el-form-item>
        <el-form-item label="完整功能"><el-checkbox v-model="publishConfig.batchDelete">批量删除</el-checkbox><el-checkbox v-model="publishConfig.import">导入</el-checkbox><el-checkbox v-model="publishConfig.export">导出</el-checkbox><el-checkbox v-model="publishConfig.softDeletes">软删除</el-checkbox></el-form-item>
        <el-form-item label="数据权限"><el-switch v-model="publishConfig.dataScopeEnabled" /></el-form-item>
        <el-form-item v-if="publishConfig.dataScopeEnabled" label="部门字段"><el-select v-model="publishConfig.dataScopeField" filterable class="w-full"><el-option v-for="field in dataScopeFields" :key="field.field_name" :label="`${field.label} (${field.field_name})`" :value="field.field_name" /></el-select></el-form-item>
      </el-form>

      <template v-else-if="publishStep === 1">
        <el-alert :title="publishPreview?.ddl.message || '正在等待预览'" type="info" :closable="false" class="mb-3" />
        <el-collapse>
          <el-collapse-item title="数据库迁移" name="ddl"><el-input :model-value="publishPreview?.ddl.sql || '无结构变更'" type="textarea" :rows="8" readonly /></el-collapse-item>
          <el-collapse-item title="发布 Schema" name="schema">
            <el-descriptions :column="1" border><el-descriptions-item label="Schema Hash">{{ publishPreview?.formSchemaHash }}</el-descriptions-item><el-descriptions-item label="依赖 Hash">{{ publishPreview?.formDependencyHash }}</el-descriptions-item></el-descriptions>
          </el-collapse-item>
          <el-collapse-item title="生成文件" name="files">
            <el-table :data="publishPreview?.plan.files || []" size="small" border><el-table-column prop="path" label="路径" /><el-table-column prop="status" label="状态" width="110" /></el-table>
          </el-collapse-item>
        </el-collapse>
      </template>

      <template v-else-if="publishStep === 2">
        <el-alert v-if="!conflictFiles.length" title="没有人工修改冲突，可直接发布" type="success" :closable="false" class="mb-3" />
        <el-checkbox-group v-else v-model="allowOverwrite" class="flex flex-col gap-3">
          <el-card v-for="file in conflictFiles" :key="file.path" shadow="never">
            <el-checkbox :value="file.path">允许覆盖 {{ file.path }}</el-checkbox>
            <el-input :model-value="file.diff || ''" type="textarea" :rows="7" readonly class="mt-2" />
          </el-card>
        </el-checkbox-group>
      </template>

      <el-result v-else :icon="publishResult?.publishStatus === 'published' ? 'success' : 'warning'" :title="publishResult?.publishStatus === 'published' ? '全栈发布成功' : '发布未完全完成'" :sub-title="publishResult?.generation.resourceApplyError || publishResult?.routePath || ''">
        <template #extra>
          <el-button v-if="publishResult?.routePath" type="primary" @click="openGeneratedRoute">打开独立页面</el-button>
          <el-button v-if="publishResult?.publishStatus === 'partial' && store.form.value.id" :loading="retryingResources" @click="onRetryResources">重试菜单权限</el-button>
        </template>
      </el-result>

      <template #footer>
        <el-button @click="publishVisible = false">关闭</el-button>
        <el-button v-if="publishStep > 0 && publishStep < 3" @click="publishStep--">上一步</el-button>
        <el-button v-if="publishStep === 0" type="primary" :loading="previewingPublish" @click="onPreviewPublish">预览发布</el-button>
        <el-button v-else-if="publishStep === 1" type="primary" @click="publishStep = 2">下一步</el-button>
        <el-button v-else-if="publishStep === 2" type="primary" :loading="publishing" @click="onPublish">确认发布</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="previewSettingsVisible" title="预览数据与服务端错误" width="680px">
      <el-form label-width="120px">
        <el-form-item label="初始值 JSON"><el-input v-model="previewValuesJson" type="textarea" :rows="8" /></el-form-item>
        <el-form-item label="字段错误 JSON"><el-input v-model="previewErrorsJson" type="textarea" :rows="6" placeholder='{"field":"服务端错误"}' /></el-form-item>
      </el-form>
      <template #footer><el-button @click="previewSettingsVisible = false">取消</el-button><el-button type="primary" @click="applyPreviewSettings">应用预览</el-button></template>
    </el-dialog>

    <el-dialog v-model="jsonEditorVisible" title="FormSchema v2 高级 JSON 编辑" width="860px" :close-on-click-modal="false">
      <SchemaJsonEditor :schema="store.schemaDocument.value" @apply="onApplySchemaJson" />
    </el-dialog>

    <VersionHistoryDrawer v-model="versionVisible" :form-id="store.form.value.id" @rollback="onRollback" />

    <el-card v-if="debugEnabled" shadow="never" class="mt-3">
      <template #header>{{ t('formDesigner.debugPanel', '调试面板') }}</template>
      <el-descriptions :column="4" border size="small">
        <el-descriptions-item :label="t('formDesigner.nodes', '节点')">{{ debugSummary.nodes }}</el-descriptions-item>
        <el-descriptions-item :label="t('formDesigner.fields', '字段')">{{ debugSummary.fields }}</el-descriptions-item>
        <el-descriptions-item :label="t('formDesigner.containers', '容器')">{{ debugSummary.containers }}</el-descriptions-item>
        <el-descriptions-item :label="t('formDesigner.maxDepth', '最大深度')">{{ debugSummary.maxDepth }}</el-descriptions-item>
      </el-descriptions>
      <el-collapse class="mt-3">
        <el-collapse-item :title="t('formDesigner.debugValues', '当前预览值')" name="values"><pre>{{ formatDebug(debugState.previewValues) }}</pre></el-collapse-item>
        <el-collapse-item :title="t('formDesigner.debugConditions', '条件命中')" name="conditions"><pre>{{ formatDebug(debugState.conditionHits) }}</pre></el-collapse-item>
        <el-collapse-item :title="t('formDesigner.debugActions', '动作轨迹')" name="actions"><pre>{{ formatDebug(debugState.actionTrace) }}</pre></el-collapse-item>
        <el-collapse-item :title="t('formDesigner.debugDataSources', '数据源状态')" name="dataSources"><pre>{{ formatDebug(debugState.dataSources) }}</pre></el-collapse-item>
        <el-collapse-item :title="t('formDesigner.debugValidation', '验证结果')" name="validation"><pre>{{ formatDebug(debugState.validationResults) }}</pre></el-collapse-item>
      </el-collapse>
    </el-card>

    <!-- 迁移预览 -->
    <el-dialog v-model="previewVisible" title="迁移预览" width="720px">
      <el-alert :title="preview?.message ?? ''" type="info" :closable="false" class="mb-2" />
      <el-input :model-value="preview?.sql ?? ''" type="textarea" :rows="14" readonly />
      <template #footer>
        <el-button @click="previewVisible = false">关闭</el-button>
        <el-button type="primary" :loading="applying" :disabled="!preview?.sql" @click="onApply">应用迁移</el-button>
      </template>
    </el-dialog>

    <!-- 采纳推断 -->
    <el-dialog v-model="inferVisible" title="从已有表推断字段" width="480px">
      <el-form label-width="90px">
        <el-form-item label="连接">
          <el-input model-value="mysql" disabled />
        </el-form-item>
        <el-form-item label="数据表">
          <el-input v-model="inferTable" placeholder="如 fun_activity" />
        </el-form-item>
      </el-form>
      <el-alert title="推断将替换当前画布字段（可撤销）" type="warning" :closable="false" />
      <template #footer>
        <el-button @click="inferVisible = false">取消</el-button>
        <el-button type="primary" :loading="inferring" @click="onInfer">推断</el-button>
      </template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import Sortable from 'sortablejs';
import {
  formDesignerApi,
  formFullPublishApi,
  type FormPublishConfig,
  type FormFullPublishPreview,
  type FormFullPublishResult,
  type FormSchemaVersion,
  type MigrationPreview
} from '@/api/form';
import { crudDevelopmentApi } from '@/api/development/crud';
import { usePermissionStore } from '@/store/modules/permission';
import { CONTROL_REGISTRY, controlMeta } from '../registry';
import { useDesigner } from '../composables/useDesigner';
import { pluginCatalog } from './pluginCatalog';
import { loadPluginFormComponents } from '../schema/pluginComponentLoader';
import { buildDesignerDebugState, buildSchemaDebugSummary } from './schemaEditor';
import SchemaRenderer from '../components/SchemaRenderer.vue';
import DesignerCanvas from './components/DesignerCanvas.vue';
import PropsPanel from './components/PropsPanel.vue';
import SchemaJsonEditor from './components/SchemaJsonEditor.vue';
import SchemaNodeTree from './components/SchemaNodeTree.vue';
import SchemaStructurePanel from './components/SchemaStructurePanel.vue';
import VersionHistoryDrawer from './components/VersionHistoryDrawer.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const permissionStore = usePermissionStore();
const store = useDesigner();
const workspaceMode = ref<'edit' | 'desktop' | 'tablet' | 'mobile'>('edit');
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const previewMode = ref<'create' | 'edit' | 'readonly' | 'search'>('create');
const previewSettingsVisible = ref(false);
const previewValuesJson = ref('{}');
const previewErrorsJson = ref('{}');
const previewRenderer = ref<{ setFieldErrors: (errors: Record<string, string>) => Promise<void> }>();
const jsonEditorVisible = ref(false);
const versionVisible = ref(false);
const applying = ref(false);
const inferring = ref(false);
const previewVisible = ref(false);
const inferVisible = ref(false);
const inferTable = ref('');
const preview = ref<MigrationPreview | null>(null);
const publishVisible = ref(false);
const publishStep = ref(0);
const previewingPublish = ref(false);
const publishing = ref(false);
const retryingResources = ref(false);
const publishPreview = ref<FormFullPublishPreview | null>(null);
const publishResult = ref<FormFullPublishResult | null>(null);
const publishOperationKey = ref('');
const allowOverwrite = ref<string[]>([]);
const conflictFiles = computed(() => publishPreview.value?.conflicts ?? []);
const dataScopeFields = computed(() => store.fields.value.filter((field) => controlMeta(field.type).kind !== 'layout'));
const parentMenus = ref<Array<Record<string, unknown>>>([]);
const icons = ref<string[]>([]);
const menuTreeProps = { label: 'name', children: 'children', value: 'sourceName' };
const publishConfig = ref<FormPublishConfig>({
  module: 'generated', apiPrefix: '', routePath: '', menuEnabled: true, parentId: null,
  parentSourceName: '', menuName: '', icon: 'i-ep-document', sortOrder: 999,
  softDeletes: true, batchDelete: true, import: true, export: true, formMode: 'dialog',
  dataScopeEnabled: false, dataScopeField: ''
});
const paletteRef = ref<HTMLElement>();
const previewValues = reactive<Record<string, unknown>>(Object.fromEntries(store.fields.value.map((field) => [field.field_name, field.default_value])));
watch(() => store.fields.value, (fields) => {
  for (const field of fields) if (!(field.field_name in previewValues)) previewValues[field.field_name] = field.default_value;
  for (const key of Object.keys(previewValues)) if (!fields.some((field) => field.field_name === key)) delete previewValues[key];
}, { deep: true });
const designerControls = computed(() => [...CONTROL_REGISTRY, ...pluginCatalog.controls.value]);
const catalogDiagnostics = computed(() => pluginCatalog.fieldDiagnostics(store.fields.value));
let paletteSortable: Sortable | null = null;
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;
let saveRevision = 0;
let saveQueued = false;
const localDraftKey = computed(() => `form-designer-draft:${String(store.form.value.id ?? store.form.value.form_key ?? 'new')}`);
const persistLocalDraft = () => {
  if (typeof localStorage === 'undefined') return;
  localStorage.setItem(localDraftKey.value, JSON.stringify({ definition: definition(), savedAt: Date.now() }));
};
const clearLocalDraft = () => { if (typeof localStorage !== 'undefined') localStorage.removeItem(localDraftKey.value); };
const restoreLocalDraft = () => {
  if (typeof localStorage === 'undefined') return;
  const raw = localStorage.getItem(localDraftKey.value);
  if (!raw) return;
  try {
    const draft = JSON.parse(raw) as { definition?: import('@/api/form').FormDefinition };
    if (draft.definition?.schema_document?.schemaVersion === 2 && window.confirm('检测到未同步的本地表单草稿，是否恢复？')) {
      store.load(draft.definition);
      store.updateForm({ schema_origin: 'designer' });
    }
  } catch { clearLocalDraft(); }
};

const definition = () => ({
  ...store.form.value,
  schema_version: 2,
  schema_document: store.schemaDocument.value,
  schemaHash: store.form.value.schema_hash,
  schema_origin: 'designer',
  publish_config: publishConfig.value,
  fields: store.fields.value
});
const previewSchema = computed(() => ({
  ...store.schemaDocument.value,
  form: { ...(store.schemaDocument.value.form ?? {}), mode: previewMode.value, readOnly: previewMode.value === 'readonly' }
}));
const applyPreviewSettings = async () => {
  try {
    const values = JSON.parse(previewValuesJson.value) as Record<string, unknown>;
    const errors = JSON.parse(previewErrorsJson.value) as Record<string, string>;
    Object.keys(previewValues).forEach((key) => delete previewValues[key]);
    Object.assign(previewValues, values);
    await previewRenderer.value?.setFieldErrors(errors);
    previewSettingsVisible.value = false;
  } catch { ElMessage.warning('请输入合法 JSON'); }
};
const debugEnabled = import.meta.env.DEV && import.meta.env.VITE_FORM_DESIGNER_DEBUG !== 'false';
const debugSummary = computed(() => buildSchemaDebugSummary(store.schemaDocument.value));
const debugState = computed(() => buildDesignerDebugState(store.schemaDocument.value, previewValues));
const formatDebug = (value: unknown) => JSON.stringify(value, null, 2);
const saveStatusLabel = computed(() => ({
  unsaved: t('formDesigner.unsaved', '未保存'),
  saving: t('formDesigner.saving', '保存中'),
  failed: t('formDesigner.saveFailed', '保存失败'),
  saved: t('formDesigner.saved', '已保存')
}[store.saveStatus.value]));
const saveStatusType = computed(() => ({ unsaved: 'warning', saving: 'info', failed: 'danger', saved: 'success' } as const)[store.saveStatus.value]);
const schemaOriginLabel = computed(() => ({
  designer: '可视化设计器', import: '外部导入', migration: '旧版迁移', api: 'API 写入'
}[String(store.form.value.schema_origin ?? 'designer')] ?? String(store.form.value.schema_origin)));
const onApplySchemaJson = async (schema: import('@/api/form').FormSchemaDocument) => {
  const compiled = await formDesignerApi.compile(schema);
  const result = store.replaceSchema(compiled.document);
  if (!result.ok) {
    ElMessage.warning(result.error);
    return;
  }
  store.updateForm({ schema_origin: 'import' });
  jsonEditorVisible.value = false;
  ElMessage.success('FormSchema v2 已通过服务端校验并应用');
};
const onExportSchema = async () => {
  const { document: exportedDocument } = await formDesignerApi.exportSchema(store.schemaDocument.value);
  const blob = new Blob([exportedDocument], { type: 'application/json;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = window.document.createElement('a');
  link.href = url;
  link.download = `${store.form.value.form_key || 'form-schema'}.json`;
  link.click();
  URL.revokeObjectURL(url);
};
const onRollback = (version: FormSchemaVersion) => {
  const result = store.replaceSchema(version.schema_document);
  if (result.ok) store.updateForm({ schema_origin: 'rollback' });
};
const controlGroups = computed(() => [...new Set(designerControls.value.map((control) => control.group))]);
const controlsOf = (group: string) => designerControls.value.filter((control) => control.group === group);
const normalizeIdentifier = (value: string) => value
  .trim()
  .toLowerCase()
  .replace(/[\s-]+/g, '_')
  .replace(/[^a-z0-9_]/g, '')
  .replace(/^_+|_+$/g, '')
  .slice(0, 61);
const updateSourceType = (sourceType: string | number | boolean | undefined) => {
  if (sourceType === 'created' || sourceType === 'adopted') store.updateForm({ source_type: sourceType });
};
const normalizeFormKey = () => {
  const current = normalizeIdentifier(String(store.form.value.form_key ?? ''));
  const fromTable = normalizeIdentifier(String(store.form.value.table_name ?? '')).replace(/^fun_/, '');
  const normalized = current || fromTable;
  if (normalized !== store.form.value.form_key) store.updateForm({ form_key: normalized });
};
const validateDefinitionBasics = () => {
  normalizeFormKey();
  if (!String(store.form.value.name ?? '').trim()) {
    ElMessage.warning(t('formDesigner.nameRequired', '请填写表单名称'));
    return false;
  }
  if (!/^[a-z][a-z0-9_]{0,60}$/.test(String(store.form.value.form_key ?? ''))) {
    ElMessage.warning(t('formDesigner.keyInvalid', '请填写正确的表单标识'));
    return false;
  }
  if (!/^[a-z][a-z0-9_]*$/.test(String(store.form.value.table_name ?? ''))) {
    ElMessage.warning(t('formDesigner.tableInvalid', '请填写正确的绑定表名'));
    return false;
  }
  return true;
};

async function load() {
  const id = Number(route.query.id ?? 0);
  if (!id) return;
  const data = await formDesignerApi.detail(id);
  store.load({ ...data.form, fields: data.fields });
  inferTable.value = data.form.table_name;
}

async function onSave() {
  if (!validateDefinitionBasics() || !store.dirty.value) return;
  if (store.saveStatus.value === 'saving') { saveQueued = true; return; }
  const revision = ++saveRevision;
  const payload = definition();
  const payloadHash = JSON.stringify(payload);
  store.beginSave();
  try {
    const saved = await formDesignerApi.save(payload);
    const unchanged = revision === saveRevision && JSON.stringify(definition()) === payloadHash;
    if (unchanged) {
      store.markSaved({ ...saved.form, fields: saved.fields });
      clearLocalDraft();
      ElMessage.success(t('formDesigner.saveSuccess', '保存成功'));
    } else {
      store.failSave();
    }
  } catch (error) {
    store.failSave();
    ElMessage.error(t('formDesigner.saveError', '保存失败，请重试'));
  } finally {
    if (saveQueued || store.dirty.value) {
      saveQueued = false;
      if (online.value) queueMicrotask(() => void onSave());
    }
  }
}

async function onPreview() {
  if (!validateDefinitionBasics()) return;
  preview.value = await formDesignerApi.preview(definition());
  previewVisible.value = true;
}

async function onApply() {
  if (!validateDefinitionBasics()) return;
  applying.value = true;
  try {
    preview.value = await formDesignerApi.apply(definition());
    ElMessage.success('迁移应用成功');
  } finally {
    applying.value = false;
  }
}

const openPublish = async () => {
  if (!validateDefinitionBasics() || !store.fields.value.some((field) => controlMeta(field.type).kind !== 'layout')) return;
  if (!pluginCatalog.canPublish(store.fields.value)) {
    ElMessage.warning(catalogDiagnostics.value.map((item) => item.message).join('；') || '插件组件目录尚未加载');
    return;
  }
  const key = String(store.form.value.form_key ?? '').replace(/_/g, '-');
  publishConfig.value = {
    ...publishConfig.value,
    ...store.form.value.publish_config,
    apiPrefix: store.form.value.publish_config?.apiPrefix || `/generated/${key}`,
    routePath: store.form.value.publish_config?.routePath || `/generated/${key}`,
    menuName: store.form.value.publish_config?.menuName || String(store.form.value.name ?? key)
  };
  if (!parentMenus.value.length) {
    const options = await crudDevelopmentApi.options();
    parentMenus.value = options.parentMenus as unknown as Array<Record<string, unknown>>;
    icons.value = options.icons;
  }
  publishStep.value = 0;
  publishPreview.value = null;
  publishResult.value = null;
  publishOperationKey.value = crypto.randomUUID();
  publishVisible.value = true;
};
const validatePublishConfig = () => {
  if (!/^[a-z][a-z0-9-]*$/.test(publishConfig.value.module)) {
    ElMessage.warning('模块名须以小写字母开头，仅包含小写字母、数字和短横线');
    return false;
  }
  if (!/^\/[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*$/.test(publishConfig.value.apiPrefix)) {
    ElMessage.warning('请填写正确的 API 前缀');
    return false;
  }
  if (!/^\/[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*$/.test(publishConfig.value.routePath)) {
    ElMessage.warning('请填写正确的页面路由');
    return false;
  }
  if (!publishConfig.value.menuName.trim()) {
    ElMessage.warning('请填写菜单名称');
    return false;
  }
  if (publishConfig.value.dataScopeEnabled && !publishConfig.value.dataScopeField) {
    ElMessage.warning('启用数据权限后请选择部门字段');
    return false;
  }
  return true;
};
const onPreviewPublish = async () => {
  if (!validatePublishConfig()) return;
  const formId = Number(store.form.value.id || 0);
  if (!formId || store.dirty.value) {
    ElMessage.warning('请先保存表单，并发布服务端可信 Schema 后再执行完整发布');
    return;
  }
  previewingPublish.value = true;
  try {
    publishPreview.value = await formFullPublishApi.preview(formId);
    publishStep.value = 1;
  } finally {
    previewingPublish.value = false;
  }
};
const onPublish = async () => {
  publishing.value = true;
  try {
    const formId = Number(store.form.value.id || 0);
    const generationId = Number(publishPreview.value?.generationId || 0);
    if (!formId || !generationId) throw new Error('完整发布缺少 formId 或 generationId');
    publishResult.value = await formFullPublishApi.publish(
      formId,
      generationId,
      publishPreview.value?.formSchemaHash || '',
      publishPreview.value?.sensitive?.confirmToken || '',
      publishOperationKey.value
    );
    store.markSaved({ ...publishResult.value.form, fields: store.fields.value });
    const dynamicRoutes = await permissionStore.fetchMenus();
    dynamicRoutes.forEach((dynamicRoute) => {
      if (!dynamicRoute.name || !router.hasRoute(dynamicRoute.name)) router.addRoute(dynamicRoute);
    });
    publishStep.value = 3;
    ElMessage.success(publishResult.value.publishStatus === 'published' ? '全栈发布成功' : '代码已生成，菜单权限需要重试');
  } finally {
    publishing.value = false;
  }
};
const openGeneratedRoute = () => {
  if (publishResult.value?.routePath) router.push(publishResult.value.routePath);
};
const onRetryResources = async () => {
  const formId = Number(store.form.value.id || 0);
  if (!formId) return;
  retryingResources.value = true;
  try {
    const result = await formFullPublishApi.retryResources(formId);
    if (publishResult.value) publishResult.value.publishStatus = result.publishStatus;
    await permissionStore.fetchMenus();
    ElMessage.success('菜单与权限应用成功');
  } finally {
    retryingResources.value = false;
  }
};

async function onInfer() {
  inferring.value = true;
  try {
    const data = await formDesignerApi.infer('mysql', inferTable.value.trim());
    store.replaceFields(data.fields);
    inferVisible.value = false;
    ElMessage.success('推断完成');
  } finally {
    inferring.value = false;
  }
}

const beforeUnload = (event: BeforeUnloadEvent) => {
  if (!store.dirty.value) return;
  event.preventDefault();
  event.returnValue = '';
};
onBeforeRouteLeave(() => !store.dirty.value || window.confirm('当前表单尚未保存，确认离开吗？'));

watch([() => store.form.value, () => store.nodes.value], () => {
  if (!store.dirty.value) return;
  persistLocalDraft();
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(() => { if (online.value && store.dirty.value) void onSave(); }, 1200);
}, { deep: true });
const onOnline = () => { online.value = true; if (store.dirty.value) void onSave(); };
const onOffline = () => { online.value = false; persistLocalDraft(); };
onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnload);
  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);
  await loadPluginFormComponents();
  await load();
  restoreLocalDraft();
  if (paletteRef.value) {
    paletteSortable = Sortable.create(paletteRef.value, {
      group: { name: 'form-designer', pull: 'clone', put: false },
      draggable: '.palette-item',
      sort: false,
      animation: 150
    });
  }
});
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload);
  window.removeEventListener('online', onOnline);
  window.removeEventListener('offline', onOffline);
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  paletteSortable?.destroy();
});
</script>

<style scoped>
.publish-config-grid,
.designer-meta-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(280px, 1fr));
  column-gap: 24px;
}
.form-tip {
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 18px;
}
.designer-layout {
  align-items: flex-start;
}
.workspace-desktop .designer-canvas {
  margin: 0 auto;
  max-width: 1100px;
}
.workspace-mobile .designer-canvas,
.schema-preview-mobile {
  margin: 0 auto;
  max-width: 390px;
}
.schema-preview-tablet {
  margin: 0 auto;
  max-width: 768px;
}
.schema-preview-desktop {
  margin: 0 auto;
  max-width: 1100px;
}
.palette-item:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
.palette-list {
  scrollbar-width: thin;
}
</style>
