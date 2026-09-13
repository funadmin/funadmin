<template>
  <PageWrapper title="测试可视化">
    <DataTableShell class="min-w-0 flex-1" storage-key="generated-order-test" :loading="loading" @refresh="loadData">
      <template #search><SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset"></SearchForm></template>
      <template #toolbar-left><el-button @click="switchMode(false)">正常列表</el-button><el-button @click="switchMode(true)">回收站</el-button><el-button v-if="!recycled" type="primary" @click="onAdd">新增</el-button><el-button v-if="!recycled" type="danger" :disabled="!selection.length" @click="onBatchDelete">批量删除</el-button><el-button v-if="recycled" type="success" :disabled="!selection.length" @click="restoreSelected">批量恢复</el-button><el-button v-if="recycled" type="danger" :disabled="!selection.length" @click="forceDeleteSelected">批量永久删除</el-button><el-button @click="fileInput?.click()">导入</el-button><input ref="fileInput" class="hidden" type="file" accept=".csv,text/csv" @change="importCsv" /><el-button @click="exportRows">导出</el-button></template>
      <template #default="{ size, stripe, border, headerCellStyle }"><el-table :data="list" row-key="id" :tree-props="{ children: '__listChildren' }" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" @selection-change="handleSelectionChange">
          <el-table-column type="selection" width="48" />
          <el-table-column prop="id" label="ID"><template #default="scope"><span>{{ String(scope.row.id ?? '') }}</span></template></el-table-column>
          <el-table-column prop="field1" label="单行输入"><template #default="scope"><span>{{ String(scope.row.field1 ?? '') }}</span></template></el-table-column>
          <el-table-column prop="field2" label="提及输入"><template #default="scope"><span>{{ String(scope.row.field2 ?? '') }}</span></template></el-table-column>
          <el-table-column prop="field3" label="颜色"><template #default="scope"><span>{{ String(scope.row.field3 ?? '') }}</span></template></el-table-column>
          <el-table-column prop="field4" label="虚拟化选择"><template #default="scope"><span>{{ String(scope.row.field4 ?? '') }}</span></template></el-table-column>
          <el-table-column prop="field5" label="树形选择"><template #default="scope"><span>{{ String(scope.row.field5 ?? '') }}</span></template></el-table-column>
          <el-table-column prop="field6" label="复选框组"><template #default="scope"><span>{{ String(scope.row.field6 ?? '') }}</span></template></el-table-column>
          <el-table-column label="操作"><template #default="scope"><el-button v-if="!recycled" link @click="onEdit(scope.row as OrderTestModel)">编辑</el-button><el-button v-if="!recycled" link @click="onOpenDrawer(scope.row as OrderTestModel)">详情</el-button><el-button v-if="!recycled" link type="danger" @click="removeRow(scope.row as OrderTestModel)">删除</el-button><el-button v-else link type="success" @click="restoreRow(scope.row as OrderTestModel)">恢复</el-button><el-button v-if="recycled" link type="danger" @click="forceDeleteRow(scope.row as OrderTestModel)">永久删除</el-button></template></el-table-column>
        </el-table><el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" /></template>
    </DataTableShell><OrderTestForm v-model="dialogVisible" :row="current" @success="loadData" /><OrderTestDetail v-model="drawerVisible" :row="current" />
  </PageWrapper>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { useCrud } from '@/composables/useCrud';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import { orderTestApi, type OrderTestModel, type OrderTestModelPayload, type OrderTestModelQuery } from '@/api/generated/order-test';
import OrderTestForm from './components/OrderTestForm.vue';
import OrderTestDetail from './components/OrderTestDetail.vue';
const { loading, list, total, query, loadData, onSearch, onReset, selection, onSelectionChange, onBatchDelete, current, dialogVisible, onAdd, onEdit, drawerVisible, onOpenDrawer } = useCrud<OrderTestModel, OrderTestModelQuery, OrderTestModel['id']>({ api: { list: orderTestApi.list, removeMany: orderTestApi.removeMany }, initialQuery: () => ({ page: 1, pageSize: 20, recycled: 0 }), rowKey: 'id', pagination: true });
const recycled = computed(() => query.recycled === 1);
const selectedIds = () => selection.value.map(row => row.id);
const handleSelectionChange = (rows: OrderTestModel[]) => onSelectionChange(rows);
const fileInput = ref<HTMLInputElement>();
const csvColumns = [{"key":"field1","label":"单行输入"},{"key":"field2","label":"提及输入"},{"key":"field3","label":"颜色"},{"key":"field4","label":"虚拟化选择"},{"key":"field5","label":"树形选择"},{"key":"field6","label":"复选框组"}] as CsvColumn<OrderTestModelPayload>[];
function switchMode(value: boolean) { query.recycled = value ? 1 : 0; query.page = 1; void loadData(); }
async function removeRow(row: OrderTestModel) { await ElMessageBox.confirm('确认删除该记录？', '删除确认', { type: 'warning' }); await orderTestApi.remove(row.id); await loadData(); }
async function restoreRow(row: OrderTestModel) { await orderTestApi.restore(row.id); await loadData(); }
async function forceDeleteRow(row: OrderTestModel) { await ElMessageBox.confirm('确认永久删除该记录？此操作不可恢复。', '永久删除确认', { type: 'error' }); await orderTestApi.forceDelete(row.id); await loadData(); }
async function restoreSelected() { await orderTestApi.restoreMany(selectedIds()); await loadData(); }
async function forceDeleteSelected() { await ElMessageBox.confirm('确认永久删除选中记录？此操作不可恢复。', '永久删除确认', { type: 'error' }); await orderTestApi.forceDeleteMany(selectedIds()); await loadData(); }
async function importCsv(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; input.value = ''; if (!file) return; const rows = parseCsv<OrderTestModelPayload>(await readFileAsText(file), csvColumns); await orderTestApi.importRows(rows); await loadData(); }
async function exportRows() { const rows = await orderTestApi.exportRows(query); downloadCsv('order-test-export', toCsv(rows, csvColumns as CsvColumn<OrderTestModel>[])); }
</script>
