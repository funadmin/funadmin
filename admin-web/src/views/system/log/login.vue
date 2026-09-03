<template>
  <PageWrapper title="登录日志" subtitle="记录所有登录尝试，便于安全审计与异常排查">
    <DataTableShell
      storage-key="system-log-login"
      :loading="loading"
      :column-options="columnOptions"
      @refresh="loadData"
    >
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="账号" prop="username">
            <el-input v-model="query.username" placeholder="登录账号" clearable />
          </el-form-item>
          <el-form-item label="状态" prop="status">
            <el-select v-model="query.status" placeholder="全部" clearable class="!w-28">
              <el-option label="成功" :value="1" />
              <el-option label="失败" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="时间" prop="range">
            <el-date-picker
              v-model="dateRange"
              type="datetimerange"
              value-format="YYYY-MM-DD HH:mm:ss"
              start-placeholder="开始"
              end-placeholder="结束"
              class="!w-72"
              @change="onDateChange"
            />
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:log:login:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> 批量删除{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
        <el-button type="danger" plain v-perm="'system:log:login:clear'" @click="onClear">
          <i class="i-ep-warning" /> 清空
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
          <el-table-column type="selection" width="48" align="center" />
          <el-table-column v-if="columnKeys.includes('id')" prop="id" label="ID" width="80" align="center" />
          <el-table-column v-if="columnKeys.includes('username')" prop="username" label="账号" width="140" />
          <el-table-column v-if="columnKeys.includes('ip')" label="IP" width="160">
            <template #default="{ row }">
              <span>{{ row.ip }}</span>
              <span class="app-log__loc">/ {{ row.location }}</span>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('browser')" prop="browser" label="浏览器" width="140" />
          <el-table-column v-if="columnKeys.includes('os')" prop="os" label="操作系统" width="160" />
          <el-table-column v-if="columnKeys.includes('status')" label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                {{ row.status === 1 ? '成功' : '失败' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('message')" prop="message" label="提示" min-width="160" show-overflow-tooltip />
          <el-table-column v-if="columnKeys.includes('createdAt')" prop="createdAt" label="时间" width="170" />
          <el-table-column
            v-if="columnKeys.includes('action_btns')"
            label="操作"
            width="100"
            align="center"
            fixed="right"
          >
            <template #default="{ row }">
              <el-button
                size="small"
                type="danger"
                link
                v-perm="'system:log:login:delete'"
                @click="onDelete(row as LoginLog)"
              >
                <i class="i-ep-delete" /> 删除
              </el-button>
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
  </PageWrapper>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import PageWrapper from '@/components/PageWrapper/index.vue';
import SearchForm from '@/components/SearchForm/index.vue';
import DataTableShell from '@/components/DataTable/DataTableShell.vue';
import type { DataTableColumnOption } from '@/components/DataTable/types';
import { useCrud } from '@/composables/useCrud';
import { loginLogApi, type LoginLog, type LoginLogQuery } from '@/api/system/log';

defineOptions({ name: 'SystemLogLogin' });

const columnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '账号' },
  { key: 'ip', label: 'IP' },
  { key: 'browser', label: '浏览器' },
  { key: 'os', label: '操作系统' },
  { key: 'status', label: '状态' },
  { key: 'message', label: '提示' },
  { key: 'createdAt', label: '时间' },
  { key: 'action_btns', label: '操作', alwaysVisible: true }
];

const dateRange = ref<[string, string] | null>(null);

const {
  loading,
  list,
  total,
  query,
  selection,
  loadData,
  onSearch,
  onReset,
  onDelete,
  onBatchDelete,
  onSelectionChange
} = useCrud<LoginLog, LoginLogQuery>({
  api: {
    list: (params) => loginLogApi.list(params),
    remove: (id) => loginLogApi.remove(id),
    removeMany: (ids) => loginLogApi.removeMany(ids)
  },
  initialQuery: () => ({
    page: 1,
    pageSize: 10,
    username: '',
    status: undefined,
    startTime: '',
    endTime: ''
  }),
  pagination: true
});

function onDateChange(val: [string, string] | null) {
  if (val) {
    query.startTime = val[0];
    query.endTime = val[1];
  } else {
    query.startTime = '';
    query.endTime = '';
  }
}

async function onClear() {
  await ElMessageBox.confirm('确认清空所有登录日志？此操作不可恢复', '提示', { type: 'warning' });
  await loginLogApi.clear();
  ElMessage.success('已清空');
  loadData();
}
</script>

<style scoped>
.app-log__loc {
  margin-left: 4px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
}
.app-pagination-bar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding-top: 12px;
  margin-top: 12px;
  border-top: 1px solid var(--app-border, #ebeef5);
}
</style>
