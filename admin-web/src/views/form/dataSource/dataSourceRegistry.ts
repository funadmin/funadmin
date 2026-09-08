export type DataSourceKind = 'static' | 'dictionary' | 'department' | 'user' | 'relation' | 'endpoint' | 'computed';
export type StaleValuePolicy = 'clear' | 'retain' | 'revalidate';

export interface DataSourceResponseMapping {
  items: string;
  label: string;
  value: string;
  disabled?: string;
}

export interface DataSourceDefinition {
  kind: DataSourceKind;
  options?: unknown[];
  dictionary?: string;
  root?: unknown;
  relation?: string;
  endpoint?: string;
  operation?: string;
  inputs?: unknown[];
  params?: Record<string, unknown>;
  response?: Partial<DataSourceResponseMapping>;
  staleValue?: StaleValuePolicy;
}

export interface DataSourceContext {
  form?: Record<string, unknown>;
  context?: Record<string, unknown>;
  search?: unknown;
}

export interface EndpointMetadata {
  permission: string;
  parameters: readonly string[];
}

export interface ResolvedDataSource {
  kind: DataSourceKind;
  provider?: string;
  endpoint?: string;
  operation?: string;
  arguments?: Record<string, unknown>;
  options?: DataSourceOption[];
  response: DataSourceResponseMapping;
  permission?: string;
  staleValue: StaleValuePolicy;
}

export interface DataSourceOption {
  label: unknown;
  value: unknown;
  disabled?: boolean;
}

const kinds: readonly DataSourceKind[] = ['static', 'dictionary', 'department', 'user', 'relation', 'endpoint', 'computed'];
const stalePolicies: readonly StaleValuePolicy[] = ['clear', 'retain', 'revalidate'];
const providerParameters: Partial<Record<DataSourceKind, readonly string[]>> = {
  dictionary: ['locale'],
  department: ['root', 'keyword'],
  user: ['keyword', 'department_id'],
  relation: ['keyword', 'tenant_id', 'parent_id']
};
const defaultResponse: DataSourceResponseMapping = { items: 'data', label: 'label', value: 'value' };
const safePathPattern = /^(?!.*(?:^|\.)(?:__proto__|prototype|constructor)(?:\.|$))[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/;

const fail = (code: string): never => {
  throw new Error(code);
};

const isSafePath = (path: string): boolean => path === '$' || safePathPattern.test(path);

const readPath = (subject: unknown, path: string): unknown => {
  if (path === '$') return subject;
  return path.split('.').reduce<unknown>((value, segment) => {
    if (!value || typeof value !== 'object' || !(segment in value)) return undefined;
    return (value as Record<string, unknown>)[segment];
  }, subject);
};

const responseMapping = (mapping: Partial<DataSourceResponseMapping> = {}): DataSourceResponseMapping => {
  const result = { ...defaultResponse, ...mapping };
  if (Object.values(result).some((path) => typeof path !== 'string' || !isSafePath(path))) {
    return fail('FORM_DATA_SOURCE_RESPONSE_MAPPING_INVALID');
  }
  return result;
};

const resolveValue = (value: unknown, context: DataSourceContext): unknown => {
  if (typeof value !== 'string' || !value.startsWith('$')) return value;
  if (value === '$search') return context.search;
  return readPath(context, value.slice(1));
};

const mapParameters = (
  parameters: Record<string, unknown> = {},
  context: DataSourceContext,
  allowed: readonly string[]
): Record<string, unknown> => Object.fromEntries(
  Object.entries(parameters)
    .filter(([name]) => allowed.includes(name))
    .map(([name, value]) => [name, resolveValue(value, context)])
);

export const applyStaleValuePolicy = (
  policy: StaleValuePolicy,
  oldValue: unknown,
  validValues: readonly unknown[]
): unknown => {
  if (!stalePolicies.includes(policy)) return fail('FORM_DATA_SOURCE_STALE_POLICY_INVALID');
  if (policy === 'retain') return oldValue;
  if (policy === 'revalidate' && validValues.includes(oldValue)) return oldValue;
  return null;
};

export const mapDataSourceResponse = (payload: unknown, mapping: DataSourceResponseMapping): DataSourceOption[] => {
  const items = readPath(payload, mapping.items);
  if (!Array.isArray(items)) return [];
  return items.filter((item) => item && typeof item === 'object').map((item) => {
    const option: DataSourceOption = {
      label: readPath(item, mapping.label),
      value: readPath(item, mapping.value)
    };
    if (mapping.disabled) option.disabled = Boolean(readPath(item, mapping.disabled));
    return option;
  });
};

export const createCoreDataSourceRegistry = (endpoints: Record<string, EndpointMetadata> = {}) => ({
  kinds: (): DataSourceKind[] => [...kinds],
  resolve: (definition: DataSourceDefinition, context: DataSourceContext = {}): ResolvedDataSource => {
    if (!kinds.includes(definition.kind)) return fail('FORM_DATA_SOURCE_NOT_REGISTERED');
    const staleValue = definition.staleValue ?? 'clear';
    if (!stalePolicies.includes(staleValue)) return fail('FORM_DATA_SOURCE_STALE_POLICY_INVALID');
    const response = responseMapping(definition.response);

    if (definition.kind === 'static') {
      return { kind: 'static', options: mapDataSourceResponse(definition.options ?? [], response), response, staleValue };
    }
    if (definition.kind === 'computed') {
      if (definition.operation !== 'concat') return fail('FORM_DATA_SOURCE_COMPUTATION_NOT_ALLOWED');
      const value = (definition.inputs ?? []).map((input) => resolveValue(input, context) ?? '').join('');
      return { kind: 'computed', operation: 'concat', options: [{ label: value, value }], response, staleValue };
    }
    if (definition.kind === 'endpoint') {
      const endpoint = definition.endpoint ?? '';
      if (/^https?:\/\//i.test(endpoint) || !endpoints[endpoint]) return fail('FORM_DATA_SOURCE_ENDPOINT_NOT_ALLOWED');
      const metadata = endpoints[endpoint];
      return {
        kind: 'endpoint', endpoint,
        arguments: mapParameters(definition.params, context, metadata.parameters),
        response, permission: metadata.permission, staleValue
      };
    }

    const fixed = definition.kind === 'dictionary'
      ? { code: definition.dictionary }
      : definition.kind === 'department'
        ? { root: definition.root }
        : definition.kind === 'relation'
          ? { relation: definition.relation }
          : {};
    const mapped = mapParameters(definition.params, context, providerParameters[definition.kind] ?? []);
    const argumentsValue = Object.fromEntries(Object.entries({ ...fixed, ...mapped }).filter(([, value]) => value !== undefined && value !== ''));
    return { kind: definition.kind, provider: definition.kind, arguments: argumentsValue, response, staleValue };
  }
});
