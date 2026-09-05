<template>
  <PageWrapper title="操作日志" subtitle="查看系统已记录的后台操作审计数据">
    <DataTableShell storage-key="system-log-operation" :loading="loading" :column-options="columnOptions" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="账号" prop="username">
            <el-input v-model="query.username" placeholder="操作账号" clearable />
          </el-form-item>
          <el-form-item label="应用" prop="appName">
            <el-input v-model="query.appName" placeholder="如 backend" clearable class="!w-36" />
          </el-form-item>
          <el-form-item label="来源" prop="sourceType">
            <el-select v-model="query.sourceType" placeholder="全部" clearable class="!w-32">
              <el-option label="系统" value="system" />
              <el-option label="插件" value="plugin" />
            </el-select>
          </el-form-item>
          <el-form-item label="来源标识" prop="sourceName">
            <el-input v-model="query.sourceName" placeholder="如 core" clearable class="!w-36" />
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
          <el-table-column v-if="columnKeys.includes('appName')" prop="appName" label="应用" width="100" />
          <el-table-column v-if="columnKeys.includes('source')" label="来源" width="140">
            <template #default="{ row }">{{ row.sourceType === 'plugin' ? `插件：${row.sourceName}` : '系统' }}</template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('name')" prop="name" label="操作" min-width="140" show-overflow-tooltip />
          <el-table-column v-if="columnKeys.includes('method')" label="方法" width="100" align="center" class-name="log-tag-column">
            <template #default="{ row }">
              <el-tag :type="methodTag(row.method)" size="small">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('url')" prop="url" label="URL" min-width="180" show-overflow-tooltip />
          <el-table-column v-if="columnKeys.includes('ip')" prop="ip" label="IP" width="140" />
          <el-table-column v-if="columnKeys.includes('status')" label="状态" width="90" align="center" class-name="log-tag-column">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                {{ row.status === 1 ? '成功' : '失败' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('responseCode')" prop="responseCode" label="状态码" width="90" align="center" />
          <el-table-column v-if="columnKeys.includes('durationMs')" label="耗时" width="100" align="center">
            <template #default="{ row }">{{ row.durationMs }} ms</template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('createdAt')" prop="createdAt" label="时间" width="170" />
          <el-table-column v-if="columnKeys.includes('actions')" label="操作" width="150" fixed="right" align="center">
            <template #default="{ row }">
              <el-button link type="primary" @click="showDetail(row as OperationLog)">详情</el-button>
              <el-button link type="danger" v-perm="'system:log:operation:delete'" @click="onDelete(row as OperationLog)">删除</el-button>
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

    <el-drawer v-model="detailVisible" title="操作日志详情" size="560px">
      <el-descriptions v-if="detail" :column="1" border>
        <el-descriptions-item label="账号">{{ detail.username }}</el-descriptions-item>
        <el-descriptions-item label="应用">{{ detail.appName }}</el-descriptions-item>
        <el-descriptions-item label="来源">{{ detail.sourceType === 'plugin' ? `插件：${detail.sourceName}` : '系统' }}</el-descriptions-item>
        <el-descriptions-item label="资源">{{ detail.controller }} / {{ detail.action }}</el-descriptions-item>
        <el-descriptions-item label="操作">{{ detail.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="请求">{{ detail.method }} {{ detail.url }}</el-descriptions-item>
        <el-descriptions-item label="响应">HTTP {{ detail.responseCode }} · {{ detail.durationMs }} ms</el-descriptions-item>
        <el-descriptions-item label="请求 ID">{{ detail.requestId || '-' }}</el-descriptions-item>
        <el-descriptions-item label="IP">{{ detail.ip }}</el-descriptions-item>
        <el-descriptions-item v-if="detail.errorMessage" label="失败原因">{{ detail.errorMessage }}</el-descriptions-item>
        <el-descriptions-item label="GET 参数"><pre>{{ detail.getData || '{}' }}</pre></el-descriptions-item>
        <el-descriptions-item label="请求参数"><pre>{{ detail.postData || '{}' }}</pre></el-descriptions-item>
        <el-descriptions-item label="User-Agent"><span class="break-all">{{ detail.agent || '-' }}</span></el-descriptions-item>
        <el-descriptions-item label="时间">{{ detail.createdAt }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { TagProps } from 'element-plus';
import { operationLogApi, type OperationLog, type OperationLogQuery } from '@/api/system/log';
import type { DataTableColumnOption } from '@/components/DataTable/types';
import { useCrud } from '@/composables/useCrud';

defineOptions({ name: 'SystemLogOperation' });

const columnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '账号' },
  { key: 'appName', label: '应用' },
  { key: 'source', label: '来源' },
  { key: 'name', label: '操作' },
  { key: 'method', label: '方法' },
  { key: 'url', label: 'URL' },
  { key: 'ip', label: 'IP' },
  { key: 'status', label: '状态' },
  { key: 'responseCode', label: '状态码' },
  { key: 'durationMs', label: '耗时' },
  { key: 'createdAt', label: '时间' },
  { key: 'actions', label: '操作', alwaysVisible: true }
];
const dateRange = ref<[string, string] | null>(null);
const detailVisible = ref(false);
const detail = ref<OperationLog | null>(null);
const {
  loading, list, total, query, selection, loadData, onSearch, onReset,
  onDelete, onBatchDelete, onSelectionChange
} = useCrud<OperationLog, OperationLogQuery>({
  api: {
    list: (params) => operationLogApi.list(params),
    remove: (id) => operationLogApi.remove(id),
    removeMany: (ids) => operationLogApi.remove(ids)
  },
  initialQuery: () => ({ page: 1, pageSize: 10, username: '', appName: '', sourceType: '', sourceName: '', status: undefined, startTime: '', endTime: '' }),
  pagination: true,
  deleteConfirm: (target) => Array.isArray(target) ? `确认删除选中的 ${target.length} 条日志？` : '确认删除该日志？'
});

function onDateChange(value: [string, string] | null) {
  query.startTime = value?.[0] || '';
  query.endTime = value?.[1] || '';
}
function methodTag(method: string): TagProps['type'] {
  return ({ GET: 'info', POST: 'success', PUT: 'warning', DELETE: 'danger' } as Record<string, TagProps['type']>)[method] || 'info';
}
async function showDetail(row: OperationLog) {
  detail.value = await operationLogApi.detail(row.id);
  detailVisible.value = true;
}
</script>

<style scoped>
pre { margin: 0; white-space: pre-wrap; word-break: break-all; font: inherit; }
.log-tag-column :deep(.cell) { overflow: visible; text-overflow: clip; }
</style>
