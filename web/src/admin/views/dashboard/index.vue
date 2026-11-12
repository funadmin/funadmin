<template>
  <div class="dashboard">
    <!-- 顶部欢迎区 -->
    <div class="dashboard-hero">
      <div class="dashboard-hero__info">
        <el-avatar :size="56" :src="userStore.avatar" class="dashboard-hero__avatar">
          {{ userStore.nickname.charAt(0).toUpperCase() }}
        </el-avatar>
        <div>
          <div class="dashboard-hero__greet">
            {{ greet }}<b>{{ userStore.nickname || t('dashboard.admin', '管理员') }}</b>
          </div>
          <div class="dashboard-hero__sub">
            {{ t('dashboard.heroSubPrefix', '欢迎回到') }} <b>Admin Console</b>{{ t('dashboard.heroSubSuffix', { date: today, weekday }, '，今天是 {date} {weekday}，工作愉快！') }}
          </div>
        </div>
      </div>
      <div class="dashboard-hero__meta">
        <span v-if="overview" class="dashboard-hero__updated">
          {{ t('dashboard.updatedAt', { time: overview.generatedAt }, '数据更新于 {time}') }}
        </span>
        <el-button size="small" :loading="loading" data-test="dashboard-refresh" @click="loadOverview">
          <i class="i-ep-refresh mr-1" />{{ t('dashboard.refresh', '刷新') }}
        </el-button>
      </div>
    </div>

    <!-- 加载失败：保留页面结构，内联提示并允许重试 -->
    <el-alert
      v-if="error"
      class="mt-4"
      type="error"
      show-icon
      :closable="false"
      :title="t('dashboard.loadFailed', '系统概览加载失败')"
      data-test="dashboard-error"
    >
      <template #default>
        <div class="dashboard-error">
          <span>{{ error }}</span>
          <el-button size="small" type="primary" plain @click="loadOverview">{{ t('dashboard.retry', '重试') }}</el-button>
        </div>
      </template>
    </el-alert>

    <!-- 数据卡片 -->
    <div v-if="loading && !overview" class="dashboard-cards mt-4" data-test="dashboard-skeleton">
      <div v-for="n in 4" :key="n" class="dashboard-card">
        <el-skeleton animated :rows="2" />
      </div>
    </div>
    <div v-else-if="cards.length" class="dashboard-cards mt-4">
      <div
        v-for="card in cards"
        :key="card.key"
        class="dashboard-card"
        :style="{ '--card-color': card.color }"
        :data-test="`dashboard-card-${card.key}`"
      >
        <div class="dashboard-card__head">
          <span class="dashboard-card__label">{{ card.title }}</span>
          <div class="dashboard-card__icon">
            <i :class="card.icon" />
          </div>
        </div>
        <div class="dashboard-card__value">{{ card.value }}</div>
        <div class="dashboard-card__foot">
          <span class="dashboard-card__cmp">{{ card.sub }}</span>
        </div>
      </div>
    </div>

    <PluginSlot v-if="hookActive" name="dashboard.cards" :context="{ user: userStore.userInfo }" />

    <!-- 操作日志趋势 / 最近日志 -->
    <el-row v-if="loading && !overview" :gutter="16" class="mt-4">
      <el-col :xs="24" :lg="14">
        <el-card shadow="never" class="dashboard-chart"><el-skeleton animated :rows="8" /></el-card>
      </el-col>
      <el-col :xs="24" :lg="10">
        <el-card shadow="never" class="dashboard-chart"><el-skeleton animated :rows="8" /></el-card>
      </el-col>
    </el-row>
    <el-row v-else-if="overview?.logs" :gutter="16" class="mt-4">
      <el-col :xs="24" :lg="14">
        <el-card shadow="never" class="dashboard-chart" data-test="dashboard-trend">
          <template #header>
            <span class="font-medium">{{ t('dashboard.logTrend', '近 7 天操作日志') }}</span>
          </template>
          <Echarts :option="trendOption" :height="300" />
        </el-card>
      </el-col>
      <el-col :xs="24" :lg="10">
        <el-card shadow="never" class="dashboard-chart dashboard-chart--fill" data-test="dashboard-latest">
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-medium">{{ t('dashboard.latestLogs', '最近操作') }}</span>
              <el-button v-if="logListPath" text type="primary" size="small" @click="$router.push(logListPath)">
                {{ t('dashboard.viewAll', '查看全部') }}
              </el-button>
            </div>
          </template>
          <el-empty v-if="!overview.logs.latest.length" :image-size="72" :description="t('dashboard.noLogs', '暂无操作日志')" />
          <div v-else class="dashboard-logs">
            <div v-for="log in overview.logs.latest" :key="log.id" class="dashboard-log">
              <el-tag size="small" :type="log.status === 1 ? 'success' : 'danger'" effect="light" class="dashboard-log__method">
                {{ log.method || '-' }}
              </el-tag>
              <div class="dashboard-log__info">
                <div class="dashboard-log__name" :title="log.url">{{ log.name || log.url || log.action }}</div>
                <div class="dashboard-log__meta">{{ log.username || '-' }} · {{ log.ip || '-' }}</div>
              </div>
              <span class="dashboard-log__time">{{ log.createdAt }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 快捷入口：来自当前账号可见的菜单 -->
    <el-row v-if="shortcuts.length" :gutter="16" class="mt-4">
      <el-col :span="24">
        <el-card shadow="never" class="dashboard-chart" data-test="dashboard-shortcuts">
          <template #header><span class="font-medium">{{ t('dashboard.shortcuts', '快捷入口') }}</span></template>
          <div class="dashboard-shortcuts">
            <div
              v-for="q in shortcuts"
              :key="q.path"
              class="dashboard-shortcut"
              @click="$router.push(q.path)"
            >
              <div
                class="dashboard-shortcut__icon"
                :style="{
                  background: `color-mix(in srgb, ${q.color} 12%, transparent)`,
                  color: q.color
                }"
              >
                <i :class="q.icon" />
              </div>
              <span>{{ q.title }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-empty
      v-if="overview && !cards.length && !overview.logs && !shortcuts.length"
      class="mt-4"
      :description="t('dashboard.noBlocks', '当前账号暂无可查看的系统概览数据')"
      data-test="dashboard-empty"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onActivated, onDeactivated, onUnmounted, watch } from 'vue';
