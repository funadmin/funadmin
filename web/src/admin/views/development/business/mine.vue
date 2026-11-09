<template>
  <PageWrapper :title="t('business.codegen.list.title', '代码生成')" :subtitle="t('business.codegen.list.subtitle', '装载数据表或新建表，配置字段与页面后预览并生成代码')">
    <el-tabs v-model="activeTab" class="codegen-tabs">
      <el-tab-pane name="tables" :label="t('business.codegen.list.tabTables', '数据表')">
        <DataTableShell storage-key="development-business-mine" :loading="loading" @refresh="loadData">
          <template #search>
            <SearchForm class="codegen-search-form" :model="query" :loading="loading" @search="search" @reset="reset">
              <el-form-item :label="t('business.codegen.list.keyword', '关键词')">
                <el-input v-model="query.keyword" :placeholder="t('business.codegen.list.keywordPlaceholder', '表名称 / 表描述 / 业务标识')" clearable />
              </el-form-item>
              <el-form-item :label="t('business.codegen.list.target', '生成目标')">
                <el-select v-model="query.target" data-filter="target" :placeholder="t('business.codegen.list.all', '全部')" clearable class="!w-32">
                  <el-option :label="t('business.codegen.list.targetCore', '核心')" value="core" />
                  <el-option :label="t('business.codegen.list.targetPlugin', '插件')" value="plugin" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('common.status', '状态')">
                <el-select v-model="query.status" data-filter="status" :placeholder="t('business.codegen.list.all', '全部')" clearable class="!w-36">
                  <el-option :label="t('business.mine.statusDraft', '草稿')" value="draft" />
                  <el-option :label="t('business.mine.statusDynamicPublished', '动态发布')" value="dynamic_published" />
                  <el-option :label="t('business.mine.statusPublished', '已发布')" value="published" />
                  <el-option :label="t('business.mine.statusDisabled', '已停用')" value="disabled" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('business.codegen.list.origin', '来源')">
                <el-select v-model="query.origin" data-filter="origin" :placeholder="t('business.codegen.list.all', '全部')" clearable class="!w-36">
                  <el-option :label="t('business.codegen.list.originVisual', '可视化新建')" value="visual" />
                  <el-option :label="t('business.codegen.list.originDatabase', '数据库装载')" value="database" />
                </el-select>
              </el-form-item>
            </SearchForm>
          </template>

          <template #toolbar-left>
            <div class="codegen-toolbar">
              <el-button data-action="load-tables" type="primary" v-perm:all="LOAD_PERMISSIONS" @click="loadVisible = true">
                <i class="i-ep-download" />{{ t('business.codegen.list.load', '装载数据表') }}
              </el-button>
              <el-button data-action="create-table" type="primary" plain v-perm="CREATE_PERMISSION" @click="createTable">
                <i class="i-ep-plus" />{{ t('business.codegen.list.create', '新建表') }}
              </el-button>
              <el-button data-action="batch-generate" type="success" plain v-perm="PERM_PREVIEW" :disabled="!selection.length" @click="openBatch">
                <i class="i-ep-setting" />{{ t('business.codegen.list.batchGenerate', '批量生成') }}
              </el-button>
              <el-button data-action="batch-remove" type="danger" plain v-perm:all="DELETE_PERMISSIONS" :disabled="!selection.length || batchRemoving" :loading="batchRemoving" @click="removeSelected">
                <i class="i-ep-delete" />{{ t('common.remove', '删除') }}
              </el-button>
              <span class="codegen-toolbar__count" data-selection-count aria-live="polite">{{ t('business.codegen.list.selected', { count: selection.length }, '已选 {count} 项') }}</span>
            </div>
          </template>

          <template #default="{ size, stripe, border, headerCellStyle }">
            <BusinessPageState :loading="loading" :error="loadError" :on-retry="loadData">
              <el-table
                ref="tableRef"
                :data="list"
                row-key="id"
                :size="size"
                :stripe="stripe"
                :border="border"
                :header-cell-style="headerCellStyle"
                :empty-text="emptyText"
                @selection-change="selection = $event"
              >
                <el-table-column type="selection" width="44" />
                <el-table-column :label="t('business.codegen.list.tableName', '表名称')" min-width="180">
                  <template #default="{ row }"><div class="codegen-table-name">{{ row.table_name || '-' }}</div><small>{{ row.code }}</small></template>
                </el-table-column>
                <el-table-column :label="t('business.codegen.list.tableComment', '表描述')" min-width="130">
                  <template #default="{ row }">{{ row.name }}</template>
                </el-table-column>
                <el-table-column :label="t('business.codegen.list.origin', '来源')" width="110">
                  <template #default="{ row }"><el-tag type="info" effect="plain">{{ originLabel(row.origin) }}</el-tag></template>
                </el-table-column>
                <el-table-column :label="t('business.codegen.list.target', '生成目标')" min-width="120">
                  <template #default="{ row }">
                    {{ targetLabel(row as BusinessModule) }}<small v-if="row.metadata?.target?.locked"> · {{ t('business.mine.locked', '已锁定') }}</small>
                  </template>
                </el-table-column>
                <el-table-column :label="t('business.codegen.list.generationType', '生成类型')" width="110">
                  <template #default="{ row }">
                    <el-tag data-generation-type :type="row.tree_enabled ? 'warning' : 'primary'" effect="light">
                      {{ row.tree_enabled ? t('business.codegen.list.typeTree', '树表 CRUD') : t('business.codegen.list.typeSingle', '单表 CRUD') }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="t('common.status', '状态')" min-width="170">
                  <template #default="{ row }">
                    <div class="codegen-status">
                      <el-tag :type="lifecycleType(row.lifecycle_status)" :title="lifecycleDescription(row.lifecycle_status)">{{ isPluginPendingInstall(row as BusinessModule) ? t('business.mine.generatedPendingInstall', '源码已生成，待安装／更新发布') : lifecycleLabel(row.lifecycle_status) }}</el-tag>
                      <span v-if="['recovering', 'recovery_required'].includes(row.recovery_status)" class="codegen-recovery">{{ row.recovery_status === 'recovering' ? t('business.status.recovery.recovering.label') : t('business.status.recovery.recovery_required.label') }}</span>
                      <GenerationStatusTag v-else :status="row.generation_status" />
                    </div>
                  </template>
                </el-table-column>
                <el-table-column :label="t('business.codegen.list.updatedAt', '更新时间')" width="170">
                  <template #default="{ row }">{{ row.updated_at || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('common.operation', '操作')" min-width="320" fixed="right">
                  <template #default="{ row }">
                    <div class="business-row-actions" :data-row-actions="row.id">
                      <el-button link type="primary" v-perm="PERM_PREVIEW" :data-preview="row.id" @click="openPreview(row as BusinessModule)">{{ t('business.codegen.list.preview', '预览') }}</el-button>
                      <el-button link type="primary" v-perm="EDIT_PERMISSIONS" :data-edit="row.id" @click="openEdit(row as BusinessModule)">{{ t('business.codegen.list.edit', '编辑') }}</el-button>
                      <el-button
                        link
                        type="primary"
                        v-perm="'development:business:save-schema'"
                        :data-design="row.id"
                        :disabled="!row.form_id"
                        :title="row.form_id ? t('business.mine.designTitle', '设计业务模块') : t('business.mine.noFormTitle', '缺少表单定义，无法进入设计器')"
                        @click="design(row as BusinessModule)"
                      >{{ t('business.codegen.list.design', '设计') }}</el-button>
                      <span v-perm="'development:business:database-sync'" class="codegen-action-slot" :data-sync-tip="row.id">
                        <el-tooltip :disabled="canSync(row as BusinessModule)" :content="t('business.codegen.list.syncDisabled', '可视化新建的表结构由 Schema 生成，无需从数据库同步')" placement="top">
                          <span class="codegen-action-trigger">
                            <el-button link type="primary" :data-sync="row.id" :disabled="!canSync(row as BusinessModule)" @click="openSync(row as BusinessModule)">{{ t('business.codegen.list.sync', '同步') }}</el-button>
                          </span>
                        </el-tooltip>
                      </span>
                      <el-button link type="primary" v-perm:all="GENERATE_PERMISSIONS" :data-generate="row.id" @click="openPreview(row as BusinessModule)">{{ t('business.codegen.list.generate', '生成') }}</el-button>
                      <el-dropdown trigger="click" placement="bottom-end">
                        <el-button link :data-more="row.id">{{ t('business.codegen.list.more', '更多') }}<i class="i-ep-arrow-down codegen-more-icon" /></el-button>
                        <template #dropdown>
                          <el-dropdown-menu>
                            <el-dropdown-item v-if="!isPlugin(row as BusinessModule)" v-perm:all="DYNAMIC_PUBLISH_PERMISSIONS" :data-dynamic-publish="row.id" @click="dynamicPublish(row as BusinessModule)">{{ t('business.codegen.list.dynamicPublish', '动态发布') }}</el-dropdown-item>
                            <el-dropdown-item v-if="row.runtime_route && !isPlugin(row as BusinessModule)" :data-runtime="row.id" @click="openRuntime(row as BusinessModule)">{{ t('business.codegen.list.openRuntime', '打开运行时') }}</el-dropdown-item>
                            <el-dropdown-item v-perm="'development:business:schema-versions'" :data-versions="row.id" @click="openVersions(row as BusinessModule)">{{ t('business.codegen.list.versionHistory', '版本历史') }}</el-dropdown-item>
                            <el-dropdown-item v-perm="'development:business:generations'" :data-records="row.id" @click="openRecords(row as BusinessModule)">{{ t('business.codegen.list.records', '生成记录') }}</el-dropdown-item>
                            <el-dropdown-item v-perm:all="DELETE_PERMISSIONS" divided class="codegen-danger-item" :data-remove="row.id" :disabled="deletingIds.has(row.id)" @click="removeModule(row as BusinessModule)">{{ t('common.remove', '删除') }}</el-dropdown-item>
                          </el-dropdown-menu>
                        </template>
                      </el-dropdown>
                    </div>
                  </template>
                </el-table-column>
              </el-table>
              <TablePagination
                v-model:current-page="query.page"
                v-model:page-size="query.pageSize"
                :total="total"
                :aria-label="t('business.codegen.list.paginationAria', '数据表分页')"
                @change="loadData"
              />
            </BusinessPageState>
          </template>
        </DataTableShell>
      </el-tab-pane>
      <el-tab-pane v-if="canViewRecords" name="records" :label="t('business.codegen.list.tabRecords', '生成记录')">
        <RecordsPanel v-if="activeTab === 'records'" embedded />
      </el-tab-pane>
    </el-tabs>

    <LoadTablesDialog v-model="loadVisible" @loaded="loadData" />
    <!-- 首次打开时才挂载：避免进入列表页就请求业务目标。 -->
    <CreateTableDialog v-if="createMounted" v-model="createVisible" />
    <DatabaseSyncDialog v-model="syncVisible" :module="syncModule" @synced="loadData" />
    <BatchGenerateDialog v-model="batchVisible" :modules="batchModules" @generated="loadData" @open-preview="openPreviewById" />
    <EditGenerationDialog v-if="editModuleId" v-model="editVisible" :module-id="editModuleId" :initial-tab="editTab" @saved="loadData" @preview="previewEdited" />
    <CodePreviewDialog ref="previewDialog" v-model="previewVisible" :module="previewModule" @generated="loadData" @published="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import TablePagination from '@/components/TablePagination/index.vue';
import { hasPermission } from '@/utils/permission';
import { computed, nextTick, onActivated, onDeactivated, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, type LocationQuery } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { TagProps } from 'element-plus';
import { businessDevelopmentApi, isBusinessApiError, type BusinessModule } from '@/api/development/business';
import BusinessPageState from './components/BusinessPageState.vue';
import GenerationStatusTag from './components/GenerationStatusTag.vue';
import LoadTablesDialog from './codegen/LoadTablesDialog.vue';
import CreateTableDialog from './codegen/CreateTableDialog.vue';
import DatabaseSyncDialog from './codegen/DatabaseSyncDialog.vue';
import BatchGenerateDialog from './codegen/BatchGenerateDialog.vue';
import EditGenerationDialog from './codegen/EditGenerationDialog.vue';
import CodePreviewDialog from './codegen/CodePreviewDialog.vue';
import RecordsPanel from './codegen/RecordsPanel.vue';
import { runSequential } from './codegen/batch';
import { PERM_PREVIEW, PERMS_FORMAL_GENERATION } from './codegen/formalGeneration';
import { adminRoutePath } from '@/utils/route';
import { LIFECYCLE_STATUS_META, designerLocation } from './constants';
import { useBusinessMenuRefresh } from './composables/useBusinessMenuRefresh';
import { useLatestRequest } from './composables/useLatestRequest';

defineOptions({ name: 'BusinessMine' });

type CodegenTab = 'tables' | 'records';
type EditTab = 'config' | 'fields' | 'relations' | 'menu';

const CREATE_PERMISSION = 'development:business:create-visual';
const LOAD_PERMISSIONS = ['development:business:database-tables', 'development:business:inspect-database', 'development:business:create-from-database'];
const DELETE_PERMISSIONS = ['development:business:delete-module', 'development:business:save-schema'];
const EDIT_PERMISSIONS = ['development:business:save-schema', 'development:business:save-publish-config'];
const GENERATE_PERMISSIONS = [PERM_PREVIEW, ...PERMS_FORMAL_GENERATION];
const DYNAMIC_PUBLISH_PERMISSIONS = ['development:business:preview-publish', 'development:business:publish'];
/** 代码生成页（菜单 BusinessDevelopment，组件 development/business/mine）。 */
const CODEGEN_PAGE_PATH = adminRoutePath('/development/business/mine');
/** 打开弹窗的一次性 URL 意图（如插件中心「业务开发」入口 ?plugin=x&create=1），用完即移除。 */
const ROUTE_INTENTS = [
  { key: 'load', allowed: () => hasPermission(LOAD_PERMISSIONS, 'all'), open: () => { loadVisible.value = true; } },
  { key: 'create', allowed: () => hasPermission(CREATE_PERMISSION), open: () => createTable() }
];

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { refreshBusinessMenu } = useBusinessMenuRefresh(router);

const list = ref<BusinessModule[]>([]);
const total = ref(0);
const filtered = ref(false);
type TargetFilter = '' | 'core' | 'plugin';
const query = reactive({ page: 1, pageSize: 20, keyword: '', status: '', origin: '', target: '' as TargetFilter });
const selection = ref<BusinessModule[]>([]);
const deletingIds = reactive(new Set<number>());
const batchRemoving = ref(false);

const loadVisible = ref(false);
const createVisible = ref(false);
const createMounted = ref(false);
const syncVisible = ref(false);
const syncModule = ref<BusinessModule | null>(null);
const batchVisible = ref(false);
/** 打开批量弹窗时的选中快照：批量完成后 loadData 会清空表格勾选，弹窗的「重新预览」仍要用原来的模块。 */
const batchModules = ref<BusinessModule[]>([]);
const editVisible = ref(false);
const editModuleId = ref<number | null>(null);
const editTab = ref<EditTab>('config');
const previewVisible = ref(false);
const previewModule = ref<BusinessModule | null>(null);
const previewDialog = ref<InstanceType<typeof CodePreviewDialog> | null>(null);
const tableRef = ref<{ clearSelection?: () => void } | null>(null);

const listRequest = useLatestRequest(() => businessDevelopmentApi.modules({ ...query }));
const loading = listRequest.loading;
const loadError = computed(() => errorMessage(listRequest.error.value));
const emptyText = computed(() => filtered.value
  ? t('business.codegen.list.emptyFiltered', '没有符合筛选条件的数据表')
  : t('business.codegen.list.emptyAll', '还没有数据表，点击「装载数据表」或「新建表」开始'));

// keep-alive 缓存期间其他页面的路由变化不影响本页；重新激活时再按当前 URL 同步。
const active = ref(true);
const pageQuery = ref<LocationQuery>({ ...route.query });
const canViewRecords = computed(() => hasPermission('development:business:generations'));
const activeTab = computed<CodegenTab>({
  get: () => (pageQuery.value.tab === 'records' && canViewRecords.value ? 'records' : 'tables'),
  set: (tab) => {
    const next: LocationQuery = { ...route.query };
    if (tab === 'records') next.tab = 'records';
    else delete next.tab;
    void router.replace({ query: next });
  }
});

function syncPageQuery() {
  pageQuery.value = { ...route.query };
  const intents = ROUTE_INTENTS.filter((intent) => route.query[intent.key] === '1');
  if (!intents.length) return;
  // ?load=1 打开装载弹窗、?create=1 打开新建表弹窗；用完即从 URL 移除，刷新不会重复打开。
  const next: LocationQuery = { ...route.query };
  for (const intent of intents) {
    if (intent.allowed()) intent.open();
    delete next[intent.key];
  }
  void router.replace({ query: next });
}

watch(() => route.query, () => { if (active.value) syncPageQuery(); }, { immediate: true });
// 首次激活紧跟挂载，上面的 immediate watch 已处理；之后每次从缓存恢复时再按当前 URL 同步。
let activations = 0;
onActivated(() => {
  active.value = true;
  if (activations++ > 0) syncPageQuery();
});
onDeactivated(() => { active.value = false; });

async function loadData() {
  try {
    const data = await listRequest.execute();
    if (!data || listRequest.data.value !== data) return;
    list.value = data.list;
    total.value = data.total;
    // 列表刷新后（翻页、删除、生成）清空勾选，避免批量操作作用于过期的行。
    selection.value = [];
    tableRef.value?.clearSelection?.();
  } catch {
    // 错误由 useLatestRequest 暴露给 BusinessPageState。
  }
}

function search() {
  filtered.value = true;
  query.page = 1;
  void loadData();
}

function reset() {
  filtered.value = false;
  Object.assign(query, { page: 1, pageSize: 20, keyword: '', status: '', origin: '', target: '' });
  void loadData();
}

function createTable() {
  createMounted.value = true;
  createVisible.value = true;
}

function design(row: BusinessModule) {
  if (!row.form_id) return;
  void router.push(designerLocation(row));
}

async function openRuntime(row: BusinessModule) {
  if (!row.runtime_route || isPlugin(row)) return;
  await refreshBusinessMenu();
  await router.push(row.runtime_route);
}

function openPreview(row: BusinessModule) {
  previewModule.value = row;
  previewVisible.value = true;
}

function openPreviewById(id: number) {
  const row = list.value.find((item) => item.id === id) ?? batchModules.value.find((item) => item.id === id) ?? selection.value.find((item) => item.id === id);
  if (row) openPreview(row);
}

function openBatch() {
  batchModules.value = [...selection.value];
  batchVisible.value = true;
}

function openEdit(row: BusinessModule, tab: EditTab = 'config') {
  editModuleId.value = row.id;
  editTab.value = tab;
  editVisible.value = true;
}

// EditGenerationDialog 未暴露直接打开版本历史的入口：打开配置信息页签，版本历史在弹窗右上角。
function openVersions(row: BusinessModule) {
  openEdit(row, 'config');
}

function previewEdited() {
  const id = editModuleId.value;
  const row = list.value.find((item) => item.id === id);
  if (row) openPreview(row);
}

function canSync(row: BusinessModule) {
  return row.origin === 'database';
}

function openSync(row: BusinessModule) {
  if (!canSync(row)) return;
  syncModule.value = row;
  syncVisible.value = true;
}

async function dynamicPublish(row: BusinessModule) {
  if (isPlugin(row)) return;
  // 复用代码预览弹窗暴露的动态发布流程（含确认、previewPublish、publish 与菜单刷新），无需先打开预览。
  previewModule.value = row;
  await nextTick();
  await previewDialog.value?.dynamicPublish();
}

function openRecords(row: BusinessModule) {
  void router.push({ path: CODEGEN_PAGE_PATH, query: { ...route.query, tab: 'records', moduleId: String(row.id) } });
}

async function removeModule(row: BusinessModule) {
  if (!hasPermission(DELETE_PERMISSIONS, 'all') || deletingIds.has(row.id)) return;
  try {
    await ElMessageBox.confirm(
      t('business.mine.deleteConfirm', { name: row.name }, '确认删除业务“{name}”？对应的生成菜单和权限将同时清理，已生成源码与数据表不会自动删除。'),
      t('business.mine.deleteTitle', '删除业务'),
      { type: 'warning', confirmButtonText: t('common.remove', '删除'), cancelButtonText: t('common.cancel', '取消') },
    );
  } catch {
    return;
  }
  deletingIds.add(row.id);
  try {
    if (!hasPermission(DELETE_PERMISSIONS, 'all')) return;
    await businessDevelopmentApi.removeModule(row.id);
    await loadData();
    await refreshBusinessMenu();
  } finally {
    deletingIds.delete(row.id);
  }
}

async function removeSelected() {
  const rows = [...selection.value];
  if (!rows.length || batchRemoving.value || !hasPermission(DELETE_PERMISSIONS, 'all')) return;
  try {
    await ElMessageBox.confirm(
      t('business.codegen.list.batchRemoveConfirm', { count: rows.length }, '确认删除选中的 {count} 个业务？对应的生成菜单和权限将同时清理，已生成源码与数据表不会自动删除。'),
      t('business.codegen.list.batchRemoveTitle', '批量删除'),
      { type: 'warning', confirmButtonText: t('common.remove', '删除'), cancelButtonText: t('common.cancel', '取消') },
    );
  } catch {
    return;
  }
  batchRemoving.value = true;
  try {
    // 逐个删除：单个失败不影响其他模块，最后汇总结果。
    const results = await runSequential(rows, (row) => businessDevelopmentApi.removeModule(row.id, { showSuccessMsg: false, showErrorMsg: false }));
    const failed = results.filter((item) => !item.ok);
    const summary = t('business.codegen.list.batchRemoveResult', { ok: results.length - failed.length, failed: failed.length }, '删除完成：成功 {ok} 个，失败 {failed} 个');
    if (failed.length) ElMessage.warning(`${summary}；${failed.map((item) => `${item.item.name}：${item.ok ? '' : item.error}`).join('；')}`);
    else ElMessage.success(summary);
    await loadData();
    if (failed.length < results.length) await refreshBusinessMenu();
  } finally {
    batchRemoving.value = false;
  }
}

function isPlugin(row: BusinessModule) {
  return row.metadata?.target?.type === 'plugin';
}

function targetLabel(row: BusinessModule) {
  const target = row.metadata?.target;
  if (target?.type === 'plugin') return t('business.codegen.list.targetPluginCode', { code: target.pluginCode ?? '' }, '插件 {code}');
  return t('business.codegen.list.targetCore', '核心');
}

function isPluginPendingInstall(row: BusinessModule) {
  return isPlugin(row) && ['generated', 'completed'].includes(row.generation_status ?? '');
}

function originLabel(value: string) {
  return ({ visual: t('business.codegen.list.originVisual', '可视化新建'), database: t('business.codegen.list.originDatabase', '数据库装载') } as Record<string, string>)[value] || value;
}

function lifecycleMeta(value: string) {
  return LIFECYCLE_STATUS_META[value as keyof typeof LIFECYCLE_STATUS_META];
}

function lifecycleLabel(value: string) {
  const meta = lifecycleMeta(value);
  return meta ? t(meta.labelKey) : value;
}

function lifecycleDescription(value: string) {
  const meta = lifecycleMeta(value);
  return meta ? t(meta.descriptionKey) : value;
}

function lifecycleType(value: string): TagProps['type'] {
  const tone = lifecycleMeta(value)?.tone;
  return tone === 'primary' ? undefined : tone;
}

function errorMessage(error: unknown) {
  if (!error) return '';
  if (isBusinessApiError(error)) return error.msg;
  return error instanceof Error ? error.message : String(error);
}

onMounted(loadData);
</script>

<style scoped>
small { color: var(--el-text-color-secondary); }
.codegen-tabs :deep(.el-tabs__header) { margin-bottom: 12px; }
.codegen-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.codegen-toolbar .el-button + .el-button { margin-left: 0; }
.codegen-toolbar .el-button i { margin-right: 4px; }
.codegen-toolbar__count { margin-left: 4px; color: var(--el-text-color-secondary); font-size: 13px; }
.codegen-search-form :deep(.search-form__grid) {
  grid-template-columns: minmax(320px, 1.6fr) repeat(3, minmax(190px, 1fr)) auto;
  gap: 12px 16px;
  align-items: end;
}
.codegen-search-form :deep(.search-form__actions) { justify-self: end; }
.codegen-search-form :deep(.search-form__actions .inline-flex) { justify-content: flex-end; }
.codegen-table-name { color: var(--el-text-color-primary); font-family: var(--el-font-family); }
.codegen-table-name + small { display: block; font-size: 12px; }
.codegen-status { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; }
.codegen-recovery { color: var(--el-color-warning); font-size: 12px; }
.business-row-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 12px; }
.business-row-actions :deep(.el-button + .el-button) { margin-left: 0; }
.codegen-action-slot, .codegen-action-trigger { display: inline-flex; }
.codegen-more-icon { margin-left: 2px; font-size: 12px; }
@media (max-width: 640px) {
  .codegen-search-form :deep(.search-form__grid) { grid-template-columns: minmax(0, 1fr); }
  .codegen-search-form :deep(.search-form__actions) { justify-self: stretch; }
  .codegen-search-form :deep(.search-form__actions .inline-flex) { justify-content: flex-start; }
  .business-row-actions { min-width: 160px; }
}
</style>

<style>
/* 下拉菜单 teleport 到 body，scoped 样式匹配不到，单独用非 scoped 的唯一类名。 */
.codegen-danger-item:not(.is-disabled) { color: var(--el-color-danger); }
</style>
