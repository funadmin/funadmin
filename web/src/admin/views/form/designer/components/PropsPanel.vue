<template>
  <div class="props-panel">
    <header class="props-panel__header">
      <div class="props-panel__title">
        <b data-props-title :title="field.label">{{ field.label || t('formDesigner.unnamed', '未命名') }}</b>
        <span class="props-panel__chip" data-props-chip>{{ selectedMeta.label }}</span>
      </div>
      <span class="props-panel__meta" data-props-meta>{{ headerMeta }}</span>
    </header>
    <el-tabs v-model="tab" class="props-panel__tabs">
      <el-tab-pane :label="t('formDesigner.tabForm', '表单')" name="form" data-props-tab="form">
        <el-form label-position="top" size="small" class="props-form">
          <section class="props-section" data-props-section="basic">
            <h4 class="props-section__title" data-props-section-title>{{ t('formDesigner.propsPanel.sectionBasic', '基础') }}</h4>
            <el-form-item data-prop="label" :label="t('formDesigner.displayName', '显示名称')">
              <el-input :model-value="field.label" @update:model-value="patch({ label: $event })" />
            </el-form-item>
            <div class="props-two" :class="{ 'is-single': isLayout }">
              <el-form-item data-prop="type" :label="t('formDesigner.controlType', '控件类型')">
                <el-select :model-value="field.type" class="w-full" filterable :disabled="isLayout" @update:model-value="onTypeChange">
                  <el-option-group v-for="group in controlGroups" :key="group.label" :label="group.label">
                    <el-option v-for="control in group.options" :key="control.type" :label="control.label" :value="control.type" />
                  </el-option-group>
                </el-select>
              </el-form-item>
              <el-form-item v-if="!isLayout" data-prop="form_group" :label="t('formDesigner.formGroup', '分组')">
                <el-input :model-value="field.form_group" :placeholder="t('formDesigner.none', '无')" @update:model-value="patch({ form_group: $event })" />
              </el-form-item>
            </div>
            <el-form-item v-if="!isLayout" data-prop="placeholder" :label="t('formDesigner.placeholderLabel', '占位提示')">
              <el-input :model-value="field.placeholder" @update:model-value="patch({ placeholder: $event })" />
            </el-form-item>
          </section>
          <section class="props-section" data-props-section="layout">
            <h4 class="props-section__title" data-props-section-title>{{ t('formDesigner.propsPanel.sectionLayout', '布局') }}</h4>
            <el-form-item data-prop="form_span" :label="t('formDesigner.propsPanel.width', '宽度')">
              <div class="props-seg" role="radiogroup" :aria-label="t('formDesigner.propsPanel.width', '宽度')">
                <button
                  v-for="option in spanOptions"
                  :key="option.value"
                  type="button"
                  role="radio"
                  class="props-seg__item"
                  :class="{ 'is-on': option.active }"
                  :aria-checked="option.active ? 'true' : 'false'"
                  :data-span-option="option.value"
                  @click="onSpanOption(option.value)"
                >{{ option.label }}</button>
              </div>
              <span class="props-hint" data-span-hint>{{ spanHint }}</span>
              <el-input-number
                v-if="customSpanOpen"
                data-span-custom-input
                :model-value="field.form_span"
                :min="1"
                :max="24"
                class="w-full props-seg__custom"
                @update:model-value="patch({ form_span: $event ?? 24 })"
              />
            </el-form-item>
          </section>
          <section class="props-section" data-props-section="state">
            <h4 class="props-section__title" data-props-section-title>{{ t('formDesigner.propsPanel.sectionState', '状态') }}</h4>
            <div class="props-toggles" data-props-toggles="form">
              <PropsToggle v-if="!isLayout" data-toggle="form_required" :label="t('formDesigner.required', '必填')" :model-value="field.form_required === 1" @update:model-value="patch({ form_required: $event ? 1 : 0 })" />
              <PropsToggle data-toggle="form_show" :label="t('formDesigner.show', '显示')" :model-value="field.form_show === 1" @update:model-value="patch({ form_show: $event ? 1 : 0 })" />
              <PropsToggle v-if="!isLayout" data-toggle="form_readonly" :label="t('formDesigner.propsPanel.readonly', '只读')" :model-value="field.form_readonly === 1" @update:model-value="patch({ form_readonly: $event ? 1 : 0 })" />
            </div>
          </section>
        </el-form>
        <PropsFieldFolds :field="field" :layout="isLayout" @update="patch" />
      </el-tab-pane>
      <el-tab-pane v-if="!isLayout" :label="t('formDesigner.tabList', '列表')" name="list" data-props-tab="list">
        <el-form label-position="top" size="small" class="props-form">
          <div class="props-toggles props-form__block" data-props-toggles="list">
            <PropsToggle data-toggle="list_show" :label="t('formDesigner.show', '显示')" :model-value="field.list_show === 1" @update:model-value="patch({ list_show: $event ? 1 : 0 })" />
            <PropsToggle data-toggle="list_sort" :label="t('formDesigner.sortable', '可排序')" :model-value="field.list_sort === 1" @update:model-value="patch({ list_sort: $event ? 1 : 0 })" />
          </div>
          <el-form-item data-prop="list_filter" :label="t('formDesigner.filter', '筛选')">
            <el-select :model-value="field.list_filter" class="w-full" filterable @update:model-value="patch({ list_filter: $event })">
              <el-option v-for="item in LIST_FILTERS" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item data-prop="list_formatter" :label="t('formDesigner.formatter', '格式化器')">
            <el-select :model-value="field.list_formatter" class="w-full" filterable @update:model-value="patch({ list_formatter: $event })">
              <el-option v-for="item in LIST_FORMATTERS" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item data-prop="list_width" :label="t('formDesigner.columnWidth', '列宽')">
            <el-input-number :model-value="field.list_width" :min="0" :max="800" class="w-full" @update:model-value="patch({ list_width: $event ?? 0 })" />
          </el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane v-if="!isLayout" :label="t('formDesigner.layout.tabDatabaseColumn', '数据库列')" name="column" data-props-tab="column">
        <el-form label-position="top" size="small" class="props-form" @change="emitUpdate">
          <el-form-item data-prop="field_name" :label="t('formDesigner.fieldName', '字段名')">
            <el-input :model-value="field.field_name" @update:model-value="patch({ field_name: $event })" />
          </el-form-item>
          <el-form-item data-prop="column_type" :label="t('formDesigner.columnType', '列类型')">
            <el-select
              :model-value="field.column_type"
              :disabled="sourceType === 'adopted'"
              class="w-full"
              filterable
              allow-create
              default-first-option
              :placeholder="t('formDesigner.columnTypePlaceholder', '请选择或输入列类型')"
              @update:model-value="patch({ column_type: $event })"
            >
              <el-option v-for="item in COLUMN_TYPE_OPTIONS" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
          <div class="props-toggles props-form__block" data-props-toggles="column">
            <PropsToggle data-toggle="nullable" :label="t('formDesigner.nullable', '可空')" :model-value="field.nullable === 1" :disabled="sourceType === 'adopted'" @update:model-value="patch({ nullable: $event ? 1 : 0 })" />
            <PropsToggle data-toggle="unsigned" :label="t('formDesigner.unsigned', '无符号')" :model-value="field.unsigned === 1" :disabled="sourceType === 'adopted'" @update:model-value="patch({ unsigned: $event ? 1 : 0 })" />
          </div>
          <div class="props-two">
            <el-form-item data-prop="default_value" :label="t('formDesigner.defaultValue', '默认值')">
              <el-input :model-value="field.default_value" :disabled="sourceType === 'adopted'" @update:model-value="patch({ default_value: $event })" />
            </el-form-item>
            <el-form-item data-prop="index_type" :label="t('formDesigner.index', '索引')">
              <el-select :model-value="field.index_type" :disabled="sourceType === 'adopted'" class="w-full" @update:model-value="patch({ index_type: $event })">
                <el-option :label="t('formDesigner.none', '无')" value="none" />
                <el-option :label="t('formDesigner.unique', '唯一')" value="unique" />
                <el-option :label="t('formDesigner.normal', '普通')" value="index" />
              </el-select>
            </el-form-item>
          </div>
          <el-form-item data-prop="comment" :label="t('formDesigner.comment', '注释')">
            <el-input :model-value="field.comment" @update:model-value="patch({ comment: $event })" />
          </el-form-item>
          <el-form-item data-prop="relation_type" :label="t('formDesigner.relationType', '关联类型')">
            <el-select :model-value="field.relation_type" class="w-full" @update:model-value="patch({ relation_type: $event })">
              <el-option :label="t('formDesigner.none', '无')" value="none" />
              <el-option label="belongs_to" value="belongs_to" />
              <el-option label="has_many" value="has_many" />
            </el-select>
          </el-form-item>
          <template v-if="field.relation_type !== 'none'">
            <el-form-item data-prop="relation_table" :label="t('formDesigner.relationTable', '关联表')">
              <el-select
                :model-value="field.relation_table"
                class="w-full"
                filterable
                allow-create
                default-first-option
                :loading="tablesLoading"
                :placeholder="t('formDesigner.relationTablePlaceholder', '请选择或输入关联表')"
                @visible-change="loadRelationTables"
                @update:model-value="onRelationTableChange"
              >
                <el-option v-for="table in relationTables" :key="table.name" :label="tableLabel(table)" :value="table.name" />
              </el-select>
            </el-form-item>
            <el-form-item data-prop="relation_label_field" :label="t('formDesigner.relationLabelField', '显示字段')">
              <el-select
                :model-value="field.relation_label_field"
                class="w-full"
                filterable
                allow-create
                :loading="columnsLoading"
                :placeholder="t('formDesigner.relationLabelFieldPlaceholder', '请选择显示字段')"
                @visible-change="loadRelationColumns"
                @update:model-value="patch({ relation_label_field: $event })"
              >
                <el-option v-for="column in relationColumns" :key="column.name" :label="columnLabel(column)" :value="column.name" />
              </el-select>
            </el-form-item>
            <el-form-item data-prop="relation_value_field" :label="t('formDesigner.relationValueField', '值字段')">
              <el-select
                :model-value="field.relation_value_field"
                class="w-full"
                filterable
                allow-create
                :loading="columnsLoading"
                :placeholder="t('formDesigner.relationValueFieldPlaceholder', '请选择值字段')"
                @visible-change="loadRelationColumns"
                @update:model-value="patch({ relation_value_field: $event })"
              >
                <el-option v-for="column in relationColumns" :key="column.name" :label="columnLabel(column)" :value="column.name" />
              </el-select>
            </el-form-item>
            <el-form-item v-if="field.relation_type === 'belongs_to'" data-prop="relation_on_delete" :label="t('formDesigner.relationOnDelete', '删除规则')">
              <el-select :model-value="field.relation_on_delete" class="w-full" @update:model-value="patch({ relation_on_delete: $event })">
                <el-option label="RESTRICT" value="restrict" />
                <el-option label="CASCADE" value="cascade" />
                <el-option label="SET NULL" value="set_null" />
              </el-select>
            </el-form-item>
          </template>
        </el-form>
      </el-tab-pane>
      <el-tab-pane :label="t('formDesigner.tabProperties', '属性')" name="properties" data-props-tab="properties">
        <el-form label-position="top" size="small" class="props-form">
          <el-form-item data-prop="form_span" :label="t('formDesigner.propsPanel.spanExact', '栅格列数（1–24）')">
            <el-input-number :model-value="field.form_span" :min="1" :max="24" class="w-full" @update:model-value="patch({ form_span: $event ?? 24 })" />
          </el-form-item>
          <el-alert v-if="!propertyFields.length" :title="t('formDesigner.noDynamicProps', '当前组件没有可配置的动态属性')" type="info" :closable="false" class="mb-3" />
          <template v-for="property in propertyFields" :key="property.name">
            <el-form-item :data-property="property.name" :label="property.title">
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
              <div v-if="property.description" class="props-hint">{{ property.description }}</div>
            </el-form-item>
          </template>
        </el-form>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import type { FormFieldDef } from '@/api/form';
