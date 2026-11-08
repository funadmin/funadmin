<template>
  <el-dialog
    :model-value="modelValue"
    :title="t('business.codegen.load.title', '装载数据表')"
    width="min(880px, 96vw)"
    top="6vh"
    class="codegen-load-dialog"
    :close-on-click-modal="false"
    :close-on-press-escape="!running"
    :show-close="!running"
    @update:model-value="setVisible"
  >
    <div class="codegen-load" :aria-busy="running || tablesLoading">
      <el-alert v-if="!canLoad" class="codegen-load__alert" type="warning" show-icon :closable="false" :title="t('business.codegen.load.noPermission', '没有装载权限：需要读取数据表、结构检查与从数据库创建权限。')" />
      <div class="codegen-load__toolbar">
        <label class="codegen-load__field">
          <span>{{ t('business.codegen.load.connection', '数据源') }}</span>
          <el-input
            v-model="connection"
            data-field="connection"
            class="codegen-load__connection"
            :placeholder="t('business.codegen.load.connectionPlaceholder', '连接标识，如 mysql')"
            :disabled="running"
            @input="connectionTouched = true"
            @change="reloadTables"
          />
        </label>
        <el-button data-action="load-refresh" :icon="Refresh" :loading="tablesLoading" :disabled="running || !canListTables" @click="reloadTables">{{ t('business.codegen.load.refresh', '刷新') }}</el-button>
        <span class="codegen-load__spacer" />
        <el-input
          v-model="keyword"
          data-field="keyword"
          class="codegen-load__search"
          clearable
          :prefix-icon="Search"
          :placeholder="t('business.codegen.load.search', '搜索表名或表描述')"
        />
      </div>
      <el-alert v-if="tablesError" class="codegen-load__alert" type="error" show-icon :closable="false" :title="t('business.codegen.load.tablesFailed', { msg: tablesError }, '数据表读取失败：{msg}')" />
      <el-alert v-if="modulesError" class="codegen-load__alert" type="warning" show-icon :closable="false" :title="t('business.codegen.load.modulesFailed', { msg: modulesError }, '已装载的业务模块读取失败，「已装载」提示可能不完整：{msg}')" />

      <el-table
        :data="visibleTables"
        row-key="name"
        max-height="420"
        class="codegen-load__table"
        :row-class-name="rowClass"
        :empty-text="tablesLoading ? t('business.codegen.load.tablesLoading', '正在读取数据表…') : t('business.codegen.load.empty', '没有数据表')"
      >
        <el-table-column width="48" align="center">
          <template #header>
            <el-checkbox
              data-select-all
              :model-value="allSelected"
              :indeterminate="someSelected"
              :disabled="!selectableVisible.length"
              :aria-label="t('business.codegen.load.selectAll', '全选可装载的表')"
              @update:model-value="toggleAll"
            />
          </template>
          <template #default="{ row }">
            <el-checkbox
              :data-select="row.name"
              :model-value="selected.includes(row.name)"
              :disabled="!isSelectable(row as BusinessDatabaseTable)"
              :aria-label="t('business.codegen.load.selectRow', { name: row.name }, '选择 {name}')"
              @update:model-value="(value: boolean | string | number) => toggle(row.name, Boolean(value))"
            />
          </template>
        </el-table-column>
        <el-table-column :label="t('business.codegen.load.tableName', '表名称')" prop="name" min-width="200">
          <template #default="{ row }"><span class="codegen-load__name">{{ row.name }}</span></template>
        </el-table-column>
        <el-table-column :label="t('business.codegen.load.tableComment', '表描述')" prop="comment" min-width="160" />
        <el-table-column :label="t('business.codegen.load.note', '说明')" min-width="220">
          <template #default="{ row }">
            <span :data-table-note="row.name" class="codegen-load__note" :class="`is-${noteTone(row as BusinessDatabaseTable)}`">{{ noteText(row as BusinessDatabaseTable) }}</span>
          </template>
        </el-table-column>
      </el-table>
      <p class="codegen-load__hint">{{ t('business.codegen.load.coreTableNote', '系统核心表以服务端检查为准，被拒绝时会在「说明」列显示原因。') }}</p>
    </div>

    <template #footer>
      <div class="codegen-load__footer">
        <label class="codegen-load__field codegen-load__target">
          <span>{{ t('business.codegen.load.target', '生成目标') }}</span>
          <!-- 核心目标的值为空串：el-select 默认把 '' 视为未选择而显示「请选择」，这里只把 null/undefined 视为空，使核心目标作为默认选中项显示。 -->
          <el-select v-model="targetSelected" data-field="target" :empty-values="[null, undefined]" :disabled="running || targetsLoading" :loading="targetsLoading" class="codegen-load__target-select">
            <el-option
              v-for="item in candidates"
              :key="item.pluginCode ?? ''"
              :label="candidateLabel(item)"
              :value="item.pluginCode ?? ''"
              :disabled="item.available === false"
            />
          </el-select>
        </label>
        <span v-if="running" class="codegen-load__progress" role="status">{{ t('business.codegen.load.progress', { done: progress, total: queueSize }, '正在装载 {done}/{total}…') }}</span>
        <span v-else-if="canLoad && targetNotice" class="codegen-load__progress is-warning">{{ targetNotice }}</span>
        <span class="codegen-load__spacer" />
        <el-button data-action="load-cancel" :disabled="running" @click="setVisible(false)">{{ finished ? t('business.codegen.load.close', '关闭') : t('business.codegen.load.cancel', '取消') }}</el-button>
        <el-button data-action="load-submit" type="primary" :loading="running" :disabled="!canSubmit" @click="submit">{{ t('business.codegen.load.submit', { count: queue.length }, '装载 {count} 张表') }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Search } from '@element-plus/icons-vue';
