<template>
  <PageWrapper :title="t('formDesigner.title', '表单设计器')" :subtitle="t('formDesigner.subtitle', '拖拽控件到画布；右侧编辑字段参数；创建表保存前需应用守卫式迁移')">
    <div class="designer-command-bar designer-toolbar mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-[var(--el-border-color-light)] bg-[var(--el-bg-color)] p-3">
        <el-tag v-if="!online" type="warning" effect="plain">离线草稿</el-tag>
        <el-button :disabled="!store.canUndo.value" @click="store.undo()">{{ t('formDesigner.undo', '撤销') }}</el-button>
        <el-button :disabled="!store.canRedo.value" @click="store.redo()">{{ t('formDesigner.redo', '重做') }}</el-button>
        <el-radio-group v-model="workspaceMode">
          <el-radio-button value="edit">{{ t('formDesigner.editMode', '编辑模式') }}</el-radio-button>
          <el-radio-button value="desktop">{{ t('formDesigner.desktopPreview', '桌面预览') }}</el-radio-button>
          <el-radio-button value="tablet">{{ t('formDesigner.tabletPreview', '平板预览') }}</el-radio-button>
          <el-radio-button value="mobile">{{ t('formDesigner.mobilePreview', '移动预览') }}</el-radio-button>
        </el-radio-group>
        <el-select v-if="workspaceMode !== 'edit'" v-model="previewMode" class="w-[110px]">
          <el-option label="创建" value="create" /><el-option label="编辑" value="edit" /><el-option label="只读" value="readonly" /><el-option label="搜索" value="search" />
        </el-select>
        <el-button v-if="workspaceMode !== 'edit'" @click="previewSettingsVisible = true">预览数据</el-button>
        <el-tag :type="saveStatusType" effect="plain">{{ saveStatusLabel }}</el-tag>
        <el-button
          :type="store.dirty.value ? 'primary' : 'default'"
          :loading="store.saveStatus.value === 'saving'"
          :disabled="!store.dirty.value || store.saveStatus.value === 'saving'"
          @click="onSave"
        >{{ t('formDesigner.saveDraft', '保存草稿') }}</el-button>
        <el-tag v-if="businessModule" data-business-target>{{ isPluginTarget ? `所属插件：${businessTarget?.pluginCode}` : '核心后台' }} · {{ businessTarget?.locked ? '目标已锁定' : '首次成功生成后锁定' }}</el-tag>
        <span v-if="isPluginTarget">使用已保存草稿生成源码，不动态发布；安装／更新后生效。</span>
        <el-button v-if="businessModule && !isPluginTarget" type="primary" :disabled="store.dirty.value" @click="onDynamicPublish">{{ t('formDesigner.publish', '动态发布') }}</el-button>
        <el-button v-perm="'development:business:generate'" :disabled="!businessModule || store.dirty.value || saveBlocked || publishing" :loading="previewingPublish" @click="openFormalGeneration">生成正式模块</el-button>
      <div v-if="saveBlocked" data-testid="save-conflict-alert" role="alert" class="w-full">
        <strong>保存已暂停。</strong>本地草稿已保留，刷新不会解除暂停。请核对版本并明确选择恢复方式。
        <el-button :loading="conflictReviewLoading" :disabled="conflictResolving" @click="reviewSaveConflict">核对版本</el-button>
      </div>
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
            <el-button type="primary" :loading="conflictResolving" :disabled="!conflictReview || conflictReviewLoading || conflictResolving" @click="resolveSaveConflict('local')">{{ t('formDesigner.saveConflict.useLocal', '以核对后的本地覆盖') }}</el-button>
          </div>
        </div>
      </template>
    </el-dialog>

    <el-tabs v-model="activeTab" class="designer-tabs">
      <el-tab-pane label="基本信息" name="basic" :lazy="false">
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
          <div class="w-full">
            <el-select
              :model-value="store.form.value.table_name"
              filterable
              :allow-create="store.form.value.source_type === 'created'"
              :default-first-option="store.form.value.source_type === 'created'"
              :loading="tableLoading"
              class="w-full"
              :placeholder="store.form.value.source_type === 'created' ? '选择或输入新表名' : '搜索并选择已有数据表'"
              @visible-change="onTableSelectVisible"
              @update:model-value="updateBoundTable"
            >
              <el-option v-for="table in databaseTables" :key="table.name" :label="tableLabel(table)" :value="table.name" />
            </el-select>
            <div class="form-tip">{{ tableHelp }}</div>
            <el-alert v-if="tableLoadError" :title="tableLoadError" type="warning" :closable="false" class="mt-2">
              <el-button link type="primary" @click="loadDatabaseTables(true)">重新加载</el-button>
            </el-alert>
          </div>
        </el-form-item>
      </el-form>
    </el-card>
      </el-tab-pane>
      <el-tab-pane label="表单设计" name="design" :lazy="false">
    <div v-show="workspaceMode === 'edit'">
    <el-alert
      v-if="catalogDiagnostics.length"
      class="mb-3"
      :title="catalogDiagnostics.map((item) => item.message).join('；')"
      type="error"
      :closable="false"
      show-icon
    />

    </div>

    <div class="designer-layout flex gap-3" :class="{ 'is-preview': workspaceMode !== 'edit' }">
      <!-- 左：控件 palette -->
      <el-card v-show="workspaceMode === 'edit'" shadow="never" class="control-palette shrink-0">
        <template #header>选择控件</template>
        <div ref="paletteRef" class="palette-list max-h-[calc(100vh-250px)] overflow-y-auto pr-1">
          <template v-for="group in controlGroups" :key="group">
            <div class="palette-group-title">{{ group }}</div>
            <div class="palette-group-grid">
              <div
                v-for="control in controlsOf(group)"
                :key="control.type"
                class="palette-item cursor-grab rounded border border-[var(--el-border-color)] px-2 py-2 text-sm"
                :data-type="control.type"
                role="button"
                tabindex="0"
                :aria-label="`添加${control.label}`"
                @keydown.enter.prevent="store.addNode(control.type)"
              >
                <i :class="controlIcon(control)" aria-hidden="true" />
                <span>{{ control.label }}</span>
              </div>
            </div>
          </template>
        </div>
      </el-card>

      <!-- 中：画布 -->
      <el-card shadow="never" class="min-w-0 flex-1">
        <template #header>
          <div class="designer-canvas-heading">
            <span>设计画布（{{ store.fields.value.length }} 字段）</span>
            <el-button v-if="workspaceMode === 'edit'" size="small" :aria-expanded="outlineVisible" @click="outlineVisible = true">表单大纲</el-button>
            <span class="text-xs text-[var(--el-text-color-secondary)]">{{ store.form.value.name || '未命名' }} → {{ store.form.value.table_name }}</span>
          </div>
        </template>
        <div>
          <DesignerCanvas
            v-show="workspaceMode === 'edit'"
            class="designer-canvas"
            :nodes="store.nodes.value"
            :store="store"
          />
          <div v-if="workspaceMode !== 'edit'" class="designer-canvas schema-preview" :class="`schema-preview-${workspaceMode}`">
            <SchemaRenderer
              ref="previewRenderer"
              :schema="previewSchema"
              :values="previewValues"
              :form-key="String(store.form.value.form_key ?? '')"
              :disabled="previewMode === 'readonly'"
            />
          </div>
        </div>
      </el-card>

      <!-- 右：属性面板 -->
      <el-card v-show="workspaceMode === 'edit'" shadow="never" class="w-[360px] shrink-0">
        <template #header>字段属性</template>
        <div>
          <PropsPanel v-if="store.selected.value" :module-id="moduleId" :field="store.selected.value" :source-type="store.form.value.source_type ?? 'created'" :controls="designerControls" @update="store.updateField" />
          <el-empty v-else description="点选画布字段编辑参数" />
        </div>
      </el-card>
    </div>

      </el-tab-pane>
      <el-tab-pane label="按钮与工具" name="buttons" :lazy="false" />
      <el-tab-pane label="分类管理" name="list" :lazy="false" />
    </el-tabs>
    <ListConfigurationPanel v-if="activeTab === 'buttons' || activeTab === 'list'" :mode="activeTab === 'buttons' ? 'buttons' : 'categories'" :model-value="store.schemaDocument.value.list ?? {}" :fields="store.fields.value" :module-id="moduleId" :form-key="store.form.value.form_key" :permissions="buttonUser.permissions" :plugin-target="isPluginTarget" @update="store.updateList" />

    <el-drawer v-model="outlineVisible" title="表单大纲" size="min(480px, 100vw)" append-to-body destroy-on-close>
      <el-button size="small" @click="store.addNode('group')">添加布局分组</el-button>
      <SchemaNodeTree v-if="outlineVisible" :nodes="store.nodes.value" :store="store" />
    </el-drawer>

    <el-dialog v-model="publishVisible" title="正式生成" width="900px" :close-on-click-modal="false">
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
        <el-alert :title="publishPreview?.plan.blocked ? '存在冲突，正式生成已阻断' : '正式生成计划已就绪'" :type="publishPreview?.plan.blocked ? 'warning' : 'success'" :closable="false" class="mb-3" />
        <p v-if="isPluginTarget">{{ businessTarget?.tableStrategy === 'external' ? '外部依赖：不生成该表 CREATE／ALTER，安装／更新时校验兼容性。' : '插件拥有新表：这里只生成迁移，安装／更新时才执行。' }}</p>
        <GenerationPlanView v-if="publishPreview" :plan="publishPreview.plan" :conflicts="publishPreview.conflicts" />
        <el-collapse>
          <el-collapse-item title="正式生成基线" name="schema">
            <el-descriptions :column="1" border><el-descriptions-item label="Schema Hash">{{ publishPreview?.schemaHash }}</el-descriptions-item><el-descriptions-item label="Definition Hash">{{ publishPreview?.definitionHash }}</el-descriptions-item></el-descriptions>
          </el-collapse-item>
          <el-collapse-item title="生成文件" name="files">
            <el-table :data="publishPreview?.plan.files || []" size="small" border><el-table-column prop="path" label="路径" /><el-table-column prop="status" label="状态" width="110" /></el-table>
          </el-collapse-item>
        </el-collapse>
      </template>

      <template v-else-if="publishStep === 2">
        <el-alert v-if="!conflictFiles.length" title="没有人工修改冲突，可直接发布" type="success" :closable="false" class="mb-3" />
        <div v-else class="flex flex-col gap-3">
          <el-alert title="存在冲突时禁止生成。请在本地人工处理后重新预览；系统不会强制覆盖文件。" type="warning" :closable="false" />
          <el-card v-for="file in conflictFiles" :key="file.path" shadow="never">
            <div class="mb-2 font-medium">{{ file.path }} · {{ file.status }}</div>
            <el-tabs v-if="file.status !== 'binary-conflict'" type="border-card">
              <el-tab-pane label="Base"><el-input :model-value="file.baseContent || ''" type="textarea" :rows="7" readonly /></el-tab-pane>
              <el-tab-pane label="Local"><el-input :model-value="file.localContent || ''" type="textarea" :rows="7" readonly /></el-tab-pane>
              <el-tab-pane label="Remote"><el-input :model-value="file.remoteContent || ''" type="textarea" :rows="7" readonly /></el-tab-pane>
            </el-tabs>
            <el-descriptions v-else :column="1" border size="small"><el-descriptions-item label="Base hash">{{ file.baseHash || '-' }}</el-descriptions-item><el-descriptions-item label="Local hash">{{ file.localHash || '-' }}</el-descriptions-item><el-descriptions-item label="Remote hash">{{ file.remoteHash || '-' }}</el-descriptions-item></el-descriptions>
          </el-card>
        </div>
      </template>

      <el-result v-else :icon="publishResult?.state === 'completed' ? 'success' : 'warning'" :title="generationResultTitle" :sub-title="publishResult?.resourceApplyError || publishResult?.routePath || ''">
        <template #extra>
          <el-button v-if="generationQueryPending" :loading="publishing" @click="retryGenerationQuery">查询生成结果</el-button>
          <el-button v-if="!isPluginTarget && publishResult?.routePath" type="primary" @click="openGeneratedRoute">打开独立页面</el-button>
        </template>
      </el-result>

      <template #footer>
        <el-button @click="publishVisible = false">关闭</el-button>
        <el-button v-if="publishStep === 2" @click="publishStep = 1">上一步</el-button>
        <el-button v-if="publishStep === 0" type="primary" :loading="previewingPublish" @click="onPreviewPublish">预览发布</el-button>
        <el-button v-else-if="publishStep === 1" type="primary" @click="publishStep = 2">下一步</el-button>
        <el-button v-else-if="publishStep === 2" v-perm="'development:business:apply-resources'" type="primary" :loading="publishing" :disabled="!canConfirmGeneration" @click="onPublish">确认生成</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="previewSettingsVisible" title="预览数据与服务端错误" width="680px">
      <el-form label-width="120px">
        <el-form-item label="初始值 JSON"><el-input v-model="previewValuesJson" type="textarea" :rows="8" /></el-form-item>
        <el-form-item label="字段错误 JSON"><el-input v-model="previewErrorsJson" type="textarea" :rows="6" placeholder='{"field":"服务端错误"}' /></el-form-item>
      </el-form>
      <template #footer><el-button @click="previewSettingsVisible = false">取消</el-button><el-button type="primary" @click="applyPreviewSettings">应用预览</el-button></template>
    </el-dialog>


  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onActivated, onBeforeUnmount, onDeactivated, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import Sortable from 'sortablejs';
