import type { ActionType, FormAction } from '../runtime/actionExecutor';
import type { DataSourceKind } from '../dataSource/dataSourceRegistry';

export const VALIDATION_TYPES = [
  'required', 'type', 'min', 'max', 'minLength', 'maxLength', 'length', 'enum', 'pattern',
  'format', 'same', 'different', 'before', 'after', 'precision', 'array', 'object'
] as const;
export const VALIDATION_TRIGGERS = ['change', 'blur', 'submit'] as const;
export const CONDITION_OPERATORS = [
  'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'contains', 'startsWith', 'endsWith', 'empty', 'notEmpty', 'matches'
] as const;
export const CONDITION_ACTIONS = ['show', 'hide', 'enable', 'disable', 'setRequired', 'setValue', 'clearValue'] as const;
export const DATA_SOURCE_KINDS: DataSourceKind[] = ['static', 'dictionary', 'department', 'user', 'relation', 'endpoint', 'computed'];

export interface DynamicPropertyField {
  name: string;
  title: string;
  type: 'string' | 'number' | 'boolean' | 'select' | 'json';
  description?: string;
  options?: unknown[];
  minimum?: number;
  maximum?: number;
  defaultValue?: unknown;
}

interface PropertyDefinition {
  type?: string;
  title?: string;
  description?: string;
  enum?: unknown[];
  minimum?: number;
  maximum?: number;
  default?: unknown;
}

interface PropertySchema {
  properties?: Record<string, PropertyDefinition>;
}

export const normalizePropertySchema = (schema: Readonly<Record<string, unknown>>): DynamicPropertyField[] => {
  const properties = (schema as PropertySchema).properties ?? {};
  return Object.entries(properties).map(([name, definition]) => ({
    name,
    title: definition.title ?? name,
    type: definition.enum ? 'select'
      : ['integer', 'number'].includes(definition.type ?? '') ? 'number'
        : definition.type === 'boolean' ? 'boolean'
          : ['object', 'array'].includes(definition.type ?? '') ? 'json' : 'string',
    description: definition.description,
    options: definition.enum,
    minimum: definition.minimum,
    maximum: definition.maximum,
    defaultValue: definition.default
  }));
};

export const patchDynamicProperty = (
  source: Record<string, unknown> | null | undefined,
  name: string,
  value: unknown
): Record<string, unknown> => {
  const result = { ...(source ?? {}) };
  if (value === undefined || value === '') delete result[name];
  else result[name] = value;
  return result;
};

export interface DesignerValidationRule {
  type: string;
  trigger: string[];
  message: string;
  value: unknown;
  condition: string;
  severity: 'error' | 'warning';
  bail: boolean;
}

export const createValidationRule = (): DesignerValidationRule => ({
  type: 'required', trigger: ['change'], message: '', value: '', condition: '', severity: 'error', bail: true
});

export interface ComparisonCondition {
  field: string;
  op: string;
  value?: unknown;
}

export interface ConditionGroup {
  op: 'and' | 'or';
  conditions: ComparisonCondition[];
}

export interface DesignerConditionRule {
  when: ConditionGroup;
  then: { action: string; target: string; value?: unknown; fromField?: string };
}

export const createConditionRule = (): DesignerConditionRule => ({
  when: { op: 'and', conditions: [{ field: '', op: 'eq', value: '' }] },
  then: { action: 'show', target: '' }
});

interface NodeConditions {
  field: string;
  conditions: Array<{ then?: { target?: string } }>;
}

export const detectConditionCycles = (nodes: NodeConditions[]): string[] => {
  const graph = new Map(nodes.map((node) => [node.field, node.conditions.map((rule) => rule.then?.target ?? '').filter(Boolean)]));
  const cyclic = new Set<string>();
  const visit = (field: string, path: string[]): void => {
    const loopAt = path.indexOf(field);
    if (loopAt >= 0) {
      path.slice(loopAt).forEach((item) => cyclic.add(item));
      return;
    }
    for (const target of graph.get(field) ?? []) visit(target, [...path, field]);
  };
  graph.forEach((_, field) => visit(field, []));
  return [...cyclic].sort();
};

export interface ActionParameterField {
  name: string;
  type: 'string' | 'boolean' | 'select' | 'json';
  options?: readonly string[];
}

export const ACTION_PARAMETER_SCHEMAS: Record<ActionType, ActionParameterField[]> = {
  setValue: [{ name: 'target', type: 'string' }, { name: 'value', type: 'json' }],
  copyValue: [{ name: 'target', type: 'string' }, { name: 'from', type: 'string' }],
  clearValue: [{ name: 'target', type: 'string' }],
  show: [{ name: 'target', type: 'string' }],
  hide: [{ name: 'target', type: 'string' }],
  enable: [{ name: 'target', type: 'string' }],
  disable: [{ name: 'target', type: 'string' }],
  setRequired: [{ name: 'target', type: 'string' }, { name: 'required', type: 'boolean' }],
  validate: [{ name: 'target', type: 'string' }],
  request: [{ name: 'key', type: 'string' }, { name: 'concurrency', type: 'select', options: ['parallel', 'latest', 'queue', 'drop'] }],
  notify: [{ name: 'message', type: 'string' }, { name: 'level', type: 'select', options: ['success', 'warning', 'error', 'info'] }],
  openDialog: [{ name: 'key', type: 'string' }],
  navigate: [{ name: 'to', type: 'string' }],
  submit: [],
  reset: []
};

export const createEventAction = (type: ActionType): FormAction => {
  if (type === 'request') return { type, key: '', concurrency: 'latest' };
  const action: FormAction = { type };
  for (const parameter of ACTION_PARAMETER_SCHEMAS[type]) {
    if (!(parameter.name in action)) action[parameter.name] = parameter.type === 'boolean' ? false : '';
  }
  return action;
};

export interface DesignerDataSource {
  kind: DataSourceKind;
  endpoint?: string;
  params: Record<string, unknown>;
  response: { items: string; label: string; value: string; disabled?: string };
  dependsOn: string[];
  searchable: boolean;
  pagination: { pageSize: number };
  cacheTtl: number;
  staleValue: 'clear' | 'retain' | 'revalidate';
  [key: string]: unknown;
}

export const createDataSource = (kind: DataSourceKind): DesignerDataSource => ({
  kind,
  ...(kind === 'endpoint' ? { endpoint: '' } : {}),
  params: {},
  response: { items: 'data', label: 'label', value: 'value' },
  dependsOn: [],
  searchable: false,
  pagination: { pageSize: 20 },
  cacheTtl: 0,
  staleValue: 'clear'
});
