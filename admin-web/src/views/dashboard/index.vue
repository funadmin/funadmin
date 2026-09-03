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
            {{ greet }}<b>{{ userStore.nickname || '管理员' }}</b>
          </div>
          <div class="dashboard-hero__sub">
            欢迎回到 <b>Admin Console</b>，今天是 {{ today }} {{ weekday }}，工作愉快！
          </div>
        </div>
      </div>
      <div class="dashboard-hero__stats">
        <div class="dashboard-hero__stat">
          <div class="dashboard-hero__stat-num">12</div>
          <div class="dashboard-hero__stat-label">待办</div>
        </div>
        <el-divider direction="vertical" class="!h-9" />
        <div class="dashboard-hero__stat">
          <div class="dashboard-hero__stat-num">3</div>
          <div class="dashboard-hero__stat-label">消息</div>
        </div>
        <el-divider direction="vertical" class="!h-9" />
        <div class="dashboard-hero__stat">
          <div class="dashboard-hero__stat-num">95%</div>
          <div class="dashboard-hero__stat-label">完成率</div>
        </div>
      </div>
    </div>

    <!-- 数据卡片 -->
    <el-row :gutter="16" class="mt-4">
      <el-col v-for="card in cards" :key="card.title" :xs="12" :sm="12" :md="6">
        <div class="dashboard-card" :style="{ '--card-color': card.color }">
          <div class="dashboard-card__head">
            <span class="dashboard-card__label">{{ card.title }}</span>
            <div class="dashboard-card__icon">
              <i :class="card.icon" />
            </div>
          </div>
          <div class="dashboard-card__value">{{ card.value }}</div>
          <div class="dashboard-card__foot">
            <span :class="['dashboard-card__delta', card.delta >= 0 ? 'is-up' : 'is-down']">
              <i :class="card.delta >= 0 ? 'i-ep-top' : 'i-ep-bottom'" />
              {{ Math.abs(card.delta) }}%
            </span>
            <span class="dashboard-card__cmp">较上周</span>
          </div>
          <Echarts :option="card.option" :height="56" class="dashboard-card__spark" />
        </div>
      </el-col>
    </el-row>

    <!-- 趋势 / 来源 -->
    <el-row :gutter="16" class="mt-4">
      <el-col :xs="24" :lg="16">
        <el-card shadow="never" class="dashboard-chart">
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-medium">访问趋势</span>
              <el-radio-group v-model="trendRange" size="small">
                <el-radio-button value="week">周</el-radio-button>
                <el-radio-button value="month">月</el-radio-button>
                <el-radio-button value="year">年</el-radio-button>
              </el-radio-group>
            </div>
          </template>
          <Echarts :option="trendOption" :height="320" />
        </el-card>
      </el-col>
      <el-col :xs="24" :lg="8">
        <el-card shadow="never" class="dashboard-chart">
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-medium">访问来源</span>
              <el-tag size="small" type="primary" effect="plain">实时</el-tag>
            </div>
          </template>
          <Echarts :option="sourceOption" :height="320" />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mt-4">
      <el-col :xs="24" :lg="12">
        <el-card shadow="never" class="dashboard-chart">
          <template #header><span class="font-medium">热门商品排行</span></template>
          <Echarts :option="rankOption" :height="280" />
        </el-card>
      </el-col>
      <el-col :xs="24" :lg="12">
        <el-card shadow="never" class="dashboard-chart">
          <template #header><span class="font-medium">团队能力雷达</span></template>
          <Echarts :option="radarOption" :height="280" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 动态 + 团队 -->
    <el-row :gutter="16" class="mt-4">
      <el-col :xs="24" :lg="14">
        <el-card shadow="never" class="dashboard-chart dashboard-chart--fill">
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-medium">最新动态</span>
              <el-button text type="primary" size="small">查看全部</el-button>
            </div>
          </template>
          <el-timeline>
            <el-timeline-item
              v-for="t in timeline"
              :key="t.id"
              :timestamp="t.time"
              :type="t.type"
              :hollow="t.hollow"
            >
              <span v-html="t.content"></span>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
      <el-col :xs="24" :lg="10">
        <el-card shadow="never" class="dashboard-chart mb-4">
          <template #header><span class="font-medium">团队成员</span></template>
          <div class="dashboard-team">
            <div v-for="m in members" :key="m.id" class="dashboard-team__item">
              <el-avatar :size="36" :style="{ background: m.color }">{{ m.name.charAt(0) }}</el-avatar>
              <div class="dashboard-team__info">
                <div class="dashboard-team__name">{{ m.name }}</div>
                <div class="dashboard-team__role">{{ m.role }}</div>
              </div>
              <el-tag :type="m.online ? 'success' : 'info'" size="small" effect="light">
                {{ m.online ? '在线' : '离线' }}
              </el-tag>
            </div>
          </div>
        </el-card>
        <el-card shadow="never" class="dashboard-chart">
          <template #header><span class="font-medium">快捷入口</span></template>
          <div class="dashboard-shortcuts">
            <div
              v-for="q in shortcuts"
              :key="q.title"
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
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import dayjs from 'dayjs';
import { useUserStore } from '@/store/modules/user';
import Echarts from '@/components/Echarts/index.vue';
import { useChartTheme } from '@/composables/useChartTheme';

