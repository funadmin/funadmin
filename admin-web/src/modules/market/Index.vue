<template>
  <PageWrapper>
    <div class="market-page">
      <div v-if="overview" class="market-toolbar"><a class="storefront-link" :href="overview.signing.publicUrl" target="_blank" rel="noopener"><i class="i-ep-link" />打开前台商店</a></div>
      <section class="market-stats">
        <div v-for="item in stats" :key="item.label" class="market-stat">
          <span class="market-stat__icon" :style="{ '--tone': item.tone }"><i :class="item.icon" /></span>
          <div><strong>{{ item.value }}</strong><small>{{ item.label }}</small></div>
        </div>
      </section>

      <el-alert v-if="overview && overview.signing.keySource === 'none'" type="warning" show-icon :closable="false" title="尚未配置 Ed25519 签名密钥">
        <template #default>上传的插件包必须签名后才能发布，客户端也需要对应公钥才能验签安装。<el-button v-if="can('market:setting:manage')" link type="primary" @click="tab = 'setting'">前往市场设置生成密钥</el-button></template>
      </el-alert>
      <el-alert v-else-if="overview && !overview.signing.https" type="info" show-icon :closable="false" :title="`当前对外地址 ${overview.signing.publicUrl} 不是 HTTPS`" description="客户端只接受 HTTPS 公网地址下载插件包，支付渠道回调也要求 HTTPS；正式使用前请在插件配置中填写 HTTPS 的「市场对外地址」。" />
      <el-alert v-if="overview && overview.payment.length === 0" type="info" show-icon :closable="false" title="尚未开通在线支付">
        <template #default>付费插件的购买按钮会提示联系管理员。<el-button v-if="can('market:setting:manage')" link type="primary" @click="tab = 'setting'">前往配置支付宝 / 微信支付</el-button></template>
      </el-alert>

      <el-tabs v-model="tab" class="market-tabs">
        <el-tab-pane label="插件与版本" name="plugins"><PluginsPanel :can-manage="can('market:plugin:manage')" @changed="loadOverview" /></el-tab-pane>
        <el-tab-pane v-if="can('market:order:view')" label="订单" name="orders" lazy><OrdersPanel :can-manage="can('market:order:manage')" @changed="loadOverview" /></el-tab-pane>
        <el-tab-pane v-if="can('market:stat:view')" label="统计" name="stats" lazy><StatsPanel /></el-tab-pane>
        <el-tab-pane label="分类" name="categories" lazy><CategoriesPanel :can-manage="can('market:category:manage')" /></el-tab-pane>
        <el-tab-pane v-if="can('market:grant:manage')" label="授权" name="grants" lazy><GrantsPanel @changed="loadOverview" /></el-tab-pane>
        <el-tab-pane v-if="can('market:setting:manage')" label="市场设置" name="setting" lazy><SettingsPanel @changed="loadOverview" /></el-tab-pane>
      </el-tabs>
    </div>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import PageWrapper from '@/components/PageWrapper/index.vue';
import { useUserStore } from '@/store/modules/user';
import { marketApi, yuan, type MarketOverview } from './api';
import PluginsPanel from './components/PluginsPanel.vue';
import OrdersPanel from './components/OrdersPanel.vue';
import StatsPanel from './components/StatsPanel.vue';
import CategoriesPanel from './components/CategoriesPanel.vue';
import GrantsPanel from './components/GrantsPanel.vue';
import SettingsPanel from './components/SettingsPanel.vue';

const userStore = useUserStore();
const can = (code: string) => userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === code);
const tab = ref('plugins');
const overview = ref<MarketOverview | null>(null);

const stats = computed(() => [
  { label: '市场插件', value: overview.value?.plugins ?? '-', icon: 'i-ep-box', tone: 'var(--el-color-primary)' },
  { label: '活跃安装', value: overview.value?.activeInstalls ?? '-', icon: 'i-ep-monitor', tone: 'var(--el-color-success)' },
  { label: '累计下载', value: overview.value?.downloads ?? '-', icon: 'i-ep-download', tone: 'var(--el-color-warning)' },
  { label: `累计收入（${overview.value?.paidOrders ?? 0} 单）`, value: overview.value ? `¥${yuan(overview.value.revenue)}` : '-', icon: 'i-ep-wallet', tone: '#8b5cf6' }
]);

async function loadOverview() {
  try { overview.value = await marketApi.overview(); } catch { overview.value = null; }
}
onMounted(loadOverview);
</script>

<style scoped>
.market-page { display: grid; gap: 16px; padding-top: 4px; }
.market-toolbar { display: flex; justify-content: flex-end; margin-bottom: -8px; }
.market-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.market-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.market-stat__icon { display: grid; flex: none; width: 40px; height: 40px; place-items: center; border-radius: 10px; background: color-mix(in srgb, var(--tone) 12%, var(--el-bg-color)); color: var(--tone); font-size: 18px; }
.market-stat strong { display: block; color: var(--el-text-color-primary); font-size: 20px; font-weight: 600; line-height: 1.2; }
.market-stat small { color: var(--el-text-color-secondary); font-size: 12px; }
.market-tabs :deep(.el-tabs__header) { margin-bottom: 12px; }
.storefront-link { display: inline-flex; align-items: center; gap: 4px; color: var(--el-color-primary); font-size: 13px; }
@media (max-width: 900px) { .market-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
