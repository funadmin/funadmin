<template>
  <PageWrapper title="测试可视化">
<SchemaTablePage ref="buttonTable" class="min-w-0 flex-1" storage-key="generated-order-test" :schema="tableSchema" :query="query" :rows="list" :total="total" :loading="loading" :lock="buttonLock" :context="{ values: {}, permissions: buttonUser.permissions, handlers: {} }" @refresh="refreshButtonHost" @sort-change="({ prop, order }) => { query.sort = prop ?? ''; query.order = order === 'descending' ? 'desc' : 'asc'; loadData(); }" @selection-change="handleSelectionChange">      <template #search><SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset"><el-form-item label="单行输入"><el-input v-model="query.field1" placeholder="请输入单行输入" clearable /></el-form-item></SearchForm></template>
<template #toolbar><ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="toolbarButtonAllowed" :lock="buttonLock" :refresh="refreshButtonHost" :context="buttonContext('toolbar')" :context-version="buttonContextVersion" :permission-check="buttonPermission" :clear-selection="clearButtonSelection" :close="closeButtonHost" /><input ref="fileInput" class="hidden" type="file" accept=".csv,text/csv" @change="importCsv" /></template><template #id="scope"><span>{{ String(scope.row.id ?? '') }}</span></template><template #field1="scope"><span>{{ String(scope.row.field1 ?? '') }}</span></template><template #field7="scope"><span>{{ String(scope.row.field7 ?? '') }}</span></template><template #field2="scope"><span>{{ String(scope.row.field2 ?? '') }}</span></template><template #field3="scope"><span>{{ String(scope.row.field3 ?? '') }}</span></template><template #field4="scope"><span>{{ String(scope.row.field4 ?? '') }}</span></template><template #field5="scope"><span>{{ String(scope.row.field5 ?? '') }}</span></template><template #field6="scope"><span>{{ String(scope.row.field6 ?? '') }}</span></template><template #actions="scope"><ListButtonBar :buttons="rowButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="scope.row" :values="buttonValues(scope.row)" :fields="Object.keys(buttonFieldMap)" :lock="buttonLock" :refresh="refreshButtonHost" :context="buttonContext('row', scope.row)" :context-version="buttonContextVersion" :permission-check="buttonPermission" :clear-selection="clearButtonSelection" :close="closeButtonHost" link /></template></SchemaTablePage><OrderTestForm :lock="buttonLock" v-model="dialogVisible" :row="current" @success="loadData" /><OrderTestDetail v-model="drawerVisible" :row="current" />
  </PageWrapper>
