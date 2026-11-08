<template>
  <el-dialog
    :model-value="modelValue"
    :fullscreen="fullscreen"
    width="min(1280px, 96vw)"
    top="4vh"
    class="codegen-preview-dialog"
    :class="{ 'is-fullscreen': fullscreen }"
    destroy-on-close
    @update:model-value="setVisible"
  >
    <template #header>
      <div class="codegen-preview__header">
        <h3 class="codegen-preview__title">{{ t('business.codegen.preview.title', { name: moduleTitle }, '预览代码 — {name}') }}</h3>
        <el-tag
          v-for="item in statusCounts"
          :key="item.status"
          :data-status-count="item.status"
          :type="tagType(item.status)"
          effect="light"
          size="small"
        >{{ statusLabel(item.status) }} {{ item.count }}</el-tag>
        <span class="codegen-preview__spacer" />
        <el-button
          v-if="canDownload"
          data-action="download"
          size="small"
          plain
          type="primary"
          :icon="Download"
          :loading="downloading"
          :disabled="!preview"
          @click="downloadArchive"
        >{{ t('business.codegen.preview.download', '下载 zip') }}</el-button>
        <el-button
          data-action="fullscreen"
          size="small"
          text
          :icon="fullscreen ? Aim : FullScreen"
          :aria-pressed="fullscreen"
          @click="fullscreen = !fullscreen"
        >{{ fullscreen ? t('business.codegen.preview.exitFullscreen', '退出全屏') : t('business.codegen.preview.fullscreen', '全屏') }}</el-button>
      </div>
    </template>

    <div class="codegen-preview" :aria-busy="loading">
      <el-alert
        v-if="stalePublished"
        data-preview-stale
        class="codegen-preview__stale"
        :title="t('business.codegen.preview.stalePublished', '预览基于已发布的版本；已保存但未发布的字段改动需要先「动态发布」后才会出现在生成代码里。')"
        type="info"
        show-icon
        :closable="false"
      >
        <el-button
          v-if="canDynamicPublish"
          data-action="stale-dynamic-publish"
          size="small"
          type="primary"
          plain
          :loading="publishing"
          :disabled="busy && !publishing"
          @click="dynamicPublish"
        >{{ t('business.codegen.preview.dynamicPublish', '动态发布（不写文件）') }}</el-button>
      </el-alert>
      <div v-if="loading && !preview" class="codegen-preview__state" data-preview-loading>{{ t('business.codegen.preview.loading', '正在生成预览…') }}</div>
      <el-alert
        v-else-if="notPublished && !preview"
        data-preview-unpublished
        :title="isPluginTarget
          ? t('business.codegen.preview.notPublishedPlugin', '请先在表单设计器保存并发布后再预览。')
          : t('business.codegen.preview.notPublished', '这张表还没有发布过。正式生成使用已发布的版本：请先点击下方「动态发布」发布一次，再预览或生成代码。')"
        type="info"
        show-icon
        :closable="false"
      />
      <el-alert v-else-if="loadError && !preview" :title="loadError" type="error" show-icon :closable="false" />
      <div v-else-if="preview && !files.length" class="codegen-preview__state">{{ t('business.codegen.preview.noFiles', '没有需要生成的文件') }}</div>
      <div v-else-if="preview" class="codegen-preview__layout">
        <nav class="codegen-preview__tree" :aria-label="t('business.codegen.preview.treeAria', '生成文件')">
          <section v-for="group in groups" :key="group.group" :data-file-group="group.group" class="codegen-preview__group">
            <h4 class="codegen-preview__group-title">{{ groupLabel(group.group) }}</h4>
            <button
              v-for="file in group.files"
              :key="file.path"
              type="button"
              class="codegen-preview__file"
              :class="{ 'is-active': file.path === activePath }"
              :data-file="file.path"
              :title="`${file.path} · ${statusLabel(file.status)}`"
              :aria-current="file.path === activePath ? 'true' : undefined"
              @click="openFile(file.path)"
            >
              <i class="codegen-preview__dot" :data-status-dot="file.status" :style="{ background: toneColor(file.status) }" aria-hidden="true" />
              <span class="codegen-preview__file-name">{{ fileLabel(file.path, group.files) }}</span>
              <span class="sr-only">{{ statusLabel(file.status) }}</span>
            </button>
          </section>
        </nav>

        <section class="codegen-preview__main">
          <div class="codegen-preview__tabs" role="tablist" :aria-label="t('business.codegen.preview.tabsAria', '已打开的文件')">
            <div v-for="path in openTabs" :key="path" class="codegen-preview__tab" :class="{ 'is-active': path === activePath }">
              <button
                type="button"
                role="tab"
                class="codegen-preview__tab-label"
                :data-file-tab="path"
                :aria-selected="path === activePath"
                :title="path"
                @click="activePath = path"
              >{{ tabLabel(path) }}</button>
              <button
                v-if="openTabs.length > 1"
                type="button"
                class="codegen-preview__tab-close"
                :aria-label="t('business.codegen.preview.closeTab', { name: tabLabel(path) }, '关闭 {name}')"
                @click="closeTab(path)"
              >×</button>
            </div>
          </div>

          <template v-if="activeFile">
            <div class="codegen-preview__pathbar">
              <span class="codegen-preview__path" data-current-path>{{ activeFile.path }}</span>
              <span class="codegen-preview__spacer" />
              <div class="codegen-preview__segmented" role="radiogroup" :aria-label="t('business.codegen.preview.viewAria', '查看方式')">
                <button
                  v-for="option in viewOptions"
                  :key="option.value"
                  type="button"
                  role="radio"
                  :data-view="option.value"
                  :aria-checked="view === option.value"
                  :class="{ 'is-active': view === option.value }"
                  @click="view = option.value"
                >{{ option.label }}</button>
              </div>
              <el-button
                v-if="canAdoptActive"
                data-action="adopt-resolved"
                size="small"
                type="primary"
                plain
                :loading="adopting"
                :disabled="busy && !adopting"
                @click="adoptResolved"
              >{{ t('business.codegen.preview.adoptResolved', '采纳已解决') }}</el-button>
              <el-button size="small" text :icon="DocumentCopy" data-action="copy-path" @click="copyPath">{{ t('business.codegen.preview.copyPath', '复制路径') }}</el-button>
            </div>
            <p v-if="adoptHintVisible" class="codegen-preview__adopt-hint" data-adopt-hint>
              {{ t('business.codegen.preview.adoptResolvedHint', '该文件没有生成基线：在本地编辑器中把它改成与生成内容一致后点击「重新预览」，即可「采纳已解决」。') }}
            </p>

            <div class="codegen-preview__content">
              <div v-if="contentHint" class="codegen-preview__state" data-content-hint>{{ contentHint }}</div>
              <GenerationFileDiff v-else-if="view === 'diff'" :key="activeFile.path" :file="activeFile" class="codegen-preview__diff" />
              <CodeBlock v-else :code="activeFile.remoteContent ?? ''" :language="fileLanguage(activeFile.path)" class="codegen-preview__code" />
            </div>
          </template>
        </section>
      </div>
      <el-collapse v-if="preview" class="codegen-preview__baseline" data-generation-baseline>
        <el-collapse-item :title="t('business.codegen.preview.baseline', '生成基线')" name="baseline">
          <dl class="codegen-preview__baseline-list">
            <div data-baseline="schemaHash"><dt>Schema Hash</dt><dd><code>{{ preview.schemaHash || '—' }}</code></dd></div>
            <div data-baseline="definitionHash"><dt>Definition Hash</dt><dd><code>{{ preview.definitionHash || '—' }}</code></dd></div>
          </dl>
        </el-collapse-item>
      </el-collapse>
    </div>

    <template #footer>
      <div class="codegen-preview__footer">
        <p v-if="applyError" class="codegen-preview__hint codegen-preview__full is-danger" role="alert" data-resource-apply-error>
          {{ t('business.codegen.preview.resourceApplyError', { error: applyError }, '资源应用失败：{error}。源码已写入，请到生成记录重试资源应用。') }}
        </p>
        <dl v-if="preview && publishSummary" class="codegen-preview__summary codegen-preview__full" data-generate-summary :aria-label="t('business.codegen.preview.summaryAria', '生成后的菜单与接口')">
          <div v-for="item in publishSummary" :key="item.key" :data-summary="item.key"><dt>{{ item.label }}</dt><dd>{{ item.value }}</dd></div>
        </dl>
        <p v-if="blocked" class="codegen-preview__hint is-warning" data-blocked-hint>
          {{ conflictCount
            ? t('business.codegen.preview.conflictHint', { count: conflictCount }, '存在 {count} 个冲突文件，暂不能生成：请在「与本地对比」里查看，在本地编辑器中解决后点击「重新预览」。系统不会覆盖本地修改。')
            : t('business.codegen.preview.blockedHint', '当前生成计划被阻断，暂不能生成；处理后请点击「重新预览」。') }}
        </p>
        <p v-else-if="loadError && preview" class="codegen-preview__hint is-danger">{{ loadError }}</p>
        <span v-else class="codegen-preview__spacer" />
        <el-button @click="setVisible(false)">{{ t('business.codegen.preview.close', '关闭') }}</el-button>
        <el-button data-action="repreview" plain :icon="Refresh" :loading="loading" :disabled="busy && !loading" @click="loadPreview">{{ t('business.codegen.preview.repreview', '重新预览') }}</el-button>
        <el-button
          v-if="canDynamicPublish"
          data-action="dynamic-publish"
          type="primary"
          plain
          :loading="publishing"
          :disabled="(!preview && !notPublished) || (busy && !publishing)"
          @click="dynamicPublish"
        >{{ t('business.codegen.preview.dynamicPublish', '动态发布（不写文件）') }}</el-button>
        <el-button
          v-if="canGenerateToProject"
          data-action="generate"
          type="primary"
          :loading="generating"
          :disabled="!canConfirmGeneration"
          @click="generate"
        >{{ t('business.codegen.preview.generate', '生成到项目') }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox, type TagProps } from 'element-plus';
