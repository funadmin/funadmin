<template>
  <el-tabs v-model="tab" type="border-card">
    <el-tab-pane v-if="selectedMeta.kind !== 'layout'" label="列" name="column">
      <el-form label-width="90px" size="small" @change="emitUpdate">
        <el-form-item label="字段名">
          <el-input :model-value="field.field_name" @update:model-value="patch({ field_name: $event })" />
        </el-form-item>
        <el-form-item label="列类型">
          <el-select
            :model-value="field.column_type"
            :disabled="sourceType === 'adopted'"
            class="w-full"
            filterable
            allow-create
            default-first-option
            placeholder="请选择或输入列类型"
            @update:model-value="patch({ column_type: $event })"
          >
            <el-option v-for="item in COLUMN_TYPE_OPTIONS" :key="item" :label="item" :value="item" />
          </el-select>
        </el-form-item>
        <el-form-item label="可空">
          <el-switch :model-value="field.nullable === 1" :disabled="sourceType === 'adopted'" @update:model-value="patch({ nullable: $event ? 1 : 0 })" />
        </el-form-item>
        <el-form-item label="默认值">
          <el-input :model-value="field.default_value" :disabled="sourceType === 'adopted'" @update:model-value="patch({ default_value: $event })" />
        </el-form-item>
        <el-form-item label="无符号">
          <el-switch :model-value="field.unsigned === 1" :disabled="sourceType === 'adopted'" @update:model-value="patch({ unsigned: $event ? 1 : 0 })" />
        </el-form-item>
        <el-form-item label="索引">
          <el-select :model-value="field.index_type" :disabled="sourceType === 'adopted'" class="w-full" @update:model-value="patch({ index_type: $event })">
            <el-option label="无" value="none" />
            <el-option label="唯一" value="unique" />
            <el-option label="普通" value="index" />
          </el-select>
        </el-form-item>
        <el-form-item label="注释">
          <el-input :model-value="field.comment" @update:model-value="patch({ comment: $event })" />
        </el-form-item>
        <el-form-item label="关联类型">
          <el-select :model-value="field.relation_type" class="w-full" @update:model-value="patch({ relation_type: $event })">
            <el-option label="无" value="none" />
            <el-option label="belongs_to" value="belongs_to" />
            <el-option label="has_many" value="has_many" />
          </el-select>
        </el-form-item>
        <template v-if="field.relation_type !== 'none'">
          <el-form-item label="关联表">
            <el-select
              :model-value="field.relation_table"
              class="w-full"
              filterable
              allow-create
              default-first-option
              :loading="tablesLoading"
              placeholder="请选择或输入关联表"
              @visible-change="loadRelationTables"
              @update:model-value="onRelationTableChange"
            >
              <el-option v-for="table in relationTables" :key="table.name" :label="tableLabel(table)" :value="table.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="显示字段">
            <el-select
              :model-value="field.relation_label_field"
              class="w-full"
              filterable
              allow-create
              :loading="columnsLoading"
              placeholder="请选择显示字段"
              @visible-change="loadRelationColumns"
              @update:model-value="patch({ relation_label_field: $event })"
            >
              <el-option v-for="column in relationColumns" :key="column.name" :label="columnLabel(column)" :value="column.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="值字段">
            <el-select
              :model-value="field.relation_value_field"
              class="w-full"
              filterable
              allow-create
              :loading="columnsLoading"
              placeholder="请选择值字段"
              @visible-change="loadRelationColumns"
              @update:model-value="patch({ relation_value_field: $event })"
            >
              <el-option v-for="column in relationColumns" :key="column.name" :label="columnLabel(column)" :value="column.name" />
            </el-select>
          </el-form-item>
          <el-form-item v-if="field.relation_type === 'belongs_to'" label="删除规则">
            <el-select :model-value="field.relation_on_delete" class="w-full" @update:model-value="patch({ relation_on_delete: $event })">
              <el-option label="RESTRICT" value="restrict" />
              <el-option label="CASCADE" value="cascade" />
              <el-option label="SET NULL" value="set_null" />
            </el-select>
          </el-form-item>
        </template>
      </el-form>
    </el-tab-pane>
    <el-tab-pane label="表单" name="form">
      <el-form label-width="90px" size="small">
        <el-form-item label="控件类型">
          <el-select :model-value="field.type" class="w-full" filterable @update:model-value="onTypeChange">
            <el-option-group v-for="group in controlGroups" :key="group.label" :label="group.label">
              <el-option v-for="control in group.options" :key="control.type" :label="control.label" :value="control.type" />
            </el-option-group>
          </el-select>
        </el-form-item>
        <el-form-item label="显示名称">
          <el-input :model-value="field.label" @update:model-value="patch({ label: $event })" />
        </el-form-item>
        <el-form-item label="占位提示">
          <el-input :model-value="field.placeholder" @update:model-value="patch({ placeholder: $event })" />
        </el-form-item>
        <el-form-item label="分组">
          <el-input :model-value="field.form_group" @update:model-value="patch({ form_group: $event })" />
        </el-form-item>
        <el-form-item label="栅格 span">
          <el-input-number :model-value="field.form_span" :min="1" :max="24" class="w-full" @update:model-value="patch({ form_span: $event ?? 24 })" />
        </el-form-item>
        <el-form-item v-if="selectedMeta.kind !== 'layout'" label="必填">
          <el-switch :model-value="field.form_required === 1" @update:model-value="patch({ form_required: $event ? 1 : 0 })" />
        </el-form-item>
        <el-form-item label="显示">
          <el-switch :model-value="field.form_show === 1" @update:model-value="patch({ form_show: $event ? 1 : 0 })" />
        </el-form-item>
        <el-form-item v-if="selectedMeta.kind !== 'layout'" label="编辑禁改">
          <el-switch :model-value="field.form_readonly === 1" @update:model-value="patch({ form_readonly: $event ? 1 : 0 })" />
        </el-form-item>
      </el-form>
    </el-tab-pane>
    <el-tab-pane v-if="selectedMeta.kind !== 'layout'" label="列表" name="list">
      <el-form label-width="90px" size="small">
        <el-form-item label="显示">
          <el-switch :model-value="field.list_show === 1" @update:model-value="patch({ list_show: $event ? 1 : 0 })" />
        </el-form-item>
        <el-form-item label="可排序">
          <el-switch :model-value="field.list_sort === 1" @update:model-value="patch({ list_sort: $event ? 1 : 0 })" />
        </el-form-item>
        <el-form-item label="筛选">
          <el-select :model-value="field.list_filter" class="w-full" filterable @update:model-value="patch({ list_filter: $event })">
            <el-option v-for="item in LIST_FILTERS" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="格式化器">
          <el-select :model-value="field.list_formatter" class="w-full" filterable @update:model-value="patch({ list_formatter: $event })">
            <el-option v-for="item in LIST_FORMATTERS" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="列宽">
          <el-input-number :model-value="field.list_width" :min="0" :max="800" class="w-full" @update:model-value="patch({ list_width: $event ?? 0 })" />
        </el-form-item>
      </el-form>
    </el-tab-pane>
    <el-tab-pane label="属性" name="properties">
      <el-form label-width="100px" size="small">
        <el-alert v-if="!propertyFields.length" title="当前组件没有可配置的动态属性" type="info" :closable="false" class="mb-3" />
        <template v-for="property in propertyFields" :key="property.name">
          <el-form-item :label="property.title">
            <el-switch
              v-if="property.type === 'boolean'"
              :model-value="Boolean(propertyValue(property))"
              @update:model-value="updateProperty(property, $event)"
            />
            <el-input-number
              v-else-if="property.type === 'number'"
              :model-value="numberPropertyValue(property)"
              :min="property.minimum"
              :max="property.maximum"
              class="w-full"
              @update:model-value="updateProperty(property, $event)"
            />
            <el-select
              v-else-if="property.type === 'select'"
              :model-value="selectPropertyValue(property)"
              clearable
              class="w-full"
              @update:model-value="updateProperty(property, $event)"
            >
              <el-option v-for="option in property.options" :key="String(option)" :label="String(option)" :value="optionValue(option)" />
            </el-select>
            <el-input
              v-else
              :model-value="displayPropertyValue(property)"
              :type="property.type === 'json' ? 'textarea' : 'text'"
              :rows="property.type === 'json' ? 3 : undefined"
              clearable
              @update:model-value="updatePropertyInput(property, $event)"
            />
            <div v-if="property.description" class="text-xs text-[var(--el-text-color-secondary)]">{{ property.description }}</div>
          </el-form-item>
        </template>
      </el-form>
      <el-collapse>
        <el-collapse-item title="原始 JSON（高级）" name="raw-props">
          <el-form label-width="90px" size="small">
            <el-form-item label="props">
              <el-input :model-value="propsJson" type="textarea" :rows="6" placeholder='{"size":"large"}' @change="onPropsJson" />
            </el-form-item>
            <el-form-item label="选项来源">
              <el-input :model-value="optionsJson" type="textarea" :rows="6" placeholder='{"mode":"static"}' @change="onOptionsJson" />
            </el-form-item>
          </el-form>
        </el-collapse-item>
      </el-collapse>
    </el-tab-pane>
  </el-tabs>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { ElMessage } from 'element-plus';