import { businessDevelopmentApi, type BusinessDatabaseTable } from '@/api/development/business';
import { componentRegistry } from '../../schema/componentRegistry';
import { normalizePropertySchema, patchDynamicProperty, type DynamicPropertyField } from '../structuredEditor';
import { COLUMN_TYPE_OPTIONS, LIST_FILTERS, LIST_FORMATTERS, controlMeta, type ControlMeta } from '../../registry';
import { NODE_FIELD_PREFIX } from '../../composables/useDesigner';
import PropsFieldFolds from './PropsFieldFolds.vue';
import PropsToggle from './PropsToggle.vue';

const { t } = useI18n();

const props = defineProps<{ moduleId: number; field: FormFieldDef; sourceType: 'created' | 'adopted'; controls: ControlMeta[]; groupTitle?: string }>();
const emit = defineEmits<{ (event: 'update', patch: Partial<FormFieldDef>): void }>();

interface RelationColumn {
  name: string;
  type: string;
  comment?: string;
  primary?: boolean;
}

const tab = ref('form');
const relationTables = ref<BusinessDatabaseTable[]>([]);
const relationColumns = ref<RelationColumn[]>([]);
const tablesLoading = ref(false);
const columnsLoading = ref(false);
const loadedRelationTable = ref('');
const patch = (value: Partial<FormFieldDef>) => emit('update', value);
const emitUpdate = () => undefined;

