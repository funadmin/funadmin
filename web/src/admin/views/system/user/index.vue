<template>
  <PageWrapper :title="t('systemUser.title', '用户管理')" :subtitle="t('systemUser.subtitle', '管理系统用户、分配角色与部门')">
    <DataTableShell
      storage-key="system-user"
      :loading="loading"
      :column-options="userColumnOptions"
      @refresh="loadData"
    >
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item :label="t('systemUser.colUsername', '账号')" prop="username">
            <el-input v-model="query.username" :placeholder="t('systemUser.searchPlaceholder', '账号 / 昵称')" clearable />
          </el-form-item>
          <el-form-item :label="t('systemUser.colStatus', '状态')" prop="status">
            <el-select v-model="query.status" :placeholder="t('systemUser.statusPlaceholder', '请选择')" clearable class="!w-32">
              <el-option :label="t('systemUser.enabled', '启用')" :value="1" />
              <el-option :label="t('systemUser.disabled', '禁用')" :value="0" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button type="primary" plain v-perm="'system:user:add'" @click="onAdd">
          <i class="i-ep-plus" /> {{ t('systemUser.add', '新增') }}
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:user:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> {{ t('systemUser.batchDelete', '批量删除') }}{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
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
          <el-table-column type="selection" width="48" align="center" :selectable="(row: UserModel) => rowAccess(row).selectable" />
          <el-table-column
            v-if="columnKeys.includes('id')"
            prop="id"
            label="ID"
            width="80"
            align="center"
          />
          <el-table-column
            v-if="columnKeys.includes('username')"
            :label="t('systemUser.colUsername', '账号')"
            min-width="170"
          >
            <template #default="{ row }">
              <span class="user-name-cell">
                <span>{{ row.username }}</span>
                <el-tag v-if="row.isSuper" size="small" type="danger" effect="plain" disable-transitions>
                  {{ t('systemUser.superAdmin', '超级管理员') }}
                </el-tag>
              </span>
            </template>
          </el-table-column>
          <el-table-column
            v-if="columnKeys.includes('nickname')"
            prop="nickname"
            :label="t('systemUser.colNickname', '昵称')"
            min-width="120"
          />
          <el-table-column
            v-if="columnKeys.includes('email')"
            prop="email"
            :label="t('systemUser.colEmail', '邮箱')"
            min-width="180"
            show-overflow-tooltip
          />
          <el-table-column
            v-if="columnKeys.includes('mobile')"
            prop="mobile"
            :label="t('systemUser.colMobile', '手机')"
            width="140"
          />
          <el-table-column v-if="columnKeys.includes('status')" :label="t('systemUser.colStatus', '状态')" width="90" align="center">
            <template #default="{ row }">
              <div class="app-status-switch">
                <el-switch
                  size="small"
                  :model-value="row.status === 1"
                  :disabled="!canToggleStatus(row as UserModel)"
                  @change="(v: string | number | boolean) => onToggleStatus(row as UserModel, v === true)"
                />
              </div>
            </template>
          </el-table-column>
          <el-table-column
            v-if="columnKeys.includes('createdAt')"
            prop="createdAt"
            :label="t('systemUser.colCreatedAt', '创建时间')"
            width="170"
          />
          <el-table-column
            v-if="columnKeys.includes('action')"
            :label="t('systemUser.colActions', '操作')"
            width="220"
            align="center"
            fixed="right"
          >
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button v-if="rowAccess(row as UserModel).editable" size="small" type="primary" link v-perm="'system:user:edit'" @click="onEdit(row as UserModel)">
                  {{ t('systemUser.edit', '编辑') }}
                </el-button>
                <el-button v-if="rowAccess(row as UserModel).resettable" size="small" type="warning" link v-perm="'system:user:reset'" @click="onResetPwd(row as UserModel)">
                  {{ t('systemUser.resetPwd', '重置密码') }}
                </el-button>
                <el-button v-if="rowAccess(row as UserModel).deletable" size="small" type="danger" link v-perm="'system:user:delete'" @click="onDelete(row as UserModel)">
                  {{ t('systemUser.delete', '删除') }}
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
import { hasPermission } from '@/utils/permission';
import { computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { userApi, type UserModel } from '@/api/system/user';
import type { DataTableColumnOption } from '@/components/DataTable';
import { useCrud } from '@/composables/useCrud';
import { isWebmanBackend } from '@/config';
import { useUserStore } from '@/store/modules/user';
import UserFormDialog from './components/UserFormDialog.vue';
import { userRowAccess } from './superAdminAccess';

defineOptions({ name: 'SystemUser' });
const { t } = useI18n();
const userStore = useUserStore();
const rowAccess = (row: UserModel) => userRowAccess(row, userStore.userInfo?.id);
const hasStatusPermission = computed(() => hasPermission('system:user:status'));
const canToggleStatus = (row: UserModel) => hasStatusPermission.value && rowAccess(row).statusToggle;

/** 与表格列一一对应，供工具栏「列设置」勾选显隐；操作列不可取消 */
const userColumnOptions = computed<DataTableColumnOption[]>(() => [
  { key: 'id', label: 'ID' },
  { key: 'username', label: t('systemUser.colUsername', '账号') },
  { key: 'nickname', label: t('systemUser.colNickname', '昵称') },
  { key: 'email', label: t('systemUser.colEmail', '邮箱') },
  { key: 'mobile', label: t('systemUser.colMobile', '手机') },
  { key: 'status', label: t('systemUser.colStatus', '状态') },
  { key: 'createdAt', label: t('systemUser.colCreatedAt', '创建时间') },
  { key: 'action', label: t('systemUser.colActions', '操作'), alwaysVisible: true }
]);

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
  permissions: { add: 'system:user:add', edit: 'system:user:edit', delete: 'system:user:delete' },
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
      ? t('systemUser.deleteManyConfirm', { n: target.length }, { default: '确认删除选中的 {n} 个账号？此操作不可恢复' })
      : t('systemUser.deleteOneConfirm', { name: (target as UserModel).username }, { default: '确认删除账号 {name} ?' })
});

async function onToggleStatus(row: UserModel, v: boolean) {
  if (!hasPermission("system:user:status")) return;
  if (!canToggleStatus(row)) return;
  const target = (v ? 1 : 0) as 0 | 1;
   await userApi.toggleStatus(row.id, target);
  row.status = target;
  ElMessage.success(t('systemUser.statusUpdated', '状态已更新'));
}

async function onResetPwd(row: UserModel) {
  if (!hasPermission("system:user:reset")) return;
  const { value } = await ElMessageBox.prompt(t('systemUser.resetPwdPrompt', { name: row.username }, { default: '重置 {name} 的密码' }), t('systemUser.resetPwdTitle', '提示'), {
    inputType: 'password',
    inputPlaceholder: isWebmanBackend() ? t('profile.passwordBytes', '新密码需为 12～72 字节') : t('systemUser.resetPwdPlaceholder', '至少 8 位新密码'),
    inputValidator: (value: string) => {
      if (!isWebmanBackend()) return value.length >= 8 || t('systemUser.resetPwdError', '密码长度至少 8 位');
      const bytes = new TextEncoder().encode(value).length;
      return bytes < 12 || bytes > 72 || value.includes('\0') ? t('profile.passwordBytes', '新密码需为 12～72 字节') : true;
    }
  });
  if (!hasPermission("system:user:reset")) return;
 await userApi.resetPassword(row.id, value);
}

</script>

<style scoped>
.user-name-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.app-status-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
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
