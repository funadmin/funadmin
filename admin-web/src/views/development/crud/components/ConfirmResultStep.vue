<template>
  <template v-if="!result">
    <el-alert title="确认 token 仅保存在当前页面内存中；返回修改、定义变化、失败或离开页面后会立即清除" type="warning" show-icon :closable="false" class="mb-3" />
    <el-descriptions title="生成风险摘要" :column="1" border class="mb-4">
      <el-descriptions-item label="新建">{{ counts.create }} 个文件</el-descriptions-item>
      <el-descriptions-item label="覆盖冲突">{{ counts.conflict }} 个文件</el-descriptions-item>
      <el-descriptions-item label="无变更">{{ counts.unchanged }} 个文件</el-descriptions-item>
      <el-descriptions-item label="阻塞">{{ counts.blocked }} 个文件</el-descriptions-item>
    </el-descriptions>
    <el-checkbox-group :model-value="allowOverwrite" @update:model-value="update">
      <div v-for="path in conflicts" :key="path" class="conflict-row"><el-checkbox :value="path" :disabled="!canOverwrite">授权覆盖 {{ path }}</el-checkbox></div>
    </el-checkbox-group>
    <el-alert v-if="conflicts.length && !canOverwrite" title="存在冲突，但当前账号没有 overwrite 权限" type="error" show-icon :closable="false" />
    <el-alert v-else-if="counts.blocked" title="存在被目录或其他对象阻塞的目标，无法生成" type="error" show-icon :closable="false" />
  </template>
  <template v-else>
    <el-result :icon="result.manifest?.error ? 'error' : 'success'" :title="result.manifest?.error ? 'CRUD 生成失败' : 'CRUD 生成完成'" :sub-title="`审计记录 #${result.generationId}`" />
    <el-descriptions title="生成结果" :column="1" border>
      <el-descriptions-item label="状态">{{ result.write?.status || result.manifest?.status || '-' }}</el-descriptions-item>
      <el-descriptions-item label="created"><div v-if="created.length" class="path-list"><code v-for="path in created" :key="path">{{ path }}</code></div><span v-else>0</span></el-descriptions-item>
      <el-descriptions-item label="overwritten"><div v-if="overwritten.length" class="path-list"><code v-for="path in overwritten" :key="path">{{ path }}</code></div><span v-else>0</span></el-descriptions-item>
      <el-descriptions-item label="skipped"><div v-if="skipped.length" class="path-list"><code v-for="path in skipped" :key="path">{{ path }}</code></div><span v-else>0</span></el-descriptions-item>
      <el-descriptions-item label="rollback">{{ result.write?.rollback?.join('；') || '无需回滚' }}</el-descriptions-item>
      <el-descriptions-item label="validation">{{ result.manifest?.validationResult?.valid === false ? '失败' : '通过' }}</el-descriptions-item>
    </el-descriptions>
  </template>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { CheckboxGroupValueType } from 'element-plus';
import type { CrudGeneration, CrudPreview } from '@/types/development/crud';

const props = defineProps<{ preview: CrudPreview; result: CrudGeneration | null; conflicts: string[]; allowOverwrite: string[]; canOverwrite: boolean }>();
const emit = defineEmits<{ 'update:allowOverwrite': [value: string[]] }>();
const update = (value: CheckboxGroupValueType) => emit('update:allowOverwrite', value.map(String));
const counts = computed(() => ({
  create: props.preview.plan.files.filter((file) => file.status === 'create').length,
  conflict: props.preview.plan.files.filter((file) => file.status === 'conflict').length,
  unchanged: props.preview.plan.files.filter((file) => file.status === 'unchanged').length,
  blocked: props.preview.plan.files.filter((file) => file.status === 'blocked').length
}));
const created = computed(() => props.result?.manifest?.createdFiles || props.result?.plan?.files.filter((file) => file.status === 'create').map((file) => file.path) || []);
const overwritten = computed(() => props.result?.manifest?.overwrittenFiles || props.result?.plan?.files.filter((file) => file.status === 'conflict').map((file) => file.path) || []);
const skipped = computed(() => props.result?.plan?.files.filter((file) => file.status === 'unchanged').map((file) => file.path) || []);
</script>

<style scoped>
.conflict-row { margin-bottom: 8px; overflow-wrap: anywhere; }
.path-list { display: flex; flex-direction: column; gap: 4px; overflow-wrap: anywhere; }
</style>
