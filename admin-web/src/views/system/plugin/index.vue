<template>
  <PageWrapper title="插件中心" subtitle="管理已安装插件、本地插件包和云市场版本">
    <div class="mb-4 flex flex-wrap gap-2">
      <el-button type="primary" plain v-perm="'system:plugin:account'" @click="accountVisible = true">市场账号</el-button>
      <el-button type="success" plain v-perm="'system:plugin:install'" @click="uploadInput?.click()">上传本地 ZIP</el-button>
      <input ref="uploadInput" class="hidden" type="file" accept=".zip" @change="uploadZip" />
      <input ref="updateInput" class="hidden" type="file" accept=".zip" @change="updateLocalZip" />
      <el-button type="info" plain @click="load">刷新</el-button>
    </div>
    <el-alert v-if="pageError" class="mb-3" type="error" :title="pageError" :closable="false" role="alert" />
    <el-tabs v-model="activeTab" @tab-change="load">
      <el-tab-pane label="已安装" name="installed" />
      <el-tab-pane label="本地包" name="local" />
      <el-tab-pane label="云市场" name="market" />
    </el-tabs>

    <el-table v-if="activeTab !== 'market'" v-loading="loading" :data="items" border>
      <el-table-column prop="name" label="code" min-width="120" />
      <el-table-column prop="title" label="名称" min-width="130" />
      <el-table-column prop="version" label="当前版本" width="110" />
      <el-table-column prop="latestVersion" label="latest version" width="120" />
      <el-table-column prop="dbVersion" label="db version" width="110" />
      <el-table-column prop="state" label="state" width="100" />
      <el-table-column label="dependency" min-width="160"><template #default="{ row }">{{ dependencies(row.dependencies) }}</template></el-table-column>
      <el-table-column label="pending" width="90"><template #default="{ row }"><el-tag :type="row.migrationPending ? 'warning' : 'success'">{{ row.migrationPending ? '是' : '否' }}</el-tag></template></el-table-column>
      <el-table-column prop="source" label="source" width="90" />
      <el-table-column prop="lastError" label="error" min-width="180" show-overflow-tooltip />
      <el-table-column label="操作" min-width="430" fixed="right">
        <template #default="{ row }">
          <el-button v-if="activeTab === 'local'" type="primary" link v-perm="'system:plugin:discovered-install'" :disabled="Boolean(actionReason(row as PluginItem, 'install'))" :title="actionReason(row as PluginItem, 'install')" @click="installDiscovered(row as PluginItem)">安装</el-button>
          <el-button v-if="activeTab === 'installed'" type="primary" link v-perm="'system:plugin:local-update'" :disabled="Boolean(actionReason(row as PluginItem, 'update'))" :title="actionReason(row as PluginItem, 'update')" @click="selectLocalUpdate(row as PluginItem)">本地 ZIP 更新</el-button>
          <el-button v-if="row.latestVersion" type="primary" link v-perm="'system:plugin:update'" :disabled="Boolean(actionReason(row as PluginItem, 'update'))" :title="actionReason(row as PluginItem, 'update')" @click="updatePlugin(row as PluginItem)">更新</el-button>
          <el-button v-if="row.migrationPending" type="warning" link v-perm="'system:plugin:migrate'" :disabled="Boolean(actionReason(row as PluginItem, 'migrate'))" :title="actionReason(row as PluginItem, 'migrate')" @click="operate(row as PluginItem, 'migrate')">迁移</el-button>
          <el-button v-if="row.state === 'disabled'" type="success" link v-perm="'system:plugin:enable'" :disabled="Boolean(actionReason(row as PluginItem, 'enable'))" :title="actionReason(row as PluginItem, 'enable')" @click="operate(row as PluginItem, 'enable')">启用</el-button>
          <el-button v-if="row.state === 'enabled'" type="warning" link v-perm="'system:plugin:disable'" :disabled="Boolean(actionReason(row as PluginItem, 'disable'))" :title="actionReason(row as PluginItem, 'disable')" @click="operate(row as PluginItem, 'disable')">禁用</el-button>
          <el-button type="primary" link v-perm="'system:plugin:config'" @click="openConfig(row as PluginItem)">配置</el-button>
          <el-button type="info" link v-perm="'system:plugin:history'" @click="openHistory(row as PluginItem)">历史</el-button>
          <el-button v-if="activeTab === 'installed'" type="danger" link v-perm="'system:plugin:uninstall'" :disabled="Boolean(actionReason(row as PluginItem, 'uninstall'))" :title="actionReason(row as PluginItem, 'uninstall')" @click="uninstall(row as PluginItem)">卸载</el-button>
          <el-button v-if="activeTab === 'installed'" type="danger" link v-perm="'system:plugin:purge'" :disabled="Boolean(actionReason(row as PluginItem, 'purge'))" :title="actionReason(row as PluginItem, 'purge')" @click="purge(row as PluginItem)">清除数据</el-button>
          <el-button v-else type="danger" link v-perm="'system:plugin:package-delete'" @click="deletePackage(row as PluginItem)">删除包</el-button>
        </template>
      </el-table-column>
    </el-table>

    <template v-else>
      <div class="mb-3 flex gap-2"><el-input v-model="marketQuery.keyword" placeholder="搜索插件" clearable class="max-w-72" @keyup.enter="loadMarket" /><el-button type="primary" @click="loadMarket">搜索</el-button></div>
      <el-table v-loading="loading" :data="marketItems" border>
        <el-table-column prop="name" label="code" width="130" /><el-table-column prop="title" label="名称" width="150" />
        <el-table-column prop="description" label="描述" min-width="240" show-overflow-tooltip /><el-table-column prop="author" label="作者" width="120" />
        <el-table-column label="latest version" width="120"><template #default="{ row }">{{ row.versions[0]?.version || '-' }}</template></el-table-column>
        <el-table-column label="操作" width="160"><template #default="{ row }"><el-button type="primary" link @click="openMarket(row as MarketplacePlugin)">详情</el-button><el-button type="success" link v-perm="'system:plugin:install'" @click="installMarket(row as MarketplacePlugin)">安装</el-button></template></el-table-column>
      </el-table>
    </template>

    <PluginAccountDrawer v-model="accountVisible" @changed="loadMarket" />
    <PluginMarketDrawer v-model="marketVisible" :name="selectedName" @install="installSelectedVersion" />
    <PluginConfigDrawer v-model="configVisible" :name="selectedName" @saved="load" />
    <PluginHistoryDrawer v-model="historyVisible" :name="selectedName" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { pluginApi, type MarketplacePlugin, type PluginItem } from '@/api/plugin';
