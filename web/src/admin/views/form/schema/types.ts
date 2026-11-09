export type FormNodeKind = 'field' | 'layout';
export type FormValueType = 'string' | 'number' | 'boolean' | 'array' | 'object';
export type FormResponsiveSpan = number | { xs?: number; sm?: number; md?: number; lg?: number; xl?: number };

export interface FormSchemaAsyncValidation {
  key: string;
  params?: Record<string, unknown>;
  debounce?: number;
  timeout?: number;
  cacheTtl?: number;
}

export interface FormSchemaCondition {
  field?: string;
  op: string;
  value?: unknown;
  conditions?: FormSchemaCondition[];
  condition?: FormSchemaCondition;
}

export interface FormSchemaValidationRule {
  type: string;
  value?: unknown;
  message?: string;
  trigger?: string[];
  validator?: FormSchemaAsyncValidation;
  when?: FormSchemaCondition;
  severity?: 'error' | 'warning';
  bail?: boolean;
}

export interface FormSchemaDataSource {
  [key: string]: unknown;
  kind?: string;
  options?: unknown[];
  dependsOn?: string[];
  params?: Record<string, unknown>;
  staleValue?: 'clear' | 'retain' | 'revalidate';
  debounce?: number;
  cacheTtl?: number;
  pagination?: { pageSize?: number };
}

export interface FormSchemaAccess {
  read?: string[];
  write?: string[];
  include?: 'auto' | 'always' | 'never';
}

export type FormSchemaRelationType = 'none' | 'belongs_to' | 'has_many';

/** 字段节点 database.relation；缺省值由读取方给出（type 缺省 none，onDelete 缺省 restrict）。 */
export interface FormSchemaRelationConfig {
  [key: string]: unknown;
  type?: FormSchemaRelationType;
  table?: string;
  labelField?: string;
  valueField?: string;
  multiple?: boolean;
  onDelete?: 'restrict' | 'cascade' | 'set_null';
}

/** 字段节点 database 切片（物理列）；未列出的键原样保留。 */
export interface FormSchemaDatabase {
  [key: string]: unknown;
  columnType?: string;
  nullable?: boolean;
  comment?: string;
  unsigned?: boolean;
  index?: 'none' | 'index' | 'unique';
  relation?: FormSchemaRelationConfig;
}

/** 字段节点 list 切片（列表列）；未列出的键原样保留。 */
export interface FormSchemaListColumn {
  [key: string]: unknown;
  show?: boolean;
  sort?: boolean;
  filter?: string;
  formatter?: string;
  width?: number;
}

export interface FormSchemaNode {
  id: string;
  kind: FormNodeKind;
  type: string;
  field?: string | null;
  title: string;
  defaultValue?: unknown;
  valueType?: FormValueType;
  props?: Record<string, unknown>;
  attrs?: Record<string, unknown>;
  className?: string;
  style?: Record<string, string | number>;
  hidden?: boolean;
  disabled?: boolean;
  info?: string;
  slot?: string | null;
  children: FormSchemaNode[];
  validation?: FormSchemaValidationRule[];
  dataSource?: FormSchemaDataSource | null;
  conditions?: unknown[];
  events?: Record<string, unknown[]>;
  access?: FormSchemaAccess;
  layout?: { span?: FormResponsiveSpan; group?: string };
  database?: FormSchemaDatabase;
  list?: FormSchemaListColumn;
}

export interface FormLeftTreeConfiguration {
  enabled: boolean;
  source: { type: 'current' | 'module'; module?: string };
  mapping: { valueField: string; labelField: string; targetField: string; parentField?: string; sortField?: string };
  selection?: { mode?: 'single' | 'multiple'; includeDescendants?: boolean };
  actions?: Partial<Record<'create' | 'addChild' | 'edit' | 'delete', boolean>>;
}

export type FormListButtonLocation = 'toolbar' | 'row' | 'categoryToolbar' | 'categoryNode';
export type FormListBuiltinAction = 'create' | 'edit' | 'detail' | 'delete' | 'batchDelete' | 'import' | 'export' | 'recycle' | 'normal' | 'restore' | 'destroy' | 'addChild' | 'copyCreate';
export type FormListButtonAction =
  | { type: 'builtin'; key: FormListBuiltinAction }
  | { type: 'registered'; key: string; capabilityVersion: string }
  | { type: 'navigate' | 'external'; key: string; capabilityVersion: string }
    | { type: 'download'; key: string }
  | { type: 'form' | 'detail' | 'copy' | 'refresh' };
