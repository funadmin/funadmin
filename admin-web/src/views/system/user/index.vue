<template>
  <PageWrapper title="用户管理" subtitle="管理系统用户、分配角色与部门">
    <DataTableShell
      storage-key="system-user"
      :loading="loading"
      :column-options="userColumnOptions"
      @refresh="loadData"
    >
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="账号" prop="username">
            <el-input v-model="query.username" placeholder="账号 / 昵称" clearable />
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-select v-model="query.status" placeholder="请选择" clearable class="!w-32">
              <el-option label="启用" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:user:add'" @click="onAdd">
          <i class="i-ep-plus" /> 新增
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:user:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> 批量删除{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
        <el-button type="primary" plain v-perm="'system:user:export'" :loading="exporting" @click="onExport">
          <i class="i-ep-download" /> 导出
        </el-button>
        <el-button type="primary" plain v-perm="'system:user:import'" @click="triggerImport">
          <i class="i-ep-upload" /> 导入
        </el-button>
        <el-button link type="info" @click="onDownloadTemplate">
          <i class="i-ep-document" /> 模板
        </el-button>
        <input
          ref="fileInputRef"
          type="file"
          accept=".csv,text/csv"
          class="hidden"
          @change="onFileSelected"
        />
      </template>

      <template #default="{ size, stripe, border, headerCellStyle, columnKeys }">
        <el-table
          :data="list"
          v-loading="loading"
          :size="size"
          :stripe="stripe"
          :border="border"
          :header-cell-style="headerCellStyle"
          @selection-change="onSelectionChange"
        >
          <el-table-column type="selection" width="48" align="center" />
          <el-table-column
            v-if="columnKeys.includes('id')"
            prop="id"
            label="ID"
            width="80"
            align="center"
          />
          <el-table-column
            v-if="columnKeys.includes('username')"
            prop="username"
            label="账号"
            min-width="120"
          />
          <el-table-column
            v-if="columnKeys.includes('nickname')"
            prop="nickname"
            label="昵称"
            min-width="120"
          />
          <el-table-column
            v-if="columnKeys.includes('email')"
            prop="email"
            label="邮箱"
            min-width="180"
            show-overflow-tooltip
          />
          <el-table-column
            v-if="columnKeys.includes('mobile')"
            prop="mobile"
            label="手机"
            width="140"
          />
          <el-table-column v-if="columnKeys.includes('status')" label="状态" width="120" align="center">
            <template #default="{ row }">
              <div class="app-status-switch">
                <el-switch
                  size="small"
                  :model-value="row.status === 1"
                  @change="(v: string | number | boolean) => onToggleStatus(row as UserModel, v === true)"
                />
                <span
                  class="app-status-switch__text"
                  :class="row.status === 1 ? 'is-on' : 'is-off'"
                >
                  {{ row.status === 1 ? '启用' : '禁用' }}
                </span>
              </div>
            </template>
          </el-table-column>
          <el-table-column
            v-if="columnKeys.includes('createdAt')"
            prop="createdAt"
            label="创建时间"
            width="170"
          />
          <el-table-column
            v-if="columnKeys.includes('action')"
            label="操作"
            width="300"
            align="center"
            fixed="right"
          >
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button size="small" type="primary" link v-perm="'system:user:edit'" @click="onEdit(row as UserModel)">
                  <i class="i-ep-edit" /> 编辑
                </el-button>
                <el-button size="small" type="warning" link v-perm="'system:user:reset'" @click="onResetPwd(row as UserModel)">
                  <i class="i-ep-key" /> 重置密码
                </el-button>
                <el-button size="small" type="danger" link v-perm="'system:user:delete'" @click="onDelete(row as UserModel)">
                  <i class="i-ep-delete" /> 删除
                </el-button>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <div class="app-pagination-bar">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @current-change="loadData"
        @size-change="loadData"
      />
    </div>

    <UserFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus';
import { userApi, type UserModel } from '@/api/system/user';
import type { DataTableColumnOption } from '@/components/DataTable';
import { useCrud } from '@/composables/useCrud';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import UserFormDialog from './components/UserFormDialog.vue';

defineOptions({ name: 'SystemUser' });

/** 与表格列一一对应，供工具栏「列设置」勾选显隐；操作列不可取消 */
const userColumnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '账号' },
  { key: 'nickname', label: '昵称' },
  { key: 'email', label: '邮箱' },
  { key: 'mobile', label: '手机' },
  { key: 'status', label: '状态' },
  { key: 'createdAt', label: '创建时间' },
  { key: 'action', label: '操作', alwaysVisible: true }
];