import { Aim, DocumentCopy, Download, FullScreen, Refresh } from '@element-plus/icons-vue';

import type { FormPublishConfig } from '@/api/form';
import {
  businessDevelopmentApi,
  isBusinessApiError,
  type BusinessFormalGenerationPreview,
  type BusinessFormalGenerationResult,
  type BusinessGenerationFile,
  type BusinessModule
} from '@/api/development/business';
import { hasPermission } from '@/utils/permission';
import GenerationFileDiff from '../components/GenerationFileDiff.vue';
import { FILE_STATUS_META, type BusinessStatusTone, type GenerationFileStatus } from '../constants';
import { useBusinessMenuRefresh } from '../composables/useBusinessMenuRefresh';
import CodeBlock from './CodeBlock.vue';
import { countFileStatuses, fileLabel, fileLanguage, groupPreviewFiles, type PreviewFileGroup } from './previewFiles';
import { PERM_GENERATE, PERM_PREVIEW, PERMS_FORMAL_GENERATION, confirmFormalGeneration, isFormNotPublishedError, isPreviewBlocked, waitForBackendReload } from './formalGeneration';
import { publishConfigOf } from './generationConfig';

const props = defineProps<{ modelValue: boolean; module: BusinessModule | null }>();
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  generated: [result: BusinessFormalGenerationResult];
  published: [];
}>();

