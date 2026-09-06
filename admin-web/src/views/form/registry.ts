import type { FormFieldDef } from '@/api/form';

/** 控件注册表 v1：type => 元信息＋新建字段默认值 */
export interface ControlMeta {
  type: string;
  label: string;
  defaultColumnType: string;
  group: '基础' | '选择' | '日期';
}

export const CONTROL_REGISTRY: ControlMeta[] = [
  { type: 'input', label: '单行输入', defaultColumnType: 'varchar(100)', group: '基础' },
  { type: 'textarea', label: '多行输入', defaultColumnType: 'varchar(500)', group: '基础' },
  { type: 'number', label: '数字', defaultColumnType: 'int', group: '基础' },
  { type: 'switch', label: '开关', defaultColumnType: 'tinyint(1)', group: '基础' },
  { type: 'select', label: '下拉选择', defaultColumnType: 'varchar(50)', group: '选择' },
  { type: 'date', label: '日期', defaultColumnType: 'date', group: '日期' }
];

export const controlMeta = (type: string): ControlMeta =>
  CONTROL_REGISTRY.find((item) => item.type === type) ?? CONTROL_REGISTRY[0];

/** 新建字段默认定义 */
export function createField(type: string, index: number): FormFieldDef {
  const meta = controlMeta(type);
  return {
    field_name: `field_${index}`,
    label: meta.label,
    type,
    column_type: meta.defaultColumnType,
    nullable: 1,
    default_value: '',
    comment: '',
    unsigned: 0,
    index_type: 'none',
    placeholder: '',
    options_source: type === 'select' ? { mode: 'static', options: [] } : null,
    control_props: null,
    validate_rules: null,
    link_rules: null,
    relation_type: 'none',
    relation_table: '',
    relation_label_field: '',
    relation_value_field: 'id',
    relation_multiple: 0,
    relation_on_delete: 'restrict',
    list_show: 1,
    list_sort: 0,
    list_filter: '',
    list_formatter: '',
    list_width: 0,
    form_show: 1,
    form_required: 0,
    form_group: '',
    form_span: 24,
    form_readonly: 0,
    sort_order: index
  };
}

/** 列格式化器注册表 v1 */
export const LIST_FORMATTERS = ['', 'tag', 'image', 'datetime', 'money', 'switch'];

/** 列表筛选类型 */
export const LIST_FILTERS = ['', 'eq', 'like', 'range', 'date'];
