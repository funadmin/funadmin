<template>
  <PageWrapper title="CRUD 生成器" subtitle="检查表结构、编辑定义、预览冲突并显式确认生成">
    <el-card v-loading="loading">
      <el-steps :active="workbench.step" finish-status="success" align-center class="mb-6"><el-step v-for="item in workbench.steps" :key="item.index" :title="item.title" /></el-steps>
      <el-alert v-if="workbench.error" :title="workbench.error" type="error" show-icon closable class="mb-4" @close="workbench.error = ''" />
      <DataSourceStep v-if="workbench.step === 0" v-model:connection="connection" v-model:table="table" :connections="connections" :tables="tables" />
      <ModuleStep v-else-if="workbench.step === 1 && definition" :model="definition" />
      <FieldsStep v-else-if="workbench.step === 2 && definition" :fields="definition.fields" />
      <CapabilitiesStep v-else-if="workbench.step === 3 && definition" :model="definition" />
      <PreviewStep v-else-if="workbench.step === 4" :preview="workbench.preview" />
      <ConfirmStep v-else-if="workbench.step === 5" v-model:allow-overwrite="workbench.allowOverwrite" :conflicts="workbench.conflicts()" :can-overwrite="canOverwrite" />
      <ResultStep v-else :result="workbench.result" />
      <div class="mt-6 flex justify-between"><el-button :disabled="workbench.step === 0 || loading" @click="previous">上一步</el-button><el-button v-if="workbench.step < 5" type="primary" :disabled="loading" @click="next">下一步</el-button><el-button v-else-if="workbench.step === 5" type="danger" :disabled="!workbench.canGenerate(canOverwrite) || loading" @click="generate">确认生成</el-button><el-button v-else type="primary" @click="reset">重新开始</el-button></div>
    </el-card>
  </PageWrapper>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { crudDevelopmentApi } from '@/api/development/crud';
import { useUserStore } from '@/store/modules/user';
import type { CrudConnection, CrudDefinition, CrudTable } from '@/types/development/crud';
import { createCrudWorkbench, validateWorkbenchStep } from './workbench';
import DataSourceStep from './components/DataSourceStep.vue'; import ModuleStep from './components/ModuleStep.vue'; import FieldsStep from './components/FieldsStep.vue'; import CapabilitiesStep from './components/CapabilitiesStep.vue'; import PreviewStep from './components/PreviewStep.vue'; import ConfirmStep from './components/ConfirmStep.vue'; import ResultStep from './components/ResultStep.vue';
defineOptions({ name: 'DevelopmentCrud' });
const userStore = useUserStore(); const workbench = createCrudWorkbench(); const loading = ref(false); const connections = ref<CrudConnection[]>([]); const tables = ref<CrudTable[]>([]); const connection = ref(''); const table = ref(''); const definition = ref<CrudDefinition | null>(null);
const canOverwrite = computed(() => userStore.permissions.includes('development:crud:overwrite'));
watch(connection, async (value) => { table.value = ''; tables.value = value ? await crudDevelopmentApi.tables(value) : []; });
function buildDefinition(fields: CrudDefinition['fields']): CrudDefinition { const name = table.value.replace(/^fun_/, '').replace(/_/g, '-'); return { schemaVersion: '1.0', name, table: table.value, title: table.value, paths: { backend: `app/backend/controller/generated/${name}.php`, frontend: `admin-web/src/views/generated/${name}/index.vue` }, apiPrefix: `/generated/${name}`, permissionPrefix: `generated:${name}`, fields, relations: [], templates: { backend: 'backend/controller.php.tpl', frontend: 'frontend/index.vue.tpl' }, metadata: { connection: connection.value }, capabilities: { list: true, search: true, form: true, detail: true, create: true, update: true, delete: true }, dataScope: { enabled: false, field: '' } }; }
async function next() { workbench.error = ''; try { loading.value = true; if (workbench.step === 0) { if (!connection.value || !table.value) throw new Error('请选择数据源和数据表'); const inferred = await crudDevelopmentApi.infer(connection.value, table.value); definition.value = buildDefinition(inferred.fields); workbench.definition = definition.value; } const error = validateWorkbenchStep(workbench.step, { fields: definition.value?.fields, capabilities: definition.value?.capabilities, dataScope: definition.value?.dataScope }); if (error) throw new Error(error); if (workbench.step === 3 && definition.value) { await crudDevelopmentApi.validate(definition.value); workbench.setPreview(await crudDevelopmentApi.preview(definition.value)); } workbench.step += 1; } catch (error) { workbench.fail(error instanceof Error ? error.message : '操作失败'); } finally { loading.value = false; } }
function previous() { if (workbench.step === 5) workbench.clearSensitive(); workbench.step -= 1; }
async function generate() { if (!definition.value) return; try { loading.value = true; workbench.result = await crudDevelopmentApi.generate(definition.value, workbench.confirmToken, workbench.allowOverwrite); workbench.clearSensitive(); workbench.step = 6; } catch (error) { workbench.fail(error instanceof Error ? error.message : '生成失败'); } finally { loading.value = false; } }
function reset() { workbench.clearSensitive(); workbench.step = 0; workbench.preview = null; workbench.result = null; definition.value = null; table.value = ''; }
onMounted(async () => { connections.value = await crudDevelopmentApi.connections(); connection.value = connections.value[0]?.name || ''; }); onBeforeUnmount(workbench.clearSensitive);
</script>