defineOptions({ name: 'Dashboard' });

const userStore = useUserStore();
const { tokens: chartTokens } = useChartTheme();

/** 兼容旧字段名的扁平化 token，供本页图表 option 直接读取 */
const tokens = computed(() => {
  const t = chartTokens.value;
  return {
    primary: t.primary,
    success: t.success,
    warning: t.warning,
    danger: t.danger,
    info: t.info,
    text: t.text,
    textPrimary: t.text,
    textRegular: t.text,
    textSecondary: t.textSecondary,
    border: t.border,
    borderLight: t.border,
    borderLighter: t.border,
    bgElevated: t.cardBg
  };
});

const today = dayjs().format('YYYY-MM-DD');
const weekday = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][dayjs().day()];

const greet = computed(() => {
  const h = dayjs().hour();
  if (h < 6) return '夜深了，';
  if (h < 11) return '早上好，';
  if (h < 13) return '中午好，';
  if (h < 18) return '下午好，';
  return '晚上好，';
});

/* ---------- 卡片 sparkline ---------- */
function makeSpark(color: string, data: number[]) {
  // 把 hex 颜色转换为带透明度的 rgba（避免 color-mix 兼容问题）
  const fade = (a: number) => {
    const m = color.replace('#', '');
    const f = (i: number) =>
      m.length === 3
        ? parseInt(m[i] + m[i], 16)
        : parseInt(m.slice(i * 2, i * 2 + 2), 16);
    return `rgba(${f(0)}, ${f(1)}, ${f(2)}, ${a})`;
  };
  return {
    grid: { left: 0, right: 0, top: 4, bottom: 0 },
    xAxis: { type: 'category', show: false, data: data.map((_, i) => i) },
    yAxis: { type: 'value', show: false },
    series: [
      {
        type: 'line',
        data,
        smooth: true,
        symbol: 'none',
        lineStyle: { color, width: 2 },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: fade(0.32) },
              { offset: 1, color: fade(0) }
            ]
          }
        }
      }
    ]
  };
}

const cards = ref([
  {
    title: '今日访问',
    value: '12,648',
    delta: 8.6,
    color: '#3b82f6',
    icon: 'i-ep-data-line',
    option: makeSpark('#3b82f6', [120, 132, 101, 134, 90, 230, 210, 320, 280, 360])
  },
  {
    title: '订单数',
    value: '892',
    delta: 12.3,
    color: '#10b981',
    icon: 'i-ep-shopping-cart',
    option: makeSpark('#10b981', [60, 90, 70, 110, 95, 130, 120, 160, 150, 200])
  },
  {
    title: '销售金额',
    value: '￥58,210',
    delta: -3.2,
    color: '#f59e0b',
    icon: 'i-ep-wallet',
    option: makeSpark('#f59e0b', [200, 180, 220, 190, 210, 170, 150, 180, 165, 175])
  },
  {
    title: '新增用户',
    value: '326',
    delta: 5.4,
    color: '#8b5cf6',
    icon: 'i-ep-user-filled',
    option: makeSpark('#8b5cf6', [40, 60, 50, 80, 70, 95, 88, 110, 105, 130])
  }
]);

/* ---------- 趋势 ---------- */
const trendRange = ref<'week' | 'month' | 'year'>('week');