const selectedMeta = computed(() => controlMeta(props.field.type));
const isLayout = computed(() => selectedMeta.value.kind === 'layout');
// 标题区：字段名（布局节点显示节点 ID）· 列类型（字段）· 位于「父容器」/根节点；控件类型单独显示为标签。
const headerMeta = computed(() => {
  const key = props.field.field_name.startsWith(NODE_FIELD_PREFIX) ? props.field.field_name.slice(NODE_FIELD_PREFIX.length) : props.field.field_name;
  const located = props.groupTitle
    ? t('formDesigner.layout.propsLocated', { group: props.groupTitle }, '位于「{group}」')
    : t('formDesigner.propsPanel.locatedRoot', '位于根节点');
  return [key, isLayout.value ? '' : props.field.column_type, located].filter(Boolean).join(' · ');
});

// 宽度分段：只映射 6/8/12/24 四个常用值；其他列数原样保留，显示「自定义 N 列」，不做任何改写。
const SPAN_PRESETS = [6, 8, 12, 24] as const;
const customSpanOpen = ref(false);
const isPresetSpan = computed(() => (SPAN_PRESETS as readonly number[]).includes(props.field.form_span));
const spanOptions = computed(() => {
  const labels: Record<number, string> = {
    6: t('formDesigner.propsPanel.spanQuarter', '1/4'),
    8: t('formDesigner.propsPanel.spanThird', '1/3'),
    12: t('formDesigner.propsPanel.spanHalf', '1/2'),
    24: t('formDesigner.propsPanel.spanFull', '整行')
  };
  return [
    ...SPAN_PRESETS.map((span) => ({ value: String(span), label: labels[span], active: !customSpanOpen.value && props.field.form_span === span })),
    { value: 'custom', label: t('formDesigner.propsPanel.spanCustom', '自定义'), active: customSpanOpen.value || !isPresetSpan.value }
  ];
});
const spanHint = computed(() => (isPresetSpan.value
  ? t('formDesigner.propsPanel.spanHint', '对应栅格 24 列中的 6 / 8 / 12 / 24')
  : t('formDesigner.propsPanel.spanCustomValue', { n: props.field.form_span }, '自定义 {n} 列')));
