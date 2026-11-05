<template>
  <div v-loading="loading" class="panel">
    <div class="panel-toolbar">
      <el-radio-group v-model="days" @change="load">
        <el-radio-button :value="7">近 7 天</el-radio-button>
        <el-radio-button :value="30">近 30 天</el-radio-button>
        <el-radio-button :value="90">近 90 天</el-radio-button>
      </el-radio-group>
      <span class="panel-hint">安装数据来自客户端插件中心在安装、升级、卸载、启用、禁用后的上报；活跃安装按站点去重。</span>
    </div>

    <section v-if="data" class="totals">
      <div v-for="item in totals" :key="item.label" class="total"><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div>
    </section>

    <div v-if="data" class="charts">
      <section class="chart-card"><h4>下载与安装</h4><Echarts :option="activityOption" :height="280" /></section>
      <section class="chart-card"><h4>销售</h4><Echarts :option="salesOption" :height="280" /></section>
    </div>

    <section v-if="data" class="chart-card">
      <h4>插件排行</h4>
      <el-table :data="data.top" size="small" empty-text="暂无插件">
        <el-table-column type="index" width="50" />
        <el-table-column label="插件" min-width="180"><template #default="{ row }">{{ row.name }}<span class="muted"> · {{ row.code }}</span></template></el-table-column>
        <el-table-column label="活跃安装" prop="activeInstalls" width="110" />
        <el-table-column label="下载" prop="downloads" width="100" />
        <el-table-column label="销售额" width="120"><template #default="{ row }">¥{{ yuan(row.revenue) }}</template></el-table-column>
      </el-table>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import Echarts from '@/components/Echarts/index.vue';
import { marketApi, yuan, type MarketStats } from '../api';

const loading = ref(false);
const days = ref(30);
const data = ref<MarketStats | null>(null);

async function load() {
  loading.value = true;
  try { data.value = await marketApi.stats(days.value); } finally { loading.value = false; }
}

const totals = computed(() => {
  const t = data.value?.totals;
  if (!t) return [];
  return [
    { label: '累计下载', value: t.downloads },
    { label: '活跃安装', value: t.activeInstalls },
    { label: '已启用', value: t.enabledInstalls },
    { label: '接入站点', value: t.sites },
    { label: '已支付订单', value: t.paidOrders },
    { label: '累计收入', value: `¥${yuan(t.revenue)}` },
    { label: '注册会员', value: t.members }
  ];
});

const axis = computed(() => ({
  xAxis: { type: 'category', data: (data.value?.dates ?? []).map((date) => date.slice(5)), boundaryGap: false },
  grid: { left: 40, right: 20, top: 40, bottom: 30 },
  tooltip: { trigger: 'axis' },
  legend: { top: 0 }
}));

const activityOption = computed(() => ({
  ...axis.value,
  yAxis: { type: 'value', minInterval: 1 },
  series: [
    { name: '下载', type: 'line', smooth: true, data: data.value?.downloads ?? [], areaStyle: { opacity: 0.12 } },
    { name: '新安装', type: 'line', smooth: true, data: data.value?.installs ?? [] },
    { name: '卸载', type: 'line', smooth: true, data: data.value?.uninstalls ?? [] }
  ]
}));

const salesOption = computed(() => ({
  ...axis.value,
  xAxis: { ...axis.value.xAxis, boundaryGap: true },
  yAxis: [{ type: 'value', name: '收入（元）' }, { type: 'value', name: '订单', minInterval: 1 }],
  series: [
    { name: '收入', type: 'bar', barMaxWidth: 18, data: (data.value?.revenue ?? []).map((cents) => cents / 100) },
    { name: '订单', type: 'line', yAxisIndex: 1, smooth: true, data: data.value?.orders ?? [] }
  ]
}));

onMounted(load);
</script>

<style scoped>
.panel { display: grid; gap: 14px; }
.panel-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.panel-hint { color: var(--el-text-color-secondary); font-size: 12px; }
.totals { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
.total { display: grid; gap: 2px; padding: 12px 14px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: var(--el-bg-color); }
.total strong { color: var(--el-text-color-primary); font-size: 18px; }
.total span { color: var(--el-text-color-secondary); font-size: 12px; }
.charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.chart-card { padding: 14px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.chart-card h4 { margin: 0 0 8px; color: var(--el-text-color-primary); font-size: 14px; }
.muted { color: var(--el-text-color-secondary); font-size: 12px; }
@media (max-width: 900px) { .charts { grid-template-columns: 1fr; } }
</style>
