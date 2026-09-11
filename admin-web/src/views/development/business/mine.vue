<template>
  <PageWrapper title="我的业务" subtitle="统一查看、设计、发布和生成业务模块">
    <DataTableShell storage-key="development-business-mine" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="search" @reset="reset">
          <el-form-item label="关键词"><el-input v-model="query.keyword" placeholder="业务名称或标识" clearable /></el-form-item>
          <el-form-item label="来源">
            <el-select v-model="query.origin" placeholder="全部" clearable class="!w-36">
              <el-option label="可视化" value="visual" /><el-option label="数据库" value="database" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="query.status" placeholder="全部" clearable class="!w-36">
              <el-option label="草稿" value="draft" /><el-option label="动态发布" value="dynamic_published" /><el-option label="已发布" value="published" /><el-option label="已停用" value="disabled" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button type="primary" v-perm="'development:business:save'" @click="router.push('/development/business/visual')"><i class="i-ep-plus" />可视化创建</el-button>
        <el-button v-perm="'development:business:inspect'" @click="router.push('/development/business/database')">采纳数据表</el-button>
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <BusinessPageState
          :loading="loading"
          :error="loadError"
          :empty="!loading && !loadError && list.length === 0"
          :empty-text="emptyText"
          :on-retry="loadData"
        >
          <el-table :data="list" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle">
            <el-table-column prop="name" label="业务模块" min-width="180"><template #default="{ row }"><div>{{ row.name }}</div><small>{{ row.code }}</small></template></el-table-column>
            <el-table-column prop="origin" label="来源" width="110"><template #default="{ row }"><el-tag effect="plain">{{ originLabel(row.origin) }}</el-tag></template></el-table-column>
            <el-table-column prop="table_name" label="数据表" min-width="160" />
            <el-table-column prop="lifecycle_status" label="发布状态" width="130">
              <template #default="{ row }"><el-tag :type="lifecycleType(row.lifecycle_status)" :title="lifecycleDescription(row.lifecycle_status)">{{ lifecycleLabel(row.lifecycle_status) }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="generation_status" label="生成状态" width="120"><template #default="{ row }"><GenerationStatusTag :status="row.generation_status" /></template></el-table-column>
            <el-table-column prop="updated_at" label="更新时间" width="170" />
            <el-table-column label="操作" min-width="250" fixed="right">
              <template #default="{ row }">
                <div class="business-row-actions" :data-row-actions="row.id">
                  <el-button
                    link
                    type="primary"
                    v-perm="'development:business:save'"
                    :data-design="row.id"
                    :disabled="!row.form_id"
                    :title="row.form_id ? '设计业务模块' : '缺少表单定义，无法进入设计器'"
                    @click="design(row as BusinessModule)"
                  >设计</el-button>
                  <el-button v-if="row.runtime_route" link :data-runtime="row.id" @click="openRuntime(row as BusinessModule)">运行时</el-button>
                  <el-button
                    link
                    v-perm="'development:business:generate'"
                    :data-preview="row.id"
                    :loading="previewingIds.has(row.id)"
                    :aria-label="`${row.name}生成预览`"
                    @click="previewGeneration(row as BusinessModule)"
                  >生成预览</el-button>
                  <el-button link v-perm="'development:business:records'" @click="router.push({ path: '/development/business/records', query: { moduleId: row.id } })">记录</el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
          <div class="business-pagination mt-4 flex justify-end">
            <el-pagination
              v-model:current-page="query.page"
              v-model:page-size="query.pageSize"
              :total="total"
              :page-sizes="[10, 20, 50, 100]"
              layout="total, sizes, prev, pager, next, jumper"
              aria-label="业务模块分页"
              @change="loadData"
            />
          </div>
        </BusinessPageState>
      </template>
    </DataTableShell>

    <el-dialog v-model="previewVisible" title="正式生成预览" width="min(900px, 94vw)" class="business-preview-dialog">
      <div class="sr-only" aria-live="polite" aria-atomic="true">{{ previewAnnouncement }}</div>
      <el-alert title="当前页面仅提供生成预览，不会执行正式生成。" type="info" show-icon class="mb-3" />
      <el-alert v-if="previewError" :title="previewError" type="error" show-icon class="mb-3" />
      <div v-if="activePreviewLoading" class="preview-loading" aria-busy="true">正在加载生成预览…</div>
      <GenerationPlanView v-else-if="preview" :plan="preview.plan" :conflicts="preview.conflicts" />
      <template #footer><el-button @click="previewVisible = false">关闭</el-button></template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type { TagProps } from 'element-plus';
