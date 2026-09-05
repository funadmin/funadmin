<template>
  <el-drawer v-model="visible" :title="`${name} 操作历史`" size="720px">
    <el-table v-loading="loading" :data="operations">
      <el-table-column prop="operation" label="操作" width="100" />
      <el-table-column prop="stage" label="阶段" width="100" />
      <el-table-column prop="progress" label="进度" width="80"><template #default="{ row }">{{ row.progress }}%</template></el-table-column>
      <el-table-column label="版本" min-width="150"><template #default="{ row }">{{ row.from_version || '-' }} → {{ row.to_version || '-' }}</template></el-table-column>
      <el-table-column prop="source" label="来源" width="90" />
      <el-table-column prop="result" label="结果" width="90" />
      <el-table-column prop="error_message" label="错误详情" min-width="220" show-overflow-tooltip />
      <el-table-column prop="recovery_path" label="恢复路径" min-width="220" show-overflow-tooltip />
      <el-table-column prop="createdAt" label="时间" width="170" />
    </el-table>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { pluginApi, type PluginOperation } from '@/api/plugin';
const visible = defineModel<boolean>({ default: false });
const props = defineProps<{ name: string }>();
const operations = ref<PluginOperation[]>([]);
const loading = ref(false);
watch(visible, async (open) => { if (!open || !props.name) return; loading.value = true; try { operations.value = await pluginApi.operations(props.name); } finally { loading.value = false; } });
</script>
