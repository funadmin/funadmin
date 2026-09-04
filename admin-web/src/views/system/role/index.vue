<template>
  <PageWrapper title="角色管理" subtitle="维护角色及其权限">
    <DataTableShell
      storage-key="system-role"
      :loading="loading"
      :column-options="roleColumnOptions"
      @refresh="loadData"
    >
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
          <el-form-item label="角色名称" prop="name">
            <el-input v-model="query.name" placeholder="请输入角色名称" clearable />
          </el-form-item>
          <el-form-item label="标识" prop="code">
            <el-input v-model="query.code" placeholder="请输入标识" clearable />
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
        <el-button type="primary" plain v-perm="'system:role:add'" @click="onAdd">
          <i class="i-ep-plus" /> 新增
        </el-button>
        <el-button
          type="danger"
          plain
          :disabled="!selection.length"
          v-perm="'system:role:delete'"
          @click="onBatchDelete"
        >
          <i class="i-ep-delete" /> 批量删除{{ selection.length ? `(${selection.length})` : '' }}
        </el-button>
      </template>

      <template #default="{ size, stripe, border, headerCellStyle, columnKeys }">
        <el-table
          :data="displayList"
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
            v-if="columnKeys.includes('name')"
            prop="name"
            label="名称"
            min-width="160"
          />
          <el-table-column
            v-if="columnKeys.includes('code')"
            prop="code"
            label="标识"
            min-width="160"
          />
          <el-table-column
            v-if="columnKeys.includes('level')"
            prop="level"
            label="等级"
            width="90"
            align="center"
          />
          <el-table-column
            v-if="columnKeys.includes('dataScope')"
            label="数据范围"
            width="150"
          >
            <template #default="{ row }">{{ dataScopeText((row as RoleModel).dataScope) }}</template>
          </el-table-column>
          <el-table-column
            v-if="columnKeys.includes('remark')"
            prop="remark"
            label="备注"
            min-width="200"
            show-overflow-tooltip
          />
          <el-table-column
            v-if="columnKeys.includes('status')"
            label="状态"
            width="100"
            align="center"
          >
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                {{ row.status === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column
            v-if="columnKeys.includes('action')"
            label="操作"
            width="300"
            fixed="right"
            align="center"
          >
            <template #default="{ row }">
              <div class="app-table-actions app-table-actions--link">
                <el-button size="small" type="primary" link v-perm="'system:role:edit'" @click="onEdit(row as RoleModel)">
                  <i class="i-ep-edit" /> 编辑
                </el-button>
                <el-button size="small" type="primary" link v-perm="'system:role:perm'" @click="onOpenDrawer(row as RoleModel)">
                  <i class="i-ep-key" /> 权限
                </el-button>
                <el-button size="small" type="danger" link v-perm="'system:role:delete'" @click="onDelete(row as RoleModel)">
                  <i class="i-ep-delete" /> 删除
                </el-button>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </DataTableShell>

    <RoleFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
    <RolePermDrawer v-model="drawerVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { roleApi, type RoleModel } from '@/api/system/role';
import { DataTableShell, type DataTableColumnOption } from '@/components/DataTable';
import SearchForm from '@/components/SearchForm/index.vue';
import { useCrud } from '@/composables/useCrud';
import RoleFormDialog from './components/RoleFormDialog.vue';
import RolePermDrawer from './components/RolePermDrawer.vue';

defineOptions({ name: 'SystemRole' });

/** 列定义：操作列 alwaysVisible，避免被取消勾选导致行内动作不可达 */
const roleColumnOptions: DataTableColumnOption[] = [
  { key: 'id', label: 'ID' },
  { key: 'name', label: '名称' },
  { key: 'code', label: '标识' },
  { key: 'level', label: '等级' },
  { key: 'dataScope', label: '数据范围' },
  { key: 'remark', label: '备注' },
  { key: 'status', label: '状态' },
  { key: 'action', label: '操作', alwaysVisible: true }
];

interface RoleQuery {
  name?: string;
  code?: string;
  status?: 0 | 1;
}

const {
  loading,
  list,
  query,
  selection,
  dialogVisible,
  drawerVisible,
  current,
  loadData,
  onReset,
  onAdd,
  onEdit,
  onOpenDrawer,
  onDelete,
  onBatchDelete,
  onSelectionChange
} = useCrud<RoleModel, RoleQuery>({
  api: {
    // 角色为全量接口（数据量小），useCrud 自动兼容数组形态
    list: () => roleApi.all(),
    remove: (id) => roleApi.remove(id),
    removeMany: (ids) => roleApi.remove(ids)
  },
  initialQuery: () => ({}),
  pagination: false,
  deleteConfirm: (target) =>
    Array.isArray(target)
      ? `确认删除选中的 ${target.length} 个角色？此操作不可恢复`
      : `确认删除角色 ${(target as RoleModel).name} ?`
});

/** 全量接口 + 前端关键字过滤（数据量小，避免新增后端字段） */
const displayList = computed(() => {
  return list.value.filter((row) => {
    if (query.name && !row.name.includes(query.name)) return false;
    if (query.code && !(row.code || '').includes(query.code)) return false;
    if (query.status !== undefined && row.status !== query.status) return false;
    return true;
  });
});


function dataScopeText(scope: RoleModel['dataScope']) {
  return {
    all: '全部数据',
    dept_and_children: '本部门及下级',
    dept: '本部门',
    self: '仅本人',
    custom: '自定义部门'
  }[scope];
}

/** 客户端过滤：按下「查询」时无需重新请求，仅触发 computed 重新求值 */
function onSearch() {
  // no-op
}
</script>
