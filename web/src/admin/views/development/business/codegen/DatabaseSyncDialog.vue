<template>
  <el-dialog
    :model-value="modelValue"
    :title="t('business.codegen.sync.title', { name: moduleTitle }, '同步表结构 — {name}')"
    width="min(760px, 96vw)"
    top="8vh"
    class="codegen-sync-dialog"
    :close-on-click-modal="false"
    :close-on-press-escape="!busy"
    :show-close="!busy"
    @update:model-value="setVisible"
  >
    <div class="codegen-sync" :aria-busy="loading">
      <el-alert v-if="blockedReason" type="warning" show-icon :closable="false" :title="blockedReason" />
      <div v-else-if="loading && !diff" class="codegen-sync__state" role="status">{{ t('business.codegen.sync.loading', '正在读取表结构差异…') }}</div>
      <el-alert v-else-if="loadError" type="error" show-icon :closable="false" :title="loadError">
        <el-button link type="primary" data-action="sync-retry" @click="refresh">{{ t('business.codegen.sync.retry', '重新读取') }}</el-button>
      </el-alert>
      <template v-else-if="diff">
        <el-alert
          v-if="editor.conflict.value"
          data-testid="sync-conflict-alert"
          type="warning"
          show-icon
          :closable="false"
          class="codegen-sync__alert"
          :title="t('business.codegen.sync.conflictBanner', 'Schema 版本已变化，同步未保存。所选改动已保留，请核对版本并选择处理方式。')"
        >
          <el-button size="small" data-action="sync-review-conflict" :loading="editor.conflictLoading.value" @click="openConflictReview">{{ t('business.codegen.sync.reviewConflict', '核对版本') }}</el-button>
        </el-alert>
        <div v-if="identical" class="codegen-sync__state" data-sync-identical>{{ t('business.codegen.sync.identical', '表结构与配置一致') }}</div>
        <template v-else>
          <p class="codegen-sync__intro">{{ t('business.codegen.sync.intro', '已重新读取数据库表结构，勾选要合并进字段配置的改动；描述、控件、列表与选项等已配置的内容不会被覆盖。') }}</p>
          <section v-for="section in sections" :key="section.kind" class="codegen-sync__section" :data-sync-section="section.kind">
            <header class="codegen-sync__section-head">
              <el-checkbox
                :model-value="section.items.every((item) => picks[section.kind].includes(item.key))"
                :indeterminate="section.items.some((item) => picks[section.kind].includes(item.key)) && !section.items.every((item) => picks[section.kind].includes(item.key))"
                :disabled="busy"
                :aria-label="t('business.codegen.sync.selectSection', { name: section.title }, '全选{name}')"
                @update:model-value="(value: boolean | string | number) => pickAll(section.kind, Boolean(value))"
              />
              <h4 class="codegen-sync__section-title" :class="`is-${section.kind}`">{{ section.title }}<span class="codegen-sync__count">{{ section.items.length }}</span></h4>
              <span class="codegen-sync__section-hint">{{ section.hint }}</span>
            </header>
            <ul class="codegen-sync__list">
              <li v-for="item in section.items" :key="item.key" class="codegen-sync__item" :data-sync-change="section.kind === 'changed' ? item.key : undefined">
                <el-checkbox
                  :data-sync-pick="`${section.kind}:${item.key}`"
                  :model-value="picks[section.kind].includes(item.key)"
                  :disabled="busy"
                  @update:model-value="(value: boolean | string | number) => pick(section.kind, item.key, Boolean(value))"
                >
                  <span class="codegen-sync__field">{{ item.key }}</span>
                  <span v-if="item.title && item.title !== item.key" class="codegen-sync__title">{{ item.title }}</span>
                  <span v-if="item.columnType" class="codegen-sync__type">{{ item.columnType }}</span>
                </el-checkbox>
                <ul v-if="item.deltas.length" class="codegen-sync__deltas">
                  <li v-for="delta in item.deltas" :key="delta.key">
                    <span class="codegen-sync__delta-label">{{ delta.label }}</span>
                    <del class="codegen-sync__from">{{ delta.from }}</del>
                    <span aria-hidden="true">→</span>
                    <ins class="codegen-sync__to">{{ delta.to }}</ins>
                  </li>
                </ul>
              </li>
            </ul>
          </section>
        </template>
      </template>
    </div>

    <template #footer>
      <el-button data-action="sync-cancel" :disabled="busy" @click="setVisible(false)">{{ t('business.codegen.sync.cancel', '取消') }}</el-button>
      <el-button
        v-if="diff && !identical && !blockedReason"
        data-action="sync-apply"
        type="primary"
        :loading="editor.saving.value"
        :disabled="!canApply || busy || !pickedCount"
        @click="apply"
      >{{ t('business.codegen.sync.apply', '应用所选改动') }}</el-button>
    </template>
  </el-dialog>
  <SchemaConflictDialog
    :model-value="conflictVisible"
    :review="editor.conflictReview.value"
    :loading="editor.conflictLoading.value"
    :busy="editor.saving.value || editor.conflictLoading.value"
    :error="editor.conflictError.value"
    :can-overwrite="canApply"
    @cancel="closeConflictReview"
    @recheck="editor.reviewConflict()"
    @resolve="resolveConflict"
  />