const trendOption = computed(() => {
  const week = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
  const month = Array.from({ length: 30 }, (_, i) => `${i + 1}日`);
  const year = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

  const x = trendRange.value === 'week' ? week : trendRange.value === 'month' ? month : year;
  const len = x.length;
  const visit = Array.from({ length: len }, () => Math.round(800 + Math.random() * 600));
  const order = visit.map((v) => Math.round(v * (0.3 + Math.random() * 0.2)));

  return {
    color: [tokens.value.primary, tokens.value.success],
    grid: { left: 12, right: 12, top: 36, bottom: 8, containLabel: true },
    legend: { top: 0, right: 0, textStyle: { color: tokens.value.textRegular } },
    tooltip: {
      trigger: 'axis',
      backgroundColor: tokens.value.bgElevated,
      borderColor: tokens.value.borderLight,
      textStyle: { color: tokens.value.textPrimary }
    },
    xAxis: {
      type: 'category',
      data: x,
      axisLine: { lineStyle: { color: tokens.value.borderLighter } },
      axisLabel: { color: tokens.value.textSecondary }
    },
    yAxis: {
      type: 'value',
      axisLine: { show: false },
      splitLine: { lineStyle: { color: tokens.value.borderLighter, type: 'dashed' } },
      axisLabel: { color: tokens.value.textSecondary }
    },
    series: [
      {
        name: '访问量',
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 6,
        data: visit,
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(64, 158, 255, 0.28)' },
              { offset: 1, color: 'rgba(64, 158, 255, 0)' }
            ]
          }
        }
      },
      {
        name: '订单量',
        type: 'bar',
        barWidth: 14,
        itemStyle: { borderRadius: [6, 6, 0, 0] },
        data: order
      }
    ]
  };
});

/* ---------- 来源饼图 ---------- */
const sourceOption = computed(() => ({
  color: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
  tooltip: { trigger: 'item' },
  legend: {
    bottom: 0,
    icon: 'circle',
    itemWidth: 8,
    itemHeight: 8,
    textStyle: { color: tokens.value.textRegular }
  },
  series: [
    {
      name: '来源',
      type: 'pie',
      radius: ['52%', '74%'],
      center: ['50%', '46%'],
      avoidLabelOverlap: true,
      label: { show: false },
      labelLine: { show: false },
      itemStyle: {
        borderRadius: 6,
        borderColor: tokens.value.bgElevated,
        borderWidth: 2
      },
      data: [
        { value: 1048, name: '直接访问' },
        { value: 735, name: '搜索引擎' },
        { value: 580, name: '邮件营销' },
        { value: 484, name: '联盟广告' },
        { value: 300, name: '视频广告' }
      ]
    }
  ]
}));

/* ---------- 商品排行 ---------- */
const rankOption = computed(() => ({
  grid: { left: 12, right: 24, top: 12, bottom: 8, containLabel: true },
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
  xAxis: {
    type: 'value',
    axisLine: { show: false },
    splitLine: { lineStyle: { color: tokens.value.borderLighter, type: 'dashed' } },
    axisLabel: { color: tokens.value.textSecondary }
  },
  yAxis: {
    type: 'category',
    data: ['苹果 iPhone 15', '华为 Mate 60', '小米 14 Ultra', '一加 12', 'OPPO Find X7'],
    axisLine: { lineStyle: { color: tokens.value.borderLighter } },
    axisLabel: { color: tokens.value.textRegular }
  },
  series: [
    {
      type: 'bar',
      barWidth: 14,
      itemStyle: {
        borderRadius: [0, 8, 8, 0],
        color: {
          type: 'linear',
          x: 0,
          y: 0,
          x2: 1,
          y2: 0,
          colorStops: [
            { offset: 0, color: '#60a5fa' },
            { offset: 1, color: '#3b82f6' }
          ]
        }
      },
      data: [320, 280, 240, 200, 180]
    }
  ]
}));

