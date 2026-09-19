<template>
  <PageWrapper :title="t('applications.portalTitle', '应用门户')" :subtitle="t('applications.portalSubtitle', '仅展示当前身份获准查看的企业应用')">
    <div class="stats-grid">
      <el-card><el-statistic :title="t('applications.statAvailable', '可进入')" :value="availableCount" /></el-card>
      <el-card><el-statistic :title="t('applications.statAssigned', '已分配')" :value="applications.length" /></el-card>
      <el-card><el-statistic :title="t('applications.statUnavailable', '暂不可用')" :value="applications.length - availableCount" /></el-card>
    </div>

    <div class="toolbar">
      <el-input
        v-model="keyword"
        class="search"
        clearable
        :aria-label="t('applications.portalSearchAria', '搜索应用')"
        :placeholder="t('applications.portalSearchPlaceholder', '搜索应用名称或代码')"
      />
      <el-radio-group v-model="viewMode" :aria-label="t('applications.portalViewAria', '应用展示方式')">
        <el-radio-button value="card">{{ t('applications.viewCard', '卡片') }}</el-radio-button>
        <el-radio-button value="list">{{ t('applications.viewList', '列表') }}</el-radio-button>
      </el-radio-group>
    </div>

    <el-empty v-if="!filtered.length" :description="t('applications.portalNoMatch', '没有匹配的应用')" />
    <div v-else-if="viewMode === 'card'" class="application-grid">
      <el-card v-for="item in filtered" :key="item.id" class="application-card">
        <template #header>
          <div class="card-header">
            <strong>{{ item.name }}</strong>
            <el-tag :type="item.available ? 'success' : 'info'">
              {{ item.available ? t('applications.statAvailable', '可进入') : item.status }}
            </el-tag>
          </div>
        </template>
        <p class="description">{{ item.description || t('applications.noDescription', '暂无说明') }}</p>
        <el-button type="primary" plain :disabled="!item.available" @click="launch(item)">{{ t('applications.enterApp', '进入应用') }}</el-button>
        <p v-if="item.availabilityReason" class="reason" role="status">{{ item.availabilityReason }}</p>
      </el-card>
    </div>

    <el-table v-else :data="filtered" stripe :aria-label="t('applications.portalTableAria', '应用列表')">
      <el-table-column prop="name" :label="t('applications.colApp', '应用')" min-width="180" />
      <el-table-column prop="code" :label="t('applications.portalColCode', '代码')" min-width="140" />
      <el-table-column prop="description" :label="t('applications.desc', '说明')" min-width="220" show-overflow-tooltip />
      <el-table-column :label="t('common.status', '状态')" width="140">
        <template #default="scope">
          <el-tag :type="scope.row.available ? 'success' : 'info'">
            {{ scope.row.available ? t('applications.statAvailable', '可进入') : scope.row.availabilityReason }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('common.operation', '操作')" width="120" fixed="right">
        <template #default="scope">
          <el-button type="primary" link :disabled="!scope.row.available" @click="launch(scope.row as EnterpriseApplication)">{{ t('applications.enter', '进入') }}</el-button>
        </template>
      </el-table-column>
    </el-table>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { applicationApi, type EnterpriseApplication } from '@/api/identity/applications';

defineOptions({ name: 'ApplicationPortal' });

const { t } = useI18n();

const applications = ref<EnterpriseApplication[]>([]);
const keyword = ref('');
const viewMode = ref<'card' | 'list'>('card');
const availableCount = computed(() => applications.value.filter((item) => item.available).length);
const filtered = computed(() => {
  const value = keyword.value.trim().toLowerCase();
  if (!value) return applications.value;
  return applications.value.filter((item) => `${item.name} ${item.code}`.toLowerCase().includes(value));
});

const launch = async (item: EnterpriseApplication) => {
  if (!item.available) return;
  try {
    const { launchUrl } = await applicationApi.launch(item.id);
    const target = new URL(launchUrl, window.location.origin);
    if (target.searchParams.has('token') || target.searchParams.has('access_token')) {
      throw new Error(t('applications.insecureToken', '启动地址包含不安全 token'));
    }
    window.location.assign(target.toString());
  } catch (error) {
    ElMessage.error((error as { msg?: string; message?: string }).msg || (error as Error).message || t('applications.cannotEnter', '当前身份无法进入该应用'));
  }
};

onMounted(async () => {
  applications.value = (await applicationApi.portal()).list;
});
</script>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin: 16px 0; }
.search { width: min(100%, 360px); }
.application-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.card-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.description { min-height: 48px; color: var(--el-text-color-secondary); }
.reason { margin: 10px 0 0; color: var(--el-text-color-secondary); font-size: 13px; }
@media (max-width: 1024px) { .application-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .stats-grid, .application-grid { grid-template-columns: 1fr; } .search { width: 100%; } }
</style>
