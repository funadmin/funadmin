<template>
  <PageWrapper title="操作日志" subtitle="记录系统关键操作（增/删/改）的执行情况与耗时">
    <DataTableShell
      storage-key="system-log-operation"
      :loading="loading"
      :column-options="columnOptions"
      @refresh="loadData"
    >
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="账号" prop="username">
            <el-input v-model="query.username" placeholder="操作账号" clearable />
          </el-form-item>
          <el-form-item label="模块" prop="module">
            <el-select v-model="query.module" placeholder="全部模块" clearable class="!w-40">
              <el-option
                v-for="m in MODULE_OPTIONS"
                :key="m"
                :label="m"
                :value="m"
              />
            </el-select>
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
          v-perm="'system:log:operation:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> 批量删除{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
        <el-button type="danger" plain v-perm="'system:log:operation:clear'" @click="onClear">
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
          <el-table-column v-if="columnKeys.includes('username')" prop="username" label="账号" width="120" />
          <el-table-column v-if="columnKeys.includes('module')" prop="module" label="模块" width="120" />
          <el-table-column v-if="columnKeys.includes('action')" prop="action" label="操作" min-width="140" />
          <el-table-column v-if="columnKeys.includes('method')" label="方法" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="METHOD_TAG[row.method as keyof typeof METHOD_TAG]" size="small">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('url')" prop="url" label="URL" min-width="180" show-overflow-tooltip />
          <el-table-column v-if="columnKeys.includes('ip')" label="来源" width="180">
            <template #default="{ row }">
              <span>{{ row.ip }}</span>
              <span class="app-log__loc">/ {{ row.location }}</span>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('duration')" prop="duration" label="耗时" width="90" align="right">
            <template #default="{ row }">{{ row.duration }} ms</template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('status')" label="状态" width="80" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                {{ row.status === 1 ? '成功' : '失败' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('createdAt')" prop="createdAt" label="时间" width="170" />
          <el-table-column
            v-if="columnKeys.includes('action_btns')"
            label="操作"
            width="160"
            align="center"
            fixed="right"
          >
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button size="small" type="primary" link @click="onShowDetail(row as OperationLog)">
                  <i class="i-ep-view" /> 详情
                </el-button>
                <el-button
                  size="small"
                  type="danger"
                  link
                  v-perm="'system:log:operation:delete'"
                  @click="onDelete(row as OperationLog)"
                >
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

    <!-- 详情抽屉 -->
    <el-drawer v-model="detailVisible" title="操作日志详情" size="540px">
      <el-descriptions v-if="detail" :column="1" border>
        <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
        <el-descriptions-item label="账号">{{ detail.username }}</el-descriptions-item>
        <el-descriptions-item label="模块">{{ detail.module }}</el-descriptions-item>
        <el-descriptions-item label="操作">{{ detail.action }}</el-descriptions-item>
        <el-descriptions-item label="方法">
          <el-tag :type="METHOD_TAG[detail.method as keyof typeof METHOD_TAG]" size="small">{{ detail.method }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="URL">{{ detail.url }}</el-descriptions-item>
        <el-descriptions-item label="IP">{{ detail.ip }}</el-descriptions-item>
        <el-descriptions-item label="归属地">{{ detail.location }}</el-descriptions-item>
        <el-descriptions-item label="耗时">{{ detail.duration }} ms</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="detail.status === 1 ? 'success' : 'danger'" size="small">
            {{ detail.status === 1 ? '成功' : '失败' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="错误信息">{{ detail.errorMsg || '-' }}</el-descriptions-item>
        <el-descriptions-item label="时间">{{ detail.createdAt }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>
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
import { operationLogApi, type OperationLog, type OperationLogQuery } from '@/api/system/log';

defineOptions({ name: 'SystemLogOperation' });

const MODULE_OPTIONS = ['用户管理', '角色管理', '菜单管理', '部门管理', '字典管理', '认证模块'];
const METHOD_TAG = {
  GET: 'info',
  POST: 'success',
  PUT: 'warning',
  DELETE: 'danger'
} as const;

const columnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '账号' },
  { key: 'module', label: '模块' },
  { key: 'action', label: '操作' },
  { key: 'method', label: '方法' },
  { key: 'url', label: 'URL' },
  { key: 'ip', label: '来源' },
  { key: 'duration', label: '耗时' },
  { key: 'status', label: '状态' },
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
} = useCrud<OperationLog, OperationLogQuery>({
  api: {
    list: (params) => operationLogApi.list(params),
    remove: (id) => operationLogApi.remove(id),
    removeMany: (ids) => operationLogApi.removeMany(ids)
  },
  initialQuery: () => ({
    page: 1,
    pageSize: 10,
    username: '',
    module: undefined,
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
  await ElMessageBox.confirm('确认清空所有操作日志？此操作不可恢复', '提示', { type: 'warning' });
  await operationLogApi.clear();
  ElMessage.success('已清空');
  loadData();
}

const detailVisible = ref(false);
const detail = ref<OperationLog | null>(null);
function onShowDetail(row: OperationLog) {
  detail.value = row;
  detailVisible.value = true;
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