const onSpanOption = (value: string) => {
  if (value === 'custom') {
    customSpanOpen.value = !customSpanOpen.value;
    return;
  }
  customSpanOpen.value = false;
  patch({ form_span: Number(value) });
};
watch(() => props.field.field_name, () => { customSpanOpen.value = false; });
const componentDefinition = computed(() => componentRegistry.resolve(props.field.type));
// 布局节点无“数据库列/列表”页签：选中容器时回落到可见页签，避免内容区空白。
watch(selectedMeta, (meta) => {
  if (meta.kind === 'layout' && (tab.value === 'column' || tab.value === 'list')) tab.value = 'form';
}, { immediate: true });
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
    ElMessage.warning(t('formDesigner.invalidJsonProperty', { title: property.title }, '{title} 需要合法 JSON'));
  }
};
const controlGroups = computed(() => {
  const labels = [...new Set(props.controls.map((control) => control.group))];
  return labels.map((label) => ({ label, options: props.controls.filter((control) => control.group === label) }));
});
const tableLabel = (table: BusinessDatabaseTable) => table.comment ? `${table.name}（${table.comment}）` : table.name;
const columnLabel = (column: RelationColumn) => `${column.name}${column.comment ? `（${column.comment}）` : ''}${column.primary ? t('formDesigner.primaryKeyTag', ' [主键]') : ''}`;
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