import { businessDevelopmentApi, type BusinessDatabaseTable, type BusinessModule, type BusinessModuleDetail } from '@/api/development/business';
import { hasPermission } from '@/utils/permission';
import { deriveAdoptedBusiness } from '../constants';
import { useBusinessTarget } from '../composables/useBusinessTarget';
import { runSequential, sequentialErrorText } from './batch';

const props = defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  /** 至少一张表装载成功时发出，参数为新建的业务模块。 */
  loaded: [modules: BusinessModule[]];
}>();

const { t } = useI18n();

type RowState = { state: 'queued' | 'running' | 'success' | 'failed'; message: string };

const MODULE_PAGE_SIZE = 100;
const MODULE_PAGE_LIMIT = 50;

const hasAction = (action: string) => hasPermission(`development:business:${action}`);
const canListTables = computed(() => hasAction('database-tables'));
const canLoad = computed(() => canListTables.value && hasAction('inspect-database') && hasAction('create-from-database'));
// 与创建向导一致：读取业务目标沿用 modules 授权。
const {
  selected: targetSelected, candidates, candidateLabel, defaultConnection, loading: targetsLoading,
  notice: targetNotice, available: targetAvailable, target, loadTargets
} = useBusinessTarget(() => hasAction('modules') && hasAction('create-from-database'));

const connection = ref(defaultConnection.value);
const connectionTouched = ref(false);
const keyword = ref('');
const tables = ref<BusinessDatabaseTable[]>([]);
const tablesConnection = ref('');
const tablesLoading = ref(false);
const tablesError = ref('');
const existing = ref<BusinessModule[]>([]);
const modulesError = ref('');
const selected = ref<string[]>([]);
const results = ref<Record<string, RowState>>({});
const running = ref(false);
const finished = ref(false);
const progress = ref(0);
const queueSize = ref(0);
let tablesRevision = 0;

const loadedKeys = computed(() => new Set(existing.value
  .filter((module) => module.table_name)
  .map((module) => `${module.connection_name || defaultConnection.value}\u0000${module.table_name}`)));

const isLoaded = (table: BusinessDatabaseTable) => loadedKeys.value.has(`${tablesConnection.value}\u0000${table.name}`);
const isSelectable = (table: BusinessDatabaseTable) => canLoad.value && !running.value && !isLoaded(table) && results.value[table.name]?.state !== 'success';

