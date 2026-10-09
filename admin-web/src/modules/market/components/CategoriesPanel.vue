<template>
  <div class="panel">
    <div class="panel-toolbar">
      <el-button v-if="canManage" type="primary" @click="openEdit()"><i class="i-ep-plus" />新建分类</el-button>
      <span class="panel-hint">分类会通过 /api/v3/plugins/categories 下发给客户端插件中心，停用的分类不会下发。</span>
    </div>
    <el-table v-loading="loading" :data="rows" row-key="id" empty-text="暂无分类">
      <el-table-column label="名称" prop="name" min-width="160" />
      <el-table-column label="插件数" prop="pluginCount" width="100" />
      <el-table-column label="排序" prop="sort" width="100" />
      <el-table-column label="状态" width="100">
        <template #default="{ row }"><el-tag :type="row.status === 1 ? 'success' : 'info'" effect="light" round>{{ row.status === 1 ? '启用' : '停用' }}</el-tag></template>
      </el-table-column>
      <el-table-column v-if="canManage" label="操作" width="140">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row as MarketCategory)">编辑</el-button>
          <el-button link type="danger" :disabled="row.pluginCount > 0" @click="remove(row as MarketCategory)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="editOpen" :title="editingId ? '编辑分类' : '新建分类'" width="min(420px, 94vw)">
      <el-form :model="form" label-position="top">
        <el-form-item label="名称" required><el-input v-model="form.name" maxlength="50" /></el-form-item>
        <el-form-item label="排序（越大越靠前）"><el-input-number v-model="form.sort" :min="-9999" :max="9999" controls-position="right" /></el-form-item>
        <el-form-item label="启用"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editOpen = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { marketApi, type CategoryPayload, type MarketCategory } from '../api';

defineProps<{ canManage: boolean }>();
const loading = ref(false);
const rows = ref<MarketCategory[]>([]);
async function load() {
  loading.value = true;
  try { rows.value = await marketApi.categories(); } finally { loading.value = false; }
}

const editOpen = ref(false);
const saving = ref(false);
const editingId = ref(0);
const form = reactive<CategoryPayload>({ name: '', sort: 0, status: 1 });
function openEdit(row?: MarketCategory) {
  editingId.value = row?.id ?? 0;
  Object.assign(form, { name: row?.name ?? '', sort: row?.sort ?? 0, status: row?.status ?? 1 });
  editOpen.value = true;
}
async function save() {
  saving.value = true;
  try {
    if (editingId.value) await marketApi.updateCategory(editingId.value, { ...form });
    else await marketApi.createCategory({ ...form });
    editOpen.value = false;
    await load();
  } finally { saving.value = false; }
}
async function remove(row: MarketCategory) {
  try { await ElMessageBox.confirm(`删除分类「${row.name}」？`, '删除分类', { type: 'warning' }); } catch { return; }
  await marketApi.deleteCategory(row.id);
  await load();
}
onMounted(load);
</script>

<style scoped>
.panel { display: grid; gap: 12px; }
.panel-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.panel-toolbar :deep(.el-button i) { margin-right: 4px; }
.panel-hint { color: var(--el-text-color-secondary); font-size: 12px; }
</style>