/* ---------- 雷达 ---------- */
const radarOption = computed(() => ({
  tooltip: {},
  radar: {
    indicator: [
      { name: '需求分析', max: 100 },
      { name: '设计能力', max: 100 },
      { name: '编码质量', max: 100 },
      { name: '测试覆盖', max: 100 },
      { name: '上线交付', max: 100 },
      { name: '协作沟通', max: 100 }
    ],
    axisName: { color: tokens.value.textRegular },
    splitLine: { lineStyle: { color: tokens.value.borderLighter } },
    splitArea: { areaStyle: { color: ['transparent'] } }
  },
  series: [
    {
      type: 'radar',
      symbol: 'circle',
      symbolSize: 6,
      areaStyle: { opacity: 0.18 },
      data: [
        { value: [88, 82, 90, 78, 92, 85], name: '本季度', lineStyle: { color: tokens.value.primary } },
        { value: [72, 75, 80, 70, 78, 80], name: '上季度', lineStyle: { color: tokens.value.warning } }
      ]
    }
  ]
}));

/* ---------- 时间线 ---------- */
type TimelineItemType = '' | 'primary' | 'success' | 'warning' | 'info' | 'danger';
const timeline = ref<{ id: number; time: string; type: TimelineItemType; hollow: boolean; content: string }[]>([
  { id: 1, time: '10 分钟前', type: 'primary', hollow: false, content: '<b>张伟</b> 创建了订单 <i>#202403210018</i>' },
  { id: 2, time: '1 小时前', type: 'success', hollow: false, content: '<b>李娜</b> 发布了商品 <i>「春日新品 T 恤」</i>' },
  { id: 3, time: '今天 09:42', type: 'warning', hollow: true, content: '系统更新：新增<b>团队管理</b>模块' },
  { id: 4, time: '昨天', type: 'danger', hollow: false, content: '<b>王强</b> 处理了一个高优先级工单' },
  { id: 5, time: '03-19', type: 'info', hollow: true, content: '月度数据报表已生成，请前往<b>报表中心</b>查看' }
]);

/* ---------- 团队 ---------- */
const members = ref([
  { id: 1, name: '张伟', role: '产品经理', online: true, color: '#3b82f6' },
  { id: 2, name: '李娜', role: '前端开发', online: true, color: '#10b981' },
  { id: 3, name: '王强', role: '后端开发', online: false, color: '#f59e0b' },
  { id: 4, name: '赵敏', role: 'UI 设计', online: true, color: '#ef4444' },
  { id: 5, name: '孙浩', role: '测试工程师', online: false, color: '#8b5cf6' }
]);

/* ---------- 快捷入口 ---------- */
const shortcuts = ref([
  { title: '用户管理', icon: 'i-ep-user', color: '#3b82f6', path: '/system/user' },
  { title: '角色管理', icon: 'i-ep-avatar', color: '#10b981', path: '/system/role' },
  { title: '菜单管理', icon: 'i-ep-menu', color: '#f59e0b', path: '/system/menu' },
  { title: '系统监控', icon: 'i-ep-monitor', color: '#8b5cf6', path: '/monitor/server' },
  { title: '日志查看', icon: 'i-ep-document', color: '#ef4444', path: '/monitor/log' },
  { title: '系统设置', icon: 'i-ep-setting', color: '#06b6d4', path: '/system/config' }
]);
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
    background: linear-gradient(135deg, var(--el-color-primary-light-9), var(--el-bg-color-page));
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

    &__stats {
      display: flex;
      align-items: center;
      gap: 18px;
    }

    &__stat {
      text-align: center;

      &-num {
        font-size: 20px;
        font-weight: 600;
        color: var(--el-text-color-primary);
      }

      &-label {
        margin-top: 2px;
        font-size: 12px;
        color: var(--el-text-color-secondary);
      }
    }
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

    &__delta {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      font-weight: 600;

      &.is-up {
        color: var(--el-color-success);
      }

      &.is-down {
        color: var(--el-color-danger);
      }
    }

    &__cmp {
      color: var(--el-text-color-secondary);
    }

    &__spark {
      margin-top: 6px;
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

  &-team {
    display: flex;
    flex-direction: column;
    gap: 12px;

    &__item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 10px;
      border-radius: 8px;
      transition: background 0.2s;

      &:hover {
        background: var(--el-fill-color-lighter);
      }
    }

    &__info {
      flex: 1;
      min-width: 0;
    }

    &__name {
      font-size: 14px;
      font-weight: 500;
      color: var(--el-text-color-primary);
    }

    &__role {
      font-size: 12px;
      color: var(--el-text-color-secondary);
      margin-top: 2px;
    }
  }

  &-shortcuts {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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
