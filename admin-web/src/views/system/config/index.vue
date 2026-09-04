<template>
  <PageWrapper title="配置管理" subtitle="维护运行时配置定义和值；所有配置值保持字符串存储">
    <DataTableShell storage-key="system-config" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="关键词" prop="keyword"><el-input v-model="query.keyword" placeholder="配置编码/备注" clearable /></el-form-item>
          <el-form-item label="分组" prop="group">
            <el-select v-model="query.group" placeholder="全部" clearable class="!w-36">
              <el-option v-for="item in options.groups" :key="item.id" :label="item.title" :value="item.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="类型" prop="type">
            <el-select v-model="query.type" placeholder="全部" clearable filterable class="!w-36">
              <el-option v-for="item in uniqueTypes" :key="item.name" :label="item.name" :value="item.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-select v-model="query.status" placeholder="全部" clearable class="!w-28">
              <el-option label="启用" :value="1" /><el-option label="停用" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:config:add'" @click="openAdd"><i class="i-ep-plus" /> 新增配置</el-button>
        <el-button v-perm="'system:config-group:list'" @click="groupDrawer = true"><i class="i-ep-folder" /> 配置分组</el-button>
        <el-button type="danger" plain :disabled="!deletableSelection.length" v-perm="'system:config:delete'" @click="removeSelected"><i class="i-ep-delete" /> 删除{{ deletableSelection.length ? `(${deletableSelection.length})` : '' }}</el-button>
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table :data="list" v-loading="loading" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" @selection-change="selection = $event">
          <el-table-column type="selection" width="48" align="center" :selectable="(row: ConfigModel) => row.isSystem !== 1" />
          <el-table-column prop="id" label="ID" width="72" align="center" />
          <el-table-column prop="code" label="配置编码" min-width="170" show-overflow-tooltip />
          <el-table-column prop="value" label="配置值" min-width="220" show-overflow-tooltip />
          <el-table-column prop="group" label="分组" width="110" />
          <el-table-column prop="type" label="类型" width="100" />
          <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
          <el-table-column label="系统" width="76" align="center"><template #default="{ row }"><el-tag :type="row.isSystem === 1 ? 'warning' : 'info'" size="small">{{ row.isSystem === 1 ? '是' : '否' }}</el-tag></template></el-table-column>
          <el-table-column label="状态" width="120" align="center">
            <template #default="{ row }"><el-switch size="small" :model-value="row.status === 1" :disabled="!hasPermission('system:config:status')" @change="(value: string | number | boolean) => toggleStatus(row as ConfigModel, value === true)" /></template>
          </el-table-column>
          <el-table-column label="操作" width="210" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="success" link v-perm="'system:config:value'" @click="openValue(row as ConfigModel)">设置值</el-button>
              <el-button type="primary" link v-perm="'system:config:edit'" @click="openEdit(row as ConfigModel)">编辑</el-button>
              <el-button v-if="row.isSystem !== 1" type="danger" link v-perm="'system:config:delete'" @click="removeOne(row as ConfigModel)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper" @change="loadData" /></div>
      </template>
    </DataTableShell>

    <ConfigFormDialog v-model="formVisible" :row="current" :options="options" @success="onConfigChanged" />
    <ConfigValueDialog v-model="valueVisible" :row="current" @success="loadData" />
    <ConfigGroupDialog v-model="groupVisible" :row="currentGroup" @success="reloadOptions" />
    <el-drawer v-model="groupDrawer" title="配置分组" size="620px">
      <div class="mb-3"><el-button type="primary" plain v-perm="'system:config-group:add'" @click="openGroupAdd"><i class="i-ep-plus" /> 新增分组</el-button></div>
      <el-table :data="options.groups" border>
        <el-table-column prop="name" label="分组编码" min-width="140" /><el-table-column prop="title" label="分组标题" min-width="140" />
        <el-table-column label="状态" width="80" align="center"><template #default="{ row }"><el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag></template></el-table-column>
        <el-table-column label="操作" width="150" align="center"><template #default="{ row }"><el-button type="primary" link v-perm="'system:config-group:edit'" @click="openGroupEdit(row as ConfigGroupModel)">编辑</el-button><el-button type="danger" link v-perm="'system:config-group:delete'" @click="removeGroup(row as ConfigGroupModel)">删除</el-button></template></el-table-column>
      </el-table>
    </el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { configApi, type ConfigGroupModel, type ConfigModel, type ConfigOptions, type ConfigQuery } from '@/api/system/config';
import { useUserStore } from '@/store/modules/user';
import ConfigFormDialog from './components/ConfigFormDialog.vue';
import ConfigValueDialog from './components/ConfigValueDialog.vue';
import ConfigGroupDialog from './components/ConfigGroupDialog.vue';

defineOptions({ name: 'SystemConfig' });
const userStore = useUserStore();
const loading = ref(false);
const list = ref<ConfigModel[]>([]);
const total = ref(0);
const selection = ref<ConfigModel[]>([]);
const query = reactive<ConfigQuery>({ page: 1, pageSize: 20, keyword: '', group: '', type: '', status: undefined });
const options = reactive<ConfigOptions>({ groups: [], types: [], verifies: [] });
const formVisible = ref(false);
const valueVisible = ref(false);
const groupDrawer = ref(false);
const groupVisible = ref(false);
const current = ref<ConfigModel | null>(null);
const currentGroup = ref<ConfigGroupModel | null>(null);
const uniqueTypes = computed(() => Array.from(new Map(options.types.map((item) => [item.name, item])).values()));
const deletableSelection = computed(() => selection.value.filter((item) => item.isSystem !== 1));
function hasPermission(permission: string) { return userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === permission); }
async function loadOptions() { Object.assign(options, await configApi.options()); }
async function loadData() { loading.value = true; try { const result = await configApi.list(query); list.value = result.list; total.value = result.total; selection.value = []; } finally { loading.value = false; } }
async function reloadOptions() { await loadOptions(); await loadData(); }
async function onConfigChanged() { await reloadOptions(); }
function onSearch() { query.page = 1; loadData(); }
function onReset() { Object.assign(query, { page: 1, pageSize: 20, keyword: '', group: '', type: '', status: undefined }); loadData(); }
function openAdd() { current.value = null; formVisible.value = true; }
function openEdit(row: ConfigModel) { current.value = row; formVisible.value = true; }
function openValue(row: ConfigModel) { current.value = row; valueVisible.value = true; }
function openGroupAdd() { currentGroup.value = null; groupVisible.value = true; }
function openGroupEdit(row: ConfigGroupModel) { currentGroup.value = row; groupVisible.value = true; }
async function toggleStatus(row: ConfigModel, enabled: boolean) { await configApi.updateStatus(row.id, enabled ? 1 : 0); await loadData(); }
async function removeOne(row: ConfigModel) { await ElMessageBox.confirm(`确认删除配置“${row.code}”吗？`, '删除确认', { type: 'warning' }); await configApi.remove([row.id]); await reloadOptions(); }
async function removeSelected() { const ids = deletableSelection.value.map((item) => item.id); if (!ids.length) return; await ElMessageBox.confirm(`确认删除选中的 ${ids.length} 个配置吗？`, '删除确认', { type: 'warning' }); await configApi.remove(ids); await reloadOptions(); }
async function removeGroup(row: ConfigGroupModel) { await ElMessageBox.confirm(`确认删除配置分组“${row.title}”吗？仅空分组可删除。`, '删除确认', { type: 'warning' }); await configApi.removeGroup(row.id); await reloadOptions(); }
onMounted(async () => { await loadOptions(); await loadData(); });
</script>