import { businessDevelopmentApi, type BusinessFormalGenerationPreview, type BusinessModule } from '@/api/development/business';
import BusinessPageState from './components/BusinessPageState.vue';
import GenerationPlanView from './components/GenerationPlanView.vue';
import GenerationStatusTag from './components/GenerationStatusTag.vue';
import { LIFECYCLE_STATUS_META } from './constants';
import { useBusinessMenuRefresh } from './composables/useBusinessMenuRefresh';
import { useLatestRequest } from './composables/useLatestRequest';

defineOptions({ name: 'BusinessMine' });
const router = useRouter();
const { t } = useI18n();
const { refreshBusinessMenu } = useBusinessMenuRefresh(router);
const list = ref<BusinessModule[]>([]);
const total = ref(0);
const filtered = ref(false);
const query = reactive({ page: 1, pageSize: 20, keyword: '', status: '', origin: '' });
const previewVisible = ref(false);
const preview = ref<BusinessFormalGenerationPreview | null>(null);
const previewError = ref('');
const previewingIds = reactive(new Set<number>());
const activePreviewModuleId = ref<number | null>(null);
const previewNonce = crypto.randomUUID();
const listRequest = useLatestRequest(() => businessDevelopmentApi.modules({ ...query }));
const previewRequest = useLatestRequest((moduleId: number) => businessDevelopmentApi.previewFormalGeneration(moduleId, previewNonce));
const loading = listRequest.loading;
const loadError = computed(() => errorMessage(listRequest.error.value));
const emptyText = computed(() => filtered.value ? '没有符合筛选条件的业务模块' : '还没有业务模块，可先创建或采纳数据表');
const activePreviewLoading = computed(() => activePreviewModuleId.value !== null && previewingIds.has(activePreviewModuleId.value));
const previewAnnouncement = computed(() => {
  if (activePreviewLoading.value) return '正在加载生成预览';
  if (previewError.value) return `生成预览失败：${previewError.value}`;
  if (preview.value) return '生成预览已加载';
  return '';
});

async function loadData() {
  try {
    const data = await listRequest.execute();
    if (!data || listRequest.data.value !== data) return;
    list.value = data.list;
    total.value = data.total;
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
  Object.assign(query, { page: 1, pageSize: 20, keyword: '', status: '', origin: '' });
  void loadData();
}

function design(row: BusinessModule) {
  if (!row.form_id) return;
  void router.push({ path: '/development/business/designer', query: { id: String(row.form_id), moduleId: String(row.id) } });
}

async function openRuntime(row: BusinessModule) {
  if (!row.runtime_route) return;
  await refreshBusinessMenu();
  await router.push(row.runtime_route);
}

async function previewGeneration(row: BusinessModule) {
  if (previewingIds.has(row.id)) return;
  previewingIds.add(row.id);
  activePreviewModuleId.value = row.id;
  previewVisible.value = true;
  preview.value = null;
  previewError.value = '';
  try {
    const result = await previewRequest.execute(row.id);
    if (!result || previewRequest.data.value !== result || activePreviewModuleId.value !== row.id) return;
    preview.value = result;
  } catch (error) {
    if (activePreviewModuleId.value === row.id) previewError.value = errorMessage(error) || '生成预览失败';
  } finally {
    previewingIds.delete(row.id);
  }
}

function originLabel(value: string) {
  return ({ visual: '可视化', database: '数据库' } as Record<string, string>)[value] || value;
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
  return error instanceof Error ? error.message : String(error);
}

onMounted(loadData);
</script>

<style scoped>
small { color: var(--el-text-color-secondary); }
.business-row-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 0; }
.preview-loading { min-height: 160px; display: grid; place-items: center; color: var(--el-text-color-secondary); }
:deep(.business-preview-dialog .el-dialog__body) { max-height: 70vh; overflow: auto; }
@media (max-width: 640px) {
  .business-row-actions { min-width: 132px; }
  .business-row-actions :deep(.el-button) { margin-left: 0; margin-right: 8px; }
  .business-pagination { justify-content: flex-start; overflow-x: auto; padding-bottom: 4px; }
  :deep(.business-preview-dialog) { margin-top: 4vh; }
}
</style>