import type { FormPublishConfig } from '@/api/form';
import { businessDevelopmentApi, isBusinessApiError, type BusinessModule, type BusinessDatabaseTable, type BusinessFormalGenerationPreview, type BusinessFormalGenerationResult, type BusinessGeneration } from '@/api/development/business';
import { CONTROL_REGISTRY, controlMeta } from '../registry';
import { controlIcon, paletteContainers } from './controlPalette';
import { useDesigner } from '../composables/useDesigner';
import { pluginCatalog } from './pluginCatalog';
import { loadPluginFormComponents } from '../schema/pluginComponentLoader';
import SchemaRenderer from '../components/SchemaRenderer.vue';
import DesignerCanvas from './components/DesignerCanvas.vue';
import PropsPanel from './components/PropsPanel.vue';
import ListConfigurationPanel from './components/ListConfigurationPanel.vue';
import { useUserStore } from '@/store/modules/user';
import SchemaNodeTree from './components/SchemaNodeTree.vue';
import { useBusinessMenuRefresh } from '../../development/business/composables/useBusinessMenuRefresh';
import GenerationPlanView from '../../development/business/components/GenerationPlanView.vue';

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
const activeTab = ref<'basic' | 'design' | 'buttons' | 'list'>('basic');
const workspaceMode = ref<'edit' | 'desktop' | 'tablet' | 'mobile'>('edit');
// 大纲仅为临时视图状态，不进入草稿与自动保存通道。
const outlineVisible = ref(false);
watch(workspaceMode, (mode) => {
  activeTab.value = 'design';
  if (mode !== 'edit') outlineVisible.value = false;
});
watch(activeTab, (tab) => { if (tab !== 'design') outlineVisible.value = false; });
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const previewMode = ref<'create' | 'edit' | 'readonly' | 'search'>('create');
const previewSettingsVisible = ref(false);
const previewValuesJson = ref('{}');
const previewErrorsJson = ref('{}');
const previewRenderer = ref<{ setFieldErrors: (errors: Record<string, string>) => Promise<void> }>();
const publishVisible = ref(false);
const publishStep = ref(0);
const previewingPublish = ref(false);
const publishing = ref(false);
const publishPreview = ref<BusinessFormalGenerationPreview | null>(null);
const publishResult = ref<BusinessFormalGenerationResult | null>(null);
const generationQueryPending = ref(false);
const executingGenerationId = ref<number | null>(null);
const formalGenerationNonce = ref(crypto.randomUUID());
const conflictFiles = computed(() => publishPreview.value?.conflicts ?? []);
const generationResultTitle = computed(() => publishResult.value?.state !== 'completed' ? '正式模块生成未完成' : isPluginTarget.value ? '源码已生成，待安装／更新发布' : '正式模块生成成功');
const canConfirmGeneration = computed(() => Boolean(publishPreview.value?.sensitive?.confirmToken && !publishPreview.value.plan.blocked && !conflictFiles.value.length && !store.dirty.value && !saveBlocked.value && !publishing.value));
let previewRevision = 0;
const invalidateGenerationPreview = () => {
  previewRevision++;
  publishPreview.value = null;
  formalGenerationNonce.value = crypto.randomUUID();
  if (!publishing.value) { publishVisible.value = false; publishResult.value = null; }
};
watch([() => store.schemaDocument.value, () => store.form.value.schema_hash, businessTarget, moduleId], invalidateGenerationPreview, { deep: true, flush: 'sync' });
const dataScopeFields = computed(() => store.fields.value.filter((field) => controlMeta(field.type).kind !== 'layout'));
const parentMenus = ref<Array<Record<string, unknown>>>([]);
const databaseTables = ref<BusinessDatabaseTable[]>([]);
const tableLoading = ref(false);
const tableLoadError = ref('');
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
watch(() => store.fields.value.map((field) => [field.field_name, field.default_value] as const), (fields) => {
  const fieldNames = new Set(fields.map(([name]) => name));
  for (const [name, defaultValue] of fields) if (!(name in previewValues)) previewValues[name] = defaultValue;
  for (const key of Object.keys(previewValues)) if (!fieldNames.has(key)) delete previewValues[key];
});
const designerControls = computed(() => [...CONTROL_REGISTRY, ...pluginCatalog.controls.value]);
const catalogDiagnostics = computed(() => pluginCatalog.fieldDiagnostics(store.fields.value));
let paletteSortables: Sortable[] = [];
const initializePalette = () => {
  paletteSortables.forEach((sortable) => sortable.destroy());
  paletteSortables = [];
  if (!paletteRef.value) return;
  paletteSortables = paletteContainers(paletteRef.value).map((container) => Sortable.create(container, {
    group: { name: 'form-designer', pull: 'clone', put: false },
    draggable: '.palette-item',
    sort: false,
    animation: 150
  }));
};
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;
let localDraftTimer: ReturnType<typeof setTimeout> | null = null;
let saveInFlight = false;
const saveBlocked = ref(false);
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
const restoreLocalDraft = () => {
  if (!designerActive || !localDraftRestorePending) return;
  localDraftRestorePending = false;
  if (typeof localStorage === 'undefined') return;
  const raw = localStorage.getItem(localDraftKey.value);
  if (!raw) return;
  try {
    const draft = JSON.parse(raw) as { definition?: import('@/api/form').FormDefinition; saveBlocked?: boolean };
    if (draft.definition?.schema_document?.schemaVersion === 2 && window.confirm('检测到未同步的本地表单草稿，是否恢复？')) {
      const loadedHash = store.form.value.schema_hash;
      // 历史草稿不是服务端基线；版本不明时保留内容并暂停，禁止换 hash 盲目覆盖。
      saveBlocked.value = draft.saveBlocked === true || !loadedHash || draft.definition.schema_hash !== loadedHash;
      store.load({ ...draft.definition, schema_hash: loadedHash });
      store.updateForm({ schema_origin: 'designer' });
      if (saveBlocked.value) {
        store.failSave();
        ElMessage.warning('本地草稿已恢复，保存仍暂停，请点击“核对版本”选择恢复方式');
      }
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
const formatDebug = (value: unknown) => JSON.stringify(value, null, 2);
const saveStatusLabel = computed(() => ({
  unsaved: t('formDesigner.unsaved', '未保存'),
  saving: t('formDesigner.saving', '保存中'),
  failed: t('formDesigner.saveFailed', '保存失败'),
  saved: t('formDesigner.saved', '已保存')
}[store.saveStatus.value]));
const saveStatusType = computed(() => ({ unsaved: 'warning', saving: 'info', failed: 'danger', saved: 'success' } as const)[store.saveStatus.value]);
const controlGroups = computed(() => [...new Set(designerControls.value.map((control) => control.group))]);
const controlsOf = (group: string) => designerControls.value.filter((control) => control.group === group);
const normalizeIdentifier = (value: string) => value
  .trim()
  .toLowerCase()
  .replace(/[\s-]+/g, '_')
  .replace(/[^a-z0-9_]/g, '')
  .replace(/^_+|_+$/g, '')
  .slice(0, 61);
const suggestedTableName = computed(() => {
  const key = normalizeIdentifier(String(store.form.value.form_key ?? ''));
  return String(store.form.value.table_name ?? '') || key;
});
const tableHelp = computed(() => isPluginTarget.value
  ? (businessTarget.value?.tableStrategy === 'external' ? '外部依赖表：不生成 CREATE／ALTER，不取得表所有权。' : '保存只更新草稿；生成只写源码与迁移，安装／更新时建表。')
  : store.form.value.source_type === 'created'
  ? `新表将在保存或发布时按画布字段创建；建议表名：${suggestedTableName.value || '业务标识'}`
  : '仅可选择数据库中已存在的表，系统会读取其字段、主键和索引。');
const tableLabel = (table: BusinessDatabaseTable) => table.comment ? `${table.name}（${table.comment}）` : table.name;
const loadDatabaseTables = async (force = false) => {
  if (tableLoading.value || (!force && databaseTables.value.length)) return;
  tableLoading.value = true;
  tableLoadError.value = '';
  try {
    databaseTables.value = await businessDevelopmentApi.databaseTables(String(store.form.value.connection ?? 'mysql'));
  } catch (error) {
    tableLoadError.value = error instanceof Error ? error.message : '数据表加载失败';
  } finally {
    tableLoading.value = false;
  }
};
const onTableSelectVisible = (visible: boolean) => { if (visible) void loadDatabaseTables(); };
const updateBoundTable = (value: string) => store.updateForm({ table_name: value });
const updateSourceType = (sourceType: string | number | boolean | undefined) => {
  if (sourceType !== 'created' && sourceType !== 'adopted') return;
  const patch: Record<string, unknown> = { source_type: sourceType };
  if (sourceType === 'created' && !String(store.form.value.table_name ?? '').trim()) patch.table_name = suggestedTableName.value;
  if (sourceType === 'adopted' && !databaseTables.value.some((table) => table.name === store.form.value.table_name)) patch.table_name = '';
  store.updateForm(patch);
  if (sourceType === 'adopted') void loadDatabaseTables();
};
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
    await router.replace('/development/business/mine');
    return;
  }
  const data = await businessDevelopmentApi.module(moduleId.value);
  if (!data.form) throw new Error('业务模块没有可设计表单');
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
      throw new Error('服务端文档或版本 hash 缺失，无法安全恢复，请重新核对');
    }
    conflictReview.value = { local, server: JSON.parse(JSON.stringify({ ...data.form, fields: data.fields })), moduleId: reviewedModuleId };
  } catch (error) {
    conflictReviewError.value = error instanceof Error ? error.message : '版本读取失败，请重新核对';
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
  const reviewed = conflictReview.value;
  if (!designerActive || !saveBlocked.value || !reviewed || conflictReviewLoading.value || conflictResolving.value || saveInFlight) return;
  const unchanged = () => moduleId.value === reviewed.moduleId && JSON.stringify(definition()) === JSON.stringify(reviewed.local);
  if (!unchanged()) {
    conflictReview.value = null;
    conflictReviewError.value = '本地内容已变化，请重新核对版本后再选择';
    persistLocalDraft();
    return;
  }
  const prompt = choice === 'local'
    ? '确认以已核对的本地快照覆盖此服务端版本？服务端再次变化时将拒绝保存。'
    : '确认放弃本地全部未保存编辑，采用已核对的服务端版本？';
  if (!window.confirm(prompt) || !unchanged()) return;
  conflictResolving.value = true;
  saveInFlight = true;
  conflictReviewError.value = '';
  try {
    if (choice === 'local') {
      store.beginSave();
      const saved = await businessDevelopmentApi.saveSchema(reviewed.moduleId, reviewed.local.schema_document, String(reviewed.server.schema_hash), '核对冲突后采用本地');
      if (!unchanged()) {
        // 只推进已成功的版本；请求期间的新编辑必须重新核对，不能排队自动覆盖。
        store.acknowledgeSave(saved.schemaHash);
        store.failSave();
        conflictReviewError.value = '核对快照已保存，但本地又有编辑，保存仍暂停，请重新核对版本';
        return;
      }
      store.markSaved({ ...reviewed.local, schema_document: saved.document, schema_hash: saved.schemaHash } as import('@/api/form').FormDefinition);
    } else {
      store.load(reviewed.server);
      publishConfig.value = { ...publishConfig.value, ...reviewed.server.publish_config };
    }
    saveBlocked.value = false;
    if (localDraftTimer) clearTimeout(localDraftTimer);
    localDraftTimer = null;
    clearLocalDraft();
    conflictReviewVisible.value = false;
    ElMessage.success('冲突已处理，后续编辑将继续自动保存');
  } catch (error) {
    store.failSave();
    conflictReviewError.value = isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT'
      ? '服务端版本再次变化，保存仍暂停，请重新核对版本；本地编辑已保留'
      : error instanceof Error ? error.message : '恢复失败，保存仍暂停，请重新核对版本';
  } finally {
    conflictReview.value = null;
    conflictResolving.value = false;
    saveInFlight = false;
    saveQueued = false;
    if (saveBlocked.value) persistLocalDraft();
  }
}