</script>

<style scoped>
.props-panel {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
}
.props-panel__header {
  display: grid;
  gap: 3px;
  min-width: 0;
  padding: 12px 14px 6px;
}
.props-panel__title {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.props-panel__title b {
  min-width: 0;
  overflow: hidden;
  color: var(--el-text-color-primary);
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.props-panel__chip {
  flex: none;
  max-width: 50%;
  overflow: hidden;
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--el-fill-color);
  color: var(--el-text-color-regular);
  font-size: 11px;
  line-height: 16px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.props-panel__meta {
  color: var(--el-text-color-secondary);
  font-size: 11px;
  overflow-wrap: anywhere;
}
.props-panel__tabs {
  min-width: 0;
}
.props-panel__tabs > :deep(.el-tabs__header) {
  margin: 0;
  padding: 0 10px;
}
.props-panel__tabs > :deep(.el-tabs__content) {
  min-width: 0;
  padding: 12px 14px;
}
.props-panel__tabs :deep(.el-tab-pane) {
  min-width: 0;
}
/* 上下布局：标签在上、控件占满整列；所有层级 min-width:0，防止控件被撑出 320px 栏或窄屏抽屉。 */
.props-form {
  min-width: 0;
}
.props-form :deep(.el-form-item) {
  min-width: 0;
  margin-bottom: 12px;
}
.props-form :deep(.el-form-item__label) {
  margin-bottom: 4px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 18px;
}
.props-form :deep(.el-form-item__content) {
  min-width: 0;
}
.props-form :deep(.el-input),
.props-form :deep(.el-select),
.props-form :deep(.el-input-number),
.props-form :deep(.el-textarea) {
  width: 100%;
  min-width: 0;
}
.props-form__block {
  margin-bottom: 12px;
}
.props-section {
  min-width: 0;
  margin-bottom: 4px;
}
.props-section + .props-section {
  padding-top: 10px;
  border-top: 1px dashed var(--el-border-color-lighter);
}
.props-section__title {
  margin: 0 0 8px;
  color: var(--el-text-color-primary);
  font-size: 12px;
  font-weight: 600;
}
.props-two {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 0 8px;
  min-width: 0;
}
.props-two.is-single {
  grid-template-columns: minmax(0, 1fr);
}
.props-toggles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(84px, 1fr));
  gap: 6px;
  min-width: 0;
  margin-bottom: 12px;
}
.props-seg {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  width: 100%;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--el-border-color);
  border-radius: 6px;
  background: var(--el-fill-color-blank);
}
.props-seg__item {
  min-width: 0;
  height: 26px;
  padding: 0 4px;
  overflow: hidden;
  border: 0;
  border-left: 1px solid var(--el-border-color-lighter);
  background: transparent;
  color: var(--el-text-color-regular);
  font: inherit;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}
.props-seg__item:first-child {
  border-left: 0;
}
.props-seg__item:hover {
  color: var(--el-color-primary);
}
.props-seg__item:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: -2px;
}
.props-seg__item.is-on {
  background: color-mix(in srgb, var(--el-color-primary) 12%, var(--el-bg-color));
  color: var(--el-color-primary);
  font-weight: 600;
}
.props-seg__custom {
  margin-top: 6px;
}
.props-hint {
  display: block;
  width: 100%;
  margin-top: 4px;
  color: var(--el-text-color-secondary);
  font-size: 11px;
  line-height: 1.5;
}
</style>
