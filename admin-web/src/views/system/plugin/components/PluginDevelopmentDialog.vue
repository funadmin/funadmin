<template>
  <el-dialog v-model="visible" title="插件开发工具" width="760px" destroy-on-close @open="loadOptions">
    <el-tabs v-model="mode">
      <el-tab-pane label="创建插件" name="create">
        <el-form :model="createForm" label-width="110px">
          <el-form-item label="插件标识"><el-input v-model="createForm.name" placeholder="小写字母开头，仅字母和数字" /></el-form-item>
          <el-form-item label="插件名称"><el-input v-model="createForm.title" /></el-form-item>
          <el-form-item label="独立应用"><el-switch v-model="createForm.application" /></el-form-item>
          <el-form-item label="管理后台"><el-switch v-model="createForm.console" /></el-form-item>
          <el-form-item label="管理前端"><el-switch v-model="createForm.adminWeb" /></el-form-item>
        </el-form>
        <el-alert v-if="preview?.conflicts.length" type="error" :title="`冲突：${preview.conflicts.join(', ')}`" :closable="false" />
        <el-table v-if="preview?.plan" :data="planFiles(preview.plan.files)" size="small" max-height="220"><el-table-column prop="path" label="目标文件" /><el-table-column label="状态" width="90"><template #default="{ row }">{{ fileStatusLabel(row.status) }}</template></el-table-column></el-table>
        <div class="actions"><el-button v-perm="'development:plugin:create-preview'" :loading="loading" @click="previewPlugin">预览</el-button><el-button v-perm="'development:plugin:create'" type="primary" :loading="loading" :disabled="!preview || preview.conflicts.length > 0" @click="createPlugin">确认创建</el-button></div>
      </el-tab-pane>
      <el-tab-pane label="校验/打包" name="maintain">
        <el-form label-width="110px"><el-form-item label="插件"><el-select v-model="selectedCode" class="w-full"><el-option v-for="item in plugins" :key="item.code" :label="`${item.name} (${item.code})`" :value="item.code" /></el-select></el-form-item></el-form>
        <el-descriptions v-if="result" :column="1" border><el-descriptions-item label="审计编号">{{ result.auditId }}</el-descriptions-item><el-descriptions-item v-if="result.valid" label="校验">清单协议 v2 有效</el-descriptions-item><el-descriptions-item v-if="result.downloadPath" label="下载路径">{{ result.downloadPath }}</el-descriptions-item><el-descriptions-item v-if="result.sha256" label="SHA-256 摘要">{{ result.sha256 }}</el-descriptions-item></el-descriptions>
        <div class="actions"><el-button v-perm="'development:plugin:validate'" :loading="loading" @click="validatePlugin">校验插件</el-button><el-button v-perm="'development:plugin:package'" type="primary" :loading="loading" @click="packagePlugin">打包插件</el-button><el-button v-if="result?.downloadUrl" v-perm="'development:plugin:download'" tag="a" :href="result.downloadUrl">下载插件包</el-button></div>
      </el-tab-pane>
      <el-tab-pane label="生成 CRUD" name="crud">
        <el-form :model="crudForm" label-width="110px">
          <el-form-item label="插件"><el-select v-model="crudForm.plugin" class="w-full" @change="syncScope"><el-option v-for="item in plugins" :key="item.code" :label="`${item.name} (${item.code})`" :value="item.code" /></el-select></el-form-item>
          <el-form-item label="数据源"><el-select v-model="crudForm.connection" class="w-full" @change="loadTables"><el-option v-for="item in connections" :key="item.name" :label="item.name" :value="item.name" /></el-select></el-form-item>
          <el-form-item label="数据表"><el-select v-model="crudForm.table" filterable class="w-full"><el-option v-for="item in tables" :key="item.name" :label="item.comment ? `${item.name} — ${item.comment}` : item.name" :value="item.name" /></el-select></el-form-item>
          <el-form-item label="实体标识"><el-input v-model="crudForm.entity" /></el-form-item>
          <el-form-item label="生成范围"><el-select v-model="crudForm.scope" class="w-full"><el-option v-for="scope in selectedPlugin?.scopes || []" :key="scope" :label="scopeLabel(scope)" :value="scope" /></el-select></el-form-item>
        </el-form>
        <el-alert v-if="crudPreview?.plan.files.some((file) => file.status === 'conflict')" type="warning" title="存在目标文件冲突，请在 CRUD Workbench 中处理覆盖授权" :closable="false" />
        <el-table v-if="crudPreview" :data="crudPreview.plan.files" size="small" max-height="220"><el-table-column prop="path" label="目标文件" /><el-table-column label="状态" width="90"><template #default="{ row }">{{ fileStatusLabel(row.status) }}</template></el-table-column></el-table>
        <div class="actions"><el-button v-perm="'development:crud:preview'" :loading="loading" @click="previewCrud">预览 CRUD</el-button><el-button v-perm="'development:crud:generate'" type="primary" :loading="loading" :disabled="!confirmToken || Boolean(crudPreview?.plan.files.some((file) => file.status !== 'create' && file.status !== 'unchanged'))" @click="generateCrud">确认生成</el-button></div>
      </el-tab-pane>
    </el-tabs>
    <el-alert v-if="error" type="error" :title="error" :closable="false" class="mt-3" />
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { crudDevelopmentApi } from '@/api/development/crud';
import { pluginDevelopmentApi, type DevelopmentPluginOption, type PluginCreateInput, type PluginDevelopmentPlan, type PluginDevelopmentResult } from '@/api/development/plugin';
import type { CrudConnection, CrudDefinition, CrudPreview, CrudTable } from '@/types/development/crud';
import { scopeLabel } from '../pluginDisplay';

