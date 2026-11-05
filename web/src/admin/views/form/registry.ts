import { i18n } from '@/locales';
import type { FormFieldDef } from '@/api/form';

export type ControlKind = 'field' | 'business' | 'layout' | 'relation-container';
export type ControlGroup = string;

const t = (key: string, fallback: string): string => i18n.global.t(key, fallback);

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
  control('input', t('formDesigner.controls.input', '单行输入'), 'field', t('formDesigner.groups.basic', '基础控件'), 'varchar(255)', null, { clearable: true }),
  control('password', t('formDesigner.controls.password', '密码输入'), 'field', t('formDesigner.groups.basic', '基础控件'), 'varchar(255)', null, { showPassword: true }),
  control('textarea', t('formDesigner.controls.textarea', '多行输入'), 'field', t('formDesigner.groups.basic', '基础控件'), 'text', null, { rows: 4 }),
  control('mention', t('formDesigner.controls.mention', '提及输入'), 'field', t('formDesigner.groups.basic', '基础控件'), 'varchar(500)', staticOptions('张三', '李四'), { type: 'textarea', rows: 3 }),
  control('number', t('formDesigner.controls.number', '数字输入'), 'field', t('formDesigner.groups.basic', '基础控件'), 'int', null, { controlsPosition: 'right' }),
  control('select', t('formDesigner.controls.select', '下拉选择'), 'field', t('formDesigner.groups.selection', '选择控件'), 'varchar(100)', staticOptions('选项一', '选项二'), { clearable: true }),
  control('selectV2', t('formDesigner.controls.selectV2', '虚拟化选择'), 'field', t('formDesigner.groups.selection', '选择控件'), 'varchar(100)', staticOptions('选项一', '选项二'), { clearable: true }),
  control('treeSelect', t('formDesigner.controls.treeSelect', '树形选择'), 'field', t('formDesigner.groups.selection', '选择控件'), 'bigint', { mode: 'static', options: [{ label: '节点一', value: 1, children: [] }] }, { clearable: true, checkStrictly: true }),
  control('cascader', t('formDesigner.controls.cascader', '级联选择'), 'field', t('formDesigner.groups.selection', '选择控件'), 'varchar(255)', { mode: 'static', options: [{ label: '选项一', value: 1, children: [] }] }, { clearable: true }),
  control('radio', t('formDesigner.controls.radio', '单选框组'), 'field', t('formDesigner.groups.selection', '选择控件'), 'varchar(100)', staticOptions('选项一', '选项二')),
  control('checkbox', t('formDesigner.controls.checkbox', '复选框组'), 'field', t('formDesigner.groups.selection', '选择控件'), 'json', staticOptions('选项一', '选项二')),
  control('switch', t('formDesigner.controls.switch', '开关'), 'field', t('formDesigner.groups.selection', '选择控件'), 'tinyint(1)', null, { activeValue: 1, inactiveValue: 0 }),
  control('transfer', t('formDesigner.controls.transfer', '穿梭框'), 'field', t('formDesigner.groups.selection', '选择控件'), 'json', staticOptions('选项一', '选项二'), { filterable: true }),
  control('date', t('formDesigner.controls.date', '日期'), 'field', t('formDesigner.groups.datetime', '日期时间'), 'date', null, { valueFormat: 'YYYY-MM-DD' }),
  control('datetime', t('formDesigner.controls.datetime', '日期时间'), 'field', t('formDesigner.groups.datetime', '日期时间'), 'datetime', null, { valueFormat: 'YYYY-MM-DD HH:mm:ss' }),
  control('daterange', t('formDesigner.controls.daterange', '日期范围'), 'field', t('formDesigner.groups.datetime', '日期时间'), 'json', null, { valueFormat: 'YYYY-MM-DD', rangeSeparator: '至' }),
  control('datetimerange', t('formDesigner.controls.datetimerange', '日期时间范围'), 'field', t('formDesigner.groups.datetime', '日期时间'), 'json', null, { valueFormat: 'YYYY-MM-DD HH:mm:ss', rangeSeparator: '至' }),
  control('time', t('formDesigner.controls.time', '时间'), 'field', t('formDesigner.groups.datetime', '日期时间'), 'time', null, { valueFormat: 'HH:mm:ss' }),
  control('timeSelect', t('formDesigner.controls.timeSelect', '时间选择'), 'field', t('formDesigner.groups.datetime', '日期时间'), 'time', null, { start: '00:00', step: '00:30', end: '23:30' }),
  control('slider', t('formDesigner.controls.slider', '滑块'), 'field', t('formDesigner.groups.basic', '基础控件'), 'int', null, { min: 0, max: 100 }),
  control('rate', t('formDesigner.controls.rate', '评分'), 'field', t('formDesigner.groups.basic', '基础控件'), 'tinyint', null, { max: 5 }),
  control('color', t('formDesigner.controls.color', '颜色'), 'field', t('formDesigner.groups.basic', '基础控件'), 'varchar(20)', null, { showAlpha: true }),
  control('image', t('formDesigner.controls.image', '单图上传'), 'field', t('formDesigner.groups.upload', '上传控件'), 'varchar(500)', null, { maxSize: 5, bizType: 'image' }),
  control('images', t('formDesigner.controls.images', '多图上传'), 'field', t('formDesigner.groups.upload', '上传控件'), 'json', null, { maxSize: 5, maxCount: 9, bizType: 'image' }),
  control('file', t('formDesigner.controls.file', '单文件上传'), 'field', t('formDesigner.groups.upload', '上传控件'), 'json', null, { maxCount: 1, bizType: 'file' }),
  control('files', t('formDesigner.controls.files', '多文件上传'), 'field', t('formDesigner.groups.upload', '上传控件'), 'json', null, { maxCount: 0, bizType: 'file' }),
  control('dictionary', t('formDesigner.controls.dictionary', '字典选择'), 'business', t('formDesigner.groups.business', '业务控件'), 'varchar(100)', { mode: 'dictionary', dictionary: '', options: [] }, { clearable: true }),
  control('relation', t('formDesigner.controls.relation', '关联数据'), 'business', t('formDesigner.groups.business', '业务控件'), 'bigint', { mode: 'relation', options: [] }, { clearable: true, filterable: true }),
  control('department', t('formDesigner.controls.department', '部门选择'), 'business', t('formDesigner.groups.business', '业务控件'), 'bigint', { mode: 'department', options: [] }, { clearable: true, checkStrictly: true }),
  control('user', t('formDesigner.controls.user', '用户选择'), 'business', t('formDesigner.groups.business', '业务控件'), 'bigint', { mode: 'user', options: [] }, { clearable: true, filterable: true }),
  control('richtext', t('formDesigner.controls.richtext', '富文本'), 'business', t('formDesigner.groups.business', '业务控件'), 'longtext', null, { rows: 8 }),
  control('json', t('formDesigner.controls.json', 'JSON 编辑器'), 'business', t('formDesigner.groups.business', '业务控件'), 'json', null, { rows: 8 }),
  control('hidden', t('formDesigner.controls.hidden', '隐藏字段'), 'field', t('formDesigner.groups.basic', '基础控件'), 'varchar(255)'),
  control('readonly', t('formDesigner.controls.readonly', '只读文本'), 'field', t('formDesigner.groups.basic', '基础控件'), 'varchar(255)'),
  control('repeatable', t('formDesigner.controls.repeatable', '重复行'), 'relation-container', t('formDesigner.groups.business', '业务控件'), '', null, { minRows: 0, maxRows: 0, primaryKey: 'id', columns: [] }),
  control('subform', t('formDesigner.controls.subform', '子表单'), 'relation-container', t('formDesigner.groups.business', '业务控件'), '', null, { minRows: 0, maxRows: 0, primaryKey: 'id', columns: [] }),
  control('group', t('formDesigner.controls.group', '分组'), 'layout', t('formDesigner.groups.layout', '布局控件'), '', null, { title: '字段分组' }),
  control('grid', t('formDesigner.controls.grid', '栅格'), 'layout', t('formDesigner.groups.layout', '布局控件'), '', null, { columns: 2, gutter: 16 }),
  control('divider', t('formDesigner.controls.divider', '分割线'), 'layout', t('formDesigner.groups.layout', '布局控件'), '', null, { contentPosition: 'left' }),
  control('text', t('formDesigner.controls.text', '说明文字'), 'layout', t('formDesigner.groups.layout', '布局控件'), '', null, { content: '说明文字' }),
  control('collapse', t('formDesigner.controls.collapse', '折叠面板'), 'layout', t('formDesigner.groups.layout', '布局控件'), '', null, { title: '折叠区域' }),
  control('tabs', t('formDesigner.controls.tabs', '标签页'), 'layout', t('formDesigner.groups.layout', '布局控件'), '', null, { tabs: ['标签一', '标签二'] })
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
  { value: '', label: t('formDesigner.none', '无') },
  { value: 'tag', label: t('formDesigner.formatters.tag', '标签') },
  { value: 'image', label: t('formDesigner.formatters.image', '单图') },
  { value: 'images', label: t('formDesigner.formatters.images', '多图') },
  { value: 'date', label: t('formDesigner.formatters.date', '日期') },
  { value: 'datetime', label: t('formDesigner.formatters.datetime', '日期时间') },
  { value: 'time', label: t('formDesigner.formatters.time', '时间') },
  { value: 'money', label: t('formDesigner.formatters.money', '金额') },
  { value: 'number', label: t('formDesigner.formatters.number', '数字') },
  { value: 'percent', label: t('formDesigner.formatters.percent', '百分比') },
  { value: 'switch', label: t('formDesigner.formatters.switch', '状态开关') },
  { value: 'boolean', label: t('formDesigner.formatters.boolean', '是/否') },
  { value: 'link', label: t('formDesigner.formatters.link', '链接') },
  { value: 'email', label: t('formDesigner.formatters.email', '邮箱') },
  { value: 'phone', label: t('formDesigner.formatters.phone', '手机号') },
  { value: 'json', label: t('formDesigner.formatters.json', 'JSON') }
];

