<template>
  <PageWrapper title="系统升级" subtitle="校验升级包、备份项目文件并执行可恢复升级">
    <el-alert class="mb-4" type="warning" :closable="false" title="升级会依次执行校验、解压、备份、部署、migration；失败时自动恢复已备份文件。" />
    <div class="mb-4 flex flex-wrap gap-2">
      <el-button type="primary" v-perm="'system:upgrade:check'" :loading="checking" @click="check">检查更新</el-button>
      <el-button v-if="manifest" type="success" v-perm="'system:upgrade:execute'" :loading="executing" @click="execute">升级到 {{ manifest.version }}</el-button>
      <el-button v-perm="'system:upgrade:upload'" @click="uploadInput?.click()">离线 ZIP 升级</el-button>
      <input ref="uploadInput" class="hidden" type="file" accept=".zip" @change="upload" />
      <el-button @click="load">刷新任务</el-button>
    </div>
    <el-descriptions v-if="manifest" class="mb-4" border :column="1">
      <el-descriptions-item label="目标版本">{{ manifest.version }}</el-descriptions-item>
      <el-descriptions-item label="Manifest ID"><code>{{ manifest.manifestId }}</code></el-descriptions-item>
      <el-descriptions-item label="变更内容">{{ manifest.changelog || '-' }}</el-descriptions-item>
    </el-descriptions>
    <el-alert v-if="error" class="mb-4" type="error" :closable="false" :title="error" />
    <el-table v-loading="loading" :data="tasks" border>
      <el-table-column prop="id" label="任务" width="80" />
      <el-table-column label="版本" min-width="160"><template #default="{ row }">{{ row.from_version }} → {{ row.to_version }}</template></el-table-column>
      <el-table-column prop="stage" label="阶段" width="110" />
      <el-table-column label="进度" min-width="180"><template #default="{ row }"><el-progress :percentage="row.progress" /></template></el-table-column>
      <el-table-column prop="status" label="状态" width="100" />
      <el-table-column prop="error_message" label="错误" min-width="220" show-overflow-tooltip />
      <el-table-column label="操作" width="100"><template #default="{ row }"><el-button v-if="upgradeTask(row).backup_path" type="danger" link v-perm="'system:upgrade:restore'" @click="restore(upgradeTask(row))">恢复</el-button></template></el-table-column>
    </el-table>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { upgradeApi, type UpgradeManifest, type UpgradeTask } from '@/api/system/upgrade';

defineOptions({ name: 'SystemUpgrade' });
const loading = ref(false);
const checking = ref(false);
const executing = ref(false);
const tasks = ref<UpgradeTask[]>([]);
const manifest = ref<UpgradeManifest>();
const error = ref('');
const uploadInput = ref<HTMLInputElement>();
const operationToken = ref('');
const token = () => { operationToken.value = `${Date.now()}-${crypto.randomUUID()}`; return operationToken.value; };
const message = (value: unknown) => value instanceof Error ? value.message : String(value);
const upgradeTask = (row: unknown): UpgradeTask => row as UpgradeTask;

async function load() { loading.value = true; error.value = ''; try { tasks.value = (await upgradeApi.status()).tasks; } catch (value) { error.value = message(value); } finally { loading.value = false; } }
async function check() { checking.value = true; error.value = ''; try { manifest.value = await upgradeApi.check(); } catch (value) { error.value = message(value); } finally { checking.value = false; } }
async function execute() { if (!manifest.value) return; try { await ElMessageBox.confirm(`确认升级到 ${manifest.value.version}？系统将先备份。`, '升级确认', { type: 'warning' }); } catch { return; } executing.value = true; try { await upgradeApi.execute({ manifestId: manifest.value.manifestId, operationToken: token() }); manifest.value = undefined; await load(); } catch (value) { error.value = message(value); } finally { executing.value = false; } }
async function upload(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; if (!file) return; try { await ElMessageBox.confirm(`确认上传 ${file.name} 并执行升级？ZIP 内签名 manifest 将由服务器校验。`, '升级确认', { type: 'warning' }); await upgradeApi.upload(file, token()); await load(); } catch (value) { if (value !== 'cancel' && value !== 'close') error.value = message(value); } finally { input.value = ''; } }
async function restore(task: UpgradeTask) { try { await ElMessageBox.confirm(`确认从任务 ${task.id} 的备份恢复文件？数据库 migration 不会回滚。`, '恢复确认', { type: 'warning' }); await upgradeApi.restore(task.id, token()); await load(); } catch (value) { if (value !== 'cancel' && value !== 'close') error.value = message(value); } }
onMounted(load);
</script>