async function saveDefinition(automatic: boolean) {
  if (!designerActive || saveBlocked.value || !store.dirty.value || (automatic && !online.value)) return;
  if (saveInFlight) { saveQueued = true; return; }
  if (!validateDefinitionBasics(automatic)) return;
  const expectedHash = String(store.form.value.schema_hash ?? '');
  if (!moduleId.value || !expectedHash) {
    store.failSave();
    if (!automatic) ElMessage.warning('当前表单版本信息缺失，请刷新页面后重试');
    return;
  }
  saveInFlight = true;
  const payload = definition();
  const payloadHash = JSON.stringify(payload);
  store.beginSave();
  try {
    const saved = await businessDevelopmentApi.saveSchema(moduleId.value, store.schemaDocument.value, expectedHash, '业务设计器保存');
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
    if (isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT') {
      saveBlocked.value = true;
      saveQueued = false;
      if (autoSaveTimer) clearTimeout(autoSaveTimer);
      autoSaveTimer = null;
      persistLocalDraft();
    }
    if (!designerActive) return;
    if (isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT') {
      ElMessage.warning('Schema 版本已变化，保存已暂停，请点击“核对版本”选择恢复方式');
    } else if (isBusinessApiError(error)) {
      ElMessage.error(`${error.msg}（请求 ID：${error.data.error.requestId}）`);
    } else {
      ElMessage.error(error instanceof Error ? error.message : t('formDesigner.saveError', '保存失败，请重试'));
    }
  } finally {
    saveInFlight = false;
    if (saveQueued && !saveBlocked.value && designerActive && online.value) scheduleAutoSave(500);
    saveQueued = false;
  }
}

