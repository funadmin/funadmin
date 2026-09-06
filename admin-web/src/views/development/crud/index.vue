<template>
  <PageWrapper title="CRUD 生成器" subtitle="四步完成表结构检查、字段设计、安全预览与生成">
    <el-card v-loading="loading">
      <el-steps :active="workbench.step" finish-status="success" align-center class="workbench-steps"><el-step v-for="item in workbench.steps" :key="item.index" :title="item.title" /></el-steps>
      <el-alert v-if="workbench.error" :title="workbench.error" type="error" show-icon closable class="mb-4" @close="workbench.error = ''" />
      <BasicsStep v-if="workbench.step === 0" v-model:connection="connection" v-model:table="table" :connections="connections" :tables="tables" :model="definition" :schema="schema" :inferring="inferring" />
      <FieldsStep v-else-if="workbench.step === 1 && definition" :fields="definition.fields" />
      <CapabilitiesPreviewStep v-else-if="workbench.step === 2 && definition" :model="definition" :preview="workbench.preview" :loading="loading" :invalidated="workbench.previewInvalidated" @refresh="refreshPreview" />
      <ConfirmResultStep v-else-if="workbench.step === 3 && workbench.preview" v-model:allow-overwrite="workbench.allowOverwrite" :preview="workbench.preview" :result="workbench.result" :conflicts="workbench.conflicts()" :can-overwrite="canOverwrite" />
      <div class="workbench-actions">
        <el-button :disabled="workbench.step === 0 || loading" @click="previous">{{ workbench.step === 3 && workbench.result ? '返回修改' : '上一步' }}</el-button>
        <el-button v-if="workbench.step < 2" type="primary" :disabled="loading" @click="next">保存并继续</el-button>
        <el-button v-else-if="workbench.step === 2" type="primary" :disabled="!workbench.preview || loading" @click="next">保存预览并继续</el-button>
        <el-button v-else-if="!workbench.result" type="danger" :disabled="!workbench.canGenerate(canOverwrite) || hasBlocked || loading" @click="generate">确认生成</el-button>
        <el-button v-else type="primary" @click="reset">重新开始</el-button>
      </div>
    </el-card>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { crudDevelopmentApi } from '@/api/development/crud';
import { useUserStore } from '@/store/modules/user';
import type { CrudConnection, CrudDefinition, CrudTable } from '@/types/development/crud';
import { createCrudDefinition, createCrudWorkbench, validateWorkbenchStep } from './workbench';
import BasicsStep from './components/BasicsStep.vue';
import CapabilitiesPreviewStep from './components/CapabilitiesPreviewStep.vue';
import ConfirmResultStep from './components/ConfirmResultStep.vue';
import FieldsStep from './components/FieldsStep.vue';

defineOptions({ name: 'DevelopmentCrud' });
const userStore = useUserStore();
const workbench = createCrudWorkbench();
const loading = ref(false);
const inferring = ref(false);
const connections = ref<CrudConnection[]>([]);
const tables = ref<CrudTable[]>([]);
const connection = ref('');
const table = ref('');
const definition = ref<CrudDefinition | null>(null);
const schema = ref<Record<string, unknown> | null>(null);
let definitionSnapshot = '';
let inferSequence = 0;
const canOverwrite = computed(() => userStore.permissions.includes('development:crud:overwrite'));
const hasBlocked = computed(() => workbench.preview?.plan.files.some((file) => file.status === 'blocked') || false);

watch(connection, async (value) => {
  inferSequence += 1;
  table.value = '';
  definition.value = null;
  schema.value = null;
  workbench.definition = null;
  workbench.invalidatePreview();
  tables.value = value ? await crudDevelopmentApi.tables(value) : [];
});
watch(table, async (value) => {
  const sequence = ++inferSequence;
  definition.value = null;
  schema.value = null;
  workbench.definition = null;
  workbench.invalidatePreview();
  if (!connection.value || !value) return;
  try {
    inferring.value = true;
    const inferred = await crudDevelopmentApi.infer(connection.value, value);
    if (sequence !== inferSequence) return;
    schema.value = inferred.schema;
    definition.value = createCrudDefinition(connection.value, value, inferred.fields);
    workbench.definition = definition.value;
  } catch (error) {
    workbench.fail(error instanceof Error ? error.message : '表结构推断失败');
  } finally {
    if (sequence === inferSequence) inferring.value = false;
  }
});
watch(definition, (value) => {
  const current = value ? JSON.stringify(value) : '';
  if (definitionSnapshot && current !== definitionSnapshot) workbench.invalidatePreview();
}, { deep: true, flush: 'sync' });

const validationContext = () => ({ fields: definition.value?.fields, capabilities: definition.value?.capabilities, dataScope: definition.value?.dataScope });
const requireValidStep = () => {
  if (workbench.step === 0 && (!connection.value || !table.value || !definition.value)) throw new Error('请选择数据源和数据表，并等待结构推断完成');
  const error = validateWorkbenchStep(workbench.step, validationContext());
  if (error) throw new Error(error);
};
const next = async () => {
  workbench.error = '';
  try {
    requireValidStep();
    if (workbench.step === 2 && !workbench.preview) throw new Error('请先校验并刷新预览');
    workbench.step += 1;
  } catch (error) {
    workbench.fail(error instanceof Error ? error.message : '操作失败');
  }
};
const refreshPreview = async () => {
  if (!definition.value) return;
  workbench.error = '';
  try {
    loading.value = true;
    requireValidStep();
    await crudDevelopmentApi.validate(definition.value);
    workbench.setPreview(await crudDevelopmentApi.preview(definition.value));
    definitionSnapshot = JSON.stringify(definition.value);
  } catch (error) {
    workbench.fail(error instanceof Error ? error.message : '预览失败');
  } finally {
    loading.value = false;
  }
};
const previous = () => {
  workbench.clearSensitive();
  workbench.result = null;
  if (workbench.step === 3) {
    workbench.preview = null;
    workbench.previewInvalidated = true;
  }
  workbench.step = Math.max(0, workbench.step - 1);
};
const generate = async () => {
  if (!definition.value) return;
  try {
    loading.value = true;
    workbench.result = await crudDevelopmentApi.generate(definition.value, workbench.confirmToken, workbench.allowOverwrite);
    workbench.clearSensitive();
    workbench.step = 3;
  } catch (error) {
    workbench.fail(error instanceof Error ? error.message : '生成失败');
  } finally {
    loading.value = false;
  }
};
const reset = () => {
  workbench.clearSensitive();
  workbench.step = 0;
  workbench.preview = null;
  workbench.result = null;
  workbench.previewInvalidated = false;
  definition.value = null;
  schema.value = null;
  table.value = '';
  definitionSnapshot = '';
};
onMounted(async () => {
  connections.value = await crudDevelopmentApi.connections();
  connection.value = connections.value[0]?.name || '';
});
onBeforeUnmount(workbench.clearSensitive);
</script>

<style scoped>
.workbench-steps { margin-bottom: 24px; overflow-x: auto; padding-bottom: 4px; }
.workbench-actions { display: flex; justify-content: space-between; gap: 12px; margin-top: 24px; position: sticky; bottom: 0; z-index: 2; padding: 12px 0; background: var(--el-bg-color); }
@media (max-width: 640px) { .workbench-actions { align-items: stretch; flex-direction: column-reverse; } .workbench-actions :deep(.el-button) { margin-left: 0; width: 100%; } }
</style>
