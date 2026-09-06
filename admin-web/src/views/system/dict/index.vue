<template>
  <PageWrapper title="字典管理" subtitle="维护通用字典分类与字典项">
    <div class="dict-layout">
      <!-- 左：字典分类 -->
      <div class="dict-layout__aside">
        <DataTableShell
          storage-key="system-dict-type"
          :loading="typeLoading"
          @refresh="reloadTypes"
        >
          <template #search>
            <SearchForm
              class="dict-type-search"
              :model="typeQuery"
              :loading="typeLoading"
              label-width="0"
              @search="onSearchType"
              @reset="onResetType"
            >
              <el-form-item prop="name" class="dict-type-search__field">
                <el-input v-model="typeQuery.name" placeholder="字典名称" clearable />
              </el-form-item>
              <el-form-item prop="code" class="dict-type-search__field">
                <el-input v-model="typeQuery.code" placeholder="编码" clearable />
              </el-form-item>
            </SearchForm>
          </template>

          <template #toolbar-left>
            <el-button type="primary" plain v-perm="'system:dict:add'" @click="onAddType">
              <i class="i-ep-plus" /> 新增分类
            </el-button>
            <el-button
              type="danger"
              plain
              :disabled="!typeSelection.length"
              v-perm="'system:dict:delete'"
              @click="onBatchDeleteType"
            >
              <i class="i-ep-delete" /> 批量删除{{ typeSelection.length ? `(${typeSelection.length})` : '' }}
            </el-button>
          </template>

          <template #default="{ size, stripe, border, headerCellStyle }">
            <el-table
              :data="typeList"
              v-loading="typeLoading"
              :size="size"
              :stripe="stripe"
              :border="border"
              :header-cell-style="headerCellStyle"
              highlight-current-row
              row-key="id"
              :current-row-key="currentType?.id"
              @selection-change="onTypeSelectionChange"
              @row-click="onSelectType"
            >
              <el-table-column type="selection" width="48" align="center" />
              <el-table-column prop="name" label="字典名称" min-width="120" show-overflow-tooltip />
              <el-table-column prop="code" label="编码" min-width="160" show-overflow-tooltip />
              <el-table-column label="状态" width="80" align="center">
                <template #default="{ row }">
                  <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'">
                    {{ row.status === 1 ? '启用' : '禁用' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="120" align="center" fixed="right">
                <template #default="{ row }">
                  <div class="app-table-actions app-table-actions--link">
                    <el-button size="small" type="primary" link v-perm="'system:dict:edit'" @click.stop="onEditType(row as DictType)">
                      <i class="i-ep-edit" /> 编辑
                    </el-button>
                    <el-button size="small" type="danger" link v-perm="'system:dict:delete'" @click.stop="onDeleteType(row as DictType)">
                      <i class="i-ep-delete" /> 删除
                    </el-button>
                  </div>
                </template>
              </el-table-column>
            </el-table>
          </template>
        </DataTableShell>

        <div class="dict-layout__pagination">
          <el-pagination
            v-model:current-page="typeQuery.page"
            v-model:page-size="typeQuery.pageSize"
            :total="typeTotal"
            small
            background
            layout="total, prev, pager, next"
            @current-change="reloadTypes"
            @size-change="reloadTypes"
          />
        </div>
      </div>

      <!-- 右：字典项 -->
      <div class="dict-layout__main">
        <div v-if="!currentType" class="dict-layout__empty">
          <el-empty description="请先在左侧选择一个字典分类" />
        </div>

        <template v-else>
          <DataTableShell
            :storage-key="`system-dict-item:${currentType.code}`"
            :loading="itemLoading"
            @refresh="reloadItems"
          >
            <template #search>
              <SearchForm :model="itemQuery" :loading="itemLoading" @search="onSearchItem" @reset="onResetItem">
                <el-form-item label="字典标签" prop="label">
                  <el-input v-model="itemQuery.label" placeholder="请输入标签" clearable />
                </el-form-item>
                <el-form-item label="状态" prop="status">
                  <el-select v-model="itemQuery.status" placeholder="请选择" clearable class="!w-32">
                    <el-option label="启用" :value="1" />
                    <el-option label="禁用" :value="0" />
                  </el-select>
                </el-form-item>
              </SearchForm>
            </template>

            <template #toolbar-left>
              <span class="dict-current">
                当前分类：<b>{{ currentType.name }}</b>
                <code class="dict-current__code">{{ currentType.code }}</code>
              </span>
              <el-button type="primary" plain v-perm="'system:dict:add'" @click="onAddItem">
                <i class="i-ep-plus" /> 新增字典项
              </el-button>
              <el-button
                type="danger"
                plain
                :disabled="!itemSelection.length"
                v-perm="'system:dict:delete'"
                @click="onBatchDeleteItem"
              >
                <i class="i-ep-delete" /> 批量删除{{ itemSelection.length ? `(${itemSelection.length})` : '' }}
              </el-button>
            </template>

            <template #default="{ size, stripe, border, headerCellStyle }">
              <el-table
                :data="itemList"
                v-loading="itemLoading"
                :size="size"
                :stripe="stripe"
                :border="border"
                :header-cell-style="headerCellStyle"
                @selection-change="onItemSelectionChange"
              >
                <el-table-column type="selection" width="48" align="center" />
                <el-table-column prop="label" label="字典标签" min-width="120">
                  <template #default="{ row }">
                    <el-tag :type="(row.cssClass || 'info') as any" size="small">{{ row.label }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="value" label="字典键值" min-width="120" />
                <el-table-column prop="cssClass" label="样式属性" width="110" align="center">
                  <template #default="{ row }">
                    <span v-if="row.cssClass">{{ row.cssClass }}</span>
                    <span v-else class="text-gray-400">-</span>
                  </template>
                </el-table-column>
                <el-table-column prop="sort" label="排序" width="80" align="center" />
                <el-table-column label="状态" width="80" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'">
                      {{ row.status === 1 ? '启用' : '禁用' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
                <el-table-column label="操作" width="180" align="center" fixed="right">
                  <template #default="{ row }">
                    <div class="app-table-actions app-table-actions--link">
                      <el-button size="small" type="primary" link v-perm="'system:dict:edit'" @click="onEditItem(row as DictItemModel)">
                        <i class="i-ep-edit" /> 编辑
                      </el-button>
                      <el-button size="small" type="danger" link v-perm="'system:dict:delete'" @click="onDeleteItem(row as DictItemModel)">
                        <i class="i-ep-delete" /> 删除
                      </el-button>
                    </div>
                  </template>
                </el-table-column>
              </el-table>
            </template>
          </DataTableShell>

          <div class="dict-layout__pagination">
            <el-pagination
              v-model:current-page="itemQuery.page"
              v-model:page-size="itemQuery.pageSize"
              :total="itemTotal"
              small
              background
              :page-sizes="[10, 20, 50]"
              layout="total, sizes, prev, pager, next, jumper"
              @current-change="reloadItems"
              @size-change="reloadItems"
            />
          </div>
        </template>
      </div>
    </div>

    <DictTypeFormDialog v-model="typeDialogVisible" :row="currentEditType" @success="onTypeSaved" />
    <DictItemFormDialog
      v-model="itemDialogVisible"
      :row="currentEditItem"
      :type-code="currentType?.code || ''"
      :type-name="currentType?.name || ''"
      @success="reloadItems"
    />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { dictTypeApi, dictItemApi, type DictType, type DictItemModel } from '@/api/system/dict';
import DictTypeFormDialog from './components/DictTypeFormDialog.vue';
import DictItemFormDialog from './components/DictItemFormDialog.vue';

defineOptions({ name: 'SystemDict' });

// ===== 字典分类 =====
const typeLoading = ref(false);
const typeList = ref<DictType[]>([]);
const typeTotal = ref(0);
const typeSelection = ref<DictType[]>([]);
const typeDialogVisible = ref(false);
const currentEditType = ref<DictType | null>(null);
const currentType = ref<DictType | null>(null);

const typeQuery = reactive({
  page: 1,
  pageSize: 10,
  name: '',
  code: '',
  status: undefined as 0 | 1 | undefined
});

async function reloadTypes() {
  typeLoading.value = true;
  try {
    const res = await dictTypeApi.list(typeQuery);
    typeList.value = res.list;
    typeTotal.value = res.total;
    // 首次加载或当前选中分类被删除时，自动选中第一条
    if (typeList.value.length && (!currentType.value || !typeList.value.find((t) => t.id === currentType.value!.id))) {
      onSelectType(typeList.value[0]);
    } else if (!typeList.value.length) {
      currentType.value = null;
    }
  } finally {
    typeLoading.value = false;
  }
}

function onSearchType() {
  typeQuery.page = 1;
  reloadTypes();
}

function onResetType() {
  Object.assign(typeQuery, { page: 1, pageSize: 10, name: '', code: '', status: undefined });
  reloadTypes();
}

function onAddType() {
  currentEditType.value = null;
  typeDialogVisible.value = true;
}

function onEditType(row: DictType) {
  currentEditType.value = row;
  typeDialogVisible.value = true;
}

async function onDeleteType(row: DictType) {
  await ElMessageBox.confirm(`确认删除分类「${row.name}」？该操作会一并删除其下所有字典项`, '提示', {
    type: 'warning'
  });
  await dictTypeApi.remove(row.id);
  if (currentType.value?.id === row.id) currentType.value = null;
  reloadTypes();
}

async function onBatchDeleteType() {
  if (!typeSelection.value.length) {
    ElMessage.warning('请至少选择一项');
    return;
  }
  await ElMessageBox.confirm(
    `确认删除选中的 ${typeSelection.value.length} 个分类？将一并删除其下字典项，此操作不可恢复`,
    '提示',
    { type: 'warning' }
  );
  const ids = typeSelection.value.map((r) => r.id);
  await dictTypeApi.removeMany(ids);
  if (currentType.value && ids.includes(currentType.value.id)) currentType.value = null;
  typeSelection.value = [];
  reloadTypes();
}

function onTypeSelectionChange(rows: DictType[]) {
  typeSelection.value = rows;
}

function onSelectType(row: DictType) {
  currentType.value = row;
}

function onTypeSaved() {
  reloadTypes();
}

// ===== 字典项 =====
const itemLoading = ref(false);
const itemList = ref<DictItemModel[]>([]);
const itemTotal = ref(0);
const itemSelection = ref<DictItemModel[]>([]);
const itemDialogVisible = ref(false);
const currentEditItem = ref<DictItemModel | null>(null);

const itemQuery = reactive({
  page: 1,
  pageSize: 10,
  typeCode: '',
  label: '',
  status: undefined as 0 | 1 | undefined
});

async function reloadItems() {
  if (!currentType.value) {
    itemList.value = [];
    itemTotal.value = 0;
    return;
  }
  itemQuery.typeCode = currentType.value.code;
  itemLoading.value = true;
  try {
    const res = await dictItemApi.list(itemQuery);
    itemList.value = res.list;
    itemTotal.value = res.total;
  } finally {
    itemLoading.value = false;
  }
}

function onSearchItem() {
  itemQuery.page = 1;
  reloadItems();
}

function onResetItem() {
  Object.assign(itemQuery, {
    page: 1,
    pageSize: 10,
    typeCode: currentType.value?.code || '',
    label: '',
    status: undefined
  });
  reloadItems();
}

function onAddItem() {
  if (!currentType.value) {
    ElMessage.warning('请先在左侧选择一个分类');
    return;
  }
  currentEditItem.value = null;
  itemDialogVisible.value = true;
}

function onEditItem(row: DictItemModel) {
  currentEditItem.value = row;
  itemDialogVisible.value = true;
}

async function onDeleteItem(row: DictItemModel) {
  await ElMessageBox.confirm(`确认删除字典项「${row.label}」?`, '提示', { type: 'warning' });
  await dictItemApi.remove(row.id);
  reloadItems();
}

async function onBatchDeleteItem() {
  if (!itemSelection.value.length) {
    ElMessage.warning('请至少选择一项');
    return;
  }
  await ElMessageBox.confirm(`确认删除选中的 ${itemSelection.value.length} 个字典项？此操作不可恢复`, '提示', {
    type: 'warning'
  });
  await dictItemApi.removeMany(itemSelection.value.map((r) => r.id));
  itemSelection.value = [];
  reloadItems();
}

function onItemSelectionChange(rows: DictItemModel[]) {
  itemSelection.value = rows;
}

/** 切换分类时自动重新加载字典项 */
watch(
  () => currentType.value?.code,
  (code) => {
    if (code) {
      itemQuery.page = 1;
      itemQuery.typeCode = code;
      itemQuery.label = '';
      itemQuery.status = undefined;
      reloadItems();
    } else {
      itemList.value = [];
      itemTotal.value = 0;
    }
  }
);

onMounted(reloadTypes);
</script>

<style lang="scss" scoped>
.dict-layout {
  display: flex;
  gap: 0;
  height: 100%;
  min-height: 0;

  &__aside {
    /* 原 340px 下双字段 + 按钮必折行；加宽以保证与右侧搜索区同高对齐 */
    width: 460px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--el-border-color-lighter);
    padding-right: 16px;
    overflow: hidden;
  }

  &__main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    padding-left: 16px;
    overflow: hidden;
  }

  &__pagination {
    padding: 8px 0;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--el-border-color-lighter);
  }

  &__empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

/* 左侧窄栏复用 SearchForm 栅格，仅缩小单列最小宽度。 */
.dict-type-search {
  :deep(.search-form__grid) {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
  }

  :deep(.dict-type-search__field) {
    margin-bottom: 0;
    margin-right: 0;
  }
}

.dict-current {
  display: inline-flex;
  align-items: center;
  font-size: 13px;
  color: var(--el-text-color-regular);
  margin-right: 12px;
  white-space: nowrap;

  b {
    color: var(--el-color-primary);
    margin: 0 4px;
  }

  &__code {
    margin-left: 6px;
    padding: 1px 8px;
    font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
    font-size: 12px;
    color: var(--el-color-primary);
    background: color-mix(in srgb, var(--el-color-primary) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--el-color-primary) 20%, transparent);
    border-radius: 4px;
    line-height: 20px;
  }
}

@media (max-width: 1200px) {
  .dict-layout {
    flex-direction: column;

    &__aside {
      width: 100%;
      border-right: none;
      border-bottom: 1px solid var(--el-border-color-lighter);
      padding-right: 0;
      padding-bottom: 16px;
    }

    &__main {
      padding-left: 0;
      padding-top: 16px;
    }
  }
}
</style>
