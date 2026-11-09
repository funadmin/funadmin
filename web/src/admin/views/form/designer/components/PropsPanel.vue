<template>
  <div class="props-panel">
    <header class="props-panel__header">
      <div class="props-panel__title">
        <b data-props-title :title="node.title">{{ node.title || t('formDesigner.unnamed', '未命名') }}</b>
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
              <el-input :model-value="node.title" @update:model-value="patch({ title: $event })" />
            </el-form-item>
            <div class="props-two" :class="{ 'is-single': isLayout }">
              <el-form-item data-prop="type" :label="t('formDesigner.controlType', '控件类型')">
                <el-select :model-value="node.type" class="w-full" filterable :disabled="typeLocked" @update:model-value="onTypeChange">
                  <el-option-group v-for="group in controlGroups" :key="group.label" :label="group.label">
                    <el-option v-for="control in group.options" :key="control.type" :label="control.label" :value="control.type" />
                  </el-option-group>
                </el-select>
              </el-form-item>
              <el-form-item v-if="!isLayout" data-prop="form_group" :label="t('formDesigner.formGroup', '分组')">
                <el-input :model-value="layoutGroup" :placeholder="t('formDesigner.none', '无')" @update:model-value="emitNode(patchLayout(node, { group: $event }))" />
              </el-form-item>
            </div>
            <el-form-item v-if="!isLayout" data-prop="placeholder" :label="t('formDesigner.placeholderLabel', '占位提示')">
              <el-input :model-value="placeholder" @update:model-value="patch({ props: { ...(node.props ?? {}), placeholder: $event } })" />
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
                :model-value="span"
                :min="1"
                :max="24"
                class="w-full props-seg__custom"
                @update:model-value="setSpan($event ?? 24)"
              />
            </el-form-item>
          </section>
          <section class="props-section" data-props-section="state">
            <h4 class="props-section__title" data-props-section-title>{{ t('formDesigner.propsPanel.sectionState', '状态') }}</h4>
            <div class="props-toggles" data-props-toggles="form">
              <PropsToggle v-if="!isLayout" data-toggle="form_required" :label="t('formDesigner.required', '必填')" :model-value="isRequiredNode(node)" @update:model-value="emitNode(withRequired(node, $event))" />
              <PropsToggle data-toggle="form_show" :label="t('formDesigner.show', '显示')" :model-value="node.hidden !== true" @update:model-value="patch({ hidden: !$event })" />
              <PropsToggle v-if="!isLayout" data-toggle="form_readonly" :label="t('formDesigner.propsPanel.readonly', '只读')" :model-value="node.disabled === true" @update:model-value="patch({ disabled: $event })" />
            </div>
          </section>
        </el-form>
        <PropsFieldFolds :node="node" :layout="isLayout" @update="patch" />
      </el-tab-pane>
      <el-tab-pane v-if="!isLayout" :label="t('formDesigner.tabList', '列表')" name="list" data-props-tab="list">
        <el-form label-position="top" size="small" class="props-form">
          <div class="props-toggles props-form__block" data-props-toggles="list">
            <PropsToggle data-toggle="list_show" :label="t('formDesigner.show', '显示')" :model-value="node.list?.show === true" @update:model-value="emitNode(patchList(node, { show: $event }))" />
            <PropsToggle data-toggle="list_sort" :label="t('formDesigner.sortable', '可排序')" :model-value="node.list?.sort === true" @update:model-value="emitNode(patchList(node, { sort: $event }))" />
          </div>
          <el-form-item data-prop="list_filter" :label="t('formDesigner.filter', '筛选')">
            <el-select :model-value="node.list?.filter ?? ''" class="w-full" filterable @update:model-value="emitNode(patchList(node, { filter: $event }))">
              <el-option v-for="item in LIST_FILTERS" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item data-prop="list_formatter" :label="t('formDesigner.formatter', '格式化器')">
            <el-select :model-value="node.list?.formatter ?? ''" class="w-full" filterable @update:model-value="emitNode(patchList(node, { formatter: $event }))">
              <el-option v-for="item in LIST_FORMATTERS" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item data-prop="list_width" :label="t('formDesigner.columnWidth', '列宽')">
            <el-input-number :model-value="node.list?.width ?? 0" :min="0" :max="800" class="w-full" @update:model-value="emitNode(patchList(node, { width: $event ?? 0 }))" />
          </el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane v-if="!isLayout" :label="t('formDesigner.layout.tabDatabaseColumn', '数据库列')" name="column" data-props-tab="column">
        <el-form label-position="top" size="small" class="props-form">
          <el-form-item data-prop="field_name" :label="t('formDesigner.fieldName', '字段名')">
            <el-input :model-value="node.field ?? ''" @update:model-value="patch({ field: $event })" />
          </el-form-item>
          <el-form-item data-prop="column_type" :label="t('formDesigner.columnType', '列类型')">
            <el-select
              :model-value="columnTypeOf(node)"
              :disabled="sourceType === 'adopted'"
              class="w-full"
              filterable
              allow-create
              default-first-option
              :placeholder="t('formDesigner.columnTypePlaceholder', '请选择或输入列类型')"
              @update:model-value="emitNode(patchDatabase(node, { columnType: $event }))"
            >
              <el-option v-for="item in COLUMN_TYPE_OPTIONS" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
          <div class="props-toggles props-form__block" data-props-toggles="column">
            <PropsToggle data-toggle="nullable" :label="t('formDesigner.nullable', '可空')" :model-value="node.database?.nullable !== false" :disabled="sourceType === 'adopted'" @update:model-value="emitNode(patchDatabase(node, { nullable: $event }))" />
            <PropsToggle data-toggle="unsigned" :label="t('formDesigner.unsigned', '无符号')" :model-value="node.database?.unsigned === true" :disabled="sourceType === 'adopted'" @update:model-value="emitNode(patchDatabase(node, { unsigned: $event }))" />
          </div>
          <div class="props-two">
            <el-form-item data-prop="default_value" :label="t('formDesigner.defaultValue', '默认值')">
              <el-input :model-value="defaultValueText" :disabled="sourceType === 'adopted'" @update:model-value="patch({ defaultValue: $event })" />
            </el-form-item>
            <el-form-item data-prop="index_type" :label="t('formDesigner.index', '索引')">
              <el-select :model-value="node.database?.index ?? 'none'" :disabled="sourceType === 'adopted'" class="w-full" @update:model-value="emitNode(patchDatabase(node, { index: $event }))">
                <el-option :label="t('formDesigner.none', '无')" value="none" />
                <el-option :label="t('formDesigner.unique', '唯一')" value="unique" />
                <el-option :label="t('formDesigner.normal', '普通')" value="index" />
              </el-select>
            </el-form-item>
          </div>
          <el-form-item data-prop="comment" :label="t('formDesigner.comment', '注释')">
            <el-input :model-value="node.database?.comment ?? ''" @update:model-value="emitNode(patchDatabase(node, { comment: $event }))" />
          </el-form-item>
          <el-form-item data-prop="relation_type" :label="t('formDesigner.relationType', '关联类型')">
            <el-select :model-value="relationTypeOf(node)" class="w-full" @update:model-value="emitNode(patchRelation(node, { type: $event }))">
              <el-option :label="t('formDesigner.none', '无')" value="none" />
              <el-option label="belongs_to" value="belongs_to" />
              <el-option label="has_many" value="has_many" />
            </el-select>
          </el-form-item>
          <template v-if="relationTypeOf(node) !== 'none'">
            <el-form-item data-prop="relation_table" :label="t('formDesigner.relationTable', '关联表')">
              <el-select
                :model-value="relation.table ?? ''"
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
                :model-value="relation.labelField ?? ''"
                class="w-full"
                filterable
                allow-create
                :loading="columnsLoading"
                :placeholder="t('formDesigner.relationLabelFieldPlaceholder', '请选择显示字段')"
                @visible-change="loadRelationColumns"
                @update:model-value="emitNode(patchRelation(node, { labelField: $event }))"
              >
                <el-option v-for="column in relationColumns" :key="column.name" :label="columnLabel(column)" :value="column.name" />
              </el-select>
            </el-form-item>
            <el-form-item data-prop="relation_value_field" :label="t('formDesigner.relationValueField', '值字段')">
              <el-select
                :model-value="relation.valueField ?? ''"
                class="w-full"
                filterable
                allow-create
                :loading="columnsLoading"
                :placeholder="t('formDesigner.relationValueFieldPlaceholder', '请选择值字段')"
                @visible-change="loadRelationColumns"
                @update:model-value="emitNode(patchRelation(node, { valueField: $event }))"
              >
                <el-option v-for="column in relationColumns" :key="column.name" :label="columnLabel(column)" :value="column.name" />
              </el-select>
            </el-form-item>
            <el-form-item v-if="relationTypeOf(node) === 'belongs_to'" data-prop="relation_on_delete" :label="t('formDesigner.relationOnDelete', '删除规则')">
              <el-select :model-value="relation.onDelete ?? 'restrict'" class="w-full" @update:model-value="emitNode(patchRelation(node, { onDelete: $event }))">
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
            <el-input-number :model-value="span" :min="1" :max="24" class="w-full" @update:model-value="setSpan($event ?? 24)" />
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
import { businessDevelopmentApi, type BusinessDatabaseTable } from '@/api/development/business';
import { componentRegistry } from '../../schema/componentRegistry';
import type { FormSchemaNode } from '../../schema/types';
import { columnTypeOf, isRequiredNode, relationTypeOf } from '../../schema/fieldNodes';
import { changeControl, patchDatabase, patchLayout, patchList, patchRelation, withRequired } from '../../schema/nodeEdits';
import { normalizePropertySchema, patchDynamicProperty, type DynamicPropertyField } from '../structuredEditor';
import { COLUMN_TYPE_OPTIONS, LIST_FILTERS, LIST_FORMATTERS, controlMeta, type ControlMeta } from '../../registry';
import PropsFieldFolds from './PropsFieldFolds.vue';
import PropsToggle from './PropsToggle.vue';

