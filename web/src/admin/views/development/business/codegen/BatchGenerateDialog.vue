<template>
  <el-dialog
    :model-value="modelValue"
    :title="t('business.codegen.batch.title', '批量生成')"
    width="min(920px, 96vw)"
    top="6vh"
    class="codegen-batch-dialog"
    :close-on-click-modal="false"
    :close-on-press-escape="!generating"
    :show-close="!generating"
    @update:model-value="setVisible"
  >
    <div class="codegen-batch" :aria-busy="previewing || generating">
      <p class="codegen-batch__hint">{{ t('business.codegen.batch.hint', '逐个预览选中的模块，确认后只对未阻断的模块逐个生成，各自使用自己的确认令牌；任一模块失败不影响其他模块，不做跨模块回滚。') }}</p>
      <el-table :data="rows" row-key="id" max-height="460" class="codegen-batch__table">
        <el-table-column :label="t('business.codegen.batch.module', '模块')" min-width="200">
          <template #default="{ row }">
            <div class="codegen-batch__module">
              <span class="codegen-batch__table-name">{{ row.module.table_name || row.module.code }}</span>
              <small>{{ row.module.code }}<template v-if="row.module.name && row.module.name !== row.module.code"> · {{ row.module.name }}</template></small>
            </div>
          </template>
        </el-table-column>
        <el-table-column :label="t('business.codegen.batch.status', '可否生成')" min-width="220">
          <template #default="{ row }">
            <div class="codegen-batch__status">
              <el-tag :data-batch-status="row.id" :type="statusOf(row as BatchRow).tone" effect="light" size="small" class="codegen-batch__tag">{{ statusOf(row as BatchRow).text }}</el-tag>
              <el-button
                v-if="statusOf(row as BatchRow).kind === 'blocked'"
                link
                type="primary"
                size="small"
                :data-open-preview="row.id"
                :disabled="generating"
                @click="emit('open-preview', row.id)"
              >{{ t('business.codegen.batch.openPreview', '去预览处理冲突') }}</el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column :label="t('business.codegen.batch.files', '文件数')" width="90" align="center">
          <template #default="{ row }">
            <span :data-batch-files="row.id">{{ row.preview ? row.preview.plan.files.length : '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t('business.codegen.batch.result', '结果')" min-width="200">
          <template #default="{ row }">
            <span :data-batch-result="row.id" class="codegen-batch__result" :class="row.result ? `is-${row.result.tone}` : ''">{{ row.result?.text ?? '' }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <template #footer>
      <div class="codegen-batch__footer">
        <span v-if="previewing" class="codegen-batch__progress" role="status">{{ t('business.codegen.batch.previewing', { done: progress, total: rows.length }, '正在预览 {done}/{total}…') }}</span>
        <span v-else-if="generating" class="codegen-batch__progress" role="status">{{ t('business.codegen.batch.generating', { done: progress, total: progressTotal }, '正在生成 {done}/{total}…') }}</span>
        <span v-else-if="summary" class="codegen-batch__progress" role="status" data-batch-summary>{{ summary }}</span>
        <span v-else-if="phase === 'review' && !eligible.length" class="codegen-batch__progress is-warning">{{ t('business.codegen.batch.none', '没有可生成的模块') }}</span>
        <span class="codegen-batch__spacer" />
        <el-button data-action="batch-close" :disabled="generating" @click="setVisible(false)">{{ t('business.codegen.batch.close', '关闭') }}</el-button>
        <el-button data-action="batch-repreview" plain :icon="Refresh" :disabled="previewing || generating || !canPreview" @click="startPreview">{{ t('business.codegen.batch.repreview', '重新预览') }}</el-button>
        <el-button
          v-if="phase !== 'done'"
          data-action="batch-confirm"
          type="primary"
          :loading="generating"
          :disabled="phase !== 'review' || !eligible.length"
          @click="generateAll"
        >{{ t('business.codegen.batch.confirm', { count: eligible.length }, '确认生成 {count} 个模块') }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import {
  businessDevelopmentApi,
  type BusinessFormalGenerationPreview,
  type BusinessFormalGenerationResult,
  type BusinessModule
} from '@/api/development/business';
import { hasPermission } from '@/utils/permission';
import { useBusinessMenuRefresh } from '../composables/useBusinessMenuRefresh';
import { runSequential, sequentialErrorText } from './batch';
import {
  PERM_PREVIEW,
  PERMS_FORMAL_GENERATION,
  confirmFormalGeneration,
  isFormNotPublishedError,
  isPreviewBlocked,
  previewConflictCount,
  waitForBackendReload,
  type FormalGenerationOutcome
} from './formalGeneration';

const props = defineProps<{ modelValue: boolean; modules: BusinessModule[] }>();
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  /** 批量生成结束（至少提交过一个模块）后发出，参数为各模块的生成结果。 */
  generated: [results: Array<{ moduleId: number; outcome: FormalGenerationOutcome }>];
  'open-preview': [moduleId: number];
}>();

const { t } = useI18n();
const { refreshBusinessMenu } = useBusinessMenuRefresh();

type Tone = 'success' | 'warning' | 'danger' | 'info';
type StatusKind = 'pending' | 'ready' | 'blocked' | 'no-permission' | 'no-token' | 'unpublished' | 'failed';
interface BatchRow {
  id: number;
  module: BusinessModule;
  preview: BusinessFormalGenerationPreview | null;
  previewError: string;
  /** 预览因表单从未发布而被拒绝（正式生成读取已发布版本）。 */
  unpublished?: boolean;
  result: { tone: Tone; text: string } | null;
}

const rows = ref<BatchRow[]>([]);
const phase = ref<'previewing' | 'review' | 'generating' | 'done'>('previewing');
const progress = ref(0);
const progressTotal = ref(0);
const summary = ref('');
let revision = 0;

const canPreview = computed(() => hasPermission(PERM_PREVIEW));
const canGenerate = computed(() => hasPermission(PERMS_FORMAL_GENERATION, 'all'));
const previewing = computed(() => phase.value === 'previewing');
const generating = computed(() => phase.value === 'generating');

function statusOf(row: BatchRow): { kind: StatusKind; tone: Tone; text: string } {
  if (row.unpublished) {
    return {
      kind: 'unpublished',
      tone: 'warning',
      text: row.module.metadata?.target?.type === 'plugin'
        ? t('business.codegen.batch.unpublishedPlugin', '未发布，需先在表单设计器发布')
        : t('business.codegen.batch.unpublished', '未发布，需先动态发布')
    };
  }
  if (row.previewError) return { kind: 'failed', tone: 'danger', text: t('business.codegen.batch.previewFailed', { msg: row.previewError }, '预览失败：{msg}') };
  const preview = row.preview;
  if (!preview) return { kind: 'pending', tone: 'info', text: '—' };
  if (isPreviewBlocked(preview)) {
    const count = previewConflictCount(preview);
    return {
      kind: 'blocked',
      tone: 'warning',
      text: count
        ? t('business.codegen.batch.blockedConflicts', { count }, '阻断：{count} 个冲突文件')
        : t('business.codegen.batch.blocked', '阻断：计划被阻断')
    };
  }
  if (!canGenerate.value) return { kind: 'no-permission', tone: 'info', text: t('business.codegen.batch.noPermission', '缺少生成权限') };
  if (!preview.generationId || !preview.sensitive?.confirmToken) return { kind: 'no-token', tone: 'warning', text: t('business.codegen.batch.noToken', '缺少确认令牌，请重新预览') };
  return { kind: 'ready', tone: 'success', text: t('business.codegen.batch.ready', '可生成') };
}

const eligible = computed(() => rows.value.filter((row) => statusOf(row as BatchRow).kind === 'ready'));

function setVisible(value: boolean) {
  if (!value && generating.value) return;
  emit('update:modelValue', value);
}

function patchRow(id: number, patch: Partial<BatchRow>) {
  rows.value = rows.value.map((row) => (row.id === id ? { ...row, ...patch } : row));
}

async function startPreview() {
  if (generating.value) return;
  const current = ++revision;
  summary.value = '';
  progress.value = 0;
  rows.value = props.modules.map((module) => ({ id: module.id, module, preview: null, previewError: '', result: null }));
  if (!canPreview.value) {
    rows.value = rows.value.map((row) => ({ ...row, previewError: t('business.codegen.batch.noPreviewPermission', '缺少预览权限') }));
    phase.value = 'review';
    return;
  }
  phase.value = 'previewing';
  const unpublishedIds = new Set<number>();
  const results = await runSequential(
    rows.value.map((row) => row.module),
    async (module) => {
      // 弹窗关闭或重新预览后不再继续发起旧批次的预览。
      if (current !== revision) throw new Error('stale');
      try {
        return await businessDevelopmentApi.previewFormalGeneration(module.id, crypto.randomUUID());
      } catch (error) {
        if (isFormNotPublishedError(error)) unpublishedIds.add(module.id);
        throw error;
      }
    },
    (done) => { if (current === revision) progress.value = done; }
  );
  if (current !== revision) return;
  for (const result of results) {
    patchRow(result.item.id, result.ok
      ? { preview: result.value }
      : unpublishedIds.has(result.item.id) ? { unpublished: true } : { previewError: result.error });
  }
  phase.value = 'review';
}

function outcomeResult(module: BusinessModule, outcome: FormalGenerationOutcome): { tone: Tone; text: string } {
  if (outcome.kind === 'rejected') {
    const msg = outcome.error.data?.error?.requestId
      ? t('business.codegen.batch.requestError', { msg: outcome.error.msg, id: outcome.error.data.error.requestId }, '{msg}（请求 ID：{id}）')
      : outcome.error.msg;
    return { tone: 'danger', text: t('business.codegen.batch.rejected', { msg }, '生成失败：{msg}') };
  }
  if (outcome.kind === 'pending') return { tone: 'warning', text: t('business.codegen.batch.statusPending', '生成状态尚未确认，请到生成记录查看结果') };
  if (outcome.result.state !== 'completed') return { tone: 'warning', text: t('business.codegen.batch.incomplete', '生成未完成，请到生成记录查看') };
  return module.metadata?.target?.type === 'plugin'
    ? { tone: 'success', text: t('business.codegen.batch.pluginPending', '源码已生成，待安装／更新发布') }
    : { tone: 'success', text: t('business.codegen.batch.success', '生成成功') };
}

const isPluginModule = (module: BusinessModule | undefined): boolean => module?.metadata?.target?.type === 'plugin';

const isCompleted = (outcome: FormalGenerationOutcome): outcome is { kind: 'done'; result: BusinessFormalGenerationResult } =>
  outcome.kind === 'done' && outcome.result.state === 'completed';

async function generateAll() {
  if (phase.value !== 'review' || !canGenerate.value) return;
  const queue = eligible.value;
  if (!queue.length) return;
  const queued = new Set(queue.map((row) => row.id));
  phase.value = 'generating';
  progress.value = 0;
  progressTotal.value = queue.length;
  rows.value = rows.value.map((row) => ({
    ...row,
    result: queued.has(row.id)
      ? { tone: 'info', text: t('business.codegen.batch.waiting', '等待生成') }
      : { tone: 'info', text: t('business.codegen.batch.skipped', '已跳过') }
  }));
  const outcomes: Array<{ moduleId: number; outcome: FormalGenerationOutcome }> = [];
  // Workerman 重载进行中会忽略新的重载信号：只让最后一个核心模块请求重载，此时前面模块的文件均已写入。
  const reloadModuleId = [...queue].reverse().find((row) => !isPluginModule(row.module))?.id;
  try {
    // 每个模块使用自己预览返回的 generationId 与 confirmToken；任何一个失败不影响其他模块。
    const results = await runSequential(queue, async (row) => {
      const preview = row.preview as BusinessFormalGenerationPreview;
      const outcome = await confirmFormalGeneration(row.id, preview.generationId, preview.sensitive?.confirmToken ?? '', { deferReload: row.id !== reloadModuleId });
      outcomes.push({ moduleId: row.id, outcome });
      patchRow(row.id, { result: outcomeResult(row.module, outcome) });
      return outcome;
    }, (done) => { progress.value = done; });
    for (const result of results) {
      if (!result.ok) patchRow(result.item.id, { result: { tone: 'danger', text: t('business.codegen.batch.rejected', { msg: result.error }, '生成失败：{msg}') } });
    }
    const succeeded = outcomes.filter(({ outcome }) => isCompleted(outcome));
    const coreSucceeded = succeeded.some(({ moduleId }) => !isPluginModule(queue.find((row) => row.id === moduleId)?.module));
    if (coreSucceeded) {
      const reloadOutcome = outcomes.find(({ moduleId }) => moduleId === reloadModuleId)?.outcome;
      if (reloadOutcome?.kind === 'done' && reloadOutcome.result.reloadRequested === true) {
        ElMessage.success(t('business.codegen.batch.backendReloaded', '后端已重新加载，新页面稍后可用'));
        await waitForBackendReload();
      } else {
        // 最后一个核心模块失败或后端未能重载：前面延后重载的模块的新接口尚未注册。
        ElMessage.warning(t('business.codegen.batch.restartRequired', '请重启后端服务使新生成的接口生效'));
      }
      try {
        await refreshBusinessMenu();
      } catch {
        ElMessage.warning(t('business.codegen.batch.menuRefreshFailed', '生成成功，菜单刷新失败'));
      }
    }
    summary.value = t(
      'business.codegen.batch.summary',
      { ok: succeeded.length, failed: queue.length - succeeded.length, skipped: rows.value.length - queue.length },
      '批量生成完成：成功 {ok} 个，未成功 {failed} 个，跳过 {skipped} 个'
    );
    emit('generated', outcomes);
  } catch (error) {
    summary.value = sequentialErrorText(error);
  } finally {
    // 确认令牌已消费：如需再次生成，请重新预览。
    phase.value = 'done';
  }
}

watch(
  () => props.modelValue,
  (visible) => {
    if (visible) void startPreview();
    else if (!generating.value) revision++;
  },
  { immediate: true }
);
</script>

<style scoped>
.codegen-batch__hint {
  margin: 0 0 12px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
}

.codegen-batch__module {
  display: grid;
  min-width: 0;
}

.codegen-batch__table-name {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12.5px;
  overflow-wrap: anywhere;
}

.codegen-batch__module small {
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

.codegen-batch__status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.codegen-batch__tag {
  max-width: 100%;
  white-space: normal;
  height: auto;
  line-height: 1.5;
}

.codegen-batch__result {
  font-size: 12.5px;
  overflow-wrap: anywhere;
}

.codegen-batch__result.is-success {
  color: var(--el-color-success);
}

.codegen-batch__result.is-warning {
  color: var(--el-color-warning);
}

.codegen-batch__result.is-danger {
  color: var(--el-color-danger);
}

.codegen-batch__result.is-info {
  color: var(--el-text-color-secondary);
}

.codegen-batch__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.codegen-batch__footer .el-button + .el-button {
  margin-left: 0;
}

.codegen-batch__spacer {
  flex: 1 1 auto;
}

.codegen-batch__progress {
  color: var(--el-text-color-secondary);
  font-size: 13px;
  text-align: left;
}

.codegen-batch__progress.is-warning {
  color: var(--el-color-warning);
}
</style>