export const LIST_FILTERS: ListOption[] = [
  { value: '', label: t('formDesigner.none', '无') },
  { value: 'eq', label: t('formDesigner.filters.eq', '等于') },
  { value: 'ne', label: t('formDesigner.filters.ne', '不等于') },
  { value: 'like', label: t('formDesigner.filters.like', '包含') },
  { value: 'not_like', label: t('formDesigner.filters.notLike', '不包含') },
  { value: 'starts_with', label: t('formDesigner.filters.startsWith', '开头是') },
  { value: 'ends_with', label: t('formDesigner.filters.endsWith', '结尾是') },
  { value: 'gt', label: t('formDesigner.filters.gt', '大于') },
  { value: 'gte', label: t('formDesigner.filters.gte', '大于等于') },
  { value: 'lt', label: t('formDesigner.filters.lt', '小于') },
  { value: 'lte', label: t('formDesigner.filters.lte', '小于等于') },
  { value: 'range', label: t('formDesigner.filters.range', '数值范围') },
  { value: 'date', label: t('formDesigner.filters.date', '日期范围') },
  { value: 'in', label: t('formDesigner.filters.in', '属于（逗号分隔）') },
  { value: 'not_in', label: t('formDesigner.filters.notIn', '不属于（逗号分隔）') },
  { value: 'is_null', label: t('formDesigner.filters.isNull', '为空') },
  { value: 'not_null', label: t('formDesigner.filters.notNull', '不为空') }
];
