<template>
  <el-drawer v-model="visible" :title="`${code} 历史与恢复`" size="760px">
    <el-alert
      v-if="recovery"
      class="mb-4"
      :type="recovery.available ? 'warning' : 'success'"
      :title="recovery.message"
      :description="recovery.stage ? `失败阶段：${recovery.stage}` : ''"
      :closable="false"
      v-perm="'system:plugin:recovery'"
    />
    <h3 class="mb-2 font-semibold">版本包历史</h3>
    <el-table v-loading="loading" :data="versions">
      <el-table-column prop="version" label="版本" width="110" />
      <el-table-column prop="source" label="来源" width="90" />
      <el-table-column prop="signature_verified" label="签名已验证" width="110" />
      <el-table-column prop="createdAt" label="时间" min-width="160" />
      <el-table-column label="操作" width="150">
        <template #default="{ row }">
          <a
            v-if="row.downloadable"
            class="mr-3 text-primary"
            :href="pluginApi.historyDownloadUrl(code, row.id)"
            v-perm="'system:plugin:history-download'"
          >下载</a>
          <el-button
            type="primary"
            link
            :loading="redeploying === row.id"
            :disabled="Boolean(redeployDisabledReason)"
            :title="redeployDisabledReason"
            v-perm="'system:plugin:history-redeploy'"
            @click="redeploy(row as PluginVersionHistory)"
          >重部署</el-button>
        </template>
      </el-table-column>
    </el-table>

    <h3 class="mb-2 mt-5 font-semibold">操作记录</h3>
    <el-table v-loading="loading" :data="operations">
      <el-table-column prop="operation" label="操作" width="100" />
      <el-table-column prop="stage" label="阶段" width="100" />
      <el-table-column prop="progress" label="进度" width="80"><template #default="{ row }">{{ row.progress }}%</template></el-table-column>
      <el-table-column label="版本" min-width="150"><template #default="{ row }">{{ row.from_version || '-' }} → {{ row.to_version || '-' }}</template></el-table-column>
      <el-table-column prop="source" label="来源" width="90" />
      <el-table-column prop="result" label="结果" width="90" />
      <el-table-column prop="error_message" label="错误详情" min-width="220" show-overflow-tooltip />
      <el-table-column prop="createdAt" label="时间" width="170" />
    </el-table>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { ElMessageBox } from 'element-plus';
import { pluginApi, type PluginOperation, type PluginRecoveryInfo, type PluginVersionHistory } from '@/api/plugin';

const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ code: string; redeployDisabledReason?: string }>();
const operations = ref<PluginOperation[]>([]);
const versions = ref<PluginVersionHistory[]>([]);
const recovery = ref<PluginRecoveryInfo | null>(null);
const loading = ref(false);
const redeploying = ref<number | null>(null);

async function load() {
  if (!props.code) return;
  loading.value = true;
  try {
    [operations.value, versions.value, recovery.value] = await Promise.all([
      pluginApi.operations(props.code),
      pluginApi.history(props.code),
      pluginApi.recoveryInfo(props.code)
    ]);
  } finally {
    loading.value = false;
  }
}

async function redeploy(row: PluginVersionHistory) {
  if (props.redeployDisabledReason) return;
  try {
    await ElMessageBox.confirm(`确认将插件 ${props.code} 重部署为历史版本 ${row.version} 吗？数据库不会自动降级。`, '历史版本重部署');
  } catch (reason) {
    if (reason === 'cancel' || reason === 'close') return;
    throw reason;
  }
  redeploying.value = row.id;
  try {
    await pluginApi.redeployHistory(props.code, row.id, false);
    await load();
  } finally {
    redeploying.value = null;
  }
}

watch(visible, (open) => { if (open) load(); }, { immediate: true });
</script>
