<template>
  <div class="panel">
    <div class="panel-toolbar">
      <el-radio-group v-model="status" @change="reload">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="paid">已支付</el-radio-button>
        <el-radio-button value="pending">待支付</el-radio-button>
        <el-radio-button value="closed">已关闭</el-radio-button>
      </el-radio-group>
      <el-input v-model="keyword" clearable placeholder="订单号 / 交易号 / 会员 / 插件" class="panel-search" @keyup.enter="reload" @clear="reload">
        <template #prefix><i class="i-ep-search" /></template>
      </el-input>
      <span class="panel-summary">已支付 <strong>{{ summary.paidCount }}</strong> 单，合计 <strong>¥{{ yuan(summary.paidAmount) }}</strong></span>
    </div>

    <el-table v-loading="loading" :data="rows" row-key="orderNo" empty-text="暂无订单">
      <el-table-column label="订单号" min-width="200"><template #default="{ row }"><span class="mono">{{ row.orderNo }}</span></template></el-table-column>
      <el-table-column label="插件" min-width="140"><template #default="{ row }">{{ row.pluginName }}<div class="muted mono">{{ row.pluginCode }}</div></template></el-table-column>
      <el-table-column label="会员" min-width="120"><template #default="{ row }">{{ row.nickname || row.username }}<div class="muted">@{{ row.username }}</div></template></el-table-column>
      <el-table-column label="方案" prop="planText" width="130" />
      <el-table-column label="金额" width="100"><template #default="{ row }">¥{{ yuan(row.amount) }}</template></el-table-column>
      <el-table-column label="渠道" prop="channelText" width="90" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }"><el-tag :type="statusMeta[(row as MarketOrder).status].type" effect="light" round>{{ statusMeta[(row as MarketOrder).status].label }}</el-tag></template>
      </el-table-column>
      <el-table-column label="时间" min-width="160"><template #default="{ row }">{{ row.paidAt || row.createdAt }}</template></el-table-column>
      <el-table-column label="操作" width="90" fixed="right">
        <template #default="{ row }"><el-button link type="primary" @click="openDetail(row as MarketOrder)">详情</el-button></template>
      </el-table-column>
    </el-table>
    <div class="panel-pager">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize" :total="total" :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next" background @change="load" />
    </div>

    <el-drawer v-model="detailOpen" title="订单详情" size="min(560px, 100vw)" append-to-body>
      <div v-if="detail" v-loading="detailLoading" class="order-detail">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="订单号"><span class="mono">{{ detail.orderNo }}</span></el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="statusMeta[detail.status].type" effect="light" round>{{ statusMeta[detail.status].label }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="插件">{{ detail.pluginName }}（{{ detail.pluginCode }}）</el-descriptions-item>
          <el-descriptions-item label="会员">{{ detail.nickname || detail.username }}（@{{ detail.username }}{{ detail.email ? ` · ${detail.email}` : '' }}）</el-descriptions-item>
          <el-descriptions-item label="方案">{{ detail.planText }}</el-descriptions-item>
          <el-descriptions-item label="应付 / 实付">¥{{ yuan(detail.amount) }} / ¥{{ yuan(detail.paidAmount) }}</el-descriptions-item>
          <el-descriptions-item label="支付渠道">{{ detail.channelText }}</el-descriptions-item>
          <el-descriptions-item label="渠道交易号"><span class="mono">{{ detail.tradeNo || '—' }}</span></el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ detail.createdAt }}（IP {{ detail.clientIp || '—' }}）</el-descriptions-item>
          <el-descriptions-item label="支付时间">{{ detail.paidAt || '—' }}</el-descriptions-item>
          <el-descriptions-item label="备注">{{ detail.remark || '—' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="canManage && detail.status !== 'paid'" class="order-actions">
          <el-input v-model="confirmRemark" maxlength="200" placeholder="收款说明（例如对公转账流水号），人工确认时必填" />
          <div class="order-actions__buttons">
            <el-button type="primary" :loading="acting" @click="confirmPaid">确认已收款并开通授权</el-button>
            <el-button v-if="detail.status === 'pending'" :loading="acting" @click="closeOrder">关闭订单</el-button>
          </div>
        </div>

        <h4>支付回调与查询日志</h4>
        <el-table :data="detail.logs || []" size="small" empty-text="暂无日志">
          <el-table-column label="时间" prop="createdAt" width="160" />
          <el-table-column label="事件" width="110"><template #default="{ row }">{{ row.channel }} · {{ row.event }}</template></el-table-column>
          <el-table-column label="验签" width="70"><template #default="{ row }"><el-tag :type="row.verified ? 'success' : 'danger'" size="small">{{ row.verified ? '通过' : '失败' }}</el-tag></template></el-table-column>
          <el-table-column label="结果" prop="result" show-overflow-tooltip />
        </el-table>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { marketApi, yuan, type MarketOrder, type OrderStatus } from '../api';

defineProps<{ canManage: boolean }>();
const emit = defineEmits<{ changed: [] }>();

const statusMeta: Record<OrderStatus, { label: string; type: 'success' | 'warning' | 'info' }> = {
  paid: { label: '已支付', type: 'success' },
  pending: { label: '待支付', type: 'warning' },
  closed: { label: '已关闭', type: 'info' }
};
const loading = ref(false);
const rows = ref<MarketOrder[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(10);
const status = ref('');
const keyword = ref('');
const summary = ref({ paidCount: 0, paidAmount: 0 });

async function load() {
  loading.value = true;
  try {
    const result = await marketApi.orders({ status: status.value, keyword: keyword.value, page: page.value, pageSize: pageSize.value });
    rows.value = result.list;
    total.value = result.total;
    summary.value = result.summary;
  } finally { loading.value = false; }
}
function reload() { page.value = 1; void load(); }

const detailOpen = ref(false);
const detailLoading = ref(false);
const detail = ref<MarketOrder | null>(null);
const confirmRemark = ref('');
const acting = ref(false);
async function openDetail(row: MarketOrder) {
  detail.value = row;
  confirmRemark.value = '';
  detailOpen.value = true;
  await refreshDetail(row.orderNo);
}
async function refreshDetail(orderNo: string) {
  detailLoading.value = true;
  try { detail.value = await marketApi.order(orderNo); } finally { detailLoading.value = false; }
}
async function confirmPaid() {
  if (!detail.value) return;
  try {
    await ElMessageBox.confirm(`确认已收到 ¥${yuan(detail.value.amount)}，并为 @${detail.value.username} 开通「${detail.value.pluginName}」${detail.value.planText}？`, '确认收款', { type: 'warning' });
  } catch { return; }
  acting.value = true;
  try {
    await marketApi.confirmOrder(detail.value.orderNo, confirmRemark.value);
    await Promise.all([refreshDetail(detail.value.orderNo), load()]);
    emit('changed');
  } finally { acting.value = false; }
}
async function closeOrder() {
  if (!detail.value) return;
  try { await ElMessageBox.confirm('关闭后用户需要重新下单，确定关闭？', '关闭订单', { type: 'warning' }); } catch { return; }
  acting.value = true;
  try {
    await marketApi.closeOrder(detail.value.orderNo);
    await Promise.all([refreshDetail(detail.value.orderNo), load()]);
  } finally { acting.value = false; }
}

onMounted(load);
</script>

<style scoped>
.panel { display: grid; gap: 12px; }
.panel-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.panel-search { width: 260px; }
.panel-summary { margin-left: auto; color: var(--el-text-color-secondary); font-size: 13px; }
.panel-summary strong { color: var(--el-text-color-primary); }
.panel-pager { display: flex; justify-content: flex-end; }
.mono { font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; }
.muted { color: var(--el-text-color-secondary); font-size: 12px; }
.order-detail { display: grid; gap: 16px; }
.order-detail h4 { margin: 0; font-size: 14px; }
.order-actions { display: grid; gap: 8px; padding: 12px; border: 1px dashed var(--el-border-color); border-radius: 10px; }
.order-actions__buttons { display: flex; gap: 8px; }
.order-actions__buttons :deep(.el-button + .el-button) { margin-left: 0; }
@media (max-width: 640px) { .panel-search { width: 100%; } .panel-summary { margin-left: 0; } }
</style>
