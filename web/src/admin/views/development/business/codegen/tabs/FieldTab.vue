<template>
  <div class="codegen-fields">
    <div class="codegen-fields__bar">
      <el-button size="small" plain type="primary" data-action="select-all-list" :disabled="!editableRows.length" @click="toggleAll('inList')">{{ t('business.codegen.edit.selectAllList', '全选列表') }}</el-button>
      <el-button size="small" plain type="primary" data-action="select-all-filter" :disabled="!editableRows.length" @click="toggleAll('filter')">{{ t('business.codegen.edit.selectAllFilter', '全选查询') }}</el-button>
      <el-button size="small" plain type="success" data-action="suggest-controls" :disabled="!editableRows.length" @click="openSuggestions">{{ t('business.codegen.edit.suggestControls', '按字段名推荐控件') }}</el-button>
      <span class="codegen-fields__spacer" />
      <el-button v-if="!hideDesignerLink" size="small" plain type="warning" data-action="open-designer" @click="emit('open-designer')">{{ t('business.codegen.edit.openDesigner', '打开表单设计器（复杂布局）') }}</el-button>
    </div>
    <p class="codegen-fields__tip">{{ t('business.codegen.edit.fieldsTip', '拖动左侧手柄只能在同一分组内调整顺序；跨分组移动、子表字段和复杂数据源请在表单设计器中编辑。') }}</p>

    <div class="codegen-fields__scroll">
      <table class="codegen-fields__table">
        <thead>
          <tr>
            <th class="is-handle">{{ t('business.codegen.edit.colSort', '排序') }}</th>
            <th>{{ t('business.codegen.edit.colField', '字段名称') }}</th>
            <th>{{ t('business.codegen.edit.colTitle', '字段描述') }}</th>
            <th>{{ t('business.codegen.edit.colColumnType', '物理类型') }}</th>
            <th class="is-check">{{ t('business.codegen.edit.colRequired', '必填') }}</th>
            <th class="is-check">{{ t('business.codegen.edit.colForm', '表单') }}</th>
            <th class="is-check">{{ t('business.codegen.edit.colList', '列表') }}</th>
            <th class="is-check">{{ t('business.codegen.edit.colFilter', '查询') }}</th>
            <th class="is-check">{{ t('business.codegen.edit.colSortable', '排序') }}</th>
            <th>{{ t('business.codegen.edit.colFilterMode', '查询方式') }}</th>
            <th>{{ t('business.codegen.edit.colControl', '页面控件') }}</th>
            <th>{{ t('business.codegen.edit.colDictionary', '数据字典 / 选项') }}</th>
            <th>{{ t('business.codegen.edit.colWidth', '列宽') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="row.path"
            :data-field-row="row.field"
            :class="{ 'is-container': row.container, 'is-dragging': dragPath === row.path }"
            @dragover="onDragOver($event, row)"
            @drop="onDrop($event, index)"
          >
            <td class="is-handle">
              <span
                v-if="!row.container"
                class="codegen-fields__handle"
                draggable="true"
                data-drag-handle
                :aria-label="t('business.codegen.edit.dragAria', { field: row.field }, '拖动排序 {field}')"
                @dragstart="onDragStart($event, row)"
                @dragend="dragPath = ''"
              >⠿</span>
            </td>
            <td data-col="field">
              <code>{{ row.field }}</code>
              <div v-if="row.groupTitle" class="codegen-fields__group">{{ row.groupTitle }}</div>
            </td>
            <template v-if="row.container">
              <td data-col="title">{{ row.title }}</td>
              <td colspan="11" class="codegen-fields__container">{{ t('business.codegen.edit.containerRow', '子表（在设计器中编辑）') }}</td>
            </template>
            <template v-else>
              <td data-col="title"><el-input size="small" :model-value="row.title" @update:model-value="(value: string) => patch(row, { title: value })" /></td>
              <td data-col="columnType" class="codegen-fields__type">{{ row.columnType || '—' }}</td>
              <td class="is-check"><el-checkbox data-col="required" :model-value="row.required" @update:model-value="(value: unknown) => patch(row, { required: Boolean(value) })" /></td>
              <td class="is-check"><el-checkbox data-col="inForm" :model-value="row.inForm" @update:model-value="(value: unknown) => patch(row, { inForm: Boolean(value) })" /></td>
              <td class="is-check"><el-checkbox data-col="inList" :model-value="row.inList" @update:model-value="(value: unknown) => patch(row, { inList: Boolean(value) })" /></td>
              <td class="is-check"><el-checkbox data-col="filterEnabled" :model-value="row.filter !== ''" @update:model-value="(value: unknown) => patch(row, { filter: value ? defaultFilterFor(row.columnType) : '' })" /></td>
              <td class="is-check"><el-checkbox data-col="sortable" :model-value="row.sortable" @update:model-value="(value: unknown) => patch(row, { sortable: Boolean(value) })" /></td>
              <td data-col="filter">
                <el-select size="small" :model-value="row.filter" :disabled="row.filter === ''" @update:model-value="(value: unknown) => patch(row, { filter: String(value ?? '') })">
                  <el-option v-for="item in filterOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
              </td>
              <td data-col="component">
                <el-select size="small" filterable :model-value="row.component" @update:model-value="(value: unknown) => changeControl(row, String(value))">
                  <el-option-group v-for="group in controlGroups(row.component)" :key="group.label" :label="group.label">
                    <el-option v-for="control in group.options" :key="control.type" :label="control.label" :value="control.type" />
                  </el-option-group>
                </el-select>
              </td>
              <td data-col="dictionary">
                <el-select
                  v-if="OPTION_CONTROLS.has(row.component)"
                  size="small"
                  filterable
                  clearable
                  :model-value="row.dictionary || undefined"
                  :loading="dictionaryLoading"
                  :placeholder="sourceLabel(row)"
                  @visible-change="loadDictionaries"
                  @update:model-value="(value: unknown) => patch(row, { dictionary: String(value ?? '') })"
                >
                  <el-option v-for="item in dictionaryOptions(row)" :key="item.code" :label="item.label" :value="item.code" />
                </el-select>
                <span v-else class="codegen-fields__source">{{ sourceLabel(row) }}</span>
              </td>
              <td data-col="listWidth">
                <el-input-number size="small" :model-value="row.listWidth" :min="0" :max="2000" :step="10" controls-position="right" @update:model-value="(value: number | null | undefined) => patch(row, { listWidth: Number(value ?? 0) })" />
              </td>
            </template>
          </tr>
          <tr v-if="!rows.length"><td colspan="13" class="codegen-fields__empty">{{ t('business.codegen.edit.noFields', '还没有字段，请在表单设计器中添加') }}</td></tr>
        </tbody>
      </table>
    </div>

    <el-dialog v-model="suggestVisible" :title="t('business.codegen.edit.suggestTitle', '按字段名推荐控件')" width="min(560px, 92vw)" append-to-body>
      <p class="codegen-fields__tip">{{ t('business.codegen.edit.suggestTip', '只修改勾选的字段；切换控件会重置该字段的选项来源与控件属性。') }}</p>
      <ul class="codegen-fields__suggestions">
        <li v-for="item in suggestions" :key="item.path" :data-suggestion="item.field">
          <el-checkbox v-model="item.checked">
            <code>{{ item.field }}</code>：{{ controlLabel(item.from) }} → <strong>{{ controlLabel(item.to) }}</strong>
          </el-checkbox>
        </li>
      </ul>
      <template #footer>
        <el-button @click="suggestVisible = false">{{ t('business.codegen.edit.cancel', '取消') }}</el-button>
        <el-button type="primary" data-action="apply-suggestions" :disabled="!suggestions.some((item) => item.checked)" @click="applySuggestions">{{ t('business.codegen.edit.applySuggestions', '应用所选') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { dictTypeApi, type DictType } from '@/api/system/dict';
import { CONTROL_REGISTRY, LIST_FILTERS, controlMeta, type ControlMeta } from '@/views/form/registry';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import { applyFieldPatches, dataSourceKind, moveFieldWithinParent, schemaToFieldRows, walkFieldNodes, type FieldRow, type FieldRowPatch } from '../fieldTable';
import { changeFieldControl, defaultFilterFor } from '../fieldControl';
import { suggestControl } from '../suggestControl';

// hideDesignerLink：从设计器内部打开时不再提供回到设计器的入口。
const props = defineProps<{ doc: FormSchemaDocument; adopted: boolean; hideDesignerLink?: boolean }>();
const emit = defineEmits<{ 'update:doc': [doc: FormSchemaDocument]; 'open-designer': [] }>();

const { t } = useI18n();

// 选项可直接切换为字典的控件；其他数据源（关联、部门、树形静态选项等）在设计器中编辑
const OPTION_CONTROLS = new Set(['select', 'selectV2', 'radio', 'checkbox', 'transfer', 'dictionary']);
const FIELD_CONTROLS = CONTROL_REGISTRY.filter((control) => control.kind === 'field' || control.kind === 'business');

const rows = computed(() => schemaToFieldRows(props.doc));
const editableRows = computed(() => rows.value.filter((row) => !row.container));
const filterOptions = LIST_FILTERS.filter((item) => item.value !== '');

const controlLabel = (type: string): string => controlMeta(type).type === type ? controlMeta(type).label : type;
const controlGroups = (current: string): Array<{ label: string; options: ControlMeta[] }> => {
  const controls = FIELD_CONTROLS.some((control) => control.type === current) ? FIELD_CONTROLS : [...FIELD_CONTROLS, { ...controlMeta(current), type: current, label: current }];
  return [...new Set(controls.map((control) => control.group))].map((label) => ({ label, options: controls.filter((control) => control.group === label) }));
};

// 只把用户改动的键写入补丁（不传整行），由 applyFieldPatches 做最小修改
const patch = (row: FieldRow, change: FieldRowPatch) => {
  const changed = Object.fromEntries(Object.entries(change).filter(([key, value]) => row[key as keyof FieldRow] !== value)) as FieldRowPatch;
  if (!Object.keys(changed).length) return;
  emit('update:doc', applyFieldPatches(props.doc, { [row.path]: changed }));
};

const changeControl = (row: FieldRow, type: string) => {
  if (type === row.component) return;
  emit('update:doc', changeFieldControl(props.doc, row.path, type, props.adopted));
};

/** 批量：若已全部勾选则全部取消，否则只勾选未勾选的行。 */
const toggleAll = (key: 'inList' | 'filter') => {
  const isOn = (row: FieldRow) => key === 'inList' ? row.inList : row.filter !== '';
  const enable = !editableRows.value.every(isOn);
  const patches = Object.fromEntries(editableRows.value
    .filter((row) => isOn(row) !== enable)
    .map((row): [string, FieldRowPatch] => [row.path, key === 'inList' ? { inList: enable } : { filter: enable ? defaultFilterFor(row.columnType) : '' }]));
  if (Object.keys(patches).length) emit('update:doc', applyFieldPatches(props.doc, patches));
};

// 拖拽排序：只允许同一父容器内移动
const dragPath = ref('');
const dragParent = computed(() => rows.value.find((row) => row.path === dragPath.value)?.parentPath ?? null);
const onDragStart = (event: DragEvent, row: FieldRow) => {
  dragPath.value = row.path;
  event.dataTransfer?.setData('text/plain', row.path);
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
};
const onDragOver = (event: DragEvent, row: FieldRow) => {
  if (dragPath.value && row.parentPath === dragParent.value) event.preventDefault();
};
const onDrop = (event: DragEvent, index: number) => {
  event.preventDefault();
  const path = dragPath.value;
  dragPath.value = '';
  if (!path) return;
  try {
    const next = moveFieldWithinParent(props.doc, path, index);
    if (next !== props.doc) emit('update:doc', next);
  } catch (error) {
    ElMessage.warning(error instanceof Error ? error.message : String(error));
  }
};

// 数据字典
const dictionaries = ref<DictType[]>([]);
const dictionaryLoading = ref(false);
const loadDictionaries = async (visible = true) => {
  if (!visible || dictionaries.value.length || dictionaryLoading.value) return;
  dictionaryLoading.value = true;
  try {
    dictionaries.value = (await dictTypeApi.list({ page: 1, pageSize: 500 })).list ?? [];
  } catch {
    dictionaries.value = [];
  } finally {
    dictionaryLoading.value = false;
  }
};
const dictionaryOptions = (row: FieldRow) => {
  const options = dictionaries.value.map((item) => ({ code: item.code, label: `${item.name}（${item.code}）` }));
  return row.dictionary && !options.some((item) => item.code === row.dictionary) ? [{ code: row.dictionary, label: row.dictionary }, ...options] : options;
};
const nodeSources = computed(() => new Map(walkFieldNodes(props.doc.nodes).map(({ node, path }) => [path, node.dataSource as Record<string, unknown> | null | undefined])));
const sourceLabel = (row: FieldRow): string => {
  const source = nodeSources.value.get(row.path);
  const kind = dataSourceKind(source);
  if (kind === 'dictionary') return t('business.codegen.edit.sourceDictionary', '选择字典');
  if (kind === 'static') return t('business.codegen.edit.sourceStatic', { n: Array.isArray(source?.options) ? source.options.length : 0 }, '静态选项（{n}）');
  if (kind === 'relation') return t('business.codegen.edit.sourceRelation', '关联数据（见关联配置）');
  if (kind) return t('business.codegen.edit.sourceOther', { kind }, '{kind}（在设计器中编辑）');
  return '—';
};

// 按字段名推荐控件：只应用用户勾选确认的行
const suggestVisible = ref(false);
const suggestions = ref<Array<{ path: string; field: string; from: string; to: string; checked: boolean }>>([]);
const openSuggestions = () => {
  suggestions.value = editableRows.value.flatMap((row) => {
    const to = suggestControl(row.field, row.columnType);
    return to && to !== row.component ? [{ path: row.path, field: row.field, from: row.component, to, checked: true }] : [];
  });
  if (!suggestions.value.length) {
    ElMessage.info(t('business.codegen.edit.noSuggestions', '没有可推荐的控件'));
    return;
  }
  suggestVisible.value = true;
};
const applySuggestions = () => {
  const next = suggestions.value.filter((item) => item.checked).reduce((doc, item) => changeFieldControl(doc, item.path, item.to, props.adopted), props.doc);
  suggestVisible.value = false;
  if (next !== props.doc) emit('update:doc', next);
};
</script>

<style scoped>
.codegen-fields__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.codegen-fields__spacer {
  flex: 1;
}

.codegen-fields__tip {
  margin: 8px 0;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.codegen-fields__scroll {
  overflow-x: auto;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
}

.codegen-fields__table {
  width: 100%;
  min-width: 1240px;
  border-collapse: collapse;
  font-size: 12.5px;
}

.codegen-fields__table th,
.codegen-fields__table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  text-align: left;
  vertical-align: middle;
  white-space: nowrap;
}

.codegen-fields__table th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--el-fill-color-light);
  color: var(--el-text-color-secondary);
  font-weight: 600;
}

.codegen-fields__table td {
  color: var(--el-text-color-regular);
}

.codegen-fields__table .is-check,
.codegen-fields__table .is-handle {
  width: 44px;
  text-align: center;
}

.codegen-fields__table tbody tr:hover {
  background: var(--el-fill-color-lighter);
}

.codegen-fields__table tr.is-dragging {
  opacity: 0.5;
}

.codegen-fields__table tr.is-container td {
  background: var(--el-fill-color-lighter);
  color: var(--el-text-color-secondary);
}

.codegen-fields__table [data-col='title'] {
  min-width: 140px;
}

.codegen-fields__table [data-col='filter'],
.codegen-fields__table [data-col='component'],
.codegen-fields__table [data-col='dictionary'] {
  min-width: 130px;
}

.codegen-fields__table [data-col='listWidth'] :deep(.el-input-number) {
  width: 96px;
}

.codegen-fields__handle {
  cursor: grab;
  color: var(--el-text-color-placeholder);
  user-select: none;
}

.codegen-fields__group {
  color: var(--el-text-color-secondary);
  font-size: 11.5px;
}

.codegen-fields__type {
  color: var(--el-text-color-secondary);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.codegen-fields__source,
.codegen-fields__container {
  color: var(--el-text-color-secondary);
}

.codegen-fields__empty {
  padding: 24px;
  text-align: center;
  color: var(--el-text-color-secondary);
}

.codegen-fields__suggestions {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
