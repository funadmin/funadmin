import type { FormFieldDef } from '@/api/form';

export type ControlKind = 'field' | 'business' | 'layout' | 'relation-container';
export type ControlGroup = '基础控件' | '选择控件' | '日期时间' | '上传控件' | '业务控件' | '布局控件';

/** 控件元信息与新建字段默认配置。 */
export interface ControlMeta {
  type: string;
  label: string;
  kind: ControlKind;
  group: ControlGroup;
  defaultColumnType: string;
  valueType?: string;
  defaultOptions: Record<string, unknown> | null;
  defaultProps: Record<string, unknown> | null;
  propertySchema?: Record<string, unknown>;
}

const staticOptions = (...labels: string[]) => ({
  mode: 'static',
  options: labels.map((label, index) => ({ label, value: index + 1 }))
});

const control = (
  type: string,
  label: string,
  kind: ControlKind,
  group: ControlGroup,
  defaultColumnType: string,
  defaultOptions: Record<string, unknown> | null = null,
  defaultProps: Record<string, unknown> | null = null
): ControlMeta => ({ type, label, kind, group, defaultColumnType, defaultOptions, defaultProps });

/** 常见 MySQL 列类型；设计器仍允许输入列表外的合法类型。 */
export const COLUMN_TYPE_OPTIONS = [
  'tinyint(1)', 'tinyint', 'smallint', 'mediumint', 'int', 'bigint',
  'decimal(10,2)', 'decimal(12,2)', 'float', 'double',
  'char(1)', 'char(10)', 'varchar(50)', 'varchar(100)', 'varchar(255)', 'varchar(500)',
  'text', 'mediumtext', 'longtext',
  'date', 'datetime', 'timestamp', 'time', 'year',
  'json', 'binary(16)', 'varbinary(255)', 'blob', 'mediumblob', 'longblob'
] as const;

export const CONTROL_REGISTRY: ControlMeta[] = [
  control('input', '单行输入', 'field', '基础控件', 'varchar(255)', null, { clearable: true }),
  control('password', '密码输入', 'field', '基础控件', 'varchar(255)', null, { showPassword: true }),
  control('textarea', '多行输入', 'field', '基础控件', 'text', null, { rows: 4 }),
  control('mention', '提及输入', 'field', '基础控件', 'varchar(500)', staticOptions('张三', '李四'), { type: 'textarea', rows: 3 }),
  control('number', '数字输入', 'field', '基础控件', 'int', null, { controlsPosition: 'right' }),
  control('select', '下拉选择', 'field', '选择控件', 'varchar(100)', staticOptions('选项一', '选项二'), { clearable: true }),
  control('selectV2', '虚拟化选择', 'field', '选择控件', 'varchar(100)', staticOptions('选项一', '选项二'), { clearable: true }),
  control('treeSelect', '树形选择', 'field', '选择控件', 'bigint', { mode: 'static', options: [{ label: '节点一', value: 1, children: [] }] }, { clearable: true, checkStrictly: true }),
  control('cascader', '级联选择', 'field', '选择控件', 'varchar(255)', { mode: 'static', options: [{ label: '选项一', value: 1, children: [] }] }, { clearable: true }),
  control('radio', '单选框组', 'field', '选择控件', 'varchar(100)', staticOptions('选项一', '选项二')),
  control('checkbox', '复选框组', 'field', '选择控件', 'json', staticOptions('选项一', '选项二')),
  control('switch', '开关', 'field', '选择控件', 'tinyint(1)', null, { activeValue: 1, inactiveValue: 0 }),
  control('transfer', '穿梭框', 'field', '选择控件', 'json', staticOptions('选项一', '选项二'), { filterable: true }),
  control('date', '日期', 'field', '日期时间', 'date', null, { valueFormat: 'YYYY-MM-DD' }),
  control('datetime', '日期时间', 'field', '日期时间', 'datetime', null, { valueFormat: 'YYYY-MM-DD HH:mm:ss' }),
  control('daterange', '日期范围', 'field', '日期时间', 'json', null, { valueFormat: 'YYYY-MM-DD', rangeSeparator: '至' }),
  control('datetimerange', '日期时间范围', 'field', '日期时间', 'json', null, { valueFormat: 'YYYY-MM-DD HH:mm:ss', rangeSeparator: '至' }),
  control('time', '时间', 'field', '日期时间', 'time', null, { valueFormat: 'HH:mm:ss' }),
  control('timeSelect', '时间选择', 'field', '日期时间', 'time', null, { start: '00:00', step: '00:30', end: '23:30' }),
  control('slider', '滑块', 'field', '基础控件', 'int', null, { min: 0, max: 100 }),
  control('rate', '评分', 'field', '基础控件', 'tinyint', null, { max: 5 }),
  control('color', '颜色', 'field', '基础控件', 'varchar(20)', null, { showAlpha: true }),
  control('image', '单图上传', 'field', '上传控件', 'varchar(500)', null, { maxSize: 5, bizType: 'image' }),
  control('images', '多图上传', 'field', '上传控件', 'json', null, { maxSize: 5, maxCount: 9, bizType: 'image' }),
  control('file', '单文件上传', 'field', '上传控件', 'json', null, { maxCount: 1, bizType: 'file' }),
  control('files', '多文件上传', 'field', '上传控件', 'json', null, { maxCount: 0, bizType: 'file' }),
  control('dictionary', '字典选择', 'business', '业务控件', 'varchar(100)', { mode: 'dictionary', dictionary: '', options: [] }, { clearable: true }),
  control('relation', '关联数据', 'business', '业务控件', 'bigint', { mode: 'relation', options: [] }, { clearable: true, filterable: true }),
  control('department', '部门选择', 'business', '业务控件', 'bigint', { mode: 'department', options: [] }, { clearable: true, checkStrictly: true }),
  control('user', '用户选择', 'business', '业务控件', 'bigint', { mode: 'user', options: [] }, { clearable: true, filterable: true }),
  control('richtext', '富文本', 'business', '业务控件', 'longtext', null, { rows: 8 }),
  control('json', 'JSON 编辑器', 'business', '业务控件', 'json', null, { rows: 8 }),
  control('hidden', '隐藏字段', 'field', '基础控件', 'varchar(255)'),
  control('readonly', '只读文本', 'field', '基础控件', 'varchar(255)'),
  control('repeatable', '重复行', 'relation-container', '业务控件', '', null, { minRows: 0, maxRows: 0, primaryKey: 'id', columns: [] }),
  control('subform', '子表单', 'relation-container', '业务控件', '', null, { minRows: 0, maxRows: 0, primaryKey: 'id', columns: [] }),
  control('group', '分组', 'layout', '布局控件', '', null, { title: '字段分组' }),
  control('grid', '栅格', 'layout', '布局控件', '', null, { columns: 2, gutter: 16 }),
  control('divider', '分割线', 'layout', '布局控件', '', null, { contentPosition: 'left' }),
  control('text', '说明文字', 'layout', '布局控件', '', null, { content: '说明文字' }),
  control('collapse', '折叠面板', 'layout', '布局控件', '', null, { title: '折叠区域' }),
  control('tabs', '标签页', 'layout', '布局控件', '', null, { tabs: ['标签一', '标签二'] })
];

