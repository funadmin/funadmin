<template>
  <el-card class="approval-card" shadow="never">
    <template #header><div class="approval-card__title"><strong>需要审批</strong><el-tag type="danger">{{ approval.operation }}</el-tag></div></template>
    <el-alert :title="approval.risk_reason || '该操作需要人工确认'" type="warning" show-icon :closable="false" />
    <details><summary>危险详情与影响范围</summary><pre>{{ formattedImpact }}</pre></details>
    <el-input v-model="feedback" type="textarea" :rows="2" placeholder="拒绝时可填写反馈" />
    <div class="approval-card__actions">
      <el-button type="success" @click="decide('approve', 'once')">仅本次允许</el-button>
      <el-button type="primary" @click="decide('approve', 'session_operation')">允许会话操作</el-button>
      <el-button type="danger" plain @click="decide('reject', 'once')">拒绝</el-button>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { AiApproval, AiApprovalScope } from '@/api/development/ai';

const props = defineProps<{ approval: AiApproval }>();
const emit = defineEmits<{ decision: [action: 'approve' | 'reject', scope: AiApprovalScope, feedback: string] }>();
const feedback = ref('');
const formattedImpact = computed(() => JSON.stringify(props.approval.impact, null, 2));
function decide(action: 'approve' | 'reject', scope: AiApprovalScope) { emit('decision', action, scope, feedback.value); }
</script>

<style scoped>
.approval-card { border-color: var(--el-color-warning-light-5); }
.approval-card__title, .approval-card__actions { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
details { margin: 12px 0; } details pre { overflow: auto; white-space: pre-wrap; }
.approval-card__actions { justify-content: flex-end; margin-top: 12px; flex-wrap: wrap; }
</style>
