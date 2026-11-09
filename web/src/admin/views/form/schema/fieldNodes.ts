import type { FormSchemaNode, FormSchemaRelationType } from './types';

const RELATION_CONTAINER_TYPES = new Set(['repeatable', 'subform']);

const truthy = (value: unknown): boolean => value === true || value === 1 || value === '1';

/**
 * 主表字段节点，与后端 FormSchema::fields() 同一遍历规则：按文档顺序，
 * kind 为 layout 的节点跳过并展开其子节点，其余节点均为字段；repeatable/subform 的子节点不展开。
 */
export const fieldNodes = (nodes: FormSchemaNode[]): FormSchemaNode[] => nodes.flatMap((node) => {
  if (node.kind === 'layout') return fieldNodes(node.children ?? []);
  if (RELATION_CONTAINER_TYPES.has(node.type)) return [node];
  return [node, ...fieldNodes(node.children ?? [])];
});

/** 敏感字段：password 控件，或 props.sensitive / props.writeOnly 为 true | 1 | '1'。 */
export const isSensitiveNode = (node: FormSchemaNode): boolean => {
  const props = node.props ?? {};
  return node.type === 'password' || truthy(props.sensitive) || truthy(props.writeOnly);
};

export const isRequiredNode = (node: FormSchemaNode): boolean =>
  (node.validation ?? []).some((rule) => rule.type === 'required');

export const relationTypeOf = (node: FormSchemaNode): FormSchemaRelationType =>
  node.database?.relation?.type ?? 'none';

export const columnTypeOf = (node: FormSchemaNode): string => String(node.database?.columnType ?? '');
