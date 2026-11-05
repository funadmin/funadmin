<template>
  <el-drawer v-model="visible" :title="t('plugin.historyDrawerTitle', { code }, { default: '{code} 历史与恢复' })" size="760px">
    <el-alert
      v-if="recovery"
      class="mb-4"
      :type="recovery.available ? 'warning' : 'success'"
      :title="recovery.message"
      :description="recovery.stage ? t('plugin.failedStage', { stage: recovery.stage }, { default: '失败阶段：{stage}' }) : ''"
      :closable="false"
      v-perm="'system:plugin:recovery'"
    />
    <h3 class="mb-2 font-semibold">{{ t('plugin.versionHistory', '版本包历史') }}</h3>
    <el-table v-loading="loading" :data="versions">
      <el-table-column prop="version" :label="t('plugin.version', '版本')" width="110" />
      <el-table-column prop="source" :label="t('plugin.historySource', '来源')" width="90" />
      <el-table-column prop="signature_verified" :label="t('plugin.signatureVerified', '签名已验证')" width="110" />
      <el-table-column prop="createdAt" :label="t('plugin.historyTime', '时间')" min-width="160" />
      <el-table-column :label="t('common.operation', '操作')" width="150">
        <template #default="{ row }">
          <a
            v-if="row.downloadable"
            class="mr-3 text-primary"
            :href="pluginApi.historyDownloadUrl(code, row.id)"
            v-perm="'system:plugin:history-download'"
          >{{ t('common.download', '下载') }}</a>
          <el-button
            type="primary"
            link
            :loading="redeploying === row.id"
            :disabled="Boolean(redeployDisabledReason)"
            :title="redeployDisabledReason"
            v-perm="'system:plugin:history-redeploy'"
            @click="redeploy(row as PluginVersionHistory)"
          >{{ t('plugin.redeploy', '重部署') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <h3 class="mb-2 mt-5 font-semibold">{{ t('plugin.operationRecords', '操作记录') }}</h3>
    <el-table v-loading="loading" :data="operations">
      <el-table-column prop="operation" :label="t('common.operation', '操作')" width="100" />
      <el-table-column prop="stage" :label="t('plugin.stage', '阶段')" width="100" />
      <el-table-column prop="progress" :label="t('plugin.progress', '进度')" width="80"><template #default="{ row }">{{ row.progress }}%</template></el-table-column>
      <el-table-column :label="t('plugin.version', '版本')" min-width="150"><template #default="{ row }">{{ row.from_version || '-' }} → {{ row.to_version || '-' }}</template></el-table-column>
      <el-table-column prop="source" :label="t('plugin.historySource', '来源')" width="90" />
      <el-table-column prop="result" :label="t('plugin.result', '结果')" width="90" />
      <el-table-column prop="error_message" :label="t('plugin.errorDetail', '错误详情')" min-width="220" show-overflow-tooltip />
      <el-table-column prop="createdAt" :label="t('plugin.historyTime', '时间')" width="170" />
    </el-table>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { pluginApi, type PluginOperation, type PluginRecoveryInfo, type PluginVersionHistory } from '@/api/plugin';

const { t } = useI18n();
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
    await ElMessageBox.confirm(t('plugin.redeployConfirm', { code: props.code, version: row.version }, { default: '确认将插件 {code} 重部署为历史版本 {version} 吗？数据库不会自动降级。' }), t('plugin.redeployTitle', '历史版本重部署'));
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
