<template>
  <div class="repeatable-field">
    <el-card v-for="(row, index) in rows" :key="rowKey(row, index)" shadow="never" class="repeatable-row">
      <template #header>
        <div class="repeatable-header">
          <span>{{ node.title }} {{ index + 1 }}</span>
          <el-button v-if="!disabled" link type="danger" @click="removeRow(index)">{{ t('common.remove', '删除') }}</el-button>
        </div>
      </template>
      <el-row v-if="childNodes.length" :gutter="16">
        <SchemaNodeRenderer
          v-for="child in childNodes"
          :key="child.id"
          :node="child"
          :values="row"
          :disabled="disabled"
          :id-prefix="`repeatable-${node.field ?? node.id}-${index}`"
          @change="(column, value) => updateCell(index, column, value)"
        />
      </el-row>
      <template v-else>
        <el-form-item v-for="column in columns" :key="column" :label="column">
          <el-input :model-value="inputValue(row[column])" :disabled="disabled || column === primaryKey" @update:model-value="updateCell(index, column, $event)" />
        </el-form-item>
      </template>
      <span v-if="row[primaryKey] != null" class="row-identity">{{ row[primaryKey] }}</span>
    </el-card>
    <el-button v-if="!disabled && canAdd" plain type="primary" @click="addRow">{{ t('formData.addRow', '新增一行') }}</el-button>
  </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormSchemaNode } from '../schema/types';

const SchemaNodeRenderer = defineAsyncComponent(() => import('./SchemaNodeRenderer.vue'));

const { t } = useI18n();

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  modelValue?: Array<Record<string, unknown>>;
  disabled?: boolean;
}>(), { modelValue: () => [], disabled: false });
const emit = defineEmits<{ 'update:modelValue': [value: Array<Record<string, unknown>>] }>();
const rows = computed(() => Array.isArray(props.modelValue) ? props.modelValue : []);
const controlProps = computed(() => props.node.props ?? {});
const relationValueField = computed(() => props.node.database?.relation?.valueField ?? '');
const primaryKey = computed(() => String(controlProps.value.primaryKey ?? 'id'));
const minRows = computed(() => Number(controlProps.value.minRows ?? 0));
const maxRows = computed(() => Number(controlProps.value.maxRows ?? 0));
const configuredColumns = computed(() => {
  const columns = controlProps.value.columns;
  return Array.isArray(columns) ? columns.map(String) : [];
});
const childNodes = computed(() => props.node.children ?? []);
const columns = computed(() => configuredColumns.value.length
  ? configuredColumns.value.filter((column) => column !== primaryKey.value && column !== relationValueField.value)
  : [...new Set(rows.value.flatMap((row) => Object.keys(row)))].filter((column) => column !== primaryKey.value && column !== relationValueField.value));
const canAdd = computed(() => maxRows.value < 1 || rows.value.length < maxRows.value);
const rowKey = (row: Record<string, unknown>, index: number) => String(row[primaryKey.value] ?? `new-${index}`);
const inputValue = (value: unknown): string | number => typeof value === 'string' || typeof value === 'number' ? value : String(value ?? '');
const addRow = () => {
  if (!canAdd.value) return;
  const fields = childNodes.value.flatMap(function collect(node): FormSchemaNode[] {
    return node.kind === 'field' && node.field ? [node] : (node.children ?? []).flatMap(collect);
  });
  const empty = fields.length
    ? Object.fromEntries(fields.map((node) => [String(node.field), node.defaultValue ?? null]))
    : Object.fromEntries(columns.value.map((column) => [column, '']));
  const next = [...rows.value, empty];
  emit('update:modelValue', next);
};
const removeRow = (index: number) => {
  if (rows.value.length <= minRows.value) return;
  const next = rows.value.filter((_, rowIndex) => rowIndex !== index);
  emit('update:modelValue', next);
};
const updateCell = (index: number, column: string, value: unknown) => {
  const next = rows.value.map((row, rowIndex) => rowIndex === index ? { ...row, [column]: value } : row);
  emit('update:modelValue', next);
};
</script>

<style scoped>
.repeatable-field { display: grid; gap: 12px; width: 100%; }
.repeatable-row { width: 100%; }
.repeatable-header { display: flex; align-items: center; justify-content: space-between; }
.row-identity { display: none; }
</style>