const { t } = useI18n();

const props = defineProps<{ moduleId: number; node: FormSchemaNode; sourceType: 'created' | 'adopted'; controls: ControlMeta[]; groupTitle?: string }>();
const emit = defineEmits<{ (event: 'update', patch: Partial<FormSchemaNode>): void }>();

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
const patch = (value: Partial<FormSchemaNode>) => emit('update', value);
/** 把 nodeEdits 产出的新节点转成补丁：只带引用发生变化的键，被移除的键以 undefined 表示删除。 */
const emitNode = (next: FormSchemaNode) => {
  if (next === props.node) return;
  const current = props.node as unknown as Record<string, unknown>;
  const target = next as unknown as Record<string, unknown>;
  const changed: Record<string, unknown> = {};
  for (const key of new Set([...Object.keys(current), ...Object.keys(target)])) {
    if (key === 'id' || key === 'children') continue;
    if (!Object.is(current[key], target[key])) changed[key] = target[key];
  }
  if (Object.keys(changed).length) patch(changed as Partial<FormSchemaNode>);
};

const selectedMeta = computed(() => controlMeta(props.node.type));
const isLayout = computed(() => props.node.kind === 'layout');
const isRelationContainer = computed(() => selectedMeta.value.kind === 'relation-container');
// 字段只能切换为字段控件（nodeEdits.changeControl 拒绝布局与子表容器）；布局与 repeatable/subform 节点不可切换控件。
const typeLocked = computed(() => isLayout.value || isRelationContainer.value);
const relation = computed(() => props.node.database?.relation ?? {});
const placeholder = computed(() => String(props.node.props?.placeholder ?? ''));
const layoutGroup = computed(() => String(props.node.layout?.group ?? ''));
const defaultValueText = computed(() => (props.node.defaultValue == null ? '' : String(props.node.defaultValue)));
// 标题区：字段名（布局节点显示节点 ID）· 列类型（字段）· 位于「父容器」/根节点；控件类型单独显示为标签。
const headerMeta = computed(() => {
  const key = isLayout.value ? props.node.id : (props.node.field || props.node.id);
  const located = props.groupTitle
    ? t('formDesigner.layout.propsLocated', { group: props.groupTitle }, '位于「{group}」')
    : t('formDesigner.propsPanel.locatedRoot', '位于根节点');
  return [key, isLayout.value ? '' : columnTypeOf(props.node), located].filter(Boolean).join(' · ');
});