import { hooks, PluginSlot, HookCancelledError } from '@/shared/plugins/hooks';
import { useI18n } from 'vue-i18n';
import dayjs from 'dayjs';
import { useUserStore } from '@/store/modules/user';
import { usePermissionStore } from '@/store/modules/permission';
import Echarts from '@/components/Echarts/index.vue';
import { useChartTheme } from '@/composables/useChartTheme';
import { useMenuTitle } from '@/composables/useMenuTitle';
import { adminRoutePath, resolveMenuPath } from '@/utils/route';
import { checkPermission } from '@/utils/permission';
import { formatErrorMessage } from '@/utils/errorMessage';
import { dashboardApi, type DashboardOverview } from '@/api/dashboard';

defineOptions({ name: 'Dashboard' });

const userStore = useUserStore();
const permissionStore = usePermissionStore();
const { t } = useI18n();
const { tokens } = useChartTheme();
const { menuTitle } = useMenuTitle();

const today = dayjs().format('YYYY-MM-DD');
const weekday = computed(() => [t('dashboard.sun', '周日'), t('dashboard.mon', '周一'), t('dashboard.tue', '周二'), t('dashboard.wed', '周三'), t('dashboard.thu', '周四'), t('dashboard.fri', '周五'), t('dashboard.sat', '周六')][dayjs().day()]);

const greet = computed(() => {
  const h = dayjs().hour();
  if (h < 6) return t('dashboard.greetNight', '夜深了，');
  if (h < 11) return t('dashboard.greetMorning', '早上好，');
  if (h < 13) return t('dashboard.greetNoon', '中午好，');
  if (h < 18) return t('dashboard.greetAfternoon', '下午好，');
  return t('dashboard.greetEvening', '晚上好，');
});

/* ---------- 系统概览数据 ---------- */
const overview = ref<DashboardOverview | null>(null);
const loading = ref(false);
const error = ref('');
let loadSeq = 0;

async function loadOverview() {
  const seq = ++loadSeq;
  loading.value = true;
  error.value = '';
  try {
    const data = await dashboardApi.overview();
    if (seq === loadSeq) overview.value = data;
  } catch (reason) {
    if (seq === loadSeq) error.value = formatErrorMessage(reason, t('dashboard.loadFailedHint', '请稍后重试'));
  } finally {
    if (seq === loadSeq) loading.value = false;
  }
}

function formatBytes(size: number): string {
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let value = Math.max(0, size);
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit++;
  }
  return `${unit === 0 ? value : value.toFixed(value >= 100 ? 0 : 1)} ${units[unit]}`;
}

const formatNumber = (value: number) => value.toLocaleString();