const visible = defineModel<boolean>({ default: false });
const props = withDefaults(defineProps<{ initialMode?: 'create' | 'maintain' | 'crud' }>(), { initialMode: 'create' });
const emit = defineEmits<{ changed: [] }>();
const mode = ref<'create' | 'maintain' | 'crud'>('create');
const loading = ref(false);
const error = ref('');
const plugins = ref<DevelopmentPluginOption[]>([]);
const connections = ref<CrudConnection[]>([]);
const tables = ref<CrudTable[]>([]);
const selectedCode = ref('');
const preview = ref<PluginDevelopmentResult | null>(null);
const result = ref<PluginDevelopmentResult | null>(null);
const crudPreview = ref<CrudPreview | null>(null);
const crudDefinition = ref<CrudDefinition | null>(null);
const confirmToken = ref('');
const createForm = reactive<PluginCreateInput>({ name: '', title: '', application: true, console: true, adminWeb: true });
const crudForm = reactive({ plugin: '', connection: '', table: '', entity: '', scope: 'console' as 'application' | 'console' | 'both' });
const selectedPlugin = computed(() => plugins.value.find((item) => item.code === crudForm.plugin));
const message = (value: unknown) => value instanceof Error ? value.message : String(value);
const planFiles = (files: PluginDevelopmentPlan['files']) => files.map((file) => typeof file === 'string' ? { path: file } : file);
const fileStatusLabel = (status?: string) => ({ create: '新建', unchanged: '未变化', overwrite: '覆盖', conflict: '冲突' }[status || ''] || '待处理');

async function run(operation: () => Promise<void>) { error.value = ''; loading.value = true; try { await operation(); } catch (reason) { error.value = message(reason); } finally { loading.value = false; } }
async function loadOptions() { mode.value = props.initialMode; await run(async () => { [plugins.value, connections.value] = await Promise.all([pluginDevelopmentApi.options(), crudDevelopmentApi.connections()]); selectedCode.value ||= plugins.value[0]?.code || ''; crudForm.plugin ||= selectedCode.value; crudForm.connection ||= connections.value[0]?.name || ''; syncScope(); await loadTables(); }); }
function syncScope() { const scopes = selectedPlugin.value?.scopes || []; if (!scopes.includes(crudForm.scope)) crudForm.scope = scopes[0] || 'console'; }
async function loadTables() { if (!crudForm.connection) return; tables.value = await crudDevelopmentApi.tables(crudForm.connection); }
async function previewPlugin() { await run(async () => { preview.value = await pluginDevelopmentApi.previewCreate({ ...createForm }); }); }
async function createPlugin() { await run(async () => { result.value = await pluginDevelopmentApi.create({ ...createForm }); ElMessage.success(`插件已创建，审计编号：${result.value.auditId}`); preview.value = null; await loadOptions(); emit('changed'); }); }
async function validatePlugin() { if (!selectedCode.value) return; await run(async () => { result.value = await pluginDevelopmentApi.validate(selectedCode.value); ElMessage.success('插件校验通过'); }); }
async function packagePlugin() { if (!selectedCode.value) return; await run(async () => { result.value = await pluginDevelopmentApi.package(selectedCode.value); ElMessage.success('插件打包完成'); }); }
async function previewCrud() { await run(async () => { const inferred = await crudDevelopmentApi.inferPlugin(crudForm.connection, crudForm.table, crudForm.plugin, crudForm.entity, crudForm.scope); crudDefinition.value = inferred.definition; await crudDevelopmentApi.validate(inferred.definition); crudPreview.value = await crudDevelopmentApi.preview(inferred.definition); confirmToken.value = crudPreview.value.sensitive?.confirmToken || ''; }); }
async function generateCrud() { if (!crudDefinition.value || !confirmToken.value) return; await run(async () => { const generated = await crudDevelopmentApi.generate(crudDefinition.value!, confirmToken.value, []); confirmToken.value = ''; ElMessage.success(`CRUD 已生成，审计编号：${generated.generationId}`); emit('changed'); }); }
</script>

<style scoped>
.actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
</style>