// 宽度：layout.span 为整数时取其值，否则（缺省或响应式对象）按 24 列显示，与后端 FormFieldView::span() 一致。
const span = computed(() => (Number.isInteger(props.node.layout?.span) ? Number(props.node.layout?.span) : 24));
const setSpan = (value: number) => emitNode(patchLayout(props.node, { span: value }));
// 宽度分段：只映射 6/8/12/24 四个常用值；其他列数原样保留，显示「自定义 N 列」，不做任何改写。
const SPAN_PRESETS = [6, 8, 12, 24] as const;
const customSpanOpen = ref(false);
const isPresetSpan = computed(() => (SPAN_PRESETS as readonly number[]).includes(span.value));
const spanOptions = computed(() => {
  const labels: Record<number, string> = {
    6: t('formDesigner.propsPanel.spanQuarter', '1/4'),
    8: t('formDesigner.propsPanel.spanThird', '1/3'),
    12: t('formDesigner.propsPanel.spanHalf', '1/2'),
    24: t('formDesigner.propsPanel.spanFull', '整行')
  };
  return [
    ...SPAN_PRESETS.map((preset) => ({ value: String(preset), label: labels[preset], active: !customSpanOpen.value && span.value === preset })),
    { value: 'custom', label: t('formDesigner.propsPanel.spanCustom', '自定义'), active: customSpanOpen.value || !isPresetSpan.value }
  ];
});
const spanHint = computed(() => (isPresetSpan.value
  ? t('formDesigner.propsPanel.spanHint', '对应栅格 24 列中的 6 / 8 / 12 / 24')
  : t('formDesigner.propsPanel.spanCustomValue', { n: span.value }, '自定义 {n} 列')));