const visibleTables = computed(() => {
  const term = keyword.value.trim().toLowerCase();
  if (!term) return tables.value;
  return tables.value.filter((table) => table.name.toLowerCase().includes(term) || (table.comment ?? '').toLowerCase().includes(term));
});
const selectableVisible = computed(() => visibleTables.value.filter(isSelectable));
const allSelected = computed(() => selectableVisible.value.length > 0 && selectableVisible.value.every((table) => selected.value.includes(table.name)));
const someSelected = computed(() => !allSelected.value && selectableVisible.value.some((table) => selected.value.includes(table.name)));
// 只提交当前仍可装载的勾选行（按数据表顺序）。
const queue = computed(() => tables.value.filter((table) => selected.value.includes(table.name) && isSelectable(table)));
const canSubmit = computed(() => canLoad.value && targetAvailable.value && !running.value && !tablesLoading.value && queue.value.length > 0);

function setVisible(value: boolean) {
  if (!value && running.value) return;
  emit('update:modelValue', value);
}

function toggle(name: string, value: boolean) {
  const rest = selected.value.filter((item) => item !== name);
  selected.value = value ? [...rest, name] : rest;
}

function toggleAll(value: boolean | string | number) {
  const names = new Set(selectableVisible.value.map((table) => table.name));
  const rest = selected.value.filter((name) => !names.has(name));
  selected.value = value ? [...rest, ...names] : rest;
}

function noteText(table: BusinessDatabaseTable): string {
  const result = results.value[table.name];
  if (result) {
    if (result.state === 'queued') return t('business.codegen.load.queued', '等待装载');
    if (result.state === 'running') return t('business.codegen.load.running', '正在装载…');
    return result.message;
  }
  return isLoaded(table) ? t('business.codegen.load.loaded', '已装载') : '';
}

function noteTone(table: BusinessDatabaseTable): 'success' | 'danger' | 'muted' {
  const state = results.value[table.name]?.state;
  if (state === 'success') return 'success';
  if (state === 'failed') return 'danger';
  return 'muted';
}

function rowClass({ row }: { row: BusinessDatabaseTable }) {
  return isLoaded(row) || results.value[row.name]?.state === 'success' ? 'is-disabled' : '';
}

async function loadTables() {
  const current = ++tablesRevision;
  const name = connection.value.trim();
  tablesError.value = '';
  if (!canListTables.value || !name) {
    tables.value = [];
    tablesConnection.value = name;
    return;
  }
  tablesLoading.value = true;
  try {
    const list = await businessDevelopmentApi.databaseTables(name);
    if (current !== tablesRevision) return;
    tables.value = list;
    tablesConnection.value = name;
  } catch (error) {
    if (current !== tablesRevision) return;
    tables.value = [];
    tablesConnection.value = name;
    tablesError.value = sequentialErrorText(error);
  } finally {
    if (current === tablesRevision) tablesLoading.value = false;
  }
}

function reloadTables() {
  if (running.value) return;
  selected.value = [];
  results.value = {};
  void loadTables();
}

/** 读取全部业务模块（接口单页上限 100），用于判断同一连接下的表是否已装载。 */
async function loadModules() {
  modulesError.value = '';
  if (!hasAction('modules')) return;
  const list: BusinessModule[] = [];
  try {
    for (let page = 1; page <= MODULE_PAGE_LIMIT; page++) {
      const result = await businessDevelopmentApi.modules({ page, pageSize: MODULE_PAGE_SIZE });
      list.push(...result.list);
      if (result.list.length < MODULE_PAGE_SIZE || list.length >= result.total) break;
    }
    existing.value = list;
  } catch (error) {
    existing.value = list;
    modulesError.value = sequentialErrorText(error);
  }
}

async function initialize() {
  selected.value = [];
  results.value = {};
  keyword.value = '';
  finished.value = false;
  progress.value = 0;
  await Promise.all([loadTargets(), loadModules()]);
  if (!connectionTouched.value) connection.value = defaultConnection.value;
  await loadTables();
}

watch(() => props.modelValue, (visible) => {
  if (visible) void initialize();
}, { immediate: true });