const { t } = useI18n();
const { refreshBusinessMenu } = useBusinessMenuRefresh();

const PERMS_DYNAMIC_PUBLISH = ['development:business:preview-publish', 'development:business:publish'];
const PERM_ARCHIVE = 'development:business:formal-generation-archive';
const PERM_ADOPT_RESOLVED = 'development:business:adopt-resolved-baseline';

type PreviewView = 'remote' | 'diff';

const preview = ref<BusinessFormalGenerationPreview | null>(null);
const loading = ref(false);
const loadError = ref('');
/** 预览因表单从未发布而被拒绝：显示说明并允许直接「动态发布」（动态发布不依赖预览结果）。 */
const notPublished = ref(false);
const generating = ref(false);
const publishing = ref(false);
const downloading = ref(false);
const adopting = ref(false);
/** 当前预览使用的 nonce：下载 zip 复用它，服务端复用同一生成记录而不是 supersede 当前预览。 */
const previewNonce = ref('');
/** 已保存（草稿）与已发布的 Schema hash：核心目标预览读取已发布版本，二者不同时提示需先动态发布。 */
const savedSchemaHash = ref('');
const publishedSchemaHash = ref('');
const fullscreen = ref(false);
const view = ref<PreviewView>('remote');
const activePath = ref('');
const openTabs = ref<string[]>([]);
/** 生成后资源（菜单/权限）应用失败的原因，partial 结果时由服务端返回。 */
const applyError = ref('');
/** 按正式生成同一套默认值解析后的发布设置：确认生成前展示菜单与会员端 API 摘要。 */
const publishConfig = ref<FormPublishConfig | null>(null);
let revision = 0;

const isPluginTarget = computed(() => props.module?.metadata?.target?.type === 'plugin');
const moduleTitle = computed(() => props.module?.table_name || props.module?.code || '');
const files = computed<BusinessGenerationFile[]>(() => preview.value?.plan.files ?? []);
const groups = computed(() => groupPreviewFiles(files.value));
const statusCounts = computed(() => countFileStatuses(files.value));
const conflictCount = computed(() => preview.value?.conflicts.length ?? 0);
const blocked = computed(() => Boolean(preview.value && isPreviewBlocked(preview.value)));
const activeFile = computed(() => files.value.find((file) => file.path === activePath.value) ?? null);
const busy = computed(() => loading.value || generating.value || publishing.value || adopting.value);

