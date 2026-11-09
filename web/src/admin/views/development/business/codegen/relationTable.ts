import type { FormSchemaDocument, FormSchemaNode, FormSchemaRelation } from '@/views/form/schema/types';
import { changeControl } from '@/views/form/schema/nodeEdits';
import { dataSourceKind, isFieldContainer, updateNodeAtPath, walkFieldNodes } from './fieldTable';

export interface RelationRow {
  origin: 'field' | 'subform' | 'model';
  type: 'belongsTo' | 'hasOne' | 'hasMany' | 'belongsToMany';
  name: string;
  table: string;
  localField: string;
  foreignField: string;
  labelField?: string;
  pivot?: { table: string; localKey: string; foreignKey: string };
  path?: string;
  editable: boolean;
}

const MODEL_TYPES = new Set<RelationRow['type']>(['hasOne', 'hasMany', 'belongsToMany']);

const relationOf = (node: FormSchemaNode): Record<string, unknown> =>
  (node.database?.relation as Record<string, unknown> | undefined) ?? {};

// 与后端 FormCrudDefinitionFactory 的 belongsTo 命名一致
const belongsToName = (field: string): string => field.replace(/_id$/, '') || `${field}_relation`;

export const schemaToRelationRows = (doc: FormSchemaDocument): RelationRow[] => {
  const nodeRows = walkFieldNodes(doc.nodes).flatMap(({ node, path }): RelationRow[] => {
    const relation = relationOf(node);
    const field = String(node.field ?? '');
    if (isFieldContainer(node)) {
      // 子表 hasMany 由设计器维护；Schema 文档不携带主键（采纳表主键来自实时表结构），按后端默认显示 id
      return [{ origin: 'subform', type: 'hasMany', name: field, table: String(relation.table ?? ''), localField: 'id', foreignField: String(relation.valueField ?? ''), path, editable: false }];
    }
    if (relation.type !== 'belongs_to') return [];
    return [{
      origin: 'field', type: 'belongsTo', name: belongsToName(field), table: String(relation.table ?? ''),
      localField: field, foreignField: String(relation.valueField ?? 'id'), labelField: String(relation.labelField ?? ''), path, editable: true
    }];
  });
  const modelRows = (doc.relations ?? []).map((relation): RelationRow => ({
    origin: 'model', type: relation.type, name: relation.name, table: relation.table,
    localField: relation.localField, foreignField: relation.foreignField,
    ...(relation.pivot ? { pivot: { ...relation.pivot } } : {}),
    editable: true
  }));
  return [...nodeRows, ...modelRows];
};

const RELATION_CONTROL = 'relation';

const withRelation = (node: FormSchemaNode, relation: Record<string, unknown>): FormSchemaNode =>
  ({ ...node, database: { ...node.database, relation } });

// 新增/修改 belongsTo：只合并 database.relation 的关联键；新增时等同于在设计器中把控件切换为「关联数据」
const linkNode = (node: FormSchemaNode, row: RelationRow): FormSchemaNode => {
  const relation = relationOf(node);
  const base = relation.type === 'belongs_to' || node.type === RELATION_CONTROL ? node : changeControl(node, RELATION_CONTROL, { resetColumnType: false });
  return withRelation(base, {
    multiple: false, onDelete: 'restrict', ...relation,
    type: 'belongs_to', table: row.table, labelField: row.labelField ?? '', valueField: row.foreignField
  });
};

// 移除 belongsTo 时不留下没有关联的关联控件：有静态/字典选项改为 select 并保留选项，否则改为 input
const unlinkNode = (node: FormSchemaNode): FormSchemaNode => {
  let base = node;
  if (node.type === RELATION_CONTROL) {
    const kind = dataSourceKind(node.dataSource as Record<string, unknown> | null | undefined);
    base = kind === 'static' || kind === 'dictionary'
      ? { ...changeControl(node, 'select', { resetColumnType: false }), dataSource: node.dataSource }
      : changeControl(node, 'input', { resetColumnType: false });
  }
  return withRelation(base, { ...relationOf(node), type: 'none' });
};

const toModelRelation = (row: RelationRow): FormSchemaRelation => {
  if (!MODEL_TYPES.has(row.type)) throw new Error(`模型关联不支持类型 ${row.type}：${row.name}`);
  if (row.type === 'belongsToMany' && !row.pivot) throw new Error(`belongsToMany 必须配置中间表：${row.name}`);
  return {
    type: row.type as FormSchemaRelation['type'], name: row.name, table: row.table,
    localField: row.localField, foreignField: row.foreignField,
    ...(row.type === 'belongsToMany' && row.pivot ? { pivot: { ...row.pivot } } : {})
  };
};

/**
 * 以关联表格的全部行回写文档：field 行只合并字段的 database.relation（必要时切换控件），
 * model 行整体替换顶层 relations（为空时移除该键），subform 行只读、忽略。
 */
export const applyRelationRows = (doc: FormSchemaDocument, rows: RelationRow[]): FormSchemaDocument => {
  const entries = walkFieldNodes(doc.nodes).filter(({ node }) => !isFieldContainer(node));
  const fieldRows = new Map<string, RelationRow>();
  for (const row of rows.filter((item) => item.origin === 'field')) {
    if (row.type !== 'belongsTo') throw new Error(`字段关联只能是 belongsTo：${row.localField}`);
    if (!entries.some(({ node }) => node.field === row.localField)) throw new Error(`字段不存在：${row.localField}`);
    if (fieldRows.has(row.localField)) throw new Error(`字段重复配置关联：${row.localField}`);
    fieldRows.set(row.localField, row);
  }
  const relations = rows.filter((row) => row.origin === 'model').map(toModelRelation);

  const next = entries.reduce((current, { node, path }) => {
    const row = fieldRows.get(String(node.field ?? ''));
    const relation = relationOf(node);
    const linked = relation.type === 'belongs_to';
    if (!row) return linked ? updateNodeAtPath(current, path, unlinkNode) : current;
    if (linked && relation.table === row.table && relation.valueField === row.foreignField && String(relation.labelField ?? '') === (row.labelField ?? '')) return current;
    return updateNodeAtPath(current, path, (target) => linkNode(target, row));
  }, doc);

  const { relations: _previous, ...rest } = next;
  return relations.length ? { ...rest, relations } : rest;
};
