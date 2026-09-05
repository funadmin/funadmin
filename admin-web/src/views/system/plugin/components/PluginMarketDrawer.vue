<template>
  <el-drawer v-model="visible" :title="detail?.title || '市场详情'" size="620px">
    <div v-loading="loading">
      <p class="text-sm text-gray-500">{{ detail?.description }}</p>
      <el-table :data="detail?.versions || []" class="mt-4">
        <el-table-column prop="version" label="版本" width="120" />
        <el-table-column prop="changelog" label="更新说明" min-width="240" />
        <el-table-column label="兼容" width="80"><template #default="{ row }"><el-tag :type="row.compatible ? 'success' : 'danger'">{{ row.compatible ? '是' : '否' }}</el-tag></template></el-table-column>
        <el-table-column label="操作" width="100"><template #default="{ row }"><el-button type="primary" link :disabled="!row.compatible" v-perm="'system:plugin:install'" @click="emit('install', row.version)">安装</el-button></template></el-table-column>
      </el-table>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { pluginApi, type MarketplacePlugin } from '@/api/plugin';
const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ name: string }>();
const emit = defineEmits<{ install: [version: string] }>();
const detail = ref<MarketplacePlugin | null>(null);
const loading = ref(false);
watch(visible, async (open) => { if (!open || !props.name) return; loading.value = true; try { detail.value = await pluginApi.marketDetail(props.name); detail.value.versions = await pluginApi.marketVersions(props.name); } finally { loading.value = false; } });
</script>
