export type FormNodeKind = 'field' | 'layout';
export type FormValueType = 'string' | 'number' | 'boolean' | 'array' | 'object';

export interface FormSchemaValidationRule {
  type: string;
  value?: unknown;
  message?: string;
  trigger?: string[];
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
  dataSource?: Record<string, unknown> | null;
  conditions?: unknown[];
  events?: Record<string, unknown[]>;
  layout?: { span?: number; group?: string };
}

export interface FormSchemaDocument {
  schemaVersion: 2;
  key: string;
  title: string;
  nodes: FormSchemaNode[];
  form?: Record<string, unknown>;
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
