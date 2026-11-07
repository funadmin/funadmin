<template>
  <div class="codegen-relations">
    <div class="codegen-relations__bar">
      <el-button size="small" plain type="primary" data-action="add-relation" @click="addRow">{{ t('business.codegen.edit.addRelation', '＋ 新增关联') }}</el-button>
    </div>
    <el-alert class="codegen-relations__note" type="info" :closable="false" :title="t('business.codegen.edit.relationsNote', 'hasOne、hasMany、belongsToMany 只影响正式生成的模型代码，动态发布与运行时不使用；belongsTo 与字段的「关联数据」控件是同一份配置。')" />
    <el-alert v-if="error" data-testid="relation-error" class="codegen-relations__note" type="warning" :closable="false" :title="t('business.codegen.edit.relationPending', { msg: error }, '关联尚未写入：{msg}')" />

    <div class="codegen-relations__scroll">
      <table class="codegen-relations__table">
        <thead>
          <tr>
            <th>{{ t('business.codegen.edit.relationType', '关联类型') }}</th>
            <th>{{ t('business.codegen.edit.relationName', '关联名称') }}</th>
            <th>{{ t('business.codegen.edit.relationTable', '关联表') }}</th>
            <th>{{ t('business.codegen.edit.relationLocal', '本表字段') }}</th>
            <th>{{ t('business.codegen.edit.relationForeign', '对方字段') }}</th>
            <th>{{ t('business.codegen.edit.relationLabel', '显示字段') }}</th>
            <th>{{ t('business.codegen.edit.relationPivot', '中间表') }}</th>
            <th>{{ t('business.codegen.edit.relationActions', '操作') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="row.key" :data-relation-row="row.name || `new-${index}`" :class="{ 'is-readonly': !row.editable }">
            <template v-if="!row.editable">
              <td data-col="type"><code>{{ row.type }}</code></td>
              <td data-col="name">{{ row.name }}</td>
              <td data-col="table">{{ row.table || '—' }}</td>
              <td data-col="localField">{{ row.localField }}</td>
              <td data-col="foreignField">{{ row.foreignField || '—' }}</td>
              <td>—</td>
              <td>—</td>
              <td><el-button link type="primary" data-action="relation-open-designer" @click="emit('open-designer')">{{ t('business.codegen.edit.subformInDesigner', '子表单，在设计器中编辑') }}</el-button></td>
            </template>
            <template v-else>
              <td data-col="type">
                <el-select size="small" :model-value="row.type" @update:model-value="(value: unknown) => changeType(row, value as RelationRow['type'])">
                  <el-option v-for="type in TYPES" :key="type" :label="type" :value="type" />
                </el-select>
              </td>
              <td data-col="name">
                <template v-if="row.origin === 'field'">{{ row.name || '—' }}</template>
                <el-input v-else size="small" :model-value="row.name" @update:model-value="(value: string) => update(row, { name: value })" />
              </td>
              <td data-col="table"><el-input size="small" :model-value="row.table" @update:model-value="(value: string) => update(row, { table: value })" /></td>
              <td data-col="localField">
                <el-select size="small" filterable :model-value="row.localField" @update:model-value="(value: unknown) => update(row, { localField: String(value) })">
                  <el-option v-for="field in localFieldOptions(row)" :key="field" :label="field" :value="field" />
                </el-select>
              </td>
              <td data-col="foreignField"><el-input size="small" :model-value="row.foreignField" @update:model-value="(value: string) => update(row, { foreignField: value })" /></td>
              <td data-col="labelField">
                <el-input v-if="row.origin === 'field'" size="small" :model-value="row.labelField" @update:model-value="(value: string) => update(row, { labelField: value })" />
                <span v-else>—</span>
              </td>
              <td data-col="pivot">
                <div v-if="row.type === 'belongsToMany'" class="codegen-relations__pivot">
                  <el-input size="small" data-pivot="table" :placeholder="t('business.codegen.edit.pivotTable', '中间表')" :model-value="row.pivot?.table" @update:model-value="(value: string) => updatePivot(row, { table: value })" />
                  <el-input size="small" data-pivot="localKey" :placeholder="t('business.codegen.edit.pivotLocalKey', '指向本表的列')" :model-value="row.pivot?.localKey" @update:model-value="(value: string) => updatePivot(row, { localKey: value })" />
                  <el-input size="small" data-pivot="foreignKey" :placeholder="t('business.codegen.edit.pivotForeignKey', '指向对方表的列')" :model-value="row.pivot?.foreignKey" @update:model-value="(value: string) => updatePivot(row, { foreignKey: value })" />
                </div>
                <span v-else>—</span>
              </td>
              <td><el-button link type="danger" data-action="remove-relation" @click="removeRow(index)">{{ t('business.codegen.edit.remove', '删除') }}</el-button></td>
            </template>
          </tr>
          <tr v-if="!rows.length"><td colspan="8" class="codegen-relations__empty">{{ t('business.codegen.edit.noRelations', '暂无关联') }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import { applyRelationRows, schemaToRelationRows, type RelationRow } from '../relationTable';
import { schemaFieldNodes } from '../schemaFields';

const props = defineProps<{ doc: FormSchemaDocument }>();
const emit = defineEmits<{ 'update:doc': [doc: FormSchemaDocument]; 'update:pending': [pending: boolean]; 'open-designer': [] }>();

const { t } = useI18n();
const TYPES: RelationRow['type'][] = ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany'];

// 表格行是本地草稿：只有全部行合法时才整体写回文档；未完成的行保留并提示。
// 每行带稳定 key，输入时不重建 DOM。
type DraftRow = RelationRow & { key: number };
let nextKey = 0;
const toDraft = (row: RelationRow): DraftRow => ({ ...structuredClone(row), key: ++nextKey });
const rows = ref<DraftRow[]>([]);
const error = ref('');
let emitted: FormSchemaDocument | null = null;
const resetRows = (doc: FormSchemaDocument) => {
  rows.value = schemaToRelationRows(doc).map(toDraft);
  error.value = '';
};
// 其他页签改动文档（如改表描述）时：没有未完成草稿才按文档重建；有草稿则保留，补全后写回当前文档。
// 从服务端重新加载/保存时宿主通过 key 重新挂载本组件。
watch(() => props.doc, (doc) => { if (doc !== emitted && !error.value) resetRows(doc); }, { immediate: true });
watch(error, (value) => emit('update:pending', Boolean(value)));

const fieldNames = computed(() => schemaFieldNodes(props.doc).map((node) => String(node.field)));
const localFieldOptions = (row: RelationRow): string[] => {
  if (row.origin === 'model') return ['id', ...fieldNames.value.filter((name) => name !== 'id')];
  const used = new Set(rows.value.filter((item) => item.origin === 'field' && item !== row).map((item) => item.localField));
  return fieldNames.value.filter((name) => !used.has(name));
};

const incomplete = (row: RelationRow): string => {
  if (!row.editable) return '';
  if (!row.localField) return t('business.codegen.edit.relationLocalRequired', '请选择本表字段');
  if (row.origin === 'model' && !row.name) return t('business.codegen.edit.relationNameRequired', '请填写关联名称');
  if (!row.table) return t('business.codegen.edit.relationTableRequired', '请填写关联表');
  return '';
};

const commit = () => {
  const missing = rows.value.map(incomplete).find(Boolean);
  if (missing) {
    error.value = missing;
    return;
  }
  try {
    const next = applyRelationRows(props.doc, rows.value.map(({ key: _key, ...row }) => row));
    emitted = next;
    error.value = '';
    emit('update:doc', next);
    // belongsTo 名称由字段推导：写回后同步派生名称，保留行对象与 key
    const derived = schemaToRelationRows(next).filter((row) => row.origin === 'field');
    for (const row of rows.value) {
      if (row.origin !== 'field') continue;
      const match = derived.find((item) => item.localField === row.localField);
      if (match) Object.assign(row, { name: match.name, path: match.path });
    }
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : String(reason);
  }
};

const update = (row: DraftRow, patch: Partial<RelationRow>) => {
  Object.assign(row, patch);
  commit();
};
const updatePivot = (row: DraftRow, patch: Partial<NonNullable<RelationRow['pivot']>>) => {
  row.pivot = { table: '', localKey: '', foreignKey: '', ...row.pivot, ...patch };
  commit();
};

const changeType = (row: DraftRow, type: RelationRow['type']) => {
  if (type === row.type) return;
  if (type === 'belongsTo') {
    Object.assign(row, { origin: 'field', type, name: '', localField: '', foreignField: row.foreignField || 'id', labelField: '' });
    Reflect.deleteProperty(row, 'pivot');
  } else {
    if (row.origin === 'field') Object.assign(row, { origin: 'model', name: '', localField: 'id', foreignField: '' });
    Reflect.deleteProperty(row, 'labelField');
    Reflect.deleteProperty(row, 'path');
    row.type = type;
    if (type === 'belongsToMany') row.pivot = { table: '', localKey: '', foreignKey: '', ...row.pivot };
    else Reflect.deleteProperty(row, 'pivot');
  }
  commit();
};

const addRow = () => {
  rows.value.push({ origin: 'model', type: 'hasOne', name: '', table: '', localField: 'id', foreignField: '', editable: true, key: ++nextKey });
  commit();
};
const removeRow = (index: number) => {
  rows.value.splice(index, 1);
  commit();
};
</script>

<style scoped>
.codegen-relations__bar {
  display: flex;
  gap: 8px;
}

.codegen-relations__note {
  margin-top: 8px;
}

.codegen-relations__scroll {
  margin-top: 10px;
  overflow-x: auto;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
}

.codegen-relations__table {
  width: 100%;
  min-width: 1080px;
  border-collapse: collapse;
  font-size: 12.5px;
}

.codegen-relations__table th,
.codegen-relations__table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  text-align: left;
  vertical-align: middle;
}

.codegen-relations__table th {
  background: var(--el-fill-color-light);
  color: var(--el-text-color-secondary);
  font-weight: 600;
  white-space: nowrap;
}

.codegen-relations__table td {
  color: var(--el-text-color-regular);
}

.codegen-relations__table tr.is-readonly td {
  background: var(--el-fill-color-lighter);
  color: var(--el-text-color-secondary);
}

.codegen-relations__pivot {
  display: grid;
  grid-template-columns: repeat(3, minmax(96px, 1fr));
  gap: 4px;
}

.codegen-relations__empty {
  padding: 24px;
  text-align: center;
  color: var(--el-text-color-secondary);
}
</style>
