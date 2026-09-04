<template>
  <PageWrapper title="多语言" subtitle="维护后台可切换的语言注册项；语言包文件仍由代码仓库管理">
    <DataTableShell storage-key="system-language" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="语言名称" prop="name">
            <el-input v-model="query.name" placeholder="请输入语言名称" clearable />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:language:add'" @click="openAdd">
          <i class="i-ep-plus" /> 新增
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!deletableSelection.length"
          v-perm="'system:language:delete'"
          @click="removeSelected"
        >
          <i class="i-ep-delete" /> 批量删除{{ deletableSelection.length ? `(${deletableSelection.length})` : '' }}
        </el-button>
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table
          :data="list"
          v-loading="loading"
          :size="size"
          :stripe="stripe"
          :border="border"
          :header-cell-style="headerCellStyle"
          @selection-change="selection = $event"
        >
          <el-table-column type="selection" width="48" align="center" :selectable="(row: LanguageModel) => !isDefault(row)" />
          <el-table-column prop="id" label="ID" width="80" align="center" />
          <el-table-column prop="name" label="语言名称" min-width="180" />
          <el-table-column label="默认语言" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="isDefault(row as LanguageModel) ? 'success' : 'info'" size="small">
                {{ isDefault(row as LanguageModel) ? '是' : '否' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                {{ row.status === 1 ? '启用' : '停用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="createdAt" label="创建时间" width="170" />
          <el-table-column label="操作" width="170" align="center" fixed="right">
            <template #default="{ row }">
              <template v-if="!isDefault(row as LanguageModel)">
                <el-button type="primary" link v-perm="'system:language:edit'" @click="openEdit(row as LanguageModel)">编辑</el-button>
                <el-button type="danger" link v-perm="'system:language:delete'" @click="removeOne(row as LanguageModel)">删除</el-button>
              </template>
              <span v-else class="text-xs text-[var(--el-text-color-secondary)]">系统保护</span>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-4 flex justify-end">
          <el-pagination
            v-model:current-page="query.page"
            v-model:page-size="query.pageSize"
            :total="total"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            @change="loadData"
          />
        </div>
      </template>
    </DataTableShell>
    <LanguageFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { languageApi, type LanguageModel, type LanguageQuery } from '@/api/system/language';
import LanguageFormDialog from './components/LanguageFormDialog.vue';

defineOptions({ name: 'SystemLanguage' });

const loading = ref(false);
const list = ref<LanguageModel[]>([]);
const total = ref(0);
const selection = ref<LanguageModel[]>([]);
const dialogVisible = ref(false);
const current = ref<LanguageModel | null>(null);
const query = reactive<LanguageQuery>({ page: 1, pageSize: 20, name: '' });
const deletableSelection = computed(() => selection.value.filter((item) => !isDefault(item)));

function isDefault(row: LanguageModel) {
  return row.isDefault === 1 || row.name.toLowerCase() === 'zh-cn';
}

async function loadData() {
  loading.value = true;
  try {
    const result = await languageApi.list(query);
    list.value = result.list;
    total.value = result.total;
    selection.value = [];
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  query.page = 1;
  loadData();
}

function onReset() {
  Object.assign(query, { page: 1, pageSize: 20, name: '' });
  loadData();
}

function openAdd() {
  current.value = null;
  dialogVisible.value = true;
}

function openEdit(row: LanguageModel) {
  current.value = row;
  dialogVisible.value = true;
}

async function removeOne(row: LanguageModel) {
  await ElMessageBox.confirm(`确认删除语言“${row.name}”吗？`, '删除确认', { type: 'warning' });
  await languageApi.remove(row.id);
  await loadData();
}

async function removeSelected() {
  await ElMessageBox.confirm(`确认删除选中的 ${deletableSelection.value.length} 个语言吗？`, '批量删除确认', {
    type: 'warning'
  });
  await languageApi.removeMany(deletableSelection.value.map((item) => item.id));
  await loadData();
}

onMounted(loadData);
</script>
