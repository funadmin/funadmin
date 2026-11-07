import { controlMeta } from '@/views/form/registry';
import type { FormSchemaDocument, FormSchemaNode, FormSchemaValidationRule } from '@/views/form/schema/types';

export interface FieldRow {
  path: string;
  parentPath: string;
  field: string;
  title: string;
  columnType: string;
  required: boolean;
  inForm: boolean;
  inList: boolean;
  /** '' 表示不可查询，否则为 LIST_FILTERS 中的操作符 */
  filter: string;
  sortable: boolean;
  component: string;
  dictionary: string;
  listWidth: number;
  groupTitle: string;
  container: boolean;
}

export type FieldRowPatch = Partial<Pick<FieldRow, 'title' | 'required' | 'inForm' | 'inList' | 'filter' | 'sortable' | 'component' | 'dictionary' | 'listWidth'>>;

export interface FieldNodeEntry {
  node: FormSchemaNode;
  path: string;
  parentPath: string;
  groupTitle: string;
}

const FIELD_CONTAINER_TYPES = new Set(['repeatable', 'subform']);
const TITLED_LAYOUT_TYPES = new Set(['group', 'collapse', 'tabs']);

export const isFieldContainer = (node: FormSchemaNode): boolean => FIELD_CONTAINER_TYPES.has(node.type);

/** 列出主表字段节点：布局容器递归展开，repeatable/subform 只占一行、子字段不展开。 */
export const walkFieldNodes = (
  nodes: FormSchemaNode[],
  parentPath = '/nodes',
  groupTitle = '',
  parentType = ''
): FieldNodeEntry[] => nodes.flatMap((node, index) => {
  const path = `${parentPath}/${index}`;
  if (node.kind === 'layout') {
    // 标签页的直接子节点标题即页签名
    const title = TITLED_LAYOUT_TYPES.has(node.type) || parentType === 'tabs' ? node.title : groupTitle;
    return walkFieldNodes(node.children ?? [], `${path}/children`, title, node.type);
  }
  return [{ node, path, parentPath, groupTitle }];
});

const pathIndices = (path: string): number[] => {
  const parts = path.split('/').filter(Boolean);
  if (parts[0] !== 'nodes' || parts.length % 2 !== 0) throw new Error(`无效的节点路径：${path}`);
  return parts.filter((_, index) => index % 2 === 1).map((part, index) => {
    if (index > 0 && parts[index * 2] !== 'children') throw new Error(`无效的节点路径：${path}`);
    if (!/^\d+$/.test(part)) throw new Error(`无效的节点路径：${path}`);
    return Number(part);
  });
};

/** 结构共享地替换 parentIndices 所指容器的子节点数组；未经过的节点保持原引用。 */
const updateSiblings = (
  nodes: FormSchemaNode[],
  parentIndices: number[],
  update: (siblings: FormSchemaNode[]) => FormSchemaNode[]
): FormSchemaNode[] => {
  if (!parentIndices.length) return update(nodes);
  const [index, ...rest] = parentIndices;
  const node = nodes[index];
  if (!node) throw new Error('节点路径不存在');
  const next = nodes.slice();
  next[index] = { ...node, children: updateSiblings(node.children ?? [], rest, update) };
  return next;
};

export const updateNodeAtPath = (
  doc: FormSchemaDocument,
  path: string,
  update: (node: FormSchemaNode) => FormSchemaNode
): FormSchemaDocument => {
  const indices = pathIndices(path);
  const last = indices[indices.length - 1];
  const nodes = updateSiblings(doc.nodes, indices.slice(0, -1), (siblings) => {
    if (!siblings[last]) throw new Error(`节点路径不存在：${path}`);
    const next = siblings.slice();
    next[last] = update(siblings[last]);
    return next;
  });
  return { ...doc, nodes };
};

export const removeNodeAtPath = (doc: FormSchemaDocument, path: string): FormSchemaDocument => {
  const indices = pathIndices(path);
  const last = indices[indices.length - 1];
  const nodes = updateSiblings(doc.nodes, indices.slice(0, -1), (siblings) => siblings.filter((_, index) => index !== last));
  return { ...doc, nodes };
};

/**
 * 对应 PropsPanel.onTypeChange：切换控件时重置为该控件的默认选项来源与属性。
 * 有意不改 database.columnType：表格/关联编辑不应静默改动已有列的物理类型。
 */
export const controlChange = (node: FormSchemaNode, type: string): FormSchemaNode => {
  const meta = controlMeta(type);
  if (meta.type !== type) throw new Error(`未知控件：${type}`);
  if (meta.kind === 'layout' || meta.kind === 'relation-container') throw new Error(`字段不能切换为容器控件：${type}`);
  const next: FormSchemaNode = {
    ...node,
    type,
    dataSource: meta.defaultOptions === null ? null : structuredClone(meta.defaultOptions),
    props: structuredClone(meta.defaultProps ?? {})
  };
  // 与设计器一致：valueType 取自控件元信息，未声明则不保留
  if (meta.valueType) next.valueType = meta.valueType as FormSchemaNode['valueType'];
  else Reflect.deleteProperty(next, 'valueType');
  return next;
};