import type { FormFieldDef } from '@/api/form';
import { businessDevelopmentApi, type BusinessDatabaseTable } from '@/api/development/business';
import { componentRegistry } from '../../schema/componentRegistry';
import { normalizePropertySchema, patchDynamicProperty, type DynamicPropertyField } from '../structuredEditor';
import { COLUMN_TYPE_OPTIONS, LIST_FILTERS, LIST_FORMATTERS, controlMeta, type ControlMeta } from '../../registry';

const props = defineProps<{ moduleId: number; field: FormFieldDef; sourceType: 'created' | 'adopted'; controls: ControlMeta[] }>();
const emit = defineEmits<{ (event: 'update', patch: Partial<FormFieldDef>): void }>();

interface RelationColumn {
  name: string;
  type: string;
  comment?: string;
  primary?: boolean;
}

const tab = ref('column');
const relationTables = ref<BusinessDatabaseTable[]>([]);
const relationColumns = ref<RelationColumn[]>([]);
const tablesLoading = ref(false);
const columnsLoading = ref(false);
const loadedRelationTable = ref('');
const patch = (value: Partial<FormFieldDef>) => emit('update', value);
const emitUpdate = () => undefined;

const selectedMeta = computed(() => controlMeta(props.field.type));
const componentDefinition = computed(() => componentRegistry.resolve(props.field.type));
const propertyFields = computed(() => normalizePropertySchema(componentDefinition.value?.propertySchema ?? {}));
const propertyValue = (property: DynamicPropertyField): unknown => props.field.control_props?.[property.name] ?? property.defaultValue;
const numberPropertyValue = (property: DynamicPropertyField): number | undefined => {
  const value = propertyValue(property);
  return typeof value === 'number' ? value : undefined;
};
const optionValue = (value: unknown): string | number | boolean | Record<string, unknown> => (
  typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean'
    ? value
    : value && typeof value === 'object' ? value as Record<string, unknown> : String(value ?? '')
);
const selectPropertyValue = (property: DynamicPropertyField) => optionValue(propertyValue(property));
const displayPropertyValue = (property: DynamicPropertyField): string => {
  const value = propertyValue(property);
  if (value === undefined || value === null) return '';
  return property.type === 'json' ? JSON.stringify(value, null, 2) : String(value);
};
const updateProperty = (property: DynamicPropertyField, value: unknown) => {
  patch({ control_props: patchDynamicProperty(props.field.control_props, property.name, value) });
};
const updatePropertyInput = (property: DynamicPropertyField, value: string) => {
  if (property.type !== 'json' || value.trim() === '') {
    updateProperty(property, value);
    return;
  }
  try {
    updateProperty(property, JSON.parse(value));
  } catch {
    ElMessage.warning(`${property.title} 需要合法 JSON`);
  }
};
const controlGroups = computed(() => {
  const labels = [...new Set(props.controls.map((control) => control.group))];
  return labels.map((label) => ({ label, options: props.controls.filter((control) => control.group === label) }));
});
const propsJson = computed(() => JSON.stringify(props.field.control_props ?? {}, null, 2));
const optionsJson = computed(() => JSON.stringify(props.field.options_source ?? {}, null, 2));
const tableLabel = (table: BusinessDatabaseTable) => table.comment ? `${table.name}（${table.comment}）` : table.name;
const columnLabel = (column: RelationColumn) => `${column.name}${column.comment ? `（${column.comment}）` : ''}${column.primary ? ' [主键]' : ''}`;
const loadRelationTables = async (visible: boolean) => {
  if (!visible || relationTables.value.length || tablesLoading.value) return;
  tablesLoading.value = true;
  try {
    relationTables.value = await businessDevelopmentApi.databaseTables('mysql');
  } finally {
    tablesLoading.value = false;
  }
};
const loadRelationColumns = async (visible = true, selectedTable?: string) => {
  const table = (selectedTable ?? props.field.relation_table).trim();
  if (!visible || !table || columnsLoading.value || loadedRelationTable.value === table) return;
  columnsLoading.value = true;
  try {
    const schema = await businessDevelopmentApi.databaseTableSchema('mysql', table);
    relationColumns.value = Array.isArray(schema.columns) ? schema.columns as RelationColumn[] : [];
    loadedRelationTable.value = table;
  } finally {
    columnsLoading.value = false;
  }
};
const onRelationTableChange = async (table: string) => {
  loadedRelationTable.value = '';
  relationColumns.value = [];
  patch({ relation_table: table, relation_label_field: '', relation_value_field: 'id' });
  await loadRelationColumns(true, table);
  const primary = relationColumns.value.find((column) => column.primary)?.name;
  const display = relationColumns.value.find((column) => ['name', 'title', 'label', 'username', 'nickname'].includes(column.name))?.name;
  patch({
    relation_value_field: primary || relationColumns.value.find((column) => column.name === 'id')?.name || 'id',
    relation_label_field: display || relationColumns.value.find((column) => !column.primary)?.name || ''
  });
};

const cloneConfig = (value: Record<string, unknown> | null) =>
  value === null ? null : JSON.parse(JSON.stringify(value)) as Record<string, unknown>;
const onTypeChange = (type: string) => {
  const meta = controlMeta(type);
  if (meta.kind === 'layout') tab.value = 'form';
  patch({
    type,
    column_type: meta.defaultColumnType,
    options_source: cloneConfig(meta.defaultOptions),
    control_props: cloneConfig(meta.defaultProps),
    list_show: meta.kind === 'layout' ? 0 : props.field.list_show,
    form_required: meta.kind === 'layout' ? 0 : props.field.form_required
  });
};

const onPropsJson = (value: string) => {
  try {
    patch({ control_props: value.trim() === '' ? null : JSON.parse(value) });
  } catch {
    ElMessage.warning('props JSON 不合法');
  }
};
const onOptionsJson = (value: string) => {
  try {
    patch({ options_source: value.trim() === '' ? null : JSON.parse(value) });
  } catch {
    ElMessage.warning('选项来源 JSON 不合法');
  }
};
</script>