const onDynamicPublish = async () => {
  if (!businessModule.value || isPluginTarget.value || saveBlocked.value || !validateDefinitionBasics() || store.dirty.value) return;
  const moduleId = instanceModuleId;
  const schemaHash = String(store.form.value.schema_hash ?? '');
  if (!moduleId || !schemaHash) throw new Error('业务模块或 Schema hash 缺失');
  const payload = { schema_document: store.schemaDocument.value, schemaHash, expected_schema_hash: schemaHash, publish_config: publishConfig.value };
  const previewResult = await businessDevelopmentApi.previewPublish(moduleId, payload);
  await businessDevelopmentApi.publish(moduleId, { ...payload, formDependencyHash: previewResult.formDependencyHash });
  ElMessage.success('动态发布成功');
};
const openFormalGeneration = async () => {
  const moduleId = instanceModuleId;
  if (!moduleId || !businessModule.value || store.dirty.value || saveBlocked.value || saveInFlight || previewingPublish.value || publishing.value) { ElMessage.warning('请先保存业务 Schema'); return; }
  invalidateGenerationPreview();
  const revision = previewRevision;
  previewingPublish.value = true;
  generationQueryPending.value = false;
  publishResult.value = null;
  try {
    const preview = await businessDevelopmentApi.previewFormalGeneration(moduleId, formalGenerationNonce.value);
    if (!designerActive || revision !== previewRevision || store.dirty.value) return;
    publishPreview.value = preview;
    publishStep.value = 1;
    publishVisible.value = true;
  } catch (error) {
    ElMessage.error(isBusinessApiError(error) ? error.msg : error instanceof Error ? error.message : '生成预览失败');
  } finally { previewingPublish.value = false; }
};
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
  publishStep.value = 0;
  publishPreview.value = null;
  publishResult.value = null;
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
const onPreviewPublish = () => openFormalGeneration();
const completeFormalGeneration = async (result: BusinessFormalGenerationResult) => {
  publishResult.value = result;
  generationQueryPending.value = false;
  publishStep.value = 3;
  if (result.state !== 'completed') {
    ElMessage.warning('正式模块生成未完成');
    return;
  }
  if (isPluginTarget.value) {
    if (businessTarget.value) businessTarget.value.locked = true;
    publishResult.value = result;
    publishVisible.value = true;
    ElMessage.success('源码已生成，待安装／更新发布');
    return;
  }
  try {
    await refreshBusinessMenu();
    ElMessage.success('正式模块生成成功');
  } catch {
    ElMessage.warning('生成成功，菜单刷新失败');
  }
};
const generationResult = (generation: BusinessGeneration): BusinessFormalGenerationResult => ({
  generationId: generation.id,
  state: String(generation.result?.state ?? generation.status),
  resourceApplyStatus: String(generation.result?.resourceApplyStatus ?? ''),
  resourceApplyError: generation.result?.resourceApplyError,
  routePath: generation.result?.routePath ?? generation.routePath,
  definitionHash: generation.result?.definitionHash ?? generation.definitionHash,
  schemaHash: generation.result?.schemaHash ?? generation.schemaHash
});
const retryGenerationQuery = async () => {
  const generationId = executingGenerationId.value;
  if (!generationId) return;
  publishing.value = true;
  try {
    const generation = await businessDevelopmentApi.generation(generationId);
    if (generation.status === 'completed' && generation.result) {
      await completeFormalGeneration(generationResult(generation));
      return;
    }
    generationQueryPending.value = true;
    publishStep.value = 3;
    ElMessage.warning('生成状态尚未确认，请稍后重试查询');
  } catch {
    generationQueryPending.value = true;
    publishStep.value = 3;
    ElMessage.warning('生成状态查询失败，请重试查询');
  } finally {
    publishing.value = false;
  }
};
const onPublish = async () => {
  if (!canConfirmGeneration.value) return;
  publishing.value = true;
  const formId = Number(store.form.value.id || 0);
  const generationId = Number(publishPreview.value?.generationId || 0);
  executingGenerationId.value = generationId || null;
  try {
    if (!formId || !generationId) throw new Error('完整发布缺少 formId 或 generationId');
    const moduleId = instanceModuleId;
    const result = await businessDevelopmentApi.formalGeneration(
      moduleId,
      generationId,
      publishPreview.value?.sensitive?.confirmToken || ''
    );
    await completeFormalGeneration(result);
  } catch (error) {
    if (isBusinessApiError(error)) {
      ElMessage.error(`${error.msg}（请求 ID：${error.data.error.requestId}）`);
      invalidateGenerationPreview();
    } else if (generationId) await retryGenerationQuery();
    else throw new Error('完整发布缺少 formId 或 generationId');
  } finally {
    publishing.value = false;
  }
};
const openGeneratedRoute = () => {
  if (!isPluginTarget.value && publishResult.value?.routePath) router.push(publishResult.value.routePath);
};

