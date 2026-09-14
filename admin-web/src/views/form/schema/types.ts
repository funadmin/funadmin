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
  mode?: string;
  options?: unknown[];
  dependsOn?: string[];
  params?: Record<string, unknown>;
  staleValue?: 'clear' | 'retain' | 'revalidate';
  debounce?: number;
  cacheTtl?: number;
  pagination?: { pageSize?: number };
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
  layout?: { span?: FormResponsiveSpan; group?: string };
  database?: Record<string, unknown>;
  list?: Record<string, unknown>;
}

export interface FormLeftTreeConfiguration {
  enabled: boolean;
  source: { type: 'current' | 'module'; module?: string };
  mapping: { valueField: string; labelField: string; targetField: string; parentField?: string; sortField?: string };
  selection?: { mode?: 'single' | 'multiple'; includeDescendants?: boolean };
  actions?: Partial<Record<'create' | 'addChild' | 'edit' | 'delete', boolean>>;
}

export type FormListButtonLocation = 'toolbar' | 'row' | 'categoryToolbar' | 'categoryNode';
export type FormListBuiltinAction = 'create' | 'edit' | 'detail' | 'delete' | 'batchDelete' | 'import' | 'export' | 'recycle' | 'restore' | 'destroy' | 'addChild';
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
  action: FormListButtonAction;
  params?: Record<string, FormListParameterBinding>;
  success?: { message?: string; refresh?: boolean; clearSelection?: boolean; close?: boolean };
}

export interface FormListConfiguration {
  buttons?: Partial<Record<FormListButtonLocation, FormListButton[]>>;
  tools?: Partial<Record<'refresh' | 'search' | 'columns' | 'density' | 'fullscreen', boolean>>;
  leftTree?: FormLeftTreeConfiguration;
  category?: { enabled: boolean; field?: string };
  tree?: { enabled: boolean; parentField?: string };
  [key: string]: unknown;
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
}

export const flattenSchemaNodes = (
  nodes: FormSchemaNode[],
  parentPath = '/nodes'
): Array<{ node: FormSchemaNode; path: string }> => nodes.flatMap((node, index) => {
  const path = `${parentPath}/${index}`;
  return [{ node, path }, ...flattenSchemaNodes(node.children ?? [], `${path}/children`)];
});
