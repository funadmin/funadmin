<template>
  <el-empty v-if="!preview" description="尚未生成预览，请校验并刷新预览" />
  <el-tabs v-else v-model="activeGroup" class="preview-tabs">
    <el-tab-pane v-for="group in groups" :key="group.key" :name="group.key">
      <template #label>{{ group.label }}（{{ groupedFiles[group.key].length }}）</template>
      <el-table :data="groupedFiles[group.key]" border>
        <el-table-column prop="path" label="文件" min-width="320" />
        <el-table-column prop="status" label="状态" width="110"><template #default="{ row }"><el-tag :type="tagType(row.status)">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
        <el-table-column label="Diff" min-width="360"><template #default="{ row }"><el-collapse v-if="row.diff"><el-collapse-item title="查看变更"><pre class="diff">{{ row.diff }}</pre></el-collapse-item></el-collapse><span v-else>无变更</span></template></el-table-column>
      </el-table>
    </el-tab-pane>
  </el-tabs>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { CrudPlanFile, CrudPlanStatus, CrudPreview } from '@/types/development/crud';

const props = defineProps<{ preview: CrudPreview | null }>();
const activeGroup = ref('database');
const groups = [
  { key: 'database', label: '数据库' }, { key: 'php', label: 'PHP' },
  { key: 'vue', label: 'Vue' }, { key: 'test', label: '测试' },
  { key: 'permission', label: '权限菜单' }
] as const;
type GroupKey = typeof groups[number]['key'];
const fileGroup = (file: CrudPlanFile): GroupKey => {
  const path = file.path.toLowerCase();
  if (path.includes('permission') || path.includes('menu')) return 'permission';
  if (path.includes('test') || path.includes('spec.')) return 'test';
  if (path.endsWith('.sql') || path.includes('migration')) return 'database';
  if (path.endsWith('.php')) return 'php';
  return 'vue';
};
const groupedFiles = computed<Record<GroupKey, CrudPlanFile[]>>(() => {
  const grouped = { database: [], php: [], vue: [], test: [], permission: [] } as Record<GroupKey, CrudPlanFile[]>;
  for (const file of props.preview?.plan.files || []) grouped[fileGroup(file)].push(file);
  return grouped;
});
const statusLabel = (status: CrudPlanStatus) => ({ create: '新建', unchanged: '无变更', conflict: '冲突', blocked: '阻塞' })[status];
const tagType = (status: CrudPlanStatus) => status === 'conflict' || status === 'blocked' ? 'danger' : status === 'create' ? 'success' : 'info';
</script>

<style scoped>
.diff { max-height: 320px; overflow: auto; white-space: pre-wrap; word-break: break-word; font-size: 12px; }
</style>