function setResult(name: string, state: RowState) {
  results.value = { ...results.value, [name]: state };
}

async function adopt(table: BusinessDatabaseTable, name: string, selection: typeof target.value): Promise<BusinessModuleDetail> {
  setResult(table.name, { state: 'running', message: '' });
  try {
    const inspection = await businessDevelopmentApi.inspectDatabase(name, table.name);
    if (!inspection.fields.length) throw new Error(t('business.visual.noFields', '未识别到字段，无法采纳'));
    if (!inspection.primaryKey.length) throw new Error(t('business.visual.noPrimaryKeyBlock', '缺少主键，无法采纳'));
    const defaults = deriveAdoptedBusiness(table.name, table.comment);
    const detail = await businessDevelopmentApi.createFromDatabase({
      name: defaults.name,
      code: defaults.code,
      connection: name,
      table: table.name,
      target: selection,
      expectedInspectionHash: inspection.snapshotHash,
      listConfig: {}
    });
    setResult(table.name, { state: 'success', message: t('business.codegen.load.loadedAs', { code: detail.module?.code || defaults.code }, '已装载为 {code}') });
    return detail;
  } catch (error) {
    setResult(table.name, { state: 'failed', message: sequentialErrorText(error) });
    throw error;
  }
}

async function submit() {
  if (!canSubmit.value) return;
  const items = queue.value;
  const name = tablesConnection.value;
  const selection = target.value;
  running.value = true;
  progress.value = 0;
  queueSize.value = items.length;
  for (const table of items) setResult(table.name, { state: 'queued', message: '' });
  try {
    // 逐张执行；部分失败不回滚已成功的表。
    const outcome = await runSequential(items, (table) => adopt(table, name, selection), (done) => { progress.value = done; });
    const created = outcome.flatMap((item) => (item.ok ? [item.value.module] : []));
    const failed = outcome.length - created.length;
    selected.value = selected.value.filter((table) => results.value[table]?.state !== 'success');
    finished.value = true;
    if (created.length) {
      existing.value = [...existing.value, ...created.map((module) => ({ ...module, connection_name: module.connection_name || name }))];
      emit('loaded', created);
      ElMessage[failed ? 'warning' : 'success'](t('business.codegen.load.summary', { ok: created.length, failed }, '装载完成：成功 {ok} 张，失败 {failed} 张'));
    } else {
      ElMessage.error(t('business.codegen.load.allFailed', { failed }, '装载失败：{failed} 张表均未装载'));
    }
  } finally {
    running.value = false;
  }
}
</script>

<style scoped>
.codegen-load__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.codegen-load__field {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--el-text-color-regular);
  font-size: 13px;
  white-space: nowrap;
}

.codegen-load__connection {
  width: 160px;
}

.codegen-load__search {
  width: min(260px, 100%);
}

.codegen-load__spacer {
  flex: 1 1 auto;
}

.codegen-load__alert {
  margin-bottom: 12px;
}

.codegen-load__name {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12.5px;
  overflow-wrap: anywhere;
}

.codegen-load__table :deep(.is-disabled) {
  color: var(--el-text-color-placeholder);
}

.codegen-load__note {
  font-size: 12.5px;
  overflow-wrap: anywhere;
}

.codegen-load__note.is-muted {
  color: var(--el-text-color-secondary);
}

.codegen-load__note.is-success {
  color: var(--el-color-success);
}

.codegen-load__note.is-danger {
  color: var(--el-color-danger);
}

.codegen-load__hint {
  margin: 8px 0 0;
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

.codegen-load__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.codegen-load__footer .el-button + .el-button {
  margin-left: 0;
}

.codegen-load__target-select {
  width: 220px;
}

.codegen-load__progress {
  color: var(--el-text-color-secondary);
  font-size: 13px;
}

.codegen-load__progress.is-warning {
  color: var(--el-color-warning);
}

@media (max-width: 640px) {
  .codegen-load__search,
  .codegen-load__target-select {
    width: 100%;
  }

  .codegen-load__target {
    flex: 1 1 100%;
  }
}
</style>