// 文件内容只有持有生成权限时才由服务端返回；与下载 zip 同一门槛。
const canViewContent = computed(() => hasPermission(PERM_GENERATE));
const canDownload = computed(() => hasPermission([PERM_GENERATE, PERM_ARCHIVE], 'all'));
const canGenerateToProject = computed(() => hasPermission(PERMS_FORMAL_GENERATION, 'all'));
const canDynamicPublish = computed(() => Boolean(props.module) && !isPluginTarget.value && hasPermission(PERMS_DYNAMIC_PUBLISH, 'all'));
/** 核心目标正式生成读取已发布 Schema（插件目标读取草稿）：已保存版本与预览所用版本不同时提示先动态发布。 */
const stalePublished = computed(() => {
  if (isPluginTarget.value || !preview.value || !savedSchemaHash.value) return false;
  const basis = preview.value.schemaHash || publishedSchemaHash.value;
  return basis !== savedSchemaHash.value;
});
/** conflict-no-base 只能在本地已改成生成内容（Local hash = Remote hash）后采纳为基线，与服务端校验一致。 */
const activeNoBase = computed(() => view.value === 'diff' && canViewContent.value && activeFile.value?.status === 'conflict-no-base');
const activeResolved = computed(() => {
  const file = activeFile.value;
  return Boolean(file?.localHash && file.remoteHash && file.localHash === file.remoteHash);
});
const canAdoptActive = computed(() => activeNoBase.value && activeResolved.value && hasPermission(PERM_ADOPT_RESOLVED) && Boolean(preview.value?.generationId));
const adoptHintVisible = computed(() => activeNoBase.value && !activeResolved.value);
const canConfirmGeneration = computed(() => Boolean(
  preview.value?.sensitive?.confirmToken && preview.value.generationId && !blocked.value && !busy.value
));

const publishSummary = computed(() => {
  const config = publishConfig.value;
  if (!config) return null;
  const notFilled = t('business.codegen.preview.notFilled', '（未填写）');
  // 关闭「生成菜单」时 FormCrudDefinitionFactory 以 enabled=false 跳过菜单，父级与图标不再适用。
  const menu = config.menuEnabled === false
    ? [{ key: 'menuName', label: t('business.codegen.preview.summaryMenuName', '菜单名称'), value: t('business.codegen.preview.menuDisabled', '不生成菜单') }]
    : [
      { key: 'menuName', label: t('business.codegen.preview.summaryMenuName', '菜单名称'), value: config.menuName || notFilled },
      { key: 'parentMenu', label: t('business.codegen.preview.summaryParentMenu', '父级菜单'), value: config.parentSourceName || t('business.codegen.preview.topMenu', '顶级菜单') },
      { key: 'icon', label: t('business.codegen.preview.summaryIcon', '菜单图标'), value: config.icon || notFilled }
    ];
  return [
    ...menu,
    { key: 'memberApi', label: t('business.codegen.preview.summaryMemberApi', '会员端 API'), value: config.memberApiEnabled ? t('business.codegen.preview.memberApiOn', { field: config.memberApiOwnerField ?? '' }, '已启用（{field}）') : t('business.codegen.preview.memberApiOff', '未启用') }
  ];
});

const viewOptions = computed(() => [
  { value: 'remote' as const, label: t('business.codegen.preview.viewRemote', '生成内容') },
  { value: 'diff' as const, label: t('business.codegen.preview.viewDiff', '与本地对比') }
]);

const contentHint = computed(() => {
  const file = activeFile.value;
  if (!file) return '';
  if (!canViewContent.value) return t('business.codegen.preview.noPermission', '需要生成权限才能查看内容');
  if (view.value === 'diff') return '';
  if (file.status === 'delete') return t('business.codegen.preview.deleted', '该文件将被删除，没有生成内容');
  if (file.contentKind === 'binary') return t('business.codegen.preview.binary', '二进制文件，不提供预览');
  if (typeof file.remoteContent !== 'string') return t('business.codegen.preview.tooLarge', '文件过大，未提供预览');
  return '';
});

function setVisible(value: boolean) {
  emit('update:modelValue', value);
}

function statusMeta(status: string) {
  return FILE_STATUS_META[status as GenerationFileStatus];
}

function statusLabel(status: string) {
  const meta = statusMeta(status);
  return meta ? t(meta.labelKey) : status;
}

function tagType(status: string): TagProps['type'] {
  const tone = statusMeta(status)?.tone;
  return tone === 'primary' ? 'primary' : tone;
}