const PLUGIN_CONTROL_REGISTRY = new Map<string, ControlMeta>();

/** 用已通过构建白名单的插件控件替换设计器动态注册项。 */
export const setPluginControls = (controls: ControlMeta[]): void => {
  PLUGIN_CONTROL_REGISTRY.clear();
  controls.forEach((item) => PLUGIN_CONTROL_REGISTRY.set(item.type, item));
};

export const controlMeta = (type: string): ControlMeta =>
  CONTROL_REGISTRY.find((item) => item.type === type) ?? PLUGIN_CONTROL_REGISTRY.get(type) ?? CONTROL_REGISTRY[0];

const cloneConfig = (value: Record<string, unknown> | null) =>
  value === null ? null : JSON.parse(JSON.stringify(value)) as Record<string, unknown>;

/** 根据注册表创建字段或布局节点。 */
export const createField = (type: string, index: number): FormFieldDef => {
  const meta = controlMeta(type);
  const isLayout = meta.kind === 'layout';
  const isRelationContainer = meta.kind === 'relation-container';
  return {
    field_name: `field_${index}`,
    label: meta.label,
    type: meta.type,
    column_type: meta.defaultColumnType,
    nullable: 1,
    default_value: '',
    comment: '',
    unsigned: ['treeSelect', 'rate', 'relation', 'department', 'user'].includes(meta.type) ? 1 : 0,
    index_type: 'none',
    placeholder: '',
    options_source: cloneConfig(meta.defaultOptions),
    control_props: cloneConfig(meta.defaultProps),
    validate_rules: null,
    link_rules: null,
    relation_type: isRelationContainer ? 'has_many' : 'none',
    relation_table: '',
    relation_label_field: '',
    relation_value_field: 'id',
    relation_multiple: 0,
    relation_on_delete: 'restrict',
    list_show: isLayout || isRelationContainer ? 0 : 1,
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
};

export interface ListOption {
  value: string;
  label: string;
}

export const LIST_FORMATTERS: ListOption[] = [
  { value: '', label: '无' },
  { value: 'tag', label: '标签' },
  { value: 'image', label: '单图' },
  { value: 'images', label: '多图' },
  { value: 'date', label: '日期' },
  { value: 'datetime', label: '日期时间' },
  { value: 'time', label: '时间' },
  { value: 'money', label: '金额' },
  { value: 'number', label: '数字' },
  { value: 'percent', label: '百分比' },
  { value: 'switch', label: '状态开关' },
  { value: 'boolean', label: '是/否' },
  { value: 'link', label: '链接' },
  { value: 'email', label: '邮箱' },
  { value: 'phone', label: '手机号' },
  { value: 'json', label: 'JSON' }
];

export const LIST_FILTERS: ListOption[] = [
  { value: '', label: '无' },
  { value: 'eq', label: '等于' },
  { value: 'ne', label: '不等于' },
  { value: 'like', label: '包含' },
  { value: 'not_like', label: '不包含' },
  { value: 'starts_with', label: '开头是' },
  { value: 'ends_with', label: '结尾是' },
  { value: 'gt', label: '大于' },
  { value: 'gte', label: '大于等于' },
  { value: 'lt', label: '小于' },
  { value: 'lte', label: '小于等于' },
  { value: 'range', label: '数值范围' },
  { value: 'date', label: '日期范围' },
  { value: 'in', label: '属于（逗号分隔）' },
  { value: 'not_in', label: '不属于（逗号分隔）' },
  { value: 'is_null', label: '为空' },
  { value: 'not_null', label: '不为空' }
];
