<template>
  <PageWrapper :title="t('business.records.title', '生成记录')" :subtitle="pageSubtitle">
    <DataTableShell storage-key="development-business-records" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="search" @reset="reset">
          <el-form-item :label="t('business.records.module', '业务模块')" :error="moduleIdError">
            <el-select
              v-model="query.moduleId"
              clearable
              filterable
              :loading="modulesLoading"
              :placeholder="t('business.records.modulePlaceholder', '选择业务模块')"
              class="module-select"
            >
              <el-option v-for="module in modules" :key="module.id" :label="`${module.name}（${module.code}）`" :value="module.id" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('common.status', '状态')">
            <el-select v-model="query.status" clearable :placeholder="t('business.records.statusPlaceholder', '全部状态')" class="status-select">
              <el-option v-for="status in statuses" :key="status" :label="status" :value="status" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <div class="module-context" aria-live="polite">
          <div v-if="selectedModule">
            <strong>{{ selectedModule.name }}</strong>
            <span>{{ selectedModule.code }}</span>
          </div>
          <span v-else>{{ t('business.records.allModules', '全部业务模块') }}</span>
          <el-button data-action="back-to-mine" @click="router.push('/development/business/mine')"><i class="i-ep-back" /> {{ t('business.records.backToMine', '返回我的业务') }}</el-button>
        </div>
      </template>

      <template #default="{ size, stripe, border, headerCellStyle }">
        <BusinessPageState
          :loading="loading"
          :error="pageError"
          :empty="!loading && !pageError && list.length === 0"
          :empty-text="t('business.records.empty', '暂无生成记录')"
          :on-retry="loadData"
        >
          <div class="records-table-wrap" :aria-label="t('business.records.tableAria', '生成记录列表')" tabindex="0">
            <el-table :data="list" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle">
              <el-table-column prop="id" label="ID" width="80" />
              <el-table-column :label="t('business.records.module', '业务模块')" min-width="180">
                <template #default="{ row }">
                  <div>{{ moduleFor(row as BusinessGeneration)?.name || t('business.records.moduleFallback', { id: generationModuleId(row as BusinessGeneration) || '-' }, '模块 #{id}') }}</div>
                  <small>{{ moduleFor(row as BusinessGeneration)?.code || '-' }}</small>
                </template>
              </el-table-column>
              <el-table-column :label="t('business.records.mode', '模式')" min-width="110">
                <template #default="{ row }">{{ row.generationMode || row.generation_mode || '-' }}</template>
              </el-table-column>
              <el-table-column :label="t('common.status', '状态')" min-width="120">
                <template #default="{ row }"><GenerationStatusTag :status="row.status" /></template>
              </el-table-column>
              <el-table-column :label="t('business.records.recoveryStatus', '恢复状态')" min-width="130">
                <template #default="{ row }"><GenerationStatusTag :status="row.recoveryStatus || row.recovery_status" kind="recovery" /></template>
              </el-table-column>
              <el-table-column :label="t('business.records.failureSummary', '失败摘要')" min-width="180" show-overflow-tooltip>
                <template #default="{ row }">{{ failureSummary(row as BusinessGeneration) }}</template>
              </el-table-column>
              <el-table-column :label="t('business.records.updatedAt', '更新时间')" min-width="170">
                <template #default="{ row }">{{ row.updatedAt || row.updated_at || '-' }}</template>
              </el-table-column>
              <el-table-column :label="t('common.operation', '操作')" width="100" fixed="right">
                <template #default="{ row }">
                  <el-button
                    link
                    type="primary"
                    :data-action="`detail-${row.id}`"
                    :loading="detailLoadingId === row.id"
                    :aria-label="t('business.records.detailAria', { id: row.id }, '查看生成记录 {id} 详情')"
                    @click="showDetail(row.id)"
                  >{{ t('common.detail', '详情') }}</el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
          <div class="pagination-wrap">
            <el-pagination
              v-model:current-page="query.page"
              v-model:page-size="query.pageSize"
              :total="total"
              :page-sizes="[10, 20, 50, 100]"
              layout="total, sizes, prev, pager, next, jumper"
              @change="changePage"
            />
          </div>
        </BusinessPageState>
      </template>
    </DataTableShell>

    <GenerationDetailDrawer v-model="detailVisible" :generation="drawerGeneration" @recovered="refreshAfterRecovery" />
    <div v-if="detailVisible && canRecover" class="recovery-action" role="region" :aria-label="t('business.records.recoveryRegionAria', '恢复操作')">
      <el-button
        data-action="recover"
        type="warning"
        :loading="recovering"
        :disabled="recovering"
        @click="recoverDetail"
      >{{ t('business.recovery', '恢复') }}</el-button>
    </div>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRoute, useRouter } from 'vue-router';
