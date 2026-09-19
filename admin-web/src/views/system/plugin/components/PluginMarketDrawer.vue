<template>
  <el-drawer v-model="visible" :title="detail?.name || t('plugin.marketDetailTitle', '市场详情')" size="720px">
    <div v-loading="loading">
      <p class="text-sm text-gray-500">{{ detail?.description }}</p>
      <el-table :data="detail?.versions || []" class="mt-4">
        <el-table-column prop="version" :label="t('plugin.version', '版本')" width="100" />
        <el-table-column :label="t('plugin.packageInfo', '插件包信息')" min-width="280">
          <template #default="{ row }">
            <div>{{ t('plugin.manifestSchema', '清单协议') }}：v{{ row.manifestSchema }}</div>
            <div>{{ t('plugin.packageFormat', '包格式') }}：{{ row.packageFormat === 'funadmin-native-app-v1' ? t('plugin.capNative', '原生应用包') : t('plugin.capOther', '其他格式包') }}</div>
            <div>{{ t('plugin.appCapabilities', '应用能力') }}：{{ applicationCapabilities(row.applications) }}</div>
            <div>{{ t('plugin.signatureAlgorithm', '签名算法') }}：{{ row.signatureAlgorithm === 'ed25519' ? 'Ed25519' : (row.signatureAlgorithm || t('plugin.capUnsigned', '未签名')) }}</div>
            <div>{{ t('plugin.dbCapability', '数据库能力') }}：{{ row.databaseCapability || t('plugin.capDbNone', '无迁移要求') }}</div>
          </template>
        </el-table-column>
        <el-table-column :label="t('plugin.compatibility', '兼容性')" min-width="180">
          <template #default="{ row }">
            <el-tag :type="row.compatible ? 'success' : 'danger'">{{ row.compatible ? t('plugin.compatible', '兼容') : t('plugin.incompatible', '不兼容') }}</el-tag>
            <div v-if="row.compatibleReason" class="text-xs text-red-500">{{ row.compatibleReason }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="changelog" :label="t('plugin.changelog', '更新说明')" min-width="180" />
        <el-table-column :label="t('common.operation', '操作')" width="100">
          <template #default="{ row }">
            <el-button type="primary" link :disabled="!row.compatible" :title="row.compatibleReason || ''" v-perm="'system:plugin:install'" @click="install(row.compatible, row.version)">{{ t('plugin.install', '安装') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { pluginApi, type MarketplacePlugin } from '@/api/plugin';
import { applicationLabel } from '../pluginDisplay';

const { t } = useI18n();
const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ code: string }>();
const emit = defineEmits<{ install: [version: string] }>();
const detail = ref<MarketplacePlugin | null>(null);
const loading = ref(false);

function applicationCapabilities(applications: Record<string, boolean>) {
  return Object.entries(applications || {}).filter(([, enabled]) => enabled).map(([name]) => t(`plugin.application.${name}`, applicationLabel(name))).join('、') || t('plugin.none', '无');
}

function install(compatible: boolean, version: string) {
  if (compatible) emit('install', version);
}

watch(visible, async (open) => {
  if (!open || !props.code) return;
  loading.value = true;
  try {
    detail.value = await pluginApi.marketDetail(props.code);
    detail.value.versions = await pluginApi.marketVersions(props.code);
  } finally {
    loading.value = false;
  }
});
</script>
