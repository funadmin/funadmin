<template>
  <PageWrapper :title="t('business.mine.title', '我的业务')" :subtitle="t('business.mine.subtitle', '统一查看、设计、发布和生成业务模块')">
    <DataTableShell storage-key="development-business-mine" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="search" @reset="reset">
          <el-form-item :label="t('business.mine.keyword', '关键词')"><el-input v-model="query.keyword" :placeholder="t('business.mine.keywordPlaceholder', '业务名称或标识')" clearable /></el-form-item>
          <el-form-item :label="t('business.mine.origin', '来源')">
            <el-select v-model="query.origin" :placeholder="t('business.mine.all', '全部')" clearable class="!w-36">
              <el-option :label="t('business.mine.originVisual', '可视化')" value="visual" /><el-option :label="t('business.mine.originDatabase', '数据库')" value="database" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('common.status', '状态')">
            <el-select v-model="query.status" :placeholder="t('business.mine.all', '全部')" clearable class="!w-36">
              <el-option :label="t('business.mine.statusDraft', '草稿')" value="draft" /><el-option :label="t('business.mine.statusDynamicPublished', '动态发布')" value="dynamic_published" /><el-option :label="t('business.mine.statusPublished', '已发布')" value="published" /><el-option :label="t('business.mine.statusDisabled', '已停用')" value="disabled" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button v-if="creationPath" data-action="create-business" type="primary" plain @click="router.push({ path: creationPath, query: route.query })"><i class="i-ep-plus" />{{ t('business.mine.create', '创建业务') }}</el-button>
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <BusinessPageState
          :loading="loading"
          :error="loadError"
          :on-retry="loadData"
        >
          <el-table :data="list" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" :empty-text="emptyText">
            <el-table-column prop="name" :label="t('business.mine.module', '业务模块')" min-width="180"><template #default="{ row }"><div>{{ row.name }}</div><small>{{ row.code }}</small></template></el-table-column>
            <el-table-column prop="origin" :label="t('business.mine.origin', '来源')" width="110"><template #default="{ row }"><el-tag effect="plain">{{ originLabel(row.origin) }}</el-tag></template></el-table-column>
            <el-table-column :label="t('business.mine.pluginOwner', '所属插件')" min-width="160"><template #default="{ row }">{{ row.metadata?.target?.type === 'plugin' ? row.metadata.target.pluginCode : t('business.mine.coreAdmin', '核心后台') }}<small v-if="row.metadata?.target?.locked"> · {{ t('business.mine.locked', '已锁定') }}</small></template></el-table-column>
            <el-table-column prop="table_name" :label="t('business.mine.tableName', '数据表')" min-width="160" />
            <el-table-column prop="lifecycle_status" :label="t('business.mine.lifecycleStatus', '发布状态')" width="130">
              <template #default="{ row }"><el-tag :type="lifecycleType(row.lifecycle_status)" :title="lifecycleDescription(row.lifecycle_status)">{{ row.metadata?.target?.type === 'plugin' && ['generated', 'completed'].includes(row.generation_status) ? t('business.mine.generatedPendingInstall', '源码已生成，待安装／更新发布') : lifecycleLabel(row.lifecycle_status) }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="generation_status" :label="t('business.mine.generationStatus', '生成状态')" width="120"><template #default="{ row }"><span v-if="['recovering', 'recovery_required'].includes(row.recovery_status)">{{ row.recovery_status === 'recovering' ? t('business.status.recovery.recovering.label') : t('business.status.recovery.recovery_required.label') }}</span><GenerationStatusTag v-else :status="row.generation_status" /></template></el-table-column>
            <el-table-column prop="updated_at" :label="t('business.mine.updatedAt', '更新时间')" width="170" />
            <el-table-column :label="t('common.operation', '操作')" min-width="250" fixed="right">
              <template #default="{ row }">
                <div class="business-row-actions" :data-row-actions="row.id">
                  <el-button
                    link
                    type="primary"
                    v-perm="'development:business:save'"
                    :data-design="row.id"
                    :disabled="!row.form_id"
                    :title="row.form_id ? t('business.mine.designTitle', '设计业务模块') : t('business.mine.noFormTitle', '缺少表单定义，无法进入设计器')"
                    @click="design(row as BusinessModule)"
                  >{{ t('business.mine.design', '设计') }}</el-button>
                  <el-button v-if="row.runtime_route && row.metadata?.target?.type !== 'plugin'" link :data-runtime="row.id" @click="openRuntime(row as BusinessModule)">{{ t('business.mine.runtime', '运行时') }}</el-button>
                  <el-button
                    link
                    v-perm="'development:business:generate'"
                    :data-preview="row.id"
                    :loading="previewingIds.has(row.id)"
                    :aria-label="t('business.mine.previewAria', { name: row.name }, '{name}生成预览')"
                    @click="previewGeneration(row as BusinessModule)"
                  >{{ t('business.mine.preview', '生成预览') }}</el-button>
                  <el-button link v-perm="'development:business:records'" @click="router.push({ path: '/development/business/records', query: { moduleId: row.id } })">{{ t('business.mine.records', '记录') }}</el-button>
                  <el-button link type="danger" v-perm="'development:business:save'" :loading="deletingIds.has(row.id)" @click="removeModule(row as BusinessModule)">{{ t('common.remove', '删除') }}</el-button>
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
              :aria-label="t('business.mine.paginationAria', '业务模块分页')"
              @change="loadData"
            />
          </div>
        </BusinessPageState>
      </template>
    </DataTableShell>

    <el-dialog v-model="previewVisible" :title="t('business.mine.previewDialogTitle', '正式生成预览')" width="min(900px, 94vw)" class="business-preview-dialog">
      <div class="sr-only" aria-live="polite" aria-atomic="true">{{ previewAnnouncement }}</div>
      <el-alert :title="t('business.mine.previewNotice', '当前页面仅提供生成预览，不会执行正式生成。')" type="info" show-icon class="mb-3" />
      <el-alert v-if="previewError" :title="previewError" type="error" show-icon class="mb-3" />
      <div v-if="activePreviewLoading" class="preview-loading" aria-busy="true">{{ t('business.mine.previewLoading', '正在加载生成预览…') }}</div>
      <GenerationPlanView v-else-if="preview" :plan="preview.plan" :conflicts="preview.conflicts" />
      <template #footer><el-button @click="previewVisible = false">{{ t('common.close', '关闭') }}</el-button></template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/store/modules/user';