import router from '@/router';
import { loadPluginModulesSafely } from '@/router/pluginStartup';
import { buildPurgeConfirmation, confirmAction } from './pluginActions';
import PluginAccountDrawer from './components/PluginAccountDrawer.vue';
import PluginMarketDrawer from './components/PluginMarketDrawer.vue';
import PluginConfigDrawer from './components/PluginConfigDrawer.vue';
import PluginHistoryDrawer from './components/PluginHistoryDrawer.vue';

defineOptions({ name: 'SystemPlugin' });
const activeTab = ref<'installed' | 'local' | 'market'>('installed');
const loading = ref(false);
const items = ref<PluginItem[]>([]);
const marketItems = ref<MarketplacePlugin[]>([]);
const marketQuery = reactive({ page: 1, pageSize: 20, keyword: '' });
const uploadInput = ref<HTMLInputElement>();
const updateInput = ref<HTMLInputElement>();
const selectedName = ref('');
const pageError = ref('');
const accountVisible = ref(false);
const marketVisible = ref(false);
const configVisible = ref(false);
const historyVisible = ref(false);

function dependencies(value: Record<string, string>) { return Object.entries(value || {}).map(([name, version]) => `${name} ${version}`).join(', ') || '-'; }
function errorMessage(error: unknown) { return error instanceof Error ? error.message : String(error); }
function actionReason(row: PluginItem, action: 'install' | 'update' | 'migrate' | 'enable' | 'disable' | 'uninstall' | 'purge') {
  if (row.operation) return row.disabledReason || `插件正在执行 ${row.operation}（${row.progress}%）`;
  if (row.needsReinstall && action !== 'purge') return '插件需要重新安装后才能执行此操作';
  if (action === 'enable' && row.migrationPending) return '存在待执行数据库迁移，完成迁移后才能启用';
  if (action === 'enable' && Object.keys(row.dependencies || {}).length > 0 && row.disabledReason) return row.disabledReason;
  if (['update', 'migrate', 'enable', 'uninstall'].includes(action) && row.state !== 'disabled') return `当前状态 ${row.state} 不允许执行此操作`;
  if (action === 'disable' && row.state !== 'enabled') return `当前状态 ${row.state} 不允许禁用`;
  if (action === 'install' && row.state !== 'discovered') return `当前状态 ${row.state} 不允许安装`;
  return '';
}
async function load() { pageError.value = ''; if (activeTab.value === 'market') return loadMarket(); loading.value = true; try { const loaded = activeTab.value === 'installed' ? await pluginApi.installed() : await pluginApi.discovered(); if (activeTab.value !== 'installed') { items.value = loaded; return; } const updates = await pluginApi.checkUpdates(loaded.map((item) => ({ name: item.name, version: item.version }))); const updatesByName = new Map(updates.map((item) => [item.name, item])); items.value = loaded.map((item) => { const update = updatesByName.get(item.name); return { ...item, latestVersion: update?.updateAvailable ? update.latestVersion : '' }; }); } catch (error) { pageError.value = errorMessage(error); } finally { loading.value = false; } }
async function loadMarket() { pageError.value = ''; loading.value = true; try { marketItems.value = (await pluginApi.marketSearch(marketQuery)).list; } catch (error) { pageError.value = errorMessage(error); } finally { loading.value = false; } }
async function uploadZip(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; if (!file) return; try { await confirmAction(() => ElMessageBox.confirm(`确认安装本地插件包 ${file.name} 吗？`, '安装确认'), async () => { await pluginApi.installLocal(file); activeTab.value = 'installed'; await load(); }); } finally { input.value = ''; } }
function selectLocalUpdate(row: PluginItem) { selectedName.value = row.name; updateInput.value?.click(); }
async function updateLocalZip(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; if (!file || !selectedName.value) return; try { await confirmAction(() => ElMessageBox.confirm(`确认使用 ${file.name} 更新插件 ${selectedName.value} 吗？`, '本地更新确认'), async () => { await pluginApi.updateLocal(selectedName.value, file, true); await syncPluginRoutes(); await load(); }); } finally { input.value = ''; } }
async function syncPluginRoutes() { await loadPluginModulesSafely(router); }
async function operate(row: PluginItem, action: 'migrate' | 'enable' | 'disable') { const messages = { migrate: '确认迁移插件', enable: '确认启用插件', disable: '确认禁用插件' } as const; await confirmAction(() => ElMessageBox.confirm(`${messages[action]} ${row.name} 吗？`, '操作确认'), async () => { await pluginApi[action](row.name); await syncPluginRoutes(); await load(); }); }
async function updatePlugin(row: PluginItem) { await confirmAction(() => ElMessageBox.confirm(`确认将 ${row.name} 更新到 ${row.latestVersion} 吗？`, '更新确认'), async () => { await pluginApi.update(row.name, row.latestVersion, true); await syncPluginRoutes(); await load(); }); }
async function installDiscovered(row: PluginItem) { await confirmAction(() => ElMessageBox.confirm(`确认安装发现目录中的插件 ${row.name} 吗？`, '安装确认'), async () => { await pluginApi.installDiscovered(row.name); activeTab.value = 'installed'; await syncPluginRoutes(); await load(); }); }
async function uninstall(row: PluginItem) { await confirmAction(() => ElMessageBox.confirm(`确认卸载插件 ${row.name} 吗？业务数据与配置将保留。`, '卸载确认'), async () => { await pluginApi.uninstall(row.name); await syncPluginRoutes(); await load(); }); }
async function purge(row: PluginItem) { pageError.value = ''; let confirmation = ''; try { const completed = await confirmAction(() => ElMessageBox.prompt(`此操作仅清除插件业务数据，不卸载插件。请输入插件名称 ${row.name} 二次确认`, '危险操作').then(({ value }) => { confirmation = value; }), async () => { const payload = buildPurgeConfirmation(row.name, confirmation); await pluginApi.purge(row.name, payload.purgeConfirm); await load(); }); if (!completed) return; } catch (error) { pageError.value = errorMessage(error); } }
async function deletePackage(row: PluginItem) { await confirmAction(() => ElMessageBox.confirm(`确认删除本地插件包 ${row.name} 吗？`, '删除确认'), async () => { await pluginApi.deletePackage(row.name); await load(); }); }
function openConfig(row: PluginItem) { selectedName.value = row.name; configVisible.value = true; }
function openHistory(row: PluginItem) { selectedName.value = row.name; historyVisible.value = true; }
function openMarket(row: MarketplacePlugin) { selectedName.value = row.name; marketVisible.value = true; }
async function installMarket(row: MarketplacePlugin) { const version = row.versions[0]?.version; if (!version) return; await confirmAction(() => ElMessageBox.confirm(`确认安装插件 ${row.name} ${version} 吗？`, '安装确认'), async () => { await pluginApi.installCloud(row.name, version); activeTab.value = 'installed'; await load(); }); }
async function installSelectedVersion(version: string) { await confirmAction(() => ElMessageBox.confirm(`确认安装插件 ${selectedName.value} ${version} 吗？`, '安装确认'), async () => { await pluginApi.installCloud(selectedName.value, version); marketVisible.value = false; activeTab.value = 'installed'; await load(); }); }
onMounted(load);
</script>
