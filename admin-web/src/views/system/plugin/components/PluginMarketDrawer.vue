<template>
  <el-drawer v-model="visible" :title="detail?.name || '市场详情'" size="720px">
    <div v-loading="loading">
      <p class="text-sm text-gray-500">{{ detail?.description }}</p>
      <el-table :data="detail?.versions || []" class="mt-4">
        <el-table-column prop="version" label="版本" width="100" />
        <el-table-column label="插件包信息" min-width="280">
          <template #default="{ row }">
            <div>清单协议：v{{ row.manifestSchema }}</div>
            <div>包格式：{{ row.packageFormat === 'funadmin-native-app-v1' ? '原生应用包' : '其他格式包' }}</div>
            <div>应用能力：{{ applicationCapabilities(row.applications) }}</div>
            <div>签名算法：{{ row.signatureAlgorithm === 'ed25519' ? 'Ed25519' : (row.signatureAlgorithm || '未签名') }}</div>
            <div>数据库能力：{{ row.databaseCapability || '无迁移要求' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="兼容性" min-width="180">
          <template #default="{ row }">
            <el-tag :type="row.compatible ? 'success' : 'danger'">{{ row.compatible ? '兼容' : '不兼容' }}</el-tag>
            <div v-if="row.compatibleReason" class="text-xs text-red-500">{{ row.compatibleReason }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="changelog" label="更新说明" min-width="180" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button type="primary" link :disabled="!row.compatible" :title="row.compatibleReason || ''" v-perm="'system:plugin:install'" @click="install(row.compatible, row.version)">安装</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { pluginApi, type MarketplacePlugin } from '@/api/plugin';
import { applicationLabel } from '../pluginDisplay';

const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ code: string }>();
const emit = defineEmits<{ install: [version: string] }>();
const detail = ref<MarketplacePlugin | null>(null);
const loading = ref(false);

function applicationCapabilities(applications: Record<string, boolean>) {
  return Object.entries(applications || {}).filter(([, enabled]) => enabled).map(([name]) => applicationLabel(name)).join('、') || '无';
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
