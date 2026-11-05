<template>
  <div class="panel">
    <div class="panel-toolbar">
      <el-select v-model="pluginId" clearable placeholder="全部插件" class="panel-filter" @change="reload">
        <el-option v-for="item in plugins" :key="item.id" :value="item.id" :label="`${item.name}（${item.code}）`" />
      </el-select>
      <el-button type="primary" @click="openCreate"><i class="i-ep-plus" />新增授权</el-button>
      <span class="panel-hint">付费插件只有获得授权的会员才能下载安装；在线购买会自动生成授权，也可在此手动开通或延期。</span>
    </div>
    <el-table v-loading="loading" :data="rows" row-key="id" empty-text="暂无授权记录">
      <el-table-column label="插件" min-width="180"><template #default="{ row }">{{ row.pluginName }}<span class="muted mono"> · {{ row.pluginCode }}</span></template></el-table-column>
      <el-table-column label="会员账号" min-width="160"><template #default="{ row }">{{ row.nickname || row.username }}<span class="muted"> · {{ row.username }}</span></template></el-table-column>
      <el-table-column label="来源" width="110"><template #default="{ row }">{{ row.source === 'order' ? (row.plan === 'yearly' ? '购买 · 按年' : '购买 · 买断') : '后台授予' }}</template></el-table-column>
      <el-table-column label="到期时间" min-width="160"><template #default="{ row }">{{ row.expiresAt || '永久' }}</template></el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }"><el-tag :type="row.active ? 'success' : 'info'" effect="light" round>{{ row.active ? '有效' : row.status === 1 ? '已过期' : '已停用' }}</el-tag></template>
      </el-table-column>
      <el-table-column label="备注" prop="remark" min-width="140" show-overflow-tooltip />
      <el-table-column label="操作" width="140">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row as MarketGrant)">编辑</el-button>
          <el-button link type="danger" @click="remove(row as MarketGrant)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="panel-pager">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize" :total="total" layout="total, prev, pager, next" background @change="load" />
    </div>

    <el-dialog v-model="editOpen" :title="editingId ? '编辑授权' : '新增授权'" width="min(460px, 94vw)">
      <el-form :model="form" label-position="top">
        <template v-if="!editingId">
          <el-form-item label="插件" required>
            <el-select v-model="form.pluginId" placeholder="选择插件" filterable>
              <el-option v-for="item in plugins" :key="item.id" :value="item.id" :label="`${item.name}（${item.code}）`" />
            </el-select>
          </el-form-item>
          <el-form-item label="会员账号" required><el-input v-model="form.account" placeholder="市场站点的会员用户名、邮箱或手机号" /></el-form-item>
        </template>
        <el-form-item label="到期时间（留空为永久）">
          <el-date-picker v-model="form.expiresAt" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="永久有效" clearable />
        </el-form-item>
        <el-form-item label="启用"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" maxlength="255" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editOpen = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { marketApi, type GrantPayload, type MarketGrant, type MarketPlugin } from '../api';

const emit = defineEmits<{ changed: [] }>();
const loading = ref(false);
const rows = ref<MarketGrant[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(10);
const pluginId = ref<number | undefined>();
const plugins = ref<MarketPlugin[]>([]);

async function load() {
  loading.value = true;
  try {
    const result = await marketApi.grants({ pluginId: pluginId.value, page: page.value, pageSize: pageSize.value });
    rows.value = result.list;
    total.value = result.total;
  } finally { loading.value = false; }
}
function reload() { page.value = 1; void load(); }

const editOpen = ref(false);
const saving = ref(false);
const editingId = ref(0);
const form = reactive<Required<GrantPayload>>({ pluginId: 0, account: '', expiresAt: '', status: 1, remark: '' });
function openCreate() {
  editingId.value = 0;
  Object.assign(form, { pluginId: pluginId.value ?? 0, account: '', expiresAt: '', status: 1, remark: '' });
  editOpen.value = true;
}
function openEdit(row: MarketGrant) {
  editingId.value = row.id;
  Object.assign(form, { pluginId: row.pluginId, account: row.username, expiresAt: row.expiresAt, status: row.status, remark: row.remark });
  editOpen.value = true;
}
async function save() {
  saving.value = true;
  try {
    const payload = { expiresAt: form.expiresAt ?? '', status: form.status, remark: form.remark };
    if (editingId.value) await marketApi.updateGrant(editingId.value, payload);
    else await marketApi.createGrant({ ...payload, pluginId: form.pluginId, account: form.account.trim() });
    editOpen.value = false;
    await load();
    emit('changed');
  } finally { saving.value = false; }
}
async function remove(row: MarketGrant) {
  try { await ElMessageBox.confirm(`删除 ${row.username} 对「${row.pluginName}」的授权？`, '删除授权', { type: 'warning' }); } catch { return; }
  await marketApi.deleteGrant(row.id);
  await load();
  emit('changed');
}

onMounted(async () => {
  const result = await marketApi.plugins({ page: 1, pageSize: 100 });
  plugins.value = result.list;
  await load();
});
</script>

<style scoped>
.panel { display: grid; gap: 12px; }
.panel-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.panel-toolbar :deep(.el-button i) { margin-right: 4px; }
.panel-filter { width: 240px; }
.panel-hint { color: var(--el-text-color-secondary); font-size: 12px; }
.panel-pager { display: flex; justify-content: flex-end; }
.muted { color: var(--el-text-color-secondary); font-size: 12px; }
.mono { font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); }
:deep(.el-select), :deep(.el-date-editor) { width: 100%; }
.panel-filter:deep(.el-select) { width: 240px; }
</style>
