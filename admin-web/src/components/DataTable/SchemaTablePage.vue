<template>
  <div class="schema-table-page">
    <template v-if="definition.list?.leftTree?.enabled">
      <ListSourceTree v-if="sourceBinding?.formKey && sourceBinding?.schemaHash" :form-key="sourceBinding.formKey" :schema-hash="sourceBinding.schemaHash" :config="definition.list.leftTree" :list="definition.list" :permission-check="permitted" :can-read-form="permitted('console/form.data:lefttreeform')" :can-mutate="permitted('console/form.data:mutatelefttree')" :model-value="query.filters?.__leftTree ?? []" @change="onLeftTree" @mutated="emit('refresh')" />
      <div v-else role="alert">业务分类需要绑定已发布表单；未启用来源操作。</div>
    </template>
    <ListCategoryPanel v-else-if="category" :options="category.options ?? []" :model-value="query[category.field]" @change="onCategory" />
  <DataTableShell class="schema-table-main" :storage-key="storageKey || definition.key" :loading="loading" @refresh="emit('refresh')">
    <template #search>
      <SearchForm :model="query" :loading="loading" @search="emit('search')" @reset="emit('reset')">
        <el-form-item v-for="field in definition.search" :key="field.field" :label="field.label" :prop="field.field">
          <el-input v-if="field.type === 'input'" v-model="query[field.field]" :placeholder="field.placeholder" clearable />
          <el-select v-else v-model="query[field.field]" :placeholder="field.placeholder" clearable>
            <el-option v-for="option in field.options" :key="option.value" :label="option.label" :value="option.value" />
          </el-select>
        </el-form-item>
      </SearchForm>
    </template>
    <template #toolbar-left>
      <PageActions :actions="definition.toolbar" :context="context" @error="emit('actionError', $event)" />
      <slot name="toolbar-extra" />
    </template>
    <template #default="{ size, stripe, border, headerCellStyle }">
      <el-table :data="rows" v-loading="loading" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" @selection-change="emit('selectionChange', $event)">
        <template v-for="column in columns" :key="column.key">
          <el-table-column v-if="column.type === 'selection'" type="selection" :width="column.width" :align="column.align" />
          <el-table-column v-else :prop="column.prop" :label="column.label" :width="column.width" :min-width="column.minWidth" :align="column.align" :fixed="column.fixed">
            <template #default="{ row }">
              <PageActions v-if="column.slot === 'actions'" :actions="definition.rowActions" :context="{ ...context, row }" row @error="emit('actionError', $event)" />
              <slot v-else-if="column.slot" :name="column.slot" :row="row" />
              <template v-else>{{ format(column, row) }}</template>
            </template>
          </el-table-column>
        </template>
      </el-table>
      <div class="mt-4 flex justify-end">
        <el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" :page-sizes="definition.pagination.pageSizes" layout="total, sizes, prev, pager, next, jumper" @change="emit('refresh')" />
      </div>
    </template>
  </DataTableShell>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import DataTableShell from './DataTableShell.vue';
import SearchForm from '@/components/SearchForm/index.vue';
import PageActions from './PageActions.vue';
import ListCategoryPanel from '@/views/form/components/ListCategoryPanel.vue';
import ListSourceTree from '@/views/form/components/ListSourceTree.vue';
import type { FormRecordId } from '@/api/formData';
import { parsePageSchema, matchesPageCondition, type PageSchema, type PageContext, type PageColumn } from './pageSchema';
const props = defineProps<{ schema: PageSchema; sourceBinding?: { formKey: string; schemaHash: string }; query: Record<string, any>; rows: any[]; total: number; loading?: boolean; storageKey?: string; context: PageContext; formatters?: Record<string, (value: any, row: any) => unknown> }>();
const emit = defineEmits<{ search: []; reset: []; refresh: []; selectionChange: [rows: any[]]; actionError: [error: unknown] }>();
const definition = computed(() => parsePageSchema(props.schema));
const category = computed(() => definition.value.list?.category?.enabled ? definition.value.search.find(s => s.field === definition.value.list?.category?.field) : undefined);
const permitted = (code: string) => props.context.permissions.some(p => p === '*' || p === '*:*:*' || p === code);
function onCategory(value: string | number | undefined) {
  if (!category.value) return;
  props.query[category.value.field] = value;
  props.query.page = 1;
  emit('search');
}
function onLeftTree(ids: FormRecordId[]) {
  props.query.filters = { ...props.query.filters, __leftTree: ids };
  props.query.page = 1;
  emit('search');
}
const columns = computed(() => definition.value.columns.filter(c => matchesPageCondition(c.visibleWhen, props.context.values)));
function format(column: PageColumn, row: Record<string, unknown>) {
  const value = column.prop && Object.prototype.hasOwnProperty.call(row, column.prop) ? row[column.prop] : '';
  const formatter = column.formatter && props.formatters && Object.prototype.hasOwnProperty.call(props.formatters, column.formatter) ? props.formatters[column.formatter] : undefined;
  return formatter ? formatter(value, row) : value;
}
</script>
<style scoped>
.schema-table-page { display: flex; gap: 16px; align-items: flex-start; }
.schema-table-main { flex: 1; min-width: 0; }
@media (max-width: 768px) { .schema-table-page { flex-direction: column; } .schema-table-main { width: 100%; } }
</style>