const TONE_COLORS: Record<BusinessStatusTone, string> = {
  primary: 'var(--el-color-primary)',
  success: 'var(--el-color-success)',
  warning: 'var(--el-color-warning)',
  danger: 'var(--el-color-danger)',
  info: 'var(--el-color-info)'
};

function toneColor(status: string) {
  return TONE_COLORS[statusMeta(status)?.tone ?? 'info'];
}

function groupLabel(group: PreviewFileGroup) {
  return ({
    backend: t('business.codegen.preview.groups.backend', '后端'),
    database: t('business.codegen.preview.groups.database', '数据库'),
    frontend: t('business.codegen.preview.groups.frontend', '前端'),
    lang: t('business.codegen.preview.groups.lang', '语言包'),
    test: t('business.codegen.preview.groups.test', '测试'),
    other: t('business.codegen.preview.groups.other', '其他')
  })[group];
}

function tabLabel(path: string) {
  const group = groups.value.find((item) => item.files.some((file) => file.path === path));
  return fileLabel(path, group?.files ?? []);
}

function openFile(path: string) {
  if (!openTabs.value.includes(path)) openTabs.value = [...openTabs.value, path];
  activePath.value = path;
}

function closeTab(path: string) {
  const index = openTabs.value.indexOf(path);
  if (index < 0 || openTabs.value.length <= 1) return;
  const next = openTabs.value.filter((item) => item !== path);
  openTabs.value = next;
  if (activePath.value === path) activePath.value = next[Math.min(index, next.length - 1)];
}

function errorMessage(error: unknown, fallback: string) {
  if (isBusinessApiError(error)) return error.data?.error?.requestId
    ? t('business.codegen.preview.requestError', { msg: error.msg, id: error.data.error.requestId }, '{msg}（请求 ID：{id}）')
    : error.msg;
  return error instanceof Error && error.message ? error.message : fallback;
}

/** 保留仍存在的已打开页签；默认选中第一个冲突文件，否则第一个文件。 */
function resetSelection(next: BusinessFormalGenerationPreview) {
  const paths = next.plan.files.map((file) => file.path);
  const keptTabs = openTabs.value.filter((path) => paths.includes(path));
  const firstConflict = next.conflicts[0]?.path;
  const ordered = groupPreviewFiles(next.plan.files).flatMap((group) => group.files.map((file) => file.path));
  const fallback = (firstConflict && paths.includes(firstConflict) ? firstConflict : ordered[0]) ?? '';
  const active = paths.includes(activePath.value) ? activePath.value : fallback;
  openTabs.value = active && !keptTabs.includes(active) ? [...keptTabs, active] : keptTabs;
  activePath.value = active;
}

async function loadPreview() {
  const module = props.module;
  if (!module || !hasPermission(PERM_PREVIEW)) return;
  const current = ++revision;
  loading.value = true;
  loadError.value = '';
  notPublished.value = false;
  void loadPublishConfig(module.id, current);
  try {
    const nonce = crypto.randomUUID();
    const result = await businessDevelopmentApi.previewFormalGeneration(module.id, nonce);
    if (current !== revision) return;
    preview.value = result;
    previewNonce.value = nonce;
    applyError.value = '';
    resetSelection(result);
  } catch (error) {
    if (current !== revision) return;
    notPublished.value = isFormNotPublishedError(error);
    loadError.value = errorMessage(error, t('business.codegen.preview.loadFailed', '生成预览失败'));
  } finally {
    if (current === revision) loading.value = false;
  }
}

// 摘要只作提示：读取失败不影响预览与生成。
async function loadPublishConfig(moduleId: number, current: number) {
  if (!hasPermission('development:business:module')) return;
  try {
    const detail = await businessDevelopmentApi.module(moduleId);
    const form = detail?.form;
    // 与编辑生成信息弹窗同一个 publishConfigOf：合并后端 FormCrudDefinitionFactory::config 的默认值，空菜单名回退表单名。
    if (current !== revision) return;
    publishConfig.value = form ? publishConfigOf({ ...form, name: form.schema_document?.title || form.name }) : null;
    savedSchemaHash.value = String(form?.schema_hash ?? '');
    publishedSchemaHash.value = String(form?.published_schema_hash ?? '');
  } catch {
    if (current === revision) {
      publishConfig.value = null;
      savedSchemaHash.value = '';
      publishedSchemaHash.value = '';
    }
  }
}