import {
  businessDevelopmentApi,
  type BusinessGeneration,
  type BusinessModule
} from '@/api/development/business';
import BusinessPageState from './components/BusinessPageState.vue';
import GenerationDetailDrawer from './components/GenerationDetailDrawer.vue';
import GenerationStatusTag from './components/GenerationStatusTag.vue';
import { useLatestRequest } from './composables/useLatestRequest';

defineOptions({ name: 'BusinessRecords' });

type GenerationQuery = { page: number; pageSize: number; moduleId?: number; status?: string };

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const statuses = ['planned', 'completed', 'failed', 'conflict'];
const query = reactive<GenerationQuery>({ page: 1, pageSize: 20, moduleId: undefined, status: '' });
const list = ref<BusinessGeneration[]>([]);
const modules = ref<BusinessModule[]>([]);
const total = ref(0);
const detailVisible = ref(false);
const detail = ref<BusinessGeneration | null>(null);
const detailLoadingId = ref<number>();
const recovering = ref(false);
const moduleIdError = ref('');
const pageError = ref<string | Error | null>(null);
const modulesLoading = ref(false);
const initialized = ref(false);

const listRequest = useLatestRequest((params: GenerationQuery) => businessDevelopmentApi.generations(params));
const detailRequest = useLatestRequest((id: number) => businessDevelopmentApi.generation(id));
const loading = computed(() => listRequest.loading.value);
const selectedModule = computed(() => modules.value.find((module) => module.id === query.moduleId));
const pageSubtitle = computed(() => selectedModule.value
  ? t('business.records.subtitleScoped', { name: selectedModule.value.name, code: selectedModule.value.code }, '{name}（{code}）的 managed 生成、冲突与恢复状态')
  : t('business.records.subtitleAll', '查看全部业务模块的 managed 生成、冲突与恢复状态'));
const canRecover = computed(() => detail.value?.availableActions?.includes('recover') === true);
// 页面统一承担恢复确认，drawer 继续负责结构化展示，避免未确认即调用恢复接口。
const drawerGeneration = computed(() => detail.value ? { ...detail.value, availableActions: detail.value.availableActions?.filter((action) => action !== 'recover') } : null);

const positiveInteger = (value: unknown, fallback: number) => {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
};

const parseModuleId = (value: unknown): number | undefined => {
  if (value === undefined || value === null || value === '') return undefined;
  const parsed = Number(value);
  if (Number.isInteger(parsed) && parsed > 0) return parsed;
  return undefined;
};

const syncFromRoute = () => {
  const rawModuleId = route.query.moduleId;
  query.moduleId = parseModuleId(rawModuleId);
  moduleIdError.value = rawModuleId !== undefined && query.moduleId === undefined ? t('business.records.moduleIdInvalid', '模块 ID 必须为正整数') : '';
  query.page = positiveInteger(route.query.page, 1);
  query.pageSize = positiveInteger(route.query.pageSize, 20);
  query.status = typeof route.query.status === 'string' && statuses.includes(route.query.status) ? route.query.status : '';
};

const requestParams = (): GenerationQuery => ({
  page: query.page,
  pageSize: query.pageSize,
  ...(query.moduleId ? { moduleId: query.moduleId } : {}),
  ...(query.status ? { status: query.status } : {})
});

async function loadData() {
  if (moduleIdError.value) {
    listRequest.invalidate();
    list.value = [];
    total.value = 0;
    return;
  }
  pageError.value = null;
  try {
    const data = await listRequest.execute(requestParams());
    if (!data || data !== listRequest.data.value) return;
    list.value = data.list;
    total.value = data.total;
  } catch (error) {
    pageError.value = error instanceof Error ? error : t('business.records.loadFailed', '生成记录加载失败');
  }
}

