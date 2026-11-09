import { fieldNodes, isSensitiveNode, relationTypeOf } from '../schema/fieldNodes';
import type { FormSchemaNode } from '../schema/types';

const truthy = (value: unknown): boolean => value === true || value === 1 || value === '1';

const isExcludedByDefault = (node: FormSchemaNode): boolean => {
  const props = node.props ?? {};
  return node.type === 'hidden'
    || node.disabled === true
    || truthy(props.disabled)
    || node.dataSource?.kind === 'computed'
    || truthy(props.computed)
    || truthy(props.primary)
    || truthy(props.system);
};

const fieldName = (node: FormSchemaNode): string => String(node.field ?? '');

/** 提交载荷：按 fieldNodes 遍历字段节点，access 策略优先于显式 include。 */
export const buildSubmissionPayload = (
  nodes: FormSchemaNode[],
  values: Record<string, unknown>,
  include: string[] = [],
  can: (permission: string) => boolean = () => false
): Record<string, unknown> => {
  const included = new Set(include);
  return Object.fromEntries(fieldNodes(nodes).flatMap((node) => {
    const name = fieldName(node);
    if (name === '' || !Object.prototype.hasOwnProperty.call(values, name)) return [];
    const access = node.access;
    if (access?.include === 'never' || (access?.write ?? []).some((permission) => !can(permission))) return [];
    if (isExcludedByDefault(node) && access?.include !== 'always' && !included.has(name)) return [];
    return [[name, values[name]]];
  }));
};

export const sanitizeRuntimeRecord = (
  nodes: FormSchemaNode[],
  record: Record<string, unknown>
): Record<string, unknown> => {
  const sensitive = new Set(fieldNodes(nodes).filter(isSensitiveNode).map(fieldName));
  return Object.fromEntries(Object.entries(record).filter(([name]) => !sensitive.has(name)));
};

const stringArray = (value: unknown): string[] => Array.isArray(value)
  ? value.filter((item): item is string => typeof item === 'string')
  : [];

export const resolveSubmissionInclude = (
  form?: { schema_document?: { submit?: { include?: unknown } } | null } | null
): string[] => stringArray(form?.schema_document?.submit?.include);

const canonicalRuntimeValue = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(canonicalRuntimeValue);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.entries(value as Record<string, unknown>)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([key, item]) => [key, canonicalRuntimeValue(item)]));
};

export const stableRuntimeValues = (values: Record<string, unknown>): string =>
  JSON.stringify(canonicalRuntimeValue(values));

export const emptyRuntimeValues = (nodes: FormSchemaNode[]): Record<string, unknown> => Object.fromEntries(
  fieldNodes(nodes).filter((node) => fieldName(node) !== '').map((node) => {
    const name = fieldName(node);
    if (isSensitiveNode(node)) return [name, ''];
    if (relationTypeOf(node) === 'has_many' || ['repeatable', 'subform'].includes(node.type)) return [name, []];
    if (node.type === 'switch') return [name, 0];
    if (node.type === 'number') return [name, undefined];
    return [name, node.defaultValue ?? ''];
  })
);