</template>

<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { businessDevelopmentApi, type BusinessModule } from '@/api/development/business';
import { hasPermission } from '@/utils/permission';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import SchemaConflictDialog from './SchemaConflictDialog.vue';
import { mergeDatabaseSync, type DatabaseSyncDiff } from './syncMerge';
import { sequentialErrorText } from './batch';
import { PERM_SAVE_SCHEMA, useGenerationEditor } from './useGenerationEditor';

const props = defineProps<{ modelValue: boolean; module: BusinessModule | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; synced: [] }>();

const { t } = useI18n();

const PERM_SYNC = 'development:business:database-sync';

type SyncKind = 'added' | 'removed' | 'changed';
interface SyncDelta { key: string; label: string; from: string; to: string }
interface SyncItem { key: string; title: string; columnType: string; deltas: SyncDelta[] }

const editor = useGenerationEditor(() => props.module?.id ?? 0);
const diff = shallowRef<DatabaseSyncDiff | null>(null);
// 合并始终基于加载时的服务端文档，重复应用不会叠加。
const baseDoc = shallowRef<FormSchemaDocument | null>(null);
const loading = ref(false);
const loadError = ref('');
const conflictVisible = ref(false);
const picks = ref<Record<SyncKind, string[]>>({ added: [], removed: [], changed: [] });
let revision = 0;

const moduleTitle = computed(() => props.module?.table_name || props.module?.code || '');
const canSync = computed(() => hasPermission(PERM_SYNC));
const canApply = computed(() => canSync.value && hasPermission(PERM_SAVE_SCHEMA));
const blockedReason = computed(() => {
  if (!props.module) return '';
  if (props.module.origin !== 'database') return t('business.codegen.sync.notDatabase', '只有数据库装载的模块可以同步表结构。');
  if (!canApply.value) return t('business.codegen.sync.noPermission', '没有同步权限：需要同步表结构与保存 Schema 权限。');
  return '';
});
const busy = computed(() => loading.value || editor.saving.value || editor.conflictLoading.value);
const identical = computed(() => Boolean(diff.value && !diff.value.added.length && !diff.value.removed.length && !diff.value.changed.length));
const pickedCount = computed(() => picks.value.added.length + picks.value.removed.length + picks.value.changed.length);

const PHYSICAL_LABELS = computed<Record<string, string>>(() => ({
  columnType: t('business.codegen.sync.columnType', '类型'),
  nullable: t('business.codegen.sync.nullable', '可空'),
  comment: t('business.codegen.sync.comment', '注释')
}));

function formatValue(value: unknown): string {
  if (typeof value === 'boolean') return value ? t('business.codegen.sync.yes', '是') : t('business.codegen.sync.no', '否');
  if (value === null || value === undefined || value === '') return t('business.codegen.sync.empty', '（空）');
  return String(value);
}

const sections = computed(() => {
  const current = diff.value;
  if (!current) return [];
  const fieldOf = (name: string) => baseDoc.value ? findField(baseDoc.value, name) : undefined;
  const list: Array<{ kind: SyncKind; title: string; hint: string; items: SyncItem[] }> = [
    {
      kind: 'added',
      title: t('business.codegen.sync.added', '新增字段'),
      hint: t('business.codegen.sync.addedHint', '追加到字段配置末尾。'),
      items: current.added.filter((node) => node.field).map((node) => ({
        key: String(node.field), title: node.title ?? '', columnType: String(node.database?.columnType ?? ''), deltas: []
      }))
    },
    {
      kind: 'removed',
      title: t('business.codegen.sync.removed', '删除字段'),
      hint: t('business.codegen.sync.removedHint', '表里已没有这些列；勾选后会移除对应字段的全部配置。'),
      items: current.removed.map((name) => ({ key: name, title: fieldOf(name)?.title ?? '', columnType: String(fieldOf(name)?.database?.columnType ?? ''), deltas: [] }))
    },
    {
      kind: 'changed',
      title: t('business.codegen.sync.changed', '变化字段'),
      hint: t('business.codegen.sync.changedHint', '只更新物理属性（类型、可空、注释）。'),
      items: current.changed.map((change) => ({
        key: change.field,
        title: fieldOf(change.field)?.title ?? '',
        columnType: '',
        deltas: Object.keys(PHYSICAL_LABELS.value)
          .filter((key) => key in change.from || key in change.to)
          .map((key) => ({ key, label: PHYSICAL_LABELS.value[key], from: formatValue(change.from[key]), to: formatValue(change.to[key]) }))
      }))
    }
  ];
  return list.filter((section) => section.items.length);
});

function findField(doc: FormSchemaDocument, name: string) {
  const stack = [...doc.nodes];
  while (stack.length) {
    const node = stack.shift()!;
    if (node.field === name) return node;
    if (node.children?.length) stack.push(...node.children);
  }
  return undefined;
}

function pick(kind: SyncKind, key: string, value: boolean) {
  const rest = picks.value[kind].filter((item) => item !== key);
  picks.value = { ...picks.value, [kind]: value ? [...rest, key] : rest };
}