export type FormListParameterBinding =
  | { source: 'literal'; value: string | number | boolean | null }
  | { source: 'selection'; field: 'ids' }
  | { source: 'row' | 'filter' | 'category' | 'form'; field: string };
export interface FormListActionMetadata {
  permission: string;
  capabilityVersion: string;
  parameters: string[];
  parameterTypes: Record<string, 'string' | 'integer' | 'number' | 'boolean' | 'ids'>;
  locations: string[];
  targets: string[];
  batch: boolean;
  effect: string;
  resultContract: string;
  requiresConfirmation: boolean;
}
export interface FormListInputField {
  name: string;
  label: string;
  type: 'input' | 'textarea' | 'number' | 'select' | 'switch' | 'date';
  required?: boolean;
  min?: number;
  max?: number;
  maxLength?: number;
  options?: Array<{ label: string; value: string | number }>;
}
export interface FormListButtonInteraction {
  type: 'none' | 'confirm' | 'input' | 'form';
  presentation?: 'dialog' | 'drawer';
  title?: string;
  message?: string;
  fields?: FormListInputField[];
}
export interface FormListButton {
  id: string;
  label: string;
  icon?: string;
  color?: 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info';
  plain?: boolean;
  size?: 'small' | 'default' | 'large';
  tips?: string;
  placement?: 'inline' | 'more';
  order?: number;
  hidden?: boolean;
  disabled?: boolean;
  disabledReason?: string;
  permission?: string;
  visibleWhen?: FormSchemaCondition;
  disabledWhen?: FormSchemaCondition;
  interaction?: FormListButtonInteraction;
  selection?: { min?: number; max?: number };
  action: FormListButtonAction;
  params?: Record<string, FormListParameterBinding>;
  success?: { message?: string; refresh?: boolean; clearSelection?: boolean; close?: boolean };
}

/** 设计器新建的列表自定义动作；正式生成后得到注册动作 business.{entity}.{key}（toolbar 动作总是批量）。 */
export type FormListCustomActionLocation = 'row' | 'toolbar';
export type FormListCustomActionParameterType = 'string' | 'integer' | 'number' | 'boolean';
export interface FormListCustomAction {
  key: string;
  label: string;
  locations: FormListCustomActionLocation[];
  batch: boolean;
  requiresConfirmation: boolean;
  effect: 'write' | 'read';
  parameters: Array<{ name: string; type: FormListCustomActionParameterType }>;
}

export interface FormListConfiguration {
  buttons?: Partial<Record<FormListButtonLocation, FormListButton[]>>;
  customActions?: FormListCustomAction[];
  tools?: Partial<Record<'refresh' | 'search' | 'columns' | 'density' | 'fullscreen', boolean>>;
  leftTree?: FormLeftTreeConfiguration;
  category?: { enabled: boolean; field?: string };
  tree?: { enabled: boolean; parentField?: string; selectionMode?: 'single' | 'multiple' };
  [key: string]: unknown;
}

/** 仅生成模型关联方法的顶层关联（hasOne / 模型级 hasMany / belongsToMany）。 */
export interface FormSchemaRelation {
  type: 'hasOne' | 'hasMany' | 'belongsToMany';
  name: string;
  table: string;
  localField: string;
  foreignField: string;
  pivot?: { table: string; localKey: string; foreignKey: string };
}

export interface FormSchemaDocument {
  list?: FormListConfiguration;
  schemaVersion: 2;
  key: string;
  title: string;
  nodes: FormSchemaNode[];
  form?: {
    labelWidth?: string | number;
    labelPosition?: 'left' | 'right' | 'top';
    size?: 'large' | 'default' | 'small';
    inline?: boolean;
    readOnly?: boolean;
    gutter?: number;
    [key: string]: unknown;
  };
  actions?: unknown[];
  submit?: Record<string, unknown>;
  relations?: FormSchemaRelation[];
}

export const flattenSchemaNodes = (
  nodes: FormSchemaNode[],
  parentPath = '/nodes'
): Array<{ node: FormSchemaNode; path: string }> => nodes.flatMap((node, index) => {
  const path = `${parentPath}/${index}`;
  return [{ node, path }, ...flattenSchemaNodes(node.children ?? [], `${path}/children`)];
});