/* ---------- 卡片：接口没有返回的块不显示 ---------- */
interface DashboardCard { key: string; title: string; value: string; sub: string; icon: string; color: string }
const cards = computed<DashboardCard[]>(() => {
  const data = overview.value;
  if (!data) return [];
  const list: DashboardCard[] = [];
  if (data.admins) {
    list.push({ key: 'admins', title: t('dashboard.cardAdmins', '管理员'), value: formatNumber(data.admins.total), sub: t('dashboard.cardAdminsSub', '可管理的管理员'), icon: 'i-ep-avatar', color: '#3b82f6' });
  }
  if (data.members) {
    list.push({ key: 'members', title: t('dashboard.cardMembers', '会员总数'), value: formatNumber(data.members.total), sub: t('dashboard.cardMembersToday', { count: data.members.today }, '今日新增 {count}'), icon: 'i-ep-user-filled', color: '#10b981' });
  }
  if (data.attachments) {
    list.push({ key: 'attachments', title: t('dashboard.cardAttachments', '附件'), value: formatNumber(data.attachments.total), sub: t('dashboard.cardAttachmentsSize', { size: formatBytes(data.attachments.size) }, '占用 {size}'), icon: 'i-ep-picture-filled', color: '#f59e0b' });
  }
  if (data.logs) {
    const week = data.logs.trend.reduce((sum, item) => sum + item.count, 0);
    list.push({ key: 'logs', title: t('dashboard.cardLogsToday', '今日操作'), value: formatNumber(data.logs.today), sub: t('dashboard.cardLogsWeek', { count: week }, '近 7 天 {count}'), icon: 'i-ep-document', color: '#ef4444' });
  }
  if (data.plugins) {
    list.push({ key: 'plugins', title: t('dashboard.cardPlugins', '已安装插件'), value: formatNumber(data.plugins.installed), sub: t('dashboard.cardPluginsEnabled', { count: data.plugins.enabled }, '已启用 {count}'), icon: 'i-ep-grid', color: '#08979c' });
  }
  return list;
});

/* ---------- 操作日志趋势 ---------- */
const trendOption = computed(() => {
  const trend = overview.value?.logs?.trend ?? [];
  return {
    color: [tokens.value.primary],
    grid: { left: 12, right: 12, top: 24, bottom: 8, containLabel: true },
    tooltip: {
      trigger: 'axis',
      backgroundColor: tokens.value.cardBg,
      borderColor: tokens.value.border,
      textStyle: { color: tokens.value.text }
    },
    xAxis: {
      type: 'category',
      data: trend.map((item) => dayjs(item.date).format('MM-DD')),
      axisLine: { lineStyle: { color: tokens.value.border } },
      axisLabel: { color: tokens.value.textSecondary }
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      axisLine: { show: false },
      splitLine: { lineStyle: { color: tokens.value.border, type: 'dashed' } },
      axisLabel: { color: tokens.value.textSecondary }
    },
    series: [
      {
        name: t('dashboard.logCount', '操作次数'),
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 6,
        data: trend.map((item) => item.count),
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: tokens.value.primaryAlpha(0.22) },
              { offset: 1, color: tokens.value.primaryAlpha(0) }
            ]
          }
        }
      }
    ]
  };
});

/* ---------- 快捷入口：按权限过滤当前账号的菜单 ---------- */
type Shortcut = { title: string; icon: string; color: string; path: string };
const SHORTCUT_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#08979c', '#ef4444', '#06b6d4', '#8b5cf6', '#64748b'];
/** 优先展示的常用页面（按顺序），其余可见页面依菜单顺序补足。 */
const PREFERRED_SHORTCUTS = ['/system/user', '/system/role', '/users/member', '/system/attachment', '/system/log/operation', '/plugin', '/system/config', '/system/menu'];
const MAX_SHORTCUTS = 8;

interface MenuPage { title: string; icon: string; path: string; key: string }
function visiblePages(menus: API.MenuItem[], parentPath: string, out: MenuPage[]): MenuPage[] {
  const sorted = [...menus].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));
  for (const menu of sorted) {
    if (menu.type === 'B' || menu.hidden) continue;
    if (!checkPermission(userStore.permissions, menu.permission)) continue;
    const fullPath = resolveMenuPath(parentPath, menu.path.startsWith('/') ? adminRoutePath(menu.path) : menu.path);
    const isPage = menu.type === 'C' && Boolean(menu.component) && !['Layout', 'Blank'].includes(menu.component!);
    if (isPage && !fullPath.includes(':')) {
      const name = menu.routeName || `Menu_${menu.id}`;
      out.push({ title: menuTitle({ name, meta: { title: menu.name } }), icon: menu.icon || 'i-ep-menu', path: fullPath, key: fullPath });
    }
    if (menu.children?.length) visiblePages(menu.children, fullPath, out);
  }
  return out;
}

const menuPages = computed(() => visiblePages(permissionStore.rawMenus, adminRoutePath('/'), []));
const baseShortcuts = computed<Shortcut[]>(() => {
  const pages = menuPages.value.filter((page) => page.path !== adminRoutePath('/dashboard'));
  const preferred = PREFERRED_SHORTCUTS.map((path) => pages.find((page) => page.path === adminRoutePath(path))).filter((page): page is MenuPage => Boolean(page));
  const ordered = [...preferred, ...pages.filter((page) => !preferred.includes(page))];
  return ordered.slice(0, MAX_SHORTCUTS).map((page, index) => ({ title: page.title, icon: page.icon, path: page.path, color: SHORTCUT_COLORS[index % SHORTCUT_COLORS.length] }));
});
const logListPath = computed(() => menuPages.value.find((page) => page.path === adminRoutePath('/system/log/operation'))?.path ?? '');