function pickAll(kind: SyncKind, value: boolean) {
  const keys = sections.value.find((section) => section.kind === kind)?.items.map((item) => item.key) ?? [];
  picks.value = { ...picks.value, [kind]: value ? keys : [] };
}

function setVisible(value: boolean) {
  if (!value && busy.value) return;
  emit('update:modelValue', value);
}

async function refresh() {
  const module = props.module;
  const current = ++revision;
  diff.value = null;
  baseDoc.value = null;
  loadError.value = '';
  conflictVisible.value = false;
  if (!module || blockedReason.value) return;
  loading.value = true;
  try {
    // 先取 Schema 文档与 hash，再取差异：保存时以这次读取的 hash 做并发检测。
    await editor.load();
    if (current !== revision) return;
    if (editor.loadError.value || !editor.doc.value) {
      loadError.value = editor.loadError.value || t('business.codegen.sync.loadFailed', '读取表结构差异失败');
      return;
    }
    const result = await businessDevelopmentApi.databaseSync(module.id);
    if (current !== revision) return;
    baseDoc.value = editor.doc.value;
    diff.value = result;
    // 新增与变化默认勾选；删除会丢弃字段配置，需用户明确勾选。
    picks.value = {
      added: result.added.flatMap((node) => (node.field ? [String(node.field)] : [])),
      removed: [],
      changed: result.changed.map((change) => change.field)
    };
  } catch (error) {
    if (current !== revision) return;
    loadError.value = `${t('business.codegen.sync.loadFailed', '读取表结构差异失败')}：${sequentialErrorText(error)}`;
  } finally {
    if (current === revision) loading.value = false;
  }
}

watch(
  () => [props.modelValue, props.module?.id] as const,
  ([visible, id], previous) => {
    if (!visible) {
      revision++;
      return;
    }
    if (!previous?.[0] || previous[1] !== id) void refresh();
  },
  { immediate: true }
);

function finish() {
  ElMessage.success(t('business.codegen.sync.success', '表结构已同步'));
  emit('synced');
  emit('update:modelValue', false);
}

async function apply() {
  const base = baseDoc.value;
  const current = diff.value;
  if (!base || !current || !canApply.value || busy.value) return;
  if (!pickedCount.value) {
    ElMessage.warning(t('business.codegen.sync.nothingSelected', '请至少勾选一项改动'));
    return;
  }
  editor.doc.value = mergeDatabaseSync(base, current, {
    added: [...picks.value.added],
    removed: [...picks.value.removed],
    changed: [...picks.value.changed]
  });
  // 冲突与失败提示由 useGenerationEditor 处理；冲突时保留合并结果等待核对。
  if (await editor.save()) finish();
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
  if (choice === 'local') {
    finish();
    return;
  }
  // 采用服务端版本后，差异需按新版本重新计算。
  ElMessage.info(t('business.codegen.sync.conflictUsedServer', '已采用服务端版本，已重新读取表结构差异'));
  await refresh();
}
</script>

<style scoped>
.codegen-sync {
  display: grid;
  gap: 12px;
  min-height: 120px;
}

.codegen-sync__state {
  padding: 32px 16px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
  text-align: center;
}

.codegen-sync__intro {
  margin: 0;
  color: var(--el-text-color-secondary);
  font-size: 13px;
}

.codegen-sync__section {
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  overflow: hidden;
}

.codegen-sync__section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--el-fill-color-lighter);
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.codegen-sync__section-title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
}

.codegen-sync__section-title.is-added {
  color: var(--el-color-success);
}

.codegen-sync__section-title.is-removed {
  color: var(--el-color-danger);
}

.codegen-sync__section-title.is-changed {
  color: var(--el-color-warning);
}

.codegen-sync__count {
  margin-left: 6px;
  color: var(--el-text-color-secondary);
  font-weight: 400;
}

.codegen-sync__section-hint {
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

.codegen-sync__list {
  max-height: 240px;
  margin: 0;
  padding: 4px 12px;
  overflow: auto;
  list-style: none;
}

.codegen-sync__item {
  padding: 4px 0;
}

.codegen-sync__item + .codegen-sync__item {
  border-top: 1px dashed var(--el-border-color-lighter);
}

.codegen-sync__field,
.codegen-sync__type,
.codegen-sync__deltas {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12.5px;
}

.codegen-sync__title {
  margin-left: 8px;
  color: var(--el-text-color-regular);
}

.codegen-sync__type {
  margin-left: 8px;
  color: var(--el-text-color-secondary);
}

.codegen-sync__deltas {
  display: grid;
  gap: 2px;
  margin: 2px 0 2px 24px;
  padding: 0;
  color: var(--el-text-color-regular);
  list-style: none;
}

.codegen-sync__deltas li {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: baseline;
}

.codegen-sync__delta-label {
  min-width: 40px;
  color: var(--el-text-color-secondary);
}

.codegen-sync__from {
  color: var(--el-color-danger);
}

.codegen-sync__to {
  color: var(--el-color-success);
  text-decoration: none;
}

.codegen-sync__alert {
  margin: 0;
}
</style>
