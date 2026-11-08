import type { FormLeftTreeConfiguration, FormSchemaDocument } from './types';

/** 后端（FormSchemaValidator）对已开启的左侧分类树要求非空的映射键，顺序即提示顺序。 */
export const LEFT_TREE_REQUIRED_MAPPINGS = ['valueField', 'labelField', 'targetField'] as const;
export type LeftTreeRequiredMapping = typeof LEFT_TREE_REQUIRED_MAPPINGS[number];

const LABELS: Record<LeftTreeRequiredMapping, [key: string, fallback: string]> = {
  valueField: ['formDesigner.listPanel.sourcePrimaryKey', '来源主键'],
  labelField: ['formDesigner.listPanel.labelField', '显示字段'],
  targetField: ['formDesigner.listPanel.targetField', '右表关联字段']
};

/**
 * 保存前兜底：已开启的分类树缺必填映射时后端会拒绝整份 Schema（「字段映射不能为空」）。
 * 设计器界面不会写出这种状态，但旧页面、撤销历史、离线草稿、版本回滚仍可能带来；
 * 此时按未开启保存（映射保留为草稿），并返回缺少的键供提示。无需处理时原样返回同一引用。
 */
// 设计器 store 与代码生成各用一份同构的文档类型（list 声明相同），按泛型保留调用方类型。
export function sanitizeLeftTreeForSave<T extends Pick<FormSchemaDocument, 'list'> = FormSchemaDocument>(doc: T): { doc: T; pendingMissing: LeftTreeRequiredMapping[] } {
  const leftTree = doc.list?.leftTree;
  if (!leftTree || leftTree.enabled !== true) return { doc, pendingMissing: [] };
  const mapping = (leftTree.mapping ?? {}) as Partial<FormLeftTreeConfiguration['mapping']>;
  const pendingMissing = LEFT_TREE_REQUIRED_MAPPINGS.filter((key) => !mapping[key]);
  if (!pendingMissing.length) return { doc, pendingMissing };
  return { doc: { ...doc, list: { ...doc.list, leftTree: { ...leftTree, enabled: false } } }, pendingMissing };
}

/** 缺少的映射键转为界面名称，以「 / 」连接。 */
export const leftTreeMissingLabels = (t: (key: string, fallback: string) => string, missing: readonly string[]): string => LEFT_TREE_REQUIRED_MAPPINGS
  .filter((key) => missing.includes(key))
  .map((key) => t(...LABELS[key]))
  .join(' / ');