export const dataSourceKind = (source: Record<string, unknown> | null | undefined): string => String(source?.kind ?? source?.mode ?? '');

const nodeToRow = ({ node, path, parentPath, groupTitle }: FieldNodeEntry): FieldRow => {
  const list = node.list ?? {};
  const source = node.dataSource as Record<string, unknown> | null | undefined;
  return {
    path,
    parentPath,
    field: String(node.field ?? ''),
    title: node.title,
    columnType: String(node.database?.columnType ?? ''),
    required: (node.validation ?? []).some((rule) => rule.type === 'required'),
    inForm: node.hidden !== true,
    inList: list.show === true,
    filter: String(list.filter ?? ''),
    sortable: list.sort === true,
    component: node.type,
    dictionary: dataSourceKind(source) === 'dictionary' ? String(source?.dictionary ?? '') : '',
    listWidth: Number(list.width ?? 0),
    groupTitle,
    container: isFieldContainer(node)
  };
};

export const schemaToFieldRows = (doc: FormSchemaDocument): FieldRow[] => walkFieldNodes(doc.nodes).map(nodeToRow);

const withRequired = (rules: FormSchemaValidationRule[], required: boolean): FormSchemaValidationRule[] => required
  ? [{ type: 'required', value: true }, ...rules]
  : rules.filter((rule) => rule.type !== 'required');

/** 只把改动映射到对应的节点切片并合并回原节点；值未变化的列不触碰，全部未变时返回原节点。 */
const applyRowPatch = (node: FormSchemaNode, row: FieldRow, patch: FieldRowPatch): FormSchemaNode => {
  const changed = <K extends keyof FieldRowPatch>(key: K): boolean => patch[key] !== undefined && patch[key] !== row[key];
  let next = node;
  if (changed('component')) next = controlChange(next, patch.component as string);
  if (changed('title')) next = { ...next, title: patch.title as string };
  if (changed('required')) next = { ...next, validation: withRequired(next.validation ?? [], patch.required as boolean) };
  if (changed('inForm')) next = { ...next, hidden: !patch.inForm };
  const list: Record<string, unknown> = {};
  if (changed('inList')) list.show = patch.inList;
  if (changed('filter')) list.filter = patch.filter;
  if (changed('sortable')) list.sort = patch.sortable;
  if (changed('listWidth')) list.width = patch.listWidth;
  if (Object.keys(list).length) next = { ...next, list: { ...next.list, ...list } };
  if (patch.dictionary !== undefined && (changed('dictionary') || changed('component'))) {
    const current = (next.dataSource ?? {}) as Record<string, unknown>;
    // 与属性面板 updateDictionary 一致：收敛为 kind 键，保留数据源其余键
    if (patch.dictionary || dataSourceKind(current) === 'dictionary') {
      const { mode: _legacyMode, ...rest } = current;
      next = { ...next, dataSource: { ...rest, kind: 'dictionary', dictionary: patch.dictionary } };
    }
  }
  return next;
};

/** 按行路径写回字段表格改动；只改动到的属性被写入，其他节点保持原引用。 */
export const applyFieldPatches = (doc: FormSchemaDocument, patches: Record<string, FieldRowPatch>): FormSchemaDocument => {
  const rows = new Map(schemaToFieldRows(doc).map((row) => [row.path, row]));
  return Object.entries(patches).reduce((current, [path, patch]) => {
    const row = rows.get(path);
    if (!row) throw new Error(`字段行不存在：${path}`);
    if (row.container) throw new Error(`子表容器请在设计器中编辑：${path}`);
    let unchanged = false;
    const next = updateNodeAtPath(current, path, (node) => {
      const patched = applyRowPatch(node, row, patch);
      unchanged = patched === node;
      return patched;
    });
    return unchanged ? current : next;
  }, doc);
};

/**
 * 在同一父容器内移动字段。
 * @param path 源行路径（FieldRow.path）
 * @param toIndex 字段表格的行序号（schemaToFieldRows 结果中的下标，即拖拽目标行），不是兄弟数组下标；
 *   目标行必须与源行同一父容器，否则抛错（跨容器移动请使用设计器）。
 */
export const moveFieldWithinParent = (doc: FormSchemaDocument, path: string, toIndex: number): FormSchemaDocument => {
  const rows = schemaToFieldRows(doc);
  const source = rows.find((row) => row.path === path);
  const target = rows[toIndex];
  if (!source) throw new Error(`字段行不存在：${path}`);
  if (!target) throw new Error(`目标行不存在：${toIndex}`);
  if (target.parentPath !== source.parentPath) throw new Error('只能在同一父容器内调整顺序，跨容器移动请使用表单设计器');
  const indices = pathIndices(source.path);
  const from = indices[indices.length - 1];
  const to = pathIndices(target.path).at(-1) as number;
  if (from === to) return doc;
  const nodes = updateSiblings(doc.nodes, indices.slice(0, -1), (siblings) => {
    const next = siblings.slice();
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    return next;
  });
  return { ...doc, nodes };
};
