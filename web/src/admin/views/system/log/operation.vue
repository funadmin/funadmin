<template>
  <PageWrapper :title="t('systemLog.title', '操作日志')" :subtitle="t('systemLog.subtitle', '查看系统已记录的后台操作审计数据')">
    <DataTableShell storage-key="system-log-operation" :loading="loading" :column-options="columnOptions" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item :label="t('systemLog.username', '账号')" prop="username">
            <el-input v-model="query.username" :placeholder="t('systemLog.usernamePlaceholder', '操作账号')" clearable />
          </el-form-item>
          <el-form-item :label="t('systemLog.appName', '应用')" prop="appName">
            <el-input v-model="query.appName" :placeholder="t('systemLog.appPlaceholder', '如 console')" clearable class="!w-36" />
          </el-form-item>
          <el-form-item :label="t('systemLog.sourceType', '来源')" prop="sourceType">
            <el-select v-model="query.sourceType" :placeholder="t('systemLog.all', '全部')" clearable class="!w-32">
              <el-option :label="t('systemLog.system', '系统')" value="system" />
              <el-option :label="t('systemLog.plugin', '插件')" value="plugin" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('systemLog.sourceName', '来源标识')" prop="sourceName">
            <el-input v-model="query.sourceName" :placeholder="t('systemLog.sourceNamePlaceholder', '如 core')" clearable class="!w-36" />
          </el-form-item>
          <el-form-item :label="t('common.status', '状态')" prop="status">
            <el-select v-model="query.status" :placeholder="t('systemLog.all', '全部')" clearable class="!w-28">
              <el-option :label="t('common.success', '成功')" :value="1" />
              <el-option :label="t('common.failed', '失败')" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('systemLog.time', '时间')" prop="range">
            <el-date-picker
              v-model="dateRange"
              type="datetimerange"
              value-format="YYYY-MM-DD HH:mm:ss"
              :start-placeholder="t('systemLog.rangeStart', '开始')"
              :end-placeholder="t('systemLog.rangeEnd', '结束')"
              class="!w-72"
              @change="onDateChange"
            />
          </el-form-item>
        </SearchForm>
      </template>

      <template #toolbar-left>
        <el-radio-group :model-value="trashView" @change="onToggleTrash">
          <el-radio-button :value="false">{{ t('systemLog.viewLive', '日志列表') }}</el-radio-button>
          <el-radio-button :value="true">{{ t('systemLog.viewTrash', '回收站') }}</el-radio-button>
        </el-radio-group>
        <template v-if="!trashView">
          <el-button
            type="danger"
            plain
            :disabled="!selection.length"
            v-perm="'system:operation-log:delete'"
            @click="onBatchDelete"
          >
            <i class="i-ep-delete" /> {{ t('common.batchRemove', '批量删除') }}{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button
            type="danger"
            plain
            v-perm="'system:operation-log:clear'"
            @click="onClear"
          >
            <i class="i-ep-delete-filled" /> {{ t('systemLog.clearAll', '清空') }}
          </el-button>
        </template>
        <template v-else>
          <el-button
            type="danger"
            plain
            :disabled="!selection.length"
            v-perm="'system:operation-log:force-delete'"
            @click="onForceDelete(selection)"
          >
            <i class="i-ep-delete" /> {{ t('systemLog.forceRemove', '彻底删除') }}{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button
            type="danger"
            plain
            v-perm="'system:operation-log:clear-force'"
            @click="onClearForce"
          >
            <i class="i-ep-delete-filled" /> {{ t('systemLog.clearTrash', '清空回收站') }}
          </el-button>
        </template>
      </template>

      <template #default="{ size, stripe, border, headerCellStyle, columnKeys }">
        <el-table
          ref="tableRef"
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
          <el-table-column v-if="columnKeys.includes('username')" prop="username" :label="t('systemLog.username', '账号')" width="120" />
          <el-table-column v-if="columnKeys.includes('appName')" prop="appName" :label="t('systemLog.appName', '应用')" width="100" />
          <el-table-column v-if="columnKeys.includes('source')" :label="t('systemLog.sourceType', '来源')" width="140">
            <template #default="{ row }">{{ row.sourceType === 'plugin' ? t('systemLog.pluginPrefix', { name: row.sourceName }, { default: '插件：{name}' }) : t('systemLog.system', '系统') }}</template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('name')" prop="name" :label="t('common.operation', '操作')" min-width="140" show-overflow-tooltip />
          <el-table-column v-if="columnKeys.includes('method')" :label="t('systemLog.method', '方法')" width="100" align="center" class-name="log-tag-column">
            <template #default="{ row }">
              <el-tag :type="methodTag(row.method)" size="small">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('url')" prop="url" label="URL" min-width="180" show-overflow-tooltip />
          <el-table-column v-if="columnKeys.includes('ip')" prop="ip" label="IP" width="140" />
          <el-table-column v-if="columnKeys.includes('status')" :label="t('common.status', '状态')" width="90" align="center" class-name="log-tag-column">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                {{ row.status === 1 ? t('common.success', '成功') : t('common.failed', '失败') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('responseCode')" prop="responseCode" :label="t('systemLog.statusCode', '状态码')" width="90" align="center" />
          <el-table-column v-if="columnKeys.includes('durationMs')" :label="t('systemLog.duration', '耗时')" width="100" align="center">
            <template #default="{ row }">{{ row.durationMs }} ms</template>
          </el-table-column>
          <el-table-column v-if="columnKeys.includes('createdAt')" prop="createdAt" :label="t('systemLog.time', '时间')" width="170" />
          <!-- 回收站专属列不进入列设置，避免历史列偏好把它隐藏。 -->
          <el-table-column v-if="trashView" key="deletedAt" prop="deletedAt" :label="t('systemLog.deletedAt', '删除时间')" width="170" />
          <el-table-column v-if="columnKeys.includes('actions')" :label="t('common.operation', '操作')" width="150" fixed="right" align="center">
            <template #default="{ row }">
              <el-button link type="primary" @click="showDetail(row as OperationLog)">{{ t('common.detail', '详情') }}</el-button>
              <el-button v-if="!trashView" link type="danger" v-perm="'system:operation-log:delete'" @click="onDelete(row as OperationLog)">{{ t('common.remove', '删除') }}</el-button>
              <el-button v-else link type="danger" v-perm="'system:operation-log:force-delete'" @click="onForceDelete([row as OperationLog])">{{ t('systemLog.forceRemove', '彻底删除') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <TablePagination
      v-model:current-page="query.page"
      v-model:page-size="query.pageSize"
      :total="total"
      @change="loadData"
    />

    <el-drawer v-model="detailVisible" :title="t('systemLog.detailTitle', '操作日志详情')" size="560px">
      <el-descriptions v-if="detail" :column="1" border>
        <el-descriptions-item :label="t('systemLog.username', '账号')">{{ detail.username }}</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.appName', '应用')">{{ detail.appName }}</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.sourceType', '来源')">{{ detail.sourceType === 'plugin' ? t('systemLog.pluginPrefix', { name: detail.sourceName }, { default: '插件：{name}' }) : t('systemLog.system', '系统') }}</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.resource', '资源')">{{ detail.controller }} / {{ detail.action }}</el-descriptions-item>
        <el-descriptions-item :label="t('common.operation', '操作')">{{ detail.name || '-' }}</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.request', '请求')">{{ detail.method }} {{ detail.url }}</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.response', '响应')">HTTP {{ detail.responseCode }} · {{ detail.durationMs }} ms</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.requestId', '请求 ID')">{{ detail.requestId || '-' }}</el-descriptions-item>
        <el-descriptions-item label="IP">{{ detail.ip }}</el-descriptions-item>
        <el-descriptions-item v-if="detail.errorMessage" :label="t('systemLog.failReason', '失败原因')">{{ detail.errorMessage }}</el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.getParams', 'GET 参数')"><pre>{{ detail.getData || '{}' }}</pre></el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.postParams', '请求参数')"><pre>{{ detail.postData || '{}' }}</pre></el-descriptions-item>
        <el-descriptions-item label="User-Agent"><span class="break-all">{{ detail.agent || '-' }}</span></el-descriptions-item>
        <el-descriptions-item :label="t('systemLog.time', '时间')">{{ detail.createdAt }}</el-descriptions-item>
        <el-descriptions-item v-if="detail.deletedAt" :label="t('systemLog.deletedAt', '删除时间')">{{ detail.deletedAt }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import TablePagination from '@/components/TablePagination/index.vue';
import { hasPermission } from '@/utils/permission';
import { ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { TableInstance, TagProps } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { operationLogApi, type OperationLog, type OperationLogQuery } from '@/api/system/log';
import type { DataTableColumnOption } from '@/components/DataTable/types';
import { useCrud } from '@/composables/useCrud';

defineOptions({ name: 'SystemLogOperation' });

const { t } = useI18n();
const columnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: t('systemLog.username', '账号') },
  { key: 'appName', label: t('systemLog.appName', '应用') },
  { key: 'source', label: t('systemLog.sourceType', '来源') },
  { key: 'name', label: t('common.operation', '操作') },
  { key: 'method', label: t('systemLog.method', '方法') },
  { key: 'url', label: 'URL' },
  { key: 'ip', label: 'IP' },
  { key: 'status', label: t('common.status', '状态') },
  { key: 'responseCode', label: t('systemLog.statusCode', '状态码') },
  { key: 'durationMs', label: t('systemLog.duration', '耗时') },
  { key: 'createdAt', label: t('systemLog.time', '时间') },
  { key: 'actions', label: t('common.operation', '操作'), alwaysVisible: true }
];
const dateRange = ref<[string, string] | null>(null);
const trashView = ref(false);
const tableRef = ref<TableInstance>();
const detailVisible = ref(false);
const detail = ref<OperationLog | null>(null);
const {
  loading, list, total, query, selection, loadData, onSearch, onReset,
  onDelete, onBatchDelete, onSelectionChange
} = useCrud<OperationLog, OperationLogQuery>({
  permissions: { delete: 'system:operation-log:delete' },
  api: {
    list: (params) => operationLogApi.list(params),
    remove: (id) => operationLogApi.remove(id),
    removeMany: (ids) => operationLogApi.remove(ids)
  },
  // 重置筛选条件时保留当前所在视图（日志列表 / 回收站）。
  initialQuery: () => ({ page: 1, pageSize: 10, username: '', appName: '', sourceType: '', sourceName: '', status: undefined, startTime: '', endTime: '', trashed: trashView.value ? 1 : undefined }),
  pagination: true,
  deleteConfirm: (target) => Array.isArray(target) ? t('systemLog.deleteManyConfirm', { n: target.length }, { default: '确认删除选中的 {n} 条日志？删除后将移入回收站。' }) : t('systemLog.deleteOneConfirm', '确认删除该日志？删除后将移入回收站。')
});

function onDateChange(value: [string, string] | null) {
  query.startTime = value?.[0] || '';
  query.endTime = value?.[1] || '';
}
function methodTag(method: string): TagProps['type'] {
  return ({ GET: 'info', POST: 'success', PUT: 'warning', DELETE: 'danger' } as Record<string, TagProps['type']>)[method] || 'info';
}
async function onClear() {
  if (!hasPermission("system:operation-log:clear")) return;
  await ElMessageBox.confirm(
    t('systemLog.clearAllConfirm', '确认清空全部操作日志？清空后日志将移入回收站。'),
    t('systemLog.clearAllTitle', '清空日志'),
    { type: 'warning', confirmButtonText: t('systemLog.clearAll', '清空') }
  );
  if (!hasPermission("system:operation-log:clear")) return;
 await operationLogApi.clear();
  await loadData();
}
function onToggleTrash(value: string | number | boolean | undefined) {
  trashView.value = value === true;
  query.trashed = trashView.value ? 1 : undefined;
  query.page = 1;
  resetSelection();
  detailVisible.value = false;
  // useCrud.loadData 只采纳最后一次请求的响应，切换前发出的慢响应会被丢弃。
  loadData();
}
function resetSelection() {
  selection.value = [];
  tableRef.value?.clearSelection();
}
/** 确认框被取消时返回 false，不向上抛出 cancel。 */
async function confirmDanger(message: string, title: string, confirmButtonText: string) {
  try {
    await ElMessageBox.confirm(message, title, { type: 'error', confirmButtonText, confirmButtonClass: 'el-button--danger' });
    return true;
  } catch {
    return false;
  }
}
async function onForceDelete(rows: OperationLog[]) {
  const permission = 'system:operation-log:force-delete';
  if (!trashView.value || !rows.length || !hasPermission(permission)) return;
  // 只允许彻底删除回收站中的日志；未删除日志（deletedAt 为空）直接拒绝，不发请求。
  if (rows.some(item => !item.deletedAt)) {
    ElMessage.warning(t('systemLog.forceRemoveLiveRejected', '只能彻底删除回收站中的日志'));
    return;
  }
  const ids = rows.map(item => item.id);
  const message = ids.length === 1
    ? t('systemLog.forceRemoveOneConfirm', '确认彻底删除该日志？日志将被永久删除，此操作不可恢复。')
    : t('systemLog.forceRemoveManyConfirm', { n: ids.length }, { default: '确认彻底删除选中的 {n} 条日志？日志将被永久删除，此操作不可恢复。' });
  if (!await confirmDanger(message, t('systemLog.forceRemoveTitle', '彻底删除日志'), t('systemLog.forceRemove', '彻底删除'))) return;
  if (!hasPermission(permission)) return;
  await runAndReload(() => operationLogApi.forceRemove(ids));
}
async function onClearForce() {
  const permission = 'system:operation-log:clear-force';
  if (!trashView.value || !hasPermission(permission)) return;
  const confirmed = await confirmDanger(
    t('systemLog.clearTrashConfirm', '确认清空回收站？回收站中的日志将被彻底删除，此操作不可恢复。'),
    t('systemLog.clearTrash', '清空回收站'),
    t('systemLog.clearTrash', '清空回收站')
  );
  if (!confirmed || !hasPermission(permission)) return;
  await runAndReload(() => operationLogApi.clearForce());
}
/** 执行彻底删除类请求；失败（403/422 等）时 http 层已提示，这里只负责清空选择并刷新列表。 */
async function runAndReload(action: () => Promise<unknown>) {
  try {
    await action();
  } catch {
    // 错误提示由 http 拦截器统一展示。
  } finally {
    resetSelection();
  }
  await loadData();
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