const {
  loading,
  list,
  total,
  query,
  selection,
  dialogVisible,
  current,
  loadData,
  onSearch,
  onReset,
  onAdd,
  onEdit,
  onDelete,
  onBatchDelete,
  onSelectionChange
} = useCrud<UserModel, { page: number; pageSize: number; username: string; status?: number }>({
  api: {
    list: (params) => userApi.list(params),
    // userApi.remove 同时支持单 id 与 ids[]，所以两条都接到同一个方法
    remove: (id) => userApi.remove(id),
    removeMany: (ids) => userApi.remove(ids)
  },
  initialQuery: () => ({ page: 1, pageSize: 10, username: '', status: undefined }),
  pagination: true,
  deleteConfirm: (target) =>
    Array.isArray(target)
      ? `确认删除选中的 ${target.length} 个账号？此操作不可恢复`
      : `确认删除账号 ${(target as UserModel).username} ?`
});

async function onToggleStatus(row: UserModel, v: boolean) {
  const target = (v ? 1 : 0) as 0 | 1;
  await userApi.toggleStatus(row.id, target);
  row.status = target;
  ElMessage.success('状态已更新');
}

async function onResetPwd(row: UserModel) {
  const { value } = await ElMessageBox.prompt(`重置 ${row.username} 的密码`, '提示', {
    inputPlaceholder: '至少 6 位新密码',
    inputPattern: /^.{6,}$/,
    inputErrorMessage: '密码长度至少 6 位'
  });
  await userApi.resetPassword(row.id, value);
}

/* ================== 导入 / 导出 ================== */

/** CSV 列定义：导出 + 导入 + 模板共用一份 */
const csvColumns: CsvColumn<UserModel>[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '账号' },
  { key: 'nickname', label: '昵称' },
  { key: 'email', label: '邮箱' },
  { key: 'mobile', label: '手机' },
  {
    key: 'status',
    label: '状态',
    formatter: (r) => (r.status === 1 ? '启用' : '禁用'),
    parser: (v) => (String(v).trim() === '禁用' || v === '0' ? 0 : 1)
  },
  { key: 'createdAt', label: '创建时间' }
];

const exporting = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

/**
 * 导出策略：
 * 1) 有勾选行 → 仅导出选中
 * 2) 否则 → 拉取「全量」数据后导出（pageSize 设为 9999 兜底）
 */
async function onExport() {
  exporting.value = true;
  try {
    let rows: UserModel[];
    if (selection.value.length) {
      rows = [...selection.value];
    } else {
      const res = await userApi.list({ ...query, page: 1, pageSize: 9999 });
      rows = res.list;
    }
    if (!rows.length) {
      ElMessage.warning('没有可导出的数据');
      return;
    }
    const csv = toCsv(rows, csvColumns);
    const stamp = new Date().toISOString().slice(0, 10);
    downloadCsv(`用户列表_${stamp}.csv`, csv);
    ElMessage.success(`已导出 ${rows.length} 条数据`);
  } finally {
    exporting.value = false;
  }
}

/** 下载导入模板（仅表头 + 一行示例） */
function onDownloadTemplate() {
  const sample = [
    { username: 'demo', nickname: '示例账号', email: 'demo@example.com', mobile: '13800000000', status: 1 } as any
  ];
  const csv = toCsv(sample, csvColumns);
  downloadCsv('用户导入模板.csv', csv);
}

function triggerImport() {
  fileInputRef.value?.click();
}

async function onFileSelected(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = ''; // 允许重复选同一文件
  if (!file) return;

  try {
    const text = await readFileAsText(file);
    const rows = parseCsv<UserModel>(text, csvColumns);
    if (!rows.length) {
      ElMessage.warning('CSV 中没有可导入的数据');
      return;
    }
    await ElMessageBox.confirm(`检测到 ${rows.length} 条数据，确认导入？`, '导入确认', {
      type: 'info'
    });
    const result = await userApi.batchImport(rows as any);
    if (result.errors?.length) {
      ElNotification.warning({
        title: `导入完成（成功 ${result.created}，跳过 ${result.skipped}）`,
        message: result.errors.slice(0, 5).join('\n') + (result.errors.length > 5 ? '\n…' : ''),
        duration: 6000
      });
    }
    loadData();
  } catch (err: any) {
    if (err === 'cancel' || err?.message === 'cancel') return;
    ElMessage.error(err?.message || '导入失败');
  }
}
</script>

<style scoped>
.app-status-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.app-status-switch__text {
  font-size: 12px;
  font-weight: 500;
  user-select: none;
  white-space: nowrap;
}
.app-status-switch__text.is-on {
  color: var(--el-color-primary);
}
.app-status-switch__text.is-off {
  color: var(--el-text-color-secondary);
}

/* 分页条：放在 PageWrapper 底部，与表格统一视觉 */
.app-pagination-bar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding-top: 12px;
  margin-top: 12px;
  border-top: 1px solid var(--app-border, #ebeef5);
}
</style>