function reset() {
  revision++;
  preview.value = null;
  previewNonce.value = '';
  publishConfig.value = null;
  savedSchemaHash.value = '';
  publishedSchemaHash.value = '';
  applyError.value = '';
  loading.value = false;
  loadError.value = '';
  notPublished.value = false;
  view.value = 'remote';
  activePath.value = '';
  openTabs.value = [];
}

watch(
  () => [props.modelValue, props.module?.id] as const,
  ([visible, moduleId], previous) => {
    if (!visible) {
      revision++;
      return;
    }
    // 每次打开（或切换模块）都重新预览：本地文件与确认令牌可能已在关闭期间变化。
    if (!previous?.[0] || previous[1] !== moduleId) {
      reset();
      void loadPreview();
    }
  },
  { immediate: true }
);

async function completeGeneration(result: BusinessFormalGenerationResult) {
  emit('generated', result);
  applyError.value = result.resourceApplyError ? String(result.resourceApplyError) : '';
  if (result.state !== 'completed' || applyError.value) {
    ElMessage.warning(applyError.value
      ? t('business.codegen.preview.generateIncompleteWithError', { error: applyError.value }, '正式模块生成未完成：{error}，请到生成记录查看')
      : t('business.codegen.preview.generateIncomplete', '正式模块生成未完成，请到生成记录查看'));
    return;
  }
  if (isPluginTarget.value) {
    ElMessage.success(t('business.codegen.preview.generatedPendingInstall', '源码已生成，待安装／更新发布'));
  } else {
    // 新控制器的注解路由在 worker 重启后才注册：后端已请求重载时稍等再刷新菜单；未能重载时提示手动重启。
    if (result.reloadRequested === true) {
      ElMessage.success(t('business.codegen.preview.backendReloaded', '后端已重新加载，新页面稍后可用'));
      await waitForBackendReload();
    } else if (result.reloadRequested === false) {
      ElMessage.warning(t('business.codegen.preview.restartRequired', { reason: result.reloadReason || '-' }, '请重启后端服务使新接口生效（原因：{reason}）'));
    }
    try {
      await refreshBusinessMenu();
      if (result.reloadRequested !== true) ElMessage.success(t('business.codegen.preview.generateSuccess', '正式模块生成成功'));
    } catch {
      ElMessage.warning(t('business.codegen.preview.menuRefreshFailed', '操作成功，菜单刷新失败'));
    }
  }
  setVisible(false);
}

async function generate() {
  const module = props.module;
  const current = preview.value;
  if (!module || !current || !hasPermission(PERMS_FORMAL_GENERATION, 'all') || !canConfirmGeneration.value) return;
  generating.value = true;
  try {
    const outcome = await confirmFormalGeneration(module.id, current.generationId, current.sensitive?.confirmToken ?? '');
    if (outcome.kind === 'done') {
      // 确认令牌已被消费：清掉它，避免再次点击「生成到项目」提交失效令牌。
      if (preview.value === current) preview.value = { ...current, sensitive: undefined };
      await completeGeneration(outcome.result);
    } else if (outcome.kind === 'rejected') {
      // 确认令牌已被消费或计划已失效：清掉旧预览，要求重新预览后再生成。
      ElMessage.error(errorMessage(outcome.error, t('business.codegen.preview.generateFailed', '正式生成失败')));
      preview.value = null;
      loadError.value = t('business.codegen.preview.previewExpired', '生成计划已失效，请重新预览');
    } else {
      ElMessage.warning(t('business.codegen.preview.generateStatusPending', '生成状态尚未确认，请到生成记录查看结果'));
    }
  } finally {
    generating.value = false;
  }
}

async function dynamicPublish() {
  const module = props.module;
  if (!module || isPluginTarget.value || !hasPermission(PERMS_DYNAMIC_PUBLISH, 'all')) return;
  try {
    await ElMessageBox.confirm(
      t('business.codegen.preview.dynamicPublishConfirm', '动态发布使用已保存的 Schema 更新运行时页面，不会写入任何文件。确认发布？'),
      t('business.codegen.preview.dynamicPublish', '动态发布（不写文件）'),
      { type: 'warning', confirmButtonText: t('common.confirm', '确定'), cancelButtonText: t('common.cancel', '取消') }
    );
  } catch {
    return;
  }
  publishing.value = true;
  try {
    const detail = await businessDevelopmentApi.module(module.id);
    const form = detail.form;
    const schemaHash = String(form?.schema_hash ?? '');
    if (!form?.schema_document || !schemaHash) throw new Error(t('business.codegen.preview.schemaMissing', '业务模块或 Schema hash 缺失'));
    const payload = { schema_document: form.schema_document, schemaHash, expected_schema_hash: schemaHash, publish_config: form.publish_config ?? {} };
    const previewResult = await businessDevelopmentApi.previewPublish(module.id, payload);
    if (!hasPermission('development:business:publish')) return;
    await businessDevelopmentApi.publish(module.id, { ...payload, formDependencyHash: previewResult.formDependencyHash });
    try {
      await refreshBusinessMenu();
    } catch {
      ElMessage.warning(t('business.codegen.preview.menuRefreshFailed', '操作成功，菜单刷新失败'));
    }
    ElMessage.success(t('business.codegen.preview.publishSuccess', '动态发布成功'));
    emit('published');
    // 发布后正式生成读取新的已发布版本（首次发布或已保存改动刚发布）：弹窗仍打开时直接重新预览。
    if ((notPublished.value || preview.value) && props.modelValue && props.module?.id === module.id) void loadPreview();
  } catch (error) {
    ElMessage.error(errorMessage(error, t('business.codegen.preview.publishFailed', '动态发布失败')));
  } finally {
    publishing.value = false;
  }
}

