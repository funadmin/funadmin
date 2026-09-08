<template>
  <PageWrapper title="商品">
    <DataTableShell storage-key="generated-product" :loading="loading" @refresh="loadData">
      <template #search><SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset" /></template>
      <template #toolbar-left><el-button @click="switchMode(false)">正常列表</el-button><el-button @click="switchMode(true)">回收站</el-button><el-button v-if="!recycled" type="primary" @click="onAdd">新增</el-button><el-button v-if="!recycled" type="danger" :disabled="!selection.length" @click="onBatchDelete">批量删除</el-button><el-button v-if="recycled" type="success" :disabled="!selection.length" @click="restoreSelected">批量恢复</el-button><el-button v-if="recycled" type="danger" :disabled="!selection.length" @click="forceDeleteSelected">批量永久删除</el-button></template>
      <template #default="{ size, stripe, border, headerCellStyle }"><el-table :data="list" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" @selection-change="handleSelectionChange">
          <el-table-column type="selection" width="48" />
          <el-table-column prop="id" label="id"><template #default="scope"><span>{{ scope.row.id }}</span></template></el-table-column>
          <el-table-column prop="name" label="name"><template #default="scope"><span>{{ scope.row.name }}</span></template></el-table-column>
          <el-table-column prop="price" label="price"><template #default="scope"><span>{{ scope.row.price }}</span></template></el-table-column>
          <el-table-column prop="status" label="status"><template #default="scope"><span>{{ scope.row.status }}</span></template></el-table-column>
          <el-table-column prop="createdAt" label="created_at"><template #default="scope"><span>{{ scope.row.createdAt }}</span></template></el-table-column>
          <el-table-column label="状态操作"><template #default="scope"><el-switch :model-value="Number(scope.row.status) === 1" :disabled="recycled" @change="value => changeStatus(scope.row as ProductModel, value === true)" /></template></el-table-column>
          <el-table-column label="操作"><template #default="scope"><el-button v-if="!recycled" link @click="onEdit(scope.row as ProductModel)">编辑</el-button><el-button v-if="!recycled" link @click="onOpenDrawer(scope.row as ProductModel)">详情</el-button><el-button v-if="!recycled" link type="danger" @click="removeRow(scope.row as ProductModel)">删除</el-button><el-button v-else link type="success" @click="restoreRow(scope.row as ProductModel)">恢复</el-button><el-button v-if="recycled" link type="danger" @click="forceDeleteRow(scope.row as ProductModel)">永久删除</el-button></template></el-table-column>
        </el-table><el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" @change="loadData" /></template>
    </DataTableShell><ProductForm v-model="dialogVisible" :row="current" @success="loadData" /><ProductDetail v-model="drawerVisible" :row="current" />
  </PageWrapper>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { ElMessageBox } from 'element-plus';
import { useCrud } from '@/composables/useCrud';
import { productApi, type ProductModel, type ProductModelPayload, type ProductModelQuery } from './api';
import ProductForm from './components/ProductForm.vue';
import ProductDetail from './components/ProductDetail.vue';
const { loading, list, total, query, loadData, onSearch, onReset, selection, onSelectionChange, onBatchDelete, current, dialogVisible, onAdd, onEdit, drawerVisible, onOpenDrawer } = useCrud<ProductModel, ProductModelQuery, ProductModel['id']>({ api: { list: productApi.list, removeMany: productApi.removeMany }, initialQuery: () => ({ page: 1, pageSize: 20, recycled: 0 }), rowKey: 'id', pagination: true });
const recycled = computed(() => query.recycled === 1);
const selectedIds = () => selection.value.map(row => row.id);
const handleSelectionChange = (rows: unknown[]) => onSelectionChange(rows as ProductModel[]);
function switchMode(value: boolean) { query.recycled = value ? 1 : 0; query.page = 1; void loadData(); }
async function removeRow(row: ProductModel) { await ElMessageBox.confirm('确认删除该记录？', '删除确认', { type: 'warning' }); await productApi.remove(row.id); await loadData(); }
async function restoreRow(row: ProductModel) { await productApi.restore(row.id); await loadData(); }
async function forceDeleteRow(row: ProductModel) { await ElMessageBox.confirm('确认永久删除该记录？此操作不可恢复。', '永久删除确认', { type: 'error' }); await productApi.forceDelete(row.id); await loadData(); }
async function restoreSelected() { await productApi.restoreMany(selectedIds()); await loadData(); }
async function forceDeleteSelected() { await ElMessageBox.confirm('确认永久删除选中记录？此操作不可恢复。', '永久删除确认', { type: 'error' }); await productApi.forceDeleteMany(selectedIds()); await loadData(); }
async function changeStatus(row: ProductModel, enabled: boolean) { await productApi.status(row.id, enabled ? 1 : 0); await loadData(); }
</script>