const shortcuts = ref<Shortcut[]>([]);
const hookActive = ref(false);
let hookScope: AbortController | undefined;
async function applyDashboardHooks() {
  const base = baseShortcuts.value.slice();
  shortcuts.value = base;
  if (!hookActive.value) return;
  hookScope?.abort();
  const scope = new AbortController();
  hookScope = scope;
  const context = { user: userStore.userInfo, signal: scope.signal };
  void hooks.action('dashboard.loaded', undefined, context).catch(console.error);
  try {
    const result = await hooks.filter('dashboard.quickActions', base.slice(), context);
    if (scope.signal.aborted || !Array.isArray(result)) return;
    shortcuts.value = result.filter(item => item && typeof item.path === 'string' && /^\/(?!\/)/.test(item.path) && !/[\\\x00-\x20]/.test(item.path) && ['title', 'icon', 'color'].every(key => typeof (item as Record<string, unknown>)[key] === 'string'));
  } catch (error) { if (!(error instanceof HookCancelledError)) console.error(error); }
}
function activateHooks() { if (hookActive.value) return; hookActive.value = true; void applyDashboardHooks(); }
function deactivateHooks() { hookActive.value = false; hookScope?.abort(); }
onMounted(() => {
  void loadOverview();
  activateHooks();
});
onActivated(activateHooks);
onDeactivated(deactivateHooks);
onUnmounted(deactivateHooks);
watch(hooks.revision, () => void applyDashboardHooks());
watch(baseShortcuts, () => void applyDashboardHooks(), { immediate: true });

</script>

<style scoped lang="scss">
.dashboard {
  padding: 0;

  &-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 20px 24px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color)), var(--el-bg-color-page));
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 12px;

    &__info {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    &__avatar {
      background: var(--el-color-primary);
      color: #fff;
      font-weight: 600;
    }

    &__greet {
      font-size: 18px;
      font-weight: 500;
      color: var(--el-text-color-primary);

      b {
        color: var(--el-color-primary);
        margin-left: 4px;
      }
    }

    &__sub {
      margin-top: 4px;
      font-size: 13px;
      color: var(--el-text-color-secondary);

      b {
        color: var(--el-text-color-regular);
      }
    }

    &__meta {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    &__updated {
      font-size: 12px;
      color: var(--el-text-color-secondary);
    }
  }

  &-error {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
  }

  &-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
  }

  &-card {
    position: relative;
    padding: 18px 18px 14px;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.25s ease;

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px -8px rgba(0, 0, 0, 0.12);
      border-color: var(--card-color);
    }

    &__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    &__label {
      font-size: 13px;
      color: var(--el-text-color-secondary);
    }

    &__icon {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      font-size: 18px;
      color: var(--card-color);
      background: color-mix(in srgb, var(--card-color) 12%, transparent);
    }

    &__value {
      margin-top: 10px;
      font-size: 24px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }

    &__foot {
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
    }

    &__cmp {
      color: var(--el-text-color-secondary);
    }
  }

  &-chart {
    border-radius: 12px;
    border-color: var(--el-border-color-lighter);

    :deep(.el-card__header) {
      padding: 14px 18px;
      border-bottom: 1px solid var(--el-border-color-lighter);
    }

    :deep(.el-card__body) {
      padding: 16px 18px;
    }

    /* 撑满 col 高度，解决等高 row 中卡片底部留白 */
    &--fill {
      height: 100%;
      display: flex;
      flex-direction: column;

      :deep(.el-card__body) {
        flex: 1;
      }
    }
  }

  &-logs {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  &-log {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 6px;
    border-radius: 8px;

    &:hover {
      background: var(--el-fill-color-lighter);
    }

    &__method {
      flex: 0 0 auto;
      min-width: 56px;
      justify-content: center;
    }

    &__info {
      flex: 1;
      min-width: 0;
    }

    &__name {
      font-size: 13px;
      color: var(--el-text-color-primary);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    &__meta {
      margin-top: 2px;
      font-size: 12px;
      color: var(--el-text-color-secondary);
    }

    &__time {
      flex: 0 0 auto;
      font-size: 12px;
      color: var(--el-text-color-secondary);
    }
  }

  &-shortcuts {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 12px;
  }

  &-shortcut {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
      background: var(--el-fill-color-lighter);
      transform: translateY(-2px);
    }

    &__icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      font-size: 20px;
    }

    span {
      font-size: 12px;
      color: var(--el-text-color-regular);
    }
  }
}

.flex items-center justify-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
</style>