// 设计器「⋯ → 动态发布」直接复用同一流程，无需先打开预览（动态发布不依赖正式生成预览权限）。
defineExpose({ dynamicPublish });

function timestamp(date = new Date()) {
  const pad = (value: number) => String(value).padStart(2, '0');
  return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`;
}

async function downloadArchive() {
  const module = props.module;
  const nonce = previewNonce.value;
  if (!module || !preview.value || !nonce || !canDownload.value || downloading.value) return;
  downloading.value = true;
  try {
    // 复用当前预览的 nonce：同一生成记录被复用，打开中的预览仍可「生成到项目」。
    const blob = await businessDevelopmentApi.formalGenerationArchive(module.id, nonce);
    // http.download 只返回 Blob，拿不到服务端文件名；按同一规则在本地命名。
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${module.code}-${timestamp()}.zip`;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    try {
      anchor.click();
    } finally {
      anchor.remove();
      setTimeout(() => URL.revokeObjectURL(url), 0);
    }
  } catch (error) {
    ElMessage.error(errorMessage(error, t('business.codegen.preview.downloadFailed', '下载失败')));
  } finally {
    downloading.value = false;
  }
}

async function adoptResolved() {
  const module = props.module;
  const current = preview.value;
  const file = activeFile.value;
  if (!module || !current || !file || !canAdoptActive.value || adopting.value) return;
  try {
    await ElMessageBox.confirm(
      t('business.codegen.preview.adoptResolvedConfirm', { path: file.path }, '本地文件 {path} 已与生成内容一致，将以它作为该文件的生成基线，之后的生成会在其基础上三方合并。确认采纳？'),
      t('business.codegen.preview.adoptResolvedTitle', '采纳已解决的文件'),
      { type: 'warning', confirmButtonText: t('common.confirm', '确定'), cancelButtonText: t('common.cancel', '取消') }
    );
  } catch {
    return;
  }
  adopting.value = true;
  try {
    await businessDevelopmentApi.adoptResolvedBaseline(module.id, {
      generationId: current.generationId,
      path: file.path,
      localHash: String(file.localHash),
      remoteHash: String(file.remoteHash)
    });
    ElMessage.success(t('business.codegen.preview.adoptResolvedSuccess', '已采纳，正在重新预览'));
  } catch (error) {
    ElMessage.error(errorMessage(error, t('business.codegen.preview.adoptResolvedFailed', '采纳失败')));
    return;
  } finally {
    adopting.value = false;
  }
  if (props.modelValue && props.module?.id === module.id) await loadPreview();
}

async function copyPath() {
  const path = activeFile.value?.path;
  if (!path) return;
  try {
    await navigator.clipboard.writeText(path);
    ElMessage.success(t('business.codegen.copySuccess', '已复制'));
  } catch {
    ElMessage.error(t('business.codegen.copyFailed', '复制失败，请手动选择复制'));
  }
}
</script>

<style scoped>
.codegen-preview__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding-right: 32px;
}

