import type { FormFieldDef } from '@/api/form';

const truthy = (value: unknown): boolean => value === true || value === 1 || value === '1';

export const isSensitiveField = (field: FormFieldDef): boolean => {
  const props = field.control_props ?? {};
  return field.type === 'password' || truthy(props.sensitive) || truthy(props.writeOnly);
};

const isExcludedByDefault = (field: FormFieldDef): boolean => {
  const props = field.control_props ?? {};
  const sourceKind = String(field.options_source?.kind ?? field.options_source?.mode ?? '');
  return field.type === 'hidden'
    || truthy(props.disabled)
    || field.form_readonly === 1
    || sourceKind === 'computed'
    || truthy(props.computed)
    || truthy(props.primary)
    || truthy(props.system);
};

export const buildSubmissionPayload = (
  fields: FormFieldDef[],
  values: Record<string, unknown>,
  include: string[] = []
): Record<string, unknown> => {
  const included = new Set(include);
  return Object.fromEntries(fields.flatMap((field) => {
    const name = field.field_name;
    if (!Object.prototype.hasOwnProperty.call(values, name)) return [];
    if (isExcludedByDefault(field) && !included.has(name)) return [];
    return [[name, values[name]]];
  }));
};

export const sanitizeRuntimeRecord = (
  fields: FormFieldDef[],
  record: Record<string, unknown>
): Record<string, unknown> => {
  const sensitive = new Set(fields.filter(isSensitiveField).map((field) => field.field_name));
  return Object.fromEntries(Object.entries(record).filter(([name]) => !sensitive.has(name)));
};

const stringArray = (value: unknown): string[] => Array.isArray(value)
  ? value.filter((item): item is string => typeof item === 'string')
  : [];

interface RuntimeSubmissionDefinition {
  schema_document?: { submit?: Record<string, unknown> } | null;
  form_config?: Record<string, unknown> | null;
}

export const resolveSubmissionInclude = (form?: RuntimeSubmissionDefinition | null): string[] => {
  const schemaInclude = stringArray(form?.schema_document?.submit?.include);
  if (schemaInclude.length) return schemaInclude;
  return stringArray(form?.form_config?.submitInclude);
};

const canonicalRuntimeValue = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(canonicalRuntimeValue);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.entries(value as Record<string, unknown>)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([key, item]) => [key, canonicalRuntimeValue(item)]));
};

export const stableRuntimeValues = (values: Record<string, unknown>): string =>
  JSON.stringify(canonicalRuntimeValue(values));

export const emptyRuntimeValues = (fields: FormFieldDef[]): Record<string, unknown> => Object.fromEntries(fields.map((field) => {
  if (isSensitiveField(field)) return [field.field_name, ''];
  if (field.relation_type === 'has_many' || ['repeatable', 'subform'].includes(field.type)) return [field.field_name, []];
  if (field.type === 'switch') return [field.field_name, 0];
  if (field.type === 'number') return [field.field_name, undefined];
  return [field.field_name, field.default_value ?? ''];
}));
