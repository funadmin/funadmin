<template>
  <PageWrapper :title="t('systemLanguage.title', '多语言')" :subtitle="t('systemLanguage.subtitle', '维护后台可切换的语言注册项；语言包文件仍由代码仓库管理')">
    <DataTableShell storage-key="system-language" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item :label="t('systemLanguage.name', '语言名称')" prop="name">
            <el-input v-model="query.name" :placeholder="t('systemLanguage.namePlaceholder', '请输入语言名称')" clearable />
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:language:add'" @click="openAdd">
          <i class="i-ep-plus" /> {{ t('common.add', '新增') }}
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!deletableSelection.length"
          v-perm="'system:language:delete'"
          @click="removeSelected"
        >
          <i class="i-ep-delete" /> {{ t('common.batchRemove', '批量删除') }}{{ deletableSelection.length ? `(${deletableSelection.length})` : '' }}
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
          <el-table-column prop="name" :label="t('systemLanguage.name', '语言名称')" min-width="180" />
          <el-table-column :label="t('systemLanguage.isDefault', '默认语言')" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="isDefault(row as LanguageModel) ? 'success' : 'info'" size="small">
                {{ isDefault(row as LanguageModel) ? t('systemLanguage.yes', '是') : t('systemLanguage.no', '否') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('common.status', '状态')" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                {{ row.status === 1 ? t('common.enable', '启用') : t('systemLanguage.stopped', '停用') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="createdAt" :label="t('systemLanguage.createdAt', '创建时间')" width="170" />
          <el-table-column :label="t('common.operation', '操作')" width="220" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link @click="openLines(row as LanguageModel)">{{ t('systemLanguage.lines', '译文') }}</el-button>
              <template v-if="!isDefault(row as LanguageModel)">
                <el-button type="primary" link v-perm="'system:language:edit'" @click="openEdit(row as LanguageModel)">{{ t('common.edit', '编辑') }}</el-button>
                <el-button type="danger" link v-perm="'system:language:delete'" @click="removeOne(row as LanguageModel)">{{ t('common.remove', '删除') }}</el-button>
              </template>
              <span v-else class="text-xs text-[var(--el-text-color-secondary)]">{{ t('systemLanguage.systemProtected', '系统保护') }}</span>
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
    <LanguageLinesDrawer v-model="linesVisible" :locale="linesLocale" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { languageApi, type LanguageModel, type LanguageQuery } from '@/api/system/language';
import LanguageFormDialog from './components/LanguageFormDialog.vue';
import LanguageLinesDrawer from './components/LanguageLinesDrawer.vue';

defineOptions({ name: 'SystemLanguage' });

const { t } = useI18n();
const loading = ref(false);
const list = ref<LanguageModel[]>([]);
const total = ref(0);
const selection = ref<LanguageModel[]>([]);
const dialogVisible = ref(false);
const current = ref<LanguageModel | null>(null);
const linesVisible = ref(false);
const linesLocale = ref('');
const openLines = (row: LanguageModel) => {
  linesLocale.value = row.name;
  linesVisible.value = true;
};
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
  await ElMessageBox.confirm(t('systemLanguage.deleteOneConfirm', { name: row.name }, { default: '确认删除语言“{name}”吗？' }), t('systemLanguage.deleteConfirmTitle', '删除确认'), { type: 'warning' });
  await languageApi.remove(row.id);
  await loadData();
}

async function removeSelected() {
  await ElMessageBox.confirm(t('systemLanguage.deleteManyConfirm', { n: deletableSelection.value.length }, { default: '确认删除选中的 {n} 个语言吗？' }), t('systemLanguage.batchDeleteConfirmTitle', '批量删除确认'), {
    type: 'warning'
  });
  await languageApi.removeMany(deletableSelection.value.map((item) => item.id));
  await loadData();
}

onMounted(loadData);
</script>