.codegen-preview__title {
  margin: 0 4px 0 0;
  color: var(--el-text-color-primary);
  font-size: 16px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.codegen-preview__spacer {
  flex: 1 1 auto;
}

.codegen-preview {
  min-height: 0;
}

.codegen-preview__stale {
  margin-bottom: 8px;
}

.codegen-preview__stale :deep(.el-alert__description) {
  margin-top: 6px;
}

.codegen-preview__adopt-hint {
  margin: 0;
  padding: 6px 12px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  color: var(--el-color-warning);
  font-size: 12px;
}

.codegen-preview__state {
  padding: 32px 16px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
  text-align: center;
}

.codegen-preview__layout {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  height: 70vh;
  min-height: 360px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  overflow: hidden;
}

.is-fullscreen .codegen-preview__layout {
  height: calc(100vh - 150px);
}

.codegen-preview__tree {
  overflow: auto;
  padding: 8px;
  border-right: 1px solid var(--el-border-color-lighter);
  background: var(--el-fill-color-lighter);
}

.codegen-preview__group + .codegen-preview__group {
  margin-top: 8px;
}

.codegen-preview__group-title {
  margin: 4px 8px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  font-weight: 600;
}

.codegen-preview__file {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 5px 8px;
  border: 0;
  border-radius: 4px;
  background: transparent;
  color: var(--el-text-color-regular);
  font: inherit;
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}

.codegen-preview__file:hover {
  background: var(--el-fill-color);
}

.codegen-preview__file.is-active {
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
}

.codegen-preview__file:focus-visible,
.codegen-preview__tab button:focus-visible,
.codegen-preview__segmented button:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: 1px;
}

.codegen-preview__dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.codegen-preview__file-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.codegen-preview__main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  background: var(--el-bg-color);
}

.codegen-preview__tabs {
  display: flex;
  flex: none;
  overflow-x: auto;
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.codegen-preview__tab {
  display: flex;
  flex: none;
  align-items: center;
  border-right: 1px solid var(--el-border-color-lighter);
  border-bottom: 2px solid transparent;
}

.codegen-preview__tab.is-active {
  border-bottom-color: var(--el-color-primary);
  background: var(--el-fill-color-lighter);
}

.codegen-preview__tab button {
  border: 0;
  background: transparent;
  color: var(--el-text-color-regular);
  font: inherit;
  cursor: pointer;
}

.codegen-preview__tab-label {
  padding: 8px 4px 8px 12px;
  font-size: 13px;
}

.codegen-preview__tab.is-active .codegen-preview__tab-label {
  color: var(--el-color-primary);
}

.codegen-preview__tab-close {
  padding: 4px 8px;
  color: var(--el-text-color-secondary) !important;
  font-size: 14px;
  line-height: 1;
}

.codegen-preview__pathbar {
  display: flex;
  flex: none;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

.codegen-preview__path {
  min-width: 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  overflow-wrap: anywhere;
}

.codegen-preview__segmented {
  display: inline-flex;
  padding: 2px;
  border-radius: 6px;
  background: var(--el-fill-color);
}

.codegen-preview__segmented button {
  padding: 3px 10px;
  border: 0;
  border-radius: 4px;
  background: transparent;
  color: var(--el-text-color-regular);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}

.codegen-preview__segmented button.is-active {
  background: var(--el-bg-color);
  color: var(--el-color-primary);
  box-shadow: var(--el-box-shadow-lighter);
}

.codegen-preview__content {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
  padding: 12px;
  overflow: auto;
}

.codegen-preview__code {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
}

.codegen-preview__code :deep(.code-block__scroller) {
  flex: 1 1 auto;
  max-height: none;
}

.codegen-preview__diff {
  margin-top: 0;
}

.codegen-preview__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.codegen-preview__footer .el-button + .el-button {
  margin-left: 0;
}

.codegen-preview__hint {
  flex: 1 1 320px;
  margin: 0;
  font-size: 13px;
  text-align: left;
}

.codegen-preview__hint.is-warning {
  color: var(--el-color-warning);
}

.codegen-preview__hint.is-danger {
  color: var(--el-color-danger);
}

.codegen-preview__full {
  flex: 1 1 100%;
}

.codegen-preview__summary {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 18px;
  margin: 0;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  text-align: left;
}

.codegen-preview__summary > div,
.codegen-preview__baseline-list > div {
  display: flex;
  gap: 6px;
  min-width: 0;
}

.codegen-preview__summary dt,
.codegen-preview__baseline-list dt {
  color: var(--el-text-color-secondary);
}

.codegen-preview__summary dd,
.codegen-preview__baseline-list dd {
  margin: 0;
  color: var(--el-text-color-regular);
  overflow-wrap: anywhere;
}

.codegen-preview__baseline {
  margin-top: 10px;
}

.codegen-preview__baseline-list {
  display: grid;
  gap: 4px;
  margin: 0;
  font-size: 12px;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

@media (max-width: 760px) {
  .codegen-preview__layout {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: minmax(120px, 30%) minmax(0, 1fr);
  }

  .codegen-preview__tree {
    border-right: 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
  }
}
</style>

<style>
.codegen-preview-dialog .el-dialog__body {
  padding-top: 4px;
}
</style>