const onSpanOption = (value: string) => {
  if (value === 'custom') {
    customSpanOpen.value = !customSpanOpen.value;
    return;
  }
  customSpanOpen.value = false;
  setSpan(Number(value));
};
watch(() => props.node.id, () => { customSpanOpen.value = false; });
const componentDefinition = computed(() => componentRegistry.resolve(props.node.type));
// 布局节点无“数据库列/列表”页签：选中容器时回落到可见页签，避免内容区空白。
watch(isLayout, (layout) => {
  if (layout && (tab.value === 'column' || tab.value === 'list')) tab.value = 'form';
}, { immediate: true });
const propertyFields = computed(() => normalizePropertySchema(componentDefinition.value?.propertySchema ?? {}));
const propertyValue = (property: DynamicPropertyField): unknown => props.node.props?.[property.name] ?? property.defaultValue;
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
  patch({ props: patchDynamicProperty(props.node.props, property.name, value) });
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
// 字段节点只列出可切换的字段控件；锁定的布局 / 子表容器节点只显示自身类型。
const switchableControls = computed(() => (typeLocked.value
  ? [props.controls.find((control) => control.type === props.node.type) ?? selectedMeta.value]
  : props.controls.filter((control) => control.kind !== 'layout' && control.kind !== 'relation-container')));
const controlGroups = computed(() => {
  const labels = [...new Set(switchableControls.value.map((control) => control.group))];
  return labels.map((label) => ({ label, options: switchableControls.value.filter((control) => control.group === label) }));
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
  const table = (selectedTable ?? String(relation.value.table ?? '')).trim();
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
  const nodeId = props.node.id;
  loadedRelationTable.value = '';
  relationColumns.value = [];
  emitNode(patchRelation(props.node, { table, labelField: '', valueField: 'id' }));
  await loadRelationColumns(true, table);
  // 加载期间已切换到其他节点：不把旧表的字段写到新节点上。
  if (props.node.id !== nodeId) return;
  const primary = relationColumns.value.find((column) => column.primary)?.name;
  const display = relationColumns.value.find((column) => ['name', 'title', 'label', 'username', 'nickname'].includes(column.name))?.name;
  // 第二次补丁带上 table：父组件可能尚未把第一次补丁写回 props.node。
  emitNode(patchRelation(props.node, {
    table,
    valueField: primary || relationColumns.value.find((column) => column.name === 'id')?.name || 'id',
    labelField: display || relationColumns.value.find((column) => !column.primary)?.name || ''
  }));
};

const onTypeChange = (type: string) => {
  if (typeLocked.value) return;
  // 采纳表不静默改动已有列的物理类型；新建表改为控件默认列类型。
  emitNode(changeControl(props.node, type, { resetColumnType: props.sourceType !== 'adopted' }));
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
