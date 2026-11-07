import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import { isFieldContainer, walkFieldNodes } from './fieldTable';

/** 主表的普通字段节点（不含子表容器），供配置下拉选择。 */
export const schemaFieldNodes = (doc: FormSchemaDocument): FormSchemaNode[] => walkFieldNodes(doc.nodes)
  .map(({ node }) => node)
  .filter((node) => !isFieldContainer(node) && Boolean(node.field));

const columnTypeOf = (node: FormSchemaNode): string => String(node.database?.columnType ?? '');

/** 会员归属须为整数列，与后端 DefinitionValidator 一致。 */
export const integerFieldNodes = (nodes: FormSchemaNode[]): FormSchemaNode[] => nodes
  .filter((node) => /^(?:tinyint|smallint|mediumint|int|integer|bigint)\b/i.test(columnTypeOf(node)));

const TREE_EXCLUDED_TYPES = new Set(['password', 'repeatable', 'subform', 'json', 'checkbox', 'transfer']);

/** 与设计器 treeParentFieldOptions 一致：可存储父记录主键的单值字段。 */
export const treeParentFieldNodes = (nodes: FormSchemaNode[]): FormSchemaNode[] => nodes.filter((node) => {
  const props = node.props ?? {};
  const relation = (node.database?.relation as Record<string, unknown> | undefined)?.type ?? 'none';
  return Boolean(columnTypeOf(node)) && !TREE_EXCLUDED_TYPES.has(node.type)
    && !props.multiple && !props.sensitive && !props.writeOnly && relation === 'none';
});

export const fieldLabel = (node: FormSchemaNode): string => node.title && node.title !== node.field ? `${node.title}（${node.field}）` : String(node.field);