</template>
<script setup lang="ts">
import { computed, ref, reactive, watch } from 'vue';
import { ElMessageBox } from 'element-plus';
import { useCrud } from '@/composables/useCrud';
import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';
import ListButtonBar from '@/views/form/components/ListButtonBar.vue';
import { resolveListButtons, buildListFieldMap } from '@/views/form/schema/listButtons';
import { provideListButtonAdapter, listButtonAdapterAllowed, listActionKey, type ListButtonHandlers } from '@/views/form/runtime/listButtonHost';
import type { ListButtonContext } from '@/views/form/runtime/listButtonExecutor';
import type { FormListConfiguration, FormListButton } from '@/views/form/schema/types';
import { useUserStore } from '@/store/modules/user';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import type { PageSchema } from '@/components/DataTable/pageSchema';
import { orderTestApi, type OrderTestModel, type OrderTestModelPayload, type OrderTestModelQuery } from '@/api/generated/order-test';
import OrderTestForm from './components/OrderTestForm.vue';
import OrderTestDetail from './components/OrderTestDetail.vue';
const { loading, list, total, query, loadData, onSearch, onReset, selection, onSelectionChange, onBatchDelete, current, dialogVisible, onAdd, onEdit, drawerVisible, onOpenDrawer } = useCrud<OrderTestModel, OrderTestModelQuery, OrderTestModel['id']>({ api: { list: orderTestApi.list, removeMany: orderTestApi.removeMany }, initialQuery: () => ({ page: 1, pageSize: 20, recycled: 0 }), rowKey: 'id', pagination: true });
const listConfig = {"category":{"enabled":false,"field":"field_2"},"leftTree":{"enabled":false,"mapping":{"labelField":"","targetField":"","valueField":""},"source":{"type":"current"}},"tree":{"enabled":false}} as FormListConfiguration;
const buttonUser = useUserStore();
const buttonLock = reactive({ busy: false });
const buttonAdapter = { api: orderTestApi, declaration: orderTestApi.listButtonAdapter };
provideListButtonAdapter(buttonAdapter);
const buttonFieldMap = buttonAdapter.declaration.fieldMap;
const buttonSelection = ref<OrderTestModel[]>([]);
const buttonContextVersion = ref(0);
const buttonTable = ref<{ clearSelection: () => void }>();
const clearButtonSelection = () => { buttonSelection.value = []; buttonTable.value?.clearSelection(); buttonContextVersion.value++; };
const buttonFilter = () => Object.fromEntries(([] as unknown[][]).filter((entry): entry is [string, string | number] => typeof entry[0] === 'string' && (typeof entry[1] === 'string' || typeof entry[1] === 'number') && entry[1] !== ''));
const buttonContext = (location: 'row' | 'toolbar', row?: Record<string, unknown>): ListButtonContext => ({ formKey: buttonAdapter.declaration.formKey, schemaHash: buttonAdapter.declaration.schemaHash, location, filter: buttonFilter(), ids: (row ? [row] : buttonSelection.value).map(record => record['id'] as string | number) });
const closeButtonHost = () => { dialogVisible.value = false; drawerVisible.value = false;  };
const buttonValues = (row: Record<string, unknown>) => Object.fromEntries(Object.entries(buttonFieldMap).map(([field, alias]) => [field, row[alias]]));
function resolveButtonRow(row?: Record<string, unknown>): OrderTestModel { const current = list.value.find(item => item.id === row?.['id']); if (!current) throw new Error('记录上下文已失效'); return current; }
const buttonHandlers: ListButtonHandlers = { refresh: () => loadData(), create: () => onAdd(), export: () => exportRows(), import: () => fileInput.value?.click(), batchDelete: async () => { await orderTestApi.removeMany(selectedIds()); await refreshButtonHost(); }, recycle: () => switchMode(!recycled.value), edit: row => onEdit(resolveButtonRow(row)), detail: row => onOpenDrawer(resolveButtonRow(row)), delete: row => removeRow(resolveButtonRow(row)), restore: row => row ? restoreRow(resolveButtonRow(row)) : restoreSelected(), destroy: row => row ? forceDeleteRow(resolveButtonRow(row)) : forceDeleteSelected() };
const buttonPermission = (code: string) => buttonUser.permissions.some(value => value === '*' || value === '*:*:*' || value === code);
const toolbarButtonAllowed = (button: FormListButton) => buttonAllowed(button, true) && (!['restore', 'destroy'].includes(listActionKey(button)) || buttonSelection.value.length > 0);
const buttonAllowed = (button: FormListButton, toolbar = false) => { const key = listActionKey(button); const suffix: Record<string, string> = { edit: 'update', refresh: 'list', recycle: 'list', batchDelete: 'batch-delete', destroy: 'destroy' }; if (button.permission && !buttonPermission(button.permission)) return false; if (['registered', 'navigate', 'external', 'copy', 'download'].includes(button.action.type)) return !recycled.value && listButtonAdapterAllowed(buttonAdapter, buttonPermission, buttonContext('toolbar'));  const permissionSuffix = toolbar && ['restore', 'destroy'].includes(key) ? 'batch-' + key : (suffix[key] ?? key); if (!buttonPermission("generated:order-test" + ':' + permissionSuffix)) return false; if (key === 'batchDelete' && !selection.value.length) return false; if (['restore', 'destroy'].includes(key)) return recycled.value; if (['create', 'edit', 'detail', 'delete', 'batchDelete'].includes(key)) return !recycled.value; return true; };
const toolbarButtons = computed(() => resolveListButtons(listConfig, 'toolbar', [{"id":"create","label":"新增","action":{"type":"builtin","key":"create"}},{"id":"export","label":"导出","action":{"type":"builtin","key":"export"}},{"id":"import","label":"导入","action":{"type":"builtin","key":"import"}},{"id":"batchdelete","label":"批量删除","action":{"type":"builtin","key":"batchDelete"},"interaction":{"type":"confirm","message":"确认批量删除？此操作可能不可恢复。"}},{"id":"recycle","label":"切换回收站","action":{"type":"builtin","key":"recycle"}},{"id":"restoreselected","label":"批量恢复","action":{"type":"builtin","key":"restore"}},{"id":"destroyselected","label":"批量永久删除","action":{"type":"builtin","key":"destroy"},"interaction":{"type":"confirm","message":"确认永久删除选中记录？此操作不可恢复。"}}] as FormListButton[]));
const rowButtons = computed(() => resolveListButtons(listConfig, 'row', [{"id":"edit","label":"编辑","action":{"type":"builtin","key":"edit"}},{"id":"detail","label":"详情","action":{"type":"builtin","key":"detail"}},{"id":"delete","label":"删除","action":{"type":"builtin","key":"delete"},"interaction":{"type":"confirm","message":"确认删除？此操作可能不可恢复。"}},{"id":"restore","label":"恢复","action":{"type":"builtin","key":"restore"}},{"id":"destroy","label":"永久删除","action":{"type":"builtin","key":"destroy"},"interaction":{"type":"confirm","message":"确认永久删除？此操作可能不可恢复。"}}] as FormListButton[]));
const hasRowButtons = computed(() => rowButtons.value.some(button => !button.hidden && buttonAllowed(button)));
const refreshButtonHost = async () => { clearButtonSelection(); await loadData(); };
watch(query, clearButtonSelection, { deep: true, flush: 'sync' });
watch(list, clearButtonSelection);
const handleSelectionChange = (rows: OrderTestModel[]) => { buttonSelection.value = rows; buttonContextVersion.value++; onSelectionChange(rows); };
watch(query, () => onSelectionChange([]), { deep: true, flush: 'sync' });
const tableSchema = computed<PageSchema>(() => ({ ...{"pageSchemaVersion":1,"key":"order_test","primaryKey":"id","search":[],"toolbar":[],"rowActions":[],"list":{"tools":{}},"pagination":{"pageSize":20,"pageSizes":[10,20,50,100],"enabled":true}}, columns: [...(toolbarButtons.value.some(button => button.action.type === 'registered') || true ? [{ key: 'selection', label: '', type: 'selection' as const, width: 48 }] : []), ...[{"key":"id","prop":"id","slot":"id","label":"ID","sortable":true},{"key":"field1","prop":"field1","slot":"field1","label":"单行输入","sortable":true},{"key":"field7","prop":"field7","slot":"field7","label":"多文件上传"},{"key":"field2","prop":"field2","slot":"field2","label":"提及输入"},{"key":"field3","prop":"field3","slot":"field3","label":"颜色"},{"key":"field4","prop":"field4","slot":"field4","label":"虚拟化选择"},{"key":"field5","prop":"field5","slot":"field5","label":"树形选择"},{"key":"field6","prop":"field6","slot":"field6","label":"复选框组"}], ...(hasRowButtons.value ? [{ key: 'actions', label: '操作', slot: 'actions' }] : [])] } as PageSchema));
const recycled = computed(() => query.recycled === 1);
const selectedIds = () => selection.value.map(row => row.id);
const fileInput = ref<HTMLInputElement>();
const csvColumns = [{"key":"field1","label":"单行输入"},{"key":"field7","label":"多文件上传"},{"key":"field2","label":"提及输入"},{"key":"field3","label":"颜色"},{"key":"field4","label":"虚拟化选择"},{"key":"field5","label":"树形选择"},{"key":"field6","label":"复选框组"}] as CsvColumn<OrderTestModelPayload>[];
function switchMode(value: boolean) { query.recycled = value ? 1 : 0; query.page = 1; void loadData(); }
async function removeRow(row: OrderTestModel) { await orderTestApi.remove(row.id); await loadData(); }
async function restoreRow(row: OrderTestModel) { await orderTestApi.restore(row.id); await loadData(); }
async function forceDeleteRow(row: OrderTestModel) { await orderTestApi.forceDelete(row.id); await loadData(); }
async function restoreSelected() { await orderTestApi.restoreMany(selectedIds()); await loadData(); }
async function forceDeleteSelected() { await orderTestApi.forceDeleteMany(selectedIds()); await loadData(); }
async function importCsv(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; input.value = ''; if (!file || buttonLock.busy || !buttonPermission("generated:order-test" + ':import')) return; buttonLock.busy = true; try { const version = buttonContextVersion.value; const rows = parseCsv<OrderTestModelPayload>(await readFileAsText(file), csvColumns); if (version !== buttonContextVersion.value || !buttonPermission("generated:order-test" + ':import')) return; await orderTestApi.importRows(rows); await loadData(); } finally { buttonLock.busy = false; } }
async function exportRows() { const rows = await orderTestApi.exportRows(query); downloadCsv('order-test-export', toCsv(rows, csvColumns as CsvColumn<OrderTestModel>[])); }
</script>