import { ElMessageBox } from 'element-plus';
import type { TagProps } from 'element-plus';
import { businessDevelopmentApi, isBusinessApiError, type BusinessFormalGenerationPreview, type BusinessModule } from '@/api/development/business';
import BusinessPageState from './components/BusinessPageState.vue';
import GenerationPlanView from './components/GenerationPlanView.vue';
import GenerationStatusTag from './components/GenerationStatusTag.vue';
import { LIFECYCLE_STATUS_META } from './constants';
import { useBusinessMenuRefresh } from './composables/useBusinessMenuRefresh';
import { useLatestRequest } from './composables/useLatestRequest';

defineOptions({ name: 'BusinessMine' });
const router = useRouter();
const route = useRoute();
const user = useUserStore();
const creationPath = computed(() => {
  const permissions = user.permissions;
  if (permissions.some(permission => ['*', '*:*:*', 'development.business:createvisual'].includes(permission))) return '/development/business/visual';
  if (permissions.includes('development.business:inspectdatabase')) return '/development/business/database';
  return '';
});
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
const deletingIds = reactive(new Set<number>());
const activePreviewModuleId = ref<number | null>(null);
const previewNonce = crypto.randomUUID();
const listRequest = useLatestRequest(() => businessDevelopmentApi.modules({ ...query }));
const previewRequest = useLatestRequest((moduleId: number) => businessDevelopmentApi.previewFormalGeneration(moduleId, previewNonce));
const loading = listRequest.loading;
const loadError = computed(() => errorMessage(listRequest.error.value));
const emptyText = computed(() => filtered.value ? t('business.mine.emptyFiltered', '没有符合筛选条件的业务模块') : t('business.mine.emptyAll', '还没有业务模块，可通过创建业务选择新表或已有表'));
const activePreviewLoading = computed(() => activePreviewModuleId.value !== null && previewingIds.has(activePreviewModuleId.value));
const previewAnnouncement = computed(() => {
  if (activePreviewLoading.value) return t('business.mine.previewLoadingAnnounce', '正在加载生成预览');
  if (previewError.value) return t('business.mine.previewFailedPrefix', { msg: previewError.value }, '生成预览失败：{msg}');
  if (preview.value) return t('business.mine.previewLoaded', '生成预览已加载');
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
  if (!row.runtime_route || row.metadata?.target?.type === 'plugin') return;
  await refreshBusinessMenu();
  await router.push(row.runtime_route);
}

async function removeModule(row: BusinessModule) {
  if (deletingIds.has(row.id)) return;
  await ElMessageBox.confirm(
    t('business.mine.deleteConfirm', { name: row.name }, '确认删除业务“{name}”？对应的生成菜单和权限将同时清理，已生成源码与数据表不会自动删除。'),
    t('business.mine.deleteTitle', '删除业务'),
    { type: 'warning', confirmButtonText: t('common.remove', '删除'), cancelButtonText: t('common.cancel', '取消') },
  );
  deletingIds.add(row.id);
  try {
    await businessDevelopmentApi.removeModule(row.id);
    await loadData();
    await refreshBusinessMenu();
  } finally {
    deletingIds.delete(row.id);
  }
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
    if (activePreviewModuleId.value === row.id) previewError.value = errorMessage(error) || t('business.mine.previewFailed', '生成预览失败');
  } finally {
    previewingIds.delete(row.id);
  }
}

function originLabel(value: string) {
  return ({ visual: t('business.mine.originVisual', '可视化'), database: t('business.mine.originDatabase', '数据库') } as Record<string, string>)[value] || value;
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