const beforeUnload = (event: BeforeUnloadEvent) => {
  if (!designerActive || !store.dirty.value) return;
  persistLocalDraft();
  event.preventDefault();
  event.returnValue = '';
};
onBeforeRouteLeave(() => !store.dirty.value || window.confirm('当前表单尚未保存，确认离开吗？'));

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
  outlineVisible.value = false;
  if (store.dirty.value) persistLocalDraft();
  designerActive = false;
  invalidateGenerationPreview();
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
  restoreLocalDraft();
  if (store.dirty.value) {
    scheduleLocalDraft();
    scheduleAutoSave();
  }
});
onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnload);
  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);
  initializePalette();
  await Promise.allSettled([loadPluginFormComponents()]);
  await load();
  localDraftRestorePending = true;
  restoreLocalDraft();
});
onBeforeUnmount(() => {
  deactivateDesigner();
  paletteSortables.forEach((sortable) => sortable.destroy());
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
:deep(.designer-toolbar .el-radio-button__inner),
:deep(.designer-toolbar .el-select__wrapper),
:deep(.designer-toolbar .el-tag) {
  height: 32px;
  min-height: 32px;
}
:deep(.designer-toolbar .el-tag) {
  font-size: 14px;
  padding: 0 15px;
}
.publish-config-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(280px, 1fr));
  column-gap: 24px;
}
.designer-meta-form {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  max-width: 760px;
  width: 100%;
}
.designer-meta-form :deep(.el-form-item__content) {
  min-width: 0;
}
.form-tip {
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 18px;
}
.designer-layout {
  align-items: flex-start;
}
.designer-canvas-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.designer-layout.is-preview {
  display: block;
}
.designer-layout.is-preview > :deep(.el-card) {
  width: 100%;
}
.designer-command-bar {
  position: sticky;
  top: 0;
  z-index: 20;
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
.control-palette { width: 330px; }
.palette-group-title { position: sticky; top: 0; z-index: 10; padding: 6px 2px; background: var(--el-bg-color-overlay); color: var(--el-text-color-secondary); font-size: 12px; font-weight: 600; }
.palette-group-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.palette-item { display: flex; min-height: 58px; align-items: center; flex-direction: column; justify-content: center; gap: 5px; text-align: center; }
.palette-item > i { font-size: 20px; line-height: 1; }
.palette-item > span { overflow: hidden; max-width: 100%; text-overflow: ellipsis; white-space: nowrap; }
.palette-item:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
.palette-list {
  scrollbar-width: thin;
}
</style>