async function loadModules() {
  modulesLoading.value = true;
  try {
    const data = await businessDevelopmentApi.modules({ page: 1, pageSize: 100 });
    modules.value = data.list;
  } catch {
    modules.value = [];
  } finally {
    modulesLoading.value = false;
  }
}

async function syncUrlAndLoad() {
  listRequest.invalidate();
  const nextQuery: Record<string, string> = { page: String(query.page), pageSize: String(query.pageSize) };
  if (query.moduleId) nextQuery.moduleId = String(query.moduleId);
  if (query.status) nextQuery.status = query.status;
  await router.replace({ query: nextQuery });
  await loadData();
}

function search() {
  query.page = 1;
  moduleIdError.value = '';
  void syncUrlAndLoad();
}

function reset() {
  Object.assign(query, { page: 1, pageSize: 20, moduleId: undefined, status: '' });
  moduleIdError.value = '';
  void syncUrlAndLoad();
}

function changePage() {
  void syncUrlAndLoad();
}

async function showDetail(id: number) {
  if (detailLoadingId.value !== undefined) return;
  detailLoadingId.value = id;
  try {
    const data = await detailRequest.execute(id);
    if (!data || data !== detailRequest.data.value) return;
    detail.value = data;
    detailVisible.value = true;
  } catch (error) {
    pageError.value = error instanceof Error ? error : t('business.records.detailLoadFailed', '生成记录详情加载失败');
  } finally {
    if (!detailRequest.loading.value) detailLoadingId.value = undefined;
  }
}

async function recoverDetail() {
  if (!detail.value || !canRecover.value || recovering.value) return;
  recovering.value = true;
  try {
    await ElMessageBox.confirm(t('business.records.recoverConfirm', '恢复将回滚本次生成产生的变更，是否继续？'), t('business.records.recoverConfirmTitle', '确认恢复'), { type: 'warning' });
    await businessDevelopmentApi.recoverGeneration(detail.value.id, detail.value.recoveryStatus || detail.value.recovery_status || 'recovery_required');
    await refreshAfterRecovery();
    ElMessage.success(t('business.records.recoverSuccess', '恢复成功'));
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') pageError.value = error instanceof Error ? error : t('business.records.recoverFailed', '恢复失败');
  } finally {
    recovering.value = false;
  }
}

async function refreshAfterRecovery() {
  const id = detail.value?.id;
  await loadData();
  if (!id) return;
  const refreshed = await detailRequest.execute(id);
  if (refreshed) detail.value = refreshed;
}

function generationModuleId(row: BusinessGeneration) {
  return row.businessModuleId || row.business_module_id;
}

function moduleFor(row: BusinessGeneration) {
  const moduleId = generationModuleId(row);
  return modules.value.find((module) => module.id === moduleId);
}

function failureSummary(row: BusinessGeneration) {
  return row.error?.code || (row.status === 'failed' || row.status === 'conflict' ? t('business.records.failedSummary', '生成失败，查看详情') : '-');
}

watch(() => route.query.moduleId, (value, previous) => {
  if (!initialized.value || value === previous) return;
  syncFromRoute();
  query.page = 1;
  void loadData();
});

watch(() => [route.query.page, route.query.pageSize, route.query.status], (value, previous) => {
  if (!initialized.value || value.every((item, index) => item === previous?.[index])) return;
  syncFromRoute();
  void loadData();
});

onMounted(async () => {
  syncFromRoute();
  initialized.value = true;
  await Promise.all([loadModules(), loadData()]);
});
</script>

<style scoped>
.module-select { width: min(320px, 72vw); }
.status-select { width: 144px; }
.module-context { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; }
.module-context > div { display: flex; flex-direction: column; }
.module-context span, small { color: var(--el-text-color-secondary); }
.records-table-wrap { width: 100%; overflow-x: auto; border-radius: var(--el-border-radius-base); }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; overflow-x: auto; }
.recovery-action { position: fixed; right: 32px; bottom: 24px; z-index: 3001; }
@media (max-width: 768px) {
  .module-context { align-items: flex-start; flex-direction: column; }
  .module-select, .status-select { width: 100%; }
  .pagination-wrap { justify-content: flex-start; }
  .recovery-action { right: 16px; bottom: 16px; }
}
</style>
